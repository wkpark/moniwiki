<?php

use PHPUnit\Framework\TestCase;

if (!defined('INC_MONIWIKI')) {
    define('INC_MONIWIKI', 1);
}

require_once MONIWIKI_TEST_ROOT.'/wikilib.php';
require_once MONIWIKI_TEST_ROOT.'/wiki.php';

final class WikiRequestAuthStateTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['DBInfo']);
    }

    public function testInvalidatedAuthenticationResetsCallerOptionsAndUserObject(): void
    {
        $GLOBALS['DBInfo'] = new stdClass();
        $GLOBALS['DBInfo']->user = new stdClass();
        $GLOBALS['DBInfo']->user->id = 'WikiMaster';
        $GLOBALS['DBInfo']->user->ticket = 'stale-ticket';
        $GLOBALS['DBInfo']->user->groups = array('AdminGroup');
        $GLOBALS['DBInfo']->user->is_member = true;

        $options = array(
            'id'=>'WikiMaster',
            'page'=>'FrontPage',
            'lang'=>'ko_KR',
        );

        _invalidate_authenticated_user($options);

        $this->assertSame('Anonymous', $options['id']);
        $this->assertSame('FrontPage', $options['page']);
        $this->assertSame('ko_KR', $options['lang']);
        $this->assertSame('Anonymous', $GLOBALS['DBInfo']->user->id);
        $this->assertSame('', $GLOBALS['DBInfo']->user->ticket);
        $this->assertSame(array(), $GLOBALS['DBInfo']->user->groups);
        $this->assertFalse($GLOBALS['DBInfo']->user->is_member);
    }

    public function testInvalidatedAuthenticationResetsCallerOptionsWithoutUserObject(): void
    {
        $GLOBALS['DBInfo'] = new stdClass();

        $options = array(
            'id'=>'WikiMaster',
            'page'=>'FrontPage',
        );

        _invalidate_authenticated_user($options);

        $this->assertSame('Anonymous', $options['id']);
        $this->assertSame('FrontPage', $options['page']);
        $this->assertObjectNotHasProperty('user', $GLOBALS['DBInfo']);
    }

    public function testInvalidatedAuthenticationKeepsExistingAnonymousUserStable(): void
    {
        $GLOBALS['DBInfo'] = new stdClass();
        $GLOBALS['DBInfo']->user = new stdClass();
        $GLOBALS['DBInfo']->user->id = 'Anonymous';
        $GLOBALS['DBInfo']->user->ticket = '';
        $GLOBALS['DBInfo']->user->groups = array();
        $GLOBALS['DBInfo']->user->is_member = false;

        $options = array('id'=>'Anonymous');

        _invalidate_authenticated_user($options);

        $this->assertSame('Anonymous', $options['id']);
        $this->assertSame('Anonymous', $GLOBALS['DBInfo']->user->id);
        $this->assertSame('', $GLOBALS['DBInfo']->user->ticket);
        $this->assertSame(array(), $GLOBALS['DBInfo']->user->groups);
        $this->assertFalse($GLOBALS['DBInfo']->user->is_member);
    }

    public function testSessionCookieValidatorRejectsMalformedCookie(): void
    {
        $this->assertFalse(_is_valid_session_cookie('bad-cookie', 'site-hash', 'addr-hash'));
        $this->assertFalse(_is_valid_session_cookie('site-hash-*-addr-hash', 'site-hash', 'addr-hash'));
        $this->assertFalse(_is_valid_session_cookie('site-hash-*-addr-hash-*-stamp-*-extra', 'site-hash', 'addr-hash'));
    }

    public function testSessionCookieValidatorChecksSiteAndAddressHash(): void
    {
        $this->assertTrue(_is_valid_session_cookie('site-hash-*-addr-hash-*-stamp', 'site-hash', 'addr-hash'));
        $this->assertFalse(_is_valid_session_cookie('other-*-addr-hash-*-stamp', 'site-hash', 'addr-hash'));
        $this->assertFalse(_is_valid_session_cookie('site-hash-*-other-*-stamp', 'site-hash', 'addr-hash'));
    }

    public function testImageAlignClassAllowsOnlySafeClassSuffixes(): void
    {
        $this->assertSame(' imgRight', _image_align_class('right'));
        $this->assertSame(' imgMiddle_1', _image_align_class('middle_1'));

        $this->assertSame('', _image_align_class('x" onerror="alert(1)'));
        $this->assertSame('', _image_align_class('../right'));
        $this->assertSame('', _image_align_class(''));
    }

    public function testExternalRedirectLinkEscapesHrefAndText(): void
    {
        $link = _external_redirect_link('https://example.com/%22%3E%3Cscript%3Ealert(1)%3C/script%3E', '#frag" onclick="x');

        $this->assertStringContainsString('href="https://example.com/%22%3E%3Cscript%3Ealert(1)%3C/script%3E#frag&quot; onclick=&quot;x"', $link);
        $this->assertStringContainsString('&quot;>&lt;script>alert(1)&lt;/script>#frag&quot; onclick=&quot;x', $link);
        $this->assertStringNotContainsString('<script>', $link);
    }

    public function testNormalizeHttpHostIgnoresForwardedPortAndRejectsUnsafeValues(): void
    {
        $this->assertSame('example.com', _normalize_http_host('Example.COM'));
        $this->assertSame('example.com', _normalize_http_host('example.com:8080'));
        $this->assertSame('localhost', _normalize_http_host('localhost:8080'));
        $this->assertSame('127.0.0.1', _normalize_http_host('127.0.0.1:8080'));

        $this->assertSame('', _normalize_http_host('../config'));
        $this->assertSame('', _normalize_http_host("example.com\r\nX-Test: 1"));
        $this->assertSame('', _normalize_http_host('example..com'));
        $this->assertSame('', _normalize_http_host('example.com:bad'));
    }

    public function testSameHttpHostUrlUsesNormalizedHostComparison(): void
    {
        $this->assertTrue(_is_same_http_host_url('https://example.com/image.png', 'Example.COM:443'));
        $this->assertTrue(_is_same_http_host_url('http://127.0.0.1:8080/image.png', '127.0.0.1'));

        $this->assertFalse(_is_same_http_host_url('https://evil.example/image.png', 'example.com'));
        $this->assertFalse(_is_same_http_host_url('ftp://example.com/image.png', 'example.com'));
        $this->assertFalse(_is_same_http_host_url('https://example.com/image.png', "example.com\r\nX-Test: 1"));
    }

    public function testMinorLineDeltaParsesRcsLineCountsWithoutEvaluatingCode(): void
    {
        $this->assertSame(2, _minor_line_delta('+3 -1'));
        $this->assertSame(-2, _minor_line_delta('+1 -3'));

        $this->assertFalse(_minor_line_delta('phpinfo()'));
        $this->assertFalse(_minor_line_delta('+1; phpinfo()'));
    }
}
