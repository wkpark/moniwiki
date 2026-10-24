<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/wikilib.php';
require_once MONIWIKI_TEST_ROOT.'/lib/cache.text.php';

final class InterWikiMapTest extends TestCase
{
    private string $dir = '';
    private array $server = array();

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/moniwiki-interwiki-'.getmypid().'-'.mt_rand();
        mkdir($this->dir.'/cache', 0777, true);
        $this->server = $_SERVER;
        $_SERVER['SCRIPT_NAME'] = '/wiki.php';
        $_SERVER['REQUEST_URI'] = '/wiki.php/FrontPage';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['HTTP_HOST'] = 'wiki.example';
        $GLOBALS['Config'] = array('cache_dir'=>$this->dir.'/cache');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        unset($GLOBALS['DBInfo'], $GLOBALS['Config']);
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    private function loadMap(string $map): object
    {
        file_put_contents($this->dir.'/intermap.txt', $map);

        $db = new stdClass();
        $db->intermap = $this->dir.'/intermap.txt';
        $db->shared_intermap = $this->dir.'/missing';
        $db->url_prefix = '/moniwiki';
        $db->query_prefix = '/';
        $GLOBALS['DBInfo'] = $db;

        $formatter = new class {
            public $refresh = 1;

            public function macro_repl($macro, $value = '', $options = array())
            {
                return 'pds/'.$value;
            }
        };

        macro_InterWiki($formatter, '', array('init'=>1));
        return $db;
    }

    public function testAttachmentIconKeepsInterWikiUrl(): void
    {
        $db = $this->loadMap("Moin http://moinmo.in/ attachment:moin.png 16x16\n");

        $this->assertSame('http://moinmo.in/', $db->interwiki['Moin']);
        $this->assertSame('http://wiki.example/moniwiki/pds/moin.png', $db->intericon['Moin'][2]);
    }

    public function testUnsafeMapEntriesAreSkipped(): void
    {
        $db = $this->loadMap(
            "Good http://good.example/\$PAGE\n".
            "DataUrl data:text/plain,hello\n".
            "Quote http://quote.example/'x\n"
        );

        $this->assertSame('http://good.example/$PAGE', $db->interwiki['Good']);
        $this->assertArrayNotHasKey('DataUrl', $db->interwiki);
        $this->assertArrayNotHasKey('Quote', $db->interwiki);
        $this->assertStringNotContainsString('DataUrl', $db->interwikirule);
    }
}
