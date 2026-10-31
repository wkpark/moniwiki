<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/wikilib.php';
require_once MONIWIKI_TEST_ROOT.'/plugin/restore.php';

final class WikiLibSecurityHelpersTest extends TestCase
{
    public function testRestoreFormEscapesSingleQuotes(): void
    {
        $oldDb = $GLOBALS['DBInfo'] ?? null;
        $GLOBALS['DBInfo'] = (object) array('security'=>new class {
            public function is_protected($action, $options) { return false; }
        });
        $formatter = new class {
            public function send_header($header, $options) {}
            public function send_title($title, $unused, $options) {}
            public function send_footer($unused, $options) {}
        };
        ob_start();
        try {
            do_post_restore($formatter, array('ticket'=>'', 'tar'=>'', 'value'=>"x' autofocus onfocus='alert(1)"));
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
            $GLOBALS['DBInfo'] = $oldDb;
        }
        $this->assertStringContainsString("value='x&#039; autofocus onfocus=&#039;alert(1)'", $output);
    }

    public function testSafeFilenameRejectsTraversalAndPathSeparators(): void
    {
        $this->assertTrue(_safe_filename('image.png'));
        $this->assertTrue(_safe_filename('archive.v1.tar.gz'));

        $this->assertFalse(_safe_filename('../config.php'));
        $this->assertFalse(_safe_filename('subdir/file.png'));
        $this->assertFalse(_safe_filename('subdir\\file.png'));
        $this->assertFalse(_safe_filename("bad\nname"));
        $this->assertFalse(_safe_filename('.'));
        $this->assertFalse(_safe_filename('..'));
    }

    public function testJsonStringEscapesScriptBreakingInput(): void
    {
        $this->assertSame(
            '"<\/script><img src=x onerror=alert(1)>\"\\\\\\n"',
            _json_string("</script><img src=x onerror=alert(1)>\"\\\n")
        );
    }

    public function testNormalizeHttpHostStripsPortAndRejectsUnsafeValues(): void
    {
        $this->assertSame('example.com', _normalize_http_host('Example.COM:8080'));
        $this->assertSame('localhost', _normalize_http_host('localhost:8080'));

        $this->assertSame('', _normalize_http_host("example.com\r\nX-Test: 1"));
        $this->assertSame('', _normalize_http_host('example..com'));
        $this->assertSame('', _normalize_http_host('example.com:bad'));
        $this->assertSame('', _normalize_http_host('example.com:'));
        $this->assertSame('', _normalize_http_host('.example.com'));
    }

    public function testNormalizeHttpHostAcceptsValidHostForms(): void
    {
        $this->assertSame('[::1]', _normalize_http_host('[::1]'));
        $this->assertSame('[2001:db8::1]', _normalize_http_host('[2001:DB8::1]:8000'));
        $this->assertSame('example.com', _normalize_http_host('example.com.'));
        $this->assertSame('my_host.example.com', _normalize_http_host('my_host.example.com:8080'));

        $this->assertSame('', _normalize_http_host('[not-ipv6]'));
        $this->assertSame('', _normalize_http_host('[::1]:bad'));
        $this->assertSame('', _normalize_http_host('example.com..'));
    }

    public function testQualifiedUrlKeepsIpv6HostAndPort(): void
    {
        $server = $_SERVER;
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['HTTPS'] = '';

        try {
            $_SERVER['HTTP_HOST'] = '[::1]:8000';
            $this->assertSame('http://[::1]:8000/wiki.php', qualifiedUrl('/wiki.php'));

            $_SERVER['HTTP_HOST'] = 'localhost:8000';
            $this->assertSame('http://localhost:8000/wiki.php', qualifiedUrl('/wiki.php'));
        } finally {
            $_SERVER = $server;
        }
    }

    public function testCookieDomainUsesOnlyValidatedNames(): void
    {
        $server = $_SERVER;
        $config = isset($GLOBALS['Config']) ? $GLOBALS['Config'] : null;
        $GLOBALS['Config'] = array();

        try {
            $_SERVER['SERVER_NAME'] = 'www.example.com';
            $_SERVER['HTTP_HOST'] = 'www.example.com';
            $this->assertSame('www.example.com', _cookie_domain());

            $_SERVER['SERVER_NAME'] = "www.example.com; Path=/x";
            $_SERVER['HTTP_HOST'] = 'example.com:8080';
            $this->assertSame('example.com', _cookie_domain());

            $_SERVER['SERVER_NAME'] = 'localhost';
            $_SERVER['HTTP_HOST'] = '[::1]:8000';
            $this->assertSame('', _cookie_domain());

            $GLOBALS['Config'] = array('cookie_domain'=>'.example.org');
            $this->assertSame('.example.org', _cookie_domain());
        } finally {
            $_SERVER = $server;
            $GLOBALS['Config'] = $config;
        }
    }

    public function testHtmlAttrEscapeEscapesBothQuoteTypes(): void
    {
        $this->assertSame('&quot; &#039;&lt;b>', _html_attr_escape('" \'<b>'));
    }

    public function testSafeHtmlUrlRejectsScriptableSchemes(): void
    {
        $this->assertTrue(_safe_html_url('https://example.com/path'));
        $this->assertTrue(_safe_html_url('/wiki.php/FrontPage'));
        $this->assertTrue(_safe_html_url('mailto:wiki@example.com'));

        $this->assertFalse(_safe_html_url('javascript:alert(1)'));
        $this->assertFalse(_safe_html_url('java&#x73;cript:alert(1)'));
        $this->assertFalse(_safe_html_url('javascript&colon;alert(1)'));
        $this->assertFalse(_safe_html_url(' JavaScript:alert(1)'));
        $this->assertFalse(_safe_html_url('vbscript:msgbox(1)'));
        $this->assertFalse(_safe_html_url('data:text/html,<script>alert(1)</script>'));
        $this->assertFalse(_safe_html_url("https://example.com/\nHeader: x"));
    }
}
