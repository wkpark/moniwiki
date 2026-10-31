<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/lib/version.RcsLite.php';

final class RcsLiteTest extends TestCase
{
    private $dir;
    private $version;
    private $includePath;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/moniwiki-rcslite-'.bin2hex(random_bytes(8));
        mkdir($this->dir);
        $db = new class($this->dir) {
            public $rcs_dir = 'RCS';
            public $rcs_user = 'test';
            public $text_dir;
            public $vartmp_dir;
            public function __construct($dir) { $this->text_dir = $dir; $this->vartmp_dir = $dir; }
            public function getPageKey($name) { return $this->text_dir.'/'.$name; }
            public function _getPageKey($name) { return $name; }
        };
        $GLOBALS['DBInfo'] = $db;
        $this->includePath = get_include_path();
        set_include_path(MONIWIKI_TEST_ROOT.'/lib'.PATH_SEPARATOR.$this->includePath);
        $this->version = new Version_RcsLite($db);
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($this->dir);
        set_include_path($this->includePath);
        unset($GLOBALS['DBInfo']);
    }

    public function testSaveRecallAndSwitchPages(): void
    {
        $page = $this->dir.'/First';
        file_put_contents($page, "first @ version\nsecond line\n");
        $this->assertSame(0, $this->version->_ci($page, 'first'));
        file_put_contents($page, "changed @ version\nsecond line\n");
        $this->assertStringContainsString('changed @ version', $this->version->diff('First'));
        $this->assertSame(0, $this->version->_ci($page, 'second'));
        $this->assertSame("first @ version\nsecond line\n", $this->version->co('First', '1.1'));
        $this->assertSame("changed @ version\nsecond line\n", $this->version->co('First', '1.2'));
        $this->assertSame('', $this->version->co('First', '99.1'));
        $this->assertSame('', $this->version->co('First', '1.1', array('stdout'=>1)));
        $this->assertSame("first @ version\nsecond line\n", file_get_contents($page));

        $other = $this->dir.'/Second';
        file_put_contents($other, "other page\n");
        $this->assertSame(0, $this->version->_ci($other, 'other'));
        $this->assertSame("other page\n", $this->version->co('Second', '1.1'));
        $this->assertSame("first @ version\nsecond line\n", $this->version->co('First', '1.1'));
    }

    public function testFailedHistoryWriteIsReported(): void
    {
        $this->assertSame(-1, $this->version->_ci($this->dir.'/missing/Page', 'failure'));
    }

    public function testLargeRevisionAndEscapedAtSignsRoundTrip(): void
    {
        $page = $this->dir.'/Large';
        $original = str_repeat("long @ content with a newline\n", 2000);
        file_put_contents($page, $original);
        $this->assertSame(0, $this->version->_ci($page, 'first'));
        file_put_contents($page, "new text\n".$original);
        $this->assertSame(0, $this->version->_ci($page, 'second'));
        $fresh = new Version_RcsLite($GLOBALS['DBInfo']);
        $this->assertSame($original, $fresh->co('Large', '1.1'));
    }

    public function testTruncatedHistoryIsNotOverwritten(): void
    {
        $page = $this->dir.'/Damaged';
        mkdir($this->dir.'/RCS');
        file_put_contents($page, "new page text\n");
        $archive = $this->dir.'/RCS/Damaged,v';
        file_put_contents($archive, "head 1.1;\naccess;\n");
        $this->assertSame(-1, $this->version->_ci($page, 'edit'));
        $this->assertSame("head 1.1;\naccess;\n", file_get_contents($archive));
        $this->assertTrue($this->version->is_broken('Damaged'));
    }

    public function testEscapedAtSignAtReadChunkBoundary(): void
    {
        $page = $this->dir.'/Boundary';
        $original = str_repeat('x', 65536)."@tail\n";
        file_put_contents($page, $original);
        $this->assertSame(0, $this->version->_ci($page, 'first'));
        $fresh = new Version_RcsLite($GLOBALS['DBInfo']);
        $this->assertSame($original, $fresh->co('Boundary', '1.1'));
    }

    public function testRenamePreservesHistory(): void
    {
        $old = $this->dir.'/Old';
        file_put_contents($old, "page content\n");
        $this->assertSame(0, $this->version->_ci($old, 'created'));
        rename($old, $this->dir.'/New');
        $this->assertSame(0, $this->version->rename('Old', 'New', array('log'=>'renamed')));
        $this->assertSame("page content\n", $this->version->co('New', '1.1'));
        $this->assertSame("page content\n", $this->version->co('New', '1.2'));
    }

    public function testHistorySelectionMatchesWikiRequests(): void
    {
        $page = $this->dir.'/History';
        for ($i = 1; $i <= 15; $i++) {
            file_put_contents($page, "revision $i\n");
            $this->assertSame(0, $this->version->_ci($page, "edit $i"));
        }
        $head = $this->version->rlog('History', '', '-r');
        $this->assertStringContainsString('total revisions: 15;', $head);
        $this->assertSame(1, substr_count($head, "\nrevision "));
        $this->assertStringContainsString("\nrevision 1.15\n", $head);
        $range = $this->version->rlog('History', '', '-r1.4:1.5\\;1.13:1.15');
        $this->assertSame(5, substr_count($range, "\nrevision "));
        $this->assertStringContainsString("\nrevision 1.4\n", $range);
        $this->assertStringNotContainsString("\nrevision 1.6\n", $range);
        $this->assertLessThan(strpos($range, "\nrevision 1.4\n"), strpos($range, "\nrevision 1.15\n"));
    }
    public function testPartialHistoryMatchesFullReaderAndDoesNotAffectWrites(): void
    {
        $page = $this->dir.'/Recent';
        for ($i = 1; $i <= 20; $i++) {
            $body = "revision $i\n".str_repeat("text @; desc\n", 6000);
            file_put_contents($page, $body);
            $this->assertSame(0, $this->version->_ci($page, "edit $i @;\ndesc\n"));
        }
        $full = new RcsLite();
        $full->_process($page);
        $fresh = new Version_RcsLite($GLOBALS['DBInfo']);
        foreach (array('-r', '-r1.10:1.20', '-r1.1:1.3', '-r1.19', '-r1.21', '-r1.20:1.10', '-r1.2;1.19') as $opt)
            $this->assertSame($full->rlog('', $opt), $fresh->rlog('Recent', '', $opt));
        $this->assertSame($full->rlog('1.18'), $fresh->rlog('Recent', '1.18'));
        $this->assertSame("revision 1\n".str_repeat("text @; desc\n", 6000), $fresh->co('Recent', '1.1'));
        file_put_contents($page, "last revision\n");
        $this->assertSame(0, $fresh->_ci($page, 'last'));
        $this->assertSame('1.21', $fresh->get_rev('Recent', '', 1));
        $this->assertSame("revision 1\n".str_repeat("text @; desc\n", 6000), $fresh->co('Recent', '1.1'));

        // Noncanonical whitespace uses the regular metadata parser.
        $archive = $this->dir.'/RCS/Recent,v';
        file_put_contents($archive, str_replace("\ndesc\n", "\ndesc \n", file_get_contents($archive)));
        $full = new RcsLite();
        $full->_process($page);
        $this->assertSame($full->rlog('', '-r1.11:1.21'), $fresh->rlog('Recent', '', '-r1.11:1.21'));
    }

    public function testTokenDelimitersAtEofAndAfterQuotedStrings(): void
    {
        foreach (array('a;' => array('a;', ''), ' @quoted@ a;' => array(' a;', 'quoted')) as $input => $expected) {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, $input);
            rewind($stream);
            $this->assertSame($expected, (new RcsLite())->_readTo($stream, ';'));
            fclose($stream);
        }
    }

}
