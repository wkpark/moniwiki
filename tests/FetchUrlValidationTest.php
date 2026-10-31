<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/wikilib.php';
require_once MONIWIKI_TEST_ROOT.'/plugin/fetch.php';

final class FetchUrlValidationTest extends TestCase
{
    public function testRejectsPrivateAndLocalUrls(): void
    {
        $this->assertTrue(_fetch_is_private_url('http://127.0.0.1/image.png'));
        $this->assertTrue(_fetch_is_private_url('http://localhost/image.png'));
        $this->assertTrue(_fetch_is_private_url('http://10.0.0.1/image.png'));
        $this->assertTrue(_fetch_is_private_url('http://169.254.169.254/latest/meta-data/'));
        $this->assertTrue(_fetch_is_private_url('http://100.64.0.1/image.png'));
        $this->assertTrue(_fetch_is_private_url('http://198.18.0.1/image.png'));
        $this->assertTrue(_fetch_is_private_url('http://224.0.0.1/image.png'));
    }

    public function testAllowsPublicIpUrls(): void
    {
        $this->assertFalse(_fetch_is_private_url('http://8.8.8.8/image.png'));
    }

    public function testBlockedUrlIsEscapedInErrorPage(): void
    {
        $previousConfig = $GLOBALS['Config'] ?? null;
        $GLOBALS['Config'] = array('sitename'=>'Wiki');
        $formatter = new class {
            public $refresh = false;
            public function header($header) {}
        };
        ob_start();
        try {
            do_fetch($formatter, array('value'=>'', 'url'=>'http://127.0.0.1/"><script>alert(1)</script>'));
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
            $GLOBALS['Config'] = $previousConfig;
        }
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script>', $output);
    }
}
