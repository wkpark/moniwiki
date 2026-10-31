<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/lib/version.SimpleRCS.php';

final class SimpleRCSTest extends TestCase
{
    private $dir;
    private $version;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/moniwiki-simple-'.bin2hex(random_bytes(8));
        mkdir($this->dir);
        $db = new class($this->dir) {
            public $text_dir;
            public $vartmp_dir;
            public function __construct($dir) { $this->text_dir = $dir; $this->vartmp_dir = $dir; }
            public function getPageKey($name) { return $this->text_dir.'/'.$name; }
        };
        $GLOBALS['DBInfo'] = $db;
        $this->version = new Version_SimpleRCS($db);
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($this->dir);
        unset($GLOBALS['DBInfo']);
    }

    public function testReadsPythonGeneratedV2History(): void
    {
        mkdir($this->dir.'/RCS');
        copy(MONIWIKI_TEST_ROOT.'/tests/fixtures/simple-rcs-v2.srcs', $this->dir.'/RCS/Page,srcs');
        $this->assertSame("안녕하세요 @ 사용자\n첫 줄\n", $this->version->co('Page', '1.0'));
        $this->assertSame("안녕하세요 @ 사용자\n고친 줄\n", $this->version->co('Page', '1.1'));
        $this->assertSame("안녕하세요 @ 사용자\n고친 줄\n끝\n", $this->version->co('Page', '1.2'));
        $this->assertSame('1.2', $this->version->get_rev('Page', '', 1));
        $this->assertSame('1.1', $this->version->get_rev('Page'));
        $history = $this->version->rlog('Page', '', '-r1.0:1.2');
        $this->assertStringContainsString('total revisions: 3;', $history);
        $this->assertStringContainsString('lines: +1 -1', $history);
        $this->assertSame(3, substr_count($history, "\nrevision "));
    }

    public function testSaveReadAndRestoreTextHistory(): void
    {
        $path = $this->dir.'/Page';
        $bodies = array("one\n\nlast\n", "one\nlast\n", "one\nother @ value\n");
        foreach ($bodies as $i=>$body) {
            file_put_contents($path, $body);
            $this->assertSame(0, $this->version->_ci($path, '127.0.0.1;;Anonymous;;edit '.$i));
        }
        foreach ($bodies as $i=>$body) $this->assertSame($body, $this->version->co('Page', '1.'.$i));
        $this->assertSame('', $this->version->co('Page', '1.0', array('stdout'=>1)));
        $this->assertSame($bodies[0], file_get_contents($path));
        $this->assertFileExists($this->dir.'/RCS/Page,srcs');
        $this->assertFileDoesNotExist($this->dir.'/RCS/Page,v');
    }

    public function testExistingRcsHistoryIsNotOverwritten(): void
    {
        mkdir($this->dir.'/RCS');
        file_put_contents($this->dir.'/Page', "changed\n");
        file_put_contents($this->dir.'/RCS/Page,v', 'existing RCS');
        $this->assertFalse($this->version->canSave('Page', "changed\n"));
        $this->assertSame(-1, $this->version->_ci($this->dir.'/Page', 'edit'));
        $this->assertSame('existing RCS', file_get_contents($this->dir.'/RCS/Page,v'));
        $this->assertFileDoesNotExist($this->dir.'/RCS/Page,srcs');
    }

    public function testInvalidHistoryIsNotOverwritten(): void
    {
        mkdir($this->dir.'/RCS');
        file_put_contents($this->dir.'/Page', "changed\n");
        $archive = $this->dir.'/RCS/Page,srcs';
        file_put_contents($archive, "# SimpleRCS v2.0; hash_algo=sha256; encoding=utf-8;\nver @1.0@;\ntext @unfinished");
        $before = file_get_contents($archive);
        $this->assertSame(-1, $this->version->_ci($this->dir.'/Page', 'edit'));
        $this->assertSame($before, file_get_contents($archive));
        $this->assertTrue($this->version->is_broken('Page'));
        $this->assertFalse($this->version->canSave('Page', "changed\n"));
    }

    public function testLegacyV1TextHistoryCanBeExtended(): void
    {
        mkdir($this->dir.'/RCS');
        $archive = $this->dir.'/RCS/Page,srcs';
        $v1 = "ver @1.0@;\ndate @2026-01-01T00:00:00@;\nauthor @u@;\nlog @old@;\ntext @d1 1\na1 1\nold\n@;\n\n".
              "ver @1.1@;\ndate @2026-01-01T00:01:00@;\nauthor @u@;\nlog @new@;\ntext @new\n@;\n\n";
        file_put_contents($archive, $v1);
        file_put_contents($this->dir.'/Page', "new\n");
        $this->assertSame("old\n", $this->version->co('Page', '1.0'));
        file_put_contents($this->dir.'/Page', "newer\n");
        $this->assertSame(0, $this->version->_ci($this->dir.'/Page', 'edit'));
        $this->assertSame("old\n", $this->version->co('Page', '1.0'));
        $this->assertSame("new\n", $this->version->co('Page', '1.1'));
        $this->assertSame("newer\n", $this->version->co('Page', '1.2'));
    }

    public function testHistoryEscapesImportedLogMarkup(): void
    {
        $store = new MoniSimpleRCS();
        $path = $this->dir.'/Page,srcs';
        $this->assertTrue($store->load($path, true));
        $this->assertTrue($store->save($path, "text\n", 'u', 'ip;;u;;<script>bad</script>'));
        $this->assertTrue($store->load($path));
        $history = $store->rlog('', '-r');
        $this->assertStringNotContainsString('<script>', $history);
        $this->assertStringContainsString('&lt;script&gt;', $history);
    }

    public function testCorruptHistoricalHashPreventsSave(): void
    {
        $GLOBALS['DBInfo']->simplercs_verify_hashes = 1;
        $this->version = new Version_SimpleRCS($GLOBALS['DBInfo']);
        mkdir($this->dir.'/RCS');
        $archive = $this->dir.'/RCS/Page,srcs';
        $data = file_get_contents(MONIWIKI_TEST_ROOT.'/tests/fixtures/simple-rcs-v2.srcs');
        $data = str_replace('수정 0', '변조 0', $data);
        file_put_contents($archive, $data);
        file_put_contents($this->dir.'/Page', "new content\n");
        $this->assertTrue($this->version->is_broken('Page'));
        $this->assertFalse($this->version->canSave('Page', "new content\n"));
        $this->assertSame(-1, $this->version->_ci($this->dir.'/Page', 'edit'));
        $this->assertSame($data, file_get_contents($archive));
    }

    public function testV2TextSnapshotCanBeRecalled(): void
    {
        mkdir($this->dir.'/RCS');
        $archive = $this->dir.'/RCS/Page,srcs';
        $data = file_get_contents(MONIWIKI_TEST_ROOT.'/tests/fixtures/simple-rcs-v2.srcs');
        $data = str_replace("delta @d2 1\na2 1\n첫 줄\n@;", "text @안녕하세요 @@ 사용자\n첫 줄\n@;", $data);
        file_put_contents($archive, $data);
        $this->assertSame("안녕하세요 @ 사용자\n첫 줄\n", $this->version->co('Page', '1.0'));
        $this->assertFalse($this->version->is_broken('Page'));
    }

    public function testUnsupportedBinaryHistoryIsRejected(): void
    {
        mkdir($this->dir.'/RCS');
        $archive = $this->dir.'/RCS/Page,srcs';
        $data = file_get_contents(MONIWIKI_TEST_ROOT.'/tests/fixtures/simple-rcs-v2.srcs');
        $data = str_replace('text @안녕하세요', 'binary @안녕하세요', $data);
        file_put_contents($archive, $data);
        $this->assertFalse($this->version->canSave('Page', "text\n"));
        $this->assertSame('', $this->version->co('Page', '1.2'));
        $this->assertSame($data, file_get_contents($archive));
    }

    public function testRenameAndDeletePreserveHistory(): void
    {
        $page = $this->dir.'/Page';
        file_put_contents($page, "content\n");
        $this->assertSame(0, $this->version->_ci($page, 'created'));
        rename($page, $this->dir.'/Renamed');
        $this->assertSame(0, $this->version->rename('Page', 'Renamed', array('log'=>'renamed')));
        $this->assertSame("content\n", $this->version->co('Renamed', '1.0'));
        $this->assertSame("content\n", $this->version->co('Renamed', '1.1'));
        $this->assertTrue($this->version->delete('Renamed'));
        $this->assertFileExists($this->dir.'/RCS/Renamed,srcs.1');
    }
}
