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
            public $simplercs_verify_hashes = 0;
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

    public function testVerifiedHashChainCanBeReadAndExtended(): void
    {
        $GLOBALS['DBInfo']->simplercs_verify_hashes = 1;
        $this->version = new Version_SimpleRCS($GLOBALS['DBInfo']);
        mkdir($this->dir.'/RCS');
        copy(MONIWIKI_TEST_ROOT.'/tests/fixtures/simple-rcs-v2.srcs', $this->dir.'/RCS/Page,srcs');
        $this->assertSame("안녕하세요 @ 사용자\n첫 줄\n", $this->version->co('Page', '1.0'));
        $this->assertFalse($this->version->is_broken('Page'));
        $this->assertTrue($this->version->canSave('Page', "next\n"));
        file_put_contents($this->dir.'/Page', "next\n");
        $this->assertSame(0, $this->version->_ci($this->dir.'/Page', 'edit'));
        $this->assertSame("안녕하세요 @ 사용자\n첫 줄\n", $this->version->co('Page', '1.0'));
        $this->assertSame("next\n", $this->version->co('Page', '1.3'));
    }

    public function testArchiveChangedAfterCanSaveIsVerifiedAgain(): void
    {
        $GLOBALS['DBInfo']->simplercs_verify_hashes = 1;
        $this->version = new Version_SimpleRCS($GLOBALS['DBInfo']);
        mkdir($this->dir.'/RCS');
        $archive = $this->dir.'/RCS/Page,srcs';
        $data = file_get_contents(MONIWIKI_TEST_ROOT.'/tests/fixtures/simple-rcs-v2.srcs');
        file_put_contents($archive, $data);
        $this->assertTrue($this->version->canSave('Page', "new content\n"));
        $tampered = str_replace('수정 0', '변조 0', $data);
        file_put_contents($archive, $tampered);
        file_put_contents($this->dir.'/Page', "new content\n");
        $this->assertSame(-1, $this->version->_ci($this->dir.'/Page', 'edit'));
        $this->assertSame($tampered, file_get_contents($archive));
    }

    public function testManyRevisionsRoundTrip(): void
    {
        mt_srand(205);
        $path = $this->dir.'/Page';
        $lines = array();
        for ($i = 0; $i < 30; $i++) $lines[] = "line $i @ 한글\n";
        $bodies = array();
        for ($rev = 0; $rev < 60; $rev++) {
            for ($k = mt_rand(1, 3); $k > 0; $k--) {
                $at = mt_rand(0, count($lines) - 1);
                $op = mt_rand(0, 2);
                if ($op === 0) $lines[$at] = "edit $rev.$k @@ ".mt_rand()."\n";
                else if ($op === 1) array_splice($lines, $at, 0, array("\n", "insert $rev.$k\n"));
                else if (count($lines) > 5) array_splice($lines, $at, mt_rand(1, 2));
            }
            $bodies[] = implode('', $lines);
            file_put_contents($path, end($bodies));
            $this->assertSame(0, $this->version->_ci($path, '127.0.0.1;;u;;r'.$rev));
        }
        foreach ($bodies as $i => $body) $this->assertSame($body, $this->version->co('Page', '1.'.$i));
        $this->assertFalse($this->version->is_broken('Page'));
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

    public function testLockFileStaysInHistoryDirectory(): void
    {
        file_put_contents($this->dir.'/FrontPage', "hi\n");
        $this->assertSame(0, $this->version->_ci($this->dir.'/FrontPage', 'created'));
        $this->assertSame(array('FrontPage', 'RCS'), array_values(array_diff(scandir($this->dir), array('.', '..'))));
        $this->assertFileExists($this->dir.'/RCS/FrontPage,srcs.lock');
    }

    public function testRenameOntoDeletedPageSetsItsHistoryAside(): void
    {
        file_put_contents($this->dir.'/Target', "deleted page\n");
        $this->assertSame(0, $this->version->_ci($this->dir.'/Target', 'created'));
        unlink($this->dir.'/Target');
        file_put_contents($this->dir.'/Old', "old page\n");
        $this->assertSame(0, $this->version->_ci($this->dir.'/Old', 'created'));
        rename($this->dir.'/Old', $this->dir.'/Target');
        $this->assertSame(0, $this->version->rename('Old', 'Target', array('log'=>'renamed')));
        $this->assertFileDoesNotExist($this->dir.'/RCS/Old,srcs');
        $this->assertSame("old page\n", $this->version->co('Target', '1.0'));
        $this->assertSame('1.1', $this->version->get_rev('Target', '', 1));
        $this->assertStringContainsString("text @deleted page\n@;", file_get_contents($this->dir.'/RCS/Target,srcs.1'));
    }

    public function testArchiveOptionIsNotServedFromLiveHistory(): void
    {
        file_put_contents($this->dir.'/Page', "live\n");
        $this->assertSame(0, $this->version->_ci($this->dir.'/Page', 'created'));
        $this->assertSame("live\n", $this->version->co('Page', '1.0'));
        $this->assertSame('', $this->version->co('Page', '1.0', array('archive'=>1)));
    }

    public function testUnchangedTextAddsRevisionOnlyWhenForced(): void
    {
        $path = $this->dir.'/Page';
        file_put_contents($path, "same");
        $this->assertSame(0, $this->version->_ci($path, 'created'));
        file_put_contents($path, "same\n");
        $this->assertSame(0, $this->version->_ci($path, 'again'));
        $this->assertSame('1.0', $this->version->get_rev('Page', '', 1));
        $this->assertSame(0, $this->version->_ci($path, 'forced', true));
        $this->assertSame('1.1', $this->version->get_rev('Page', '', 1));
    }

    public function testDiffIgnoresMissingFinalNewline(): void
    {
        $path = $this->dir.'/Page';
        file_put_contents($path, "one\ntwo");
        $this->assertSame(0, $this->version->_ci($path, 'created'));
        $this->assertSame('', $this->version->diff('Page'));
        file_put_contents($path, "one\nthree");
        $this->assertSame("@@ -1,2 +1,2 @@\n one\n-two\n+three\n", $this->version->diff('Page'));
    }

    public function testNewRevisionDateCarriesUtcOffset(): void
    {
        file_put_contents($this->dir.'/Page', "x\n");
        $before = time();
        $this->assertSame(0, $this->version->_ci($this->dir.'/Page', 'created'));
        $this->assertMatchesRegularExpression('/^date @\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00@;$/m', file_get_contents($this->dir.'/RCS/Page,srcs'));
        $this->assertSame('1.0', $this->version->get_rev('Page', time()));
        $this->assertSame('', $this->version->get_rev('Page', $before - 1));
    }

    public function testRevisionDatesFollowTheirZone(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('Asia/Seoul');
        try {
            mkdir($this->dir.'/RCS');
            // an explicit offset, UTC, and a local time without a zone as the Python SimpleRCS writes it
            $v1 = "ver @1.0@;\ndate @2026-01-01T09:00:00+09:00@;\nauthor @u@;\nlog @a@;\ntext @d1 1\na1 1\na\n@;\n\n".
                  "ver @1.1@;\ndate @2026-01-01T00:30:00Z@;\nauthor @u@;\nlog @b@;\ntext @d1 1\na1 1\nb\n@;\n\n".
                  "ver @1.2@;\ndate @2026-01-01T10:00:00.250000@;\nauthor @u@;\nlog @c@;\ntext @c\n@;\n\n";
            file_put_contents($this->dir.'/RCS/Page,srcs', $v1);
            $utc = function ($time) { return (new DateTime($time, new DateTimeZone('UTC')))->getTimestamp(); };
            $this->assertSame('', $this->version->get_rev('Page', $utc('2025-12-31 23:59:59')));
            $this->assertSame('1.0', $this->version->get_rev('Page', $utc('2026-01-01 00:10:00')));
            $this->assertSame('1.1', $this->version->get_rev('Page', $utc('2026-01-01 00:59:59')));
            $this->assertSame('1.2', $this->version->get_rev('Page', $utc('2026-01-01 01:00:00')));
            $history = $this->version->rlog('Page');
            $this->assertStringContainsString("revision 1.0\ndate: 2026/01/01 00:00:00;", $history);
            $this->assertStringContainsString("revision 1.2\ndate: 2026/01/01 01:00:00;", $history);
        } finally {
            date_default_timezone_set($zone);
        }
    }
}
