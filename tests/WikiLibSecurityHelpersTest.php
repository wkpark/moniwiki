<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/wikilib.php';

final class WikiLibSecurityHelpersTest extends TestCase
{
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

    public function testSafeHttpHostStripsPortAndRejectsUnsafeValues(): void
    {
        $this->assertSame('example.com', _safe_http_host('Example.COM:8080'));
        $this->assertSame('localhost', _safe_http_host('localhost:8080'));

        $this->assertSame('', _safe_http_host("example.com\r\nX-Test: 1"));
        $this->assertSame('', _safe_http_host('example..com'));
        $this->assertSame('', _safe_http_host('example.com:bad'));
        $this->assertSame('', _safe_http_host('example.com:'));
        $this->assertSame('', _safe_http_host('.example.com'));
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
