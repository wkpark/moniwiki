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

    public function testClassTokenAllowsOnlySafeCssClassNames(): void
    {
        $this->assertTrue(_safe_class_token('wiki-note_1'));
        $this->assertFalse(_safe_class_token('x" onclick="alert(1)'));
        $this->assertFalse(_safe_class_token("x\ny"));
        $this->assertFalse(_safe_class_token('../wiki'));
    }

    public function testWikiUserRejectsControlCharactersInIds(): void
    {
        $user = new WikiUser('ValidUser');

        $this->assertTrue($user->checkID('ValidUser'));
        $this->assertFalse($user->checkID("Bad\r\nBcc: attacker@example.com"));
    }

    public function testPlainProcessorDropsUnsafeClassToken(): void
    {
        $out = processor_plain(null, "#!plain x' onclick='alert(1)\nhello");

        $this->assertStringContainsString("<pre class='wiki'>", $out);
        $this->assertStringNotContainsString("onclick", $out);
    }

    public function testPlainProcessorKeepsSafeClassToken(): void
    {
        $out = processor_plain(null, "#!plain python\nhello");

        $this->assertStringContainsString("<pre class='wiki python'>", $out);
    }

    public function testQualifiedUrlNormalizesHostAndKeepsProxyPortBehavior(): void
    {
        $server = $_SERVER;
        $_SERVER['SERVER_PORT'] = '8080';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['HTTP_HOST'] = 'Example.COM:12345';
        $_SERVER['SERVER_NAME'] = 'fallback.example';
        $_SERVER['HTTPS'] = '';

        try {
            $this->assertSame('http://example.com:12345/wiki.php', qualifiedUrl('/wiki.php'));

            $_SERVER['HTTP_HOST'] = 'Example.COM';
            $this->assertSame('http://example.com:8080/wiki.php', qualifiedUrl('/wiki.php'));

            $_SERVER['HTTP_HOST'] = "bad.example\r\nX-Test: 1";
            $this->assertSame('http://fallback.example/wiki.php', qualifiedUrl('/wiki.php'));

            unset($_SERVER['HTTP_HOST']);
            $this->assertSame('http://fallback.example:8080/wiki.php', qualifiedUrl('/wiki.php'));

            $_SERVER['SERVER_NAME'] = "bad\r\nname";
            $this->assertSame('http://localhost:8080/wiki.php', qualifiedUrl('/wiki.php'));
        } finally {
            $_SERVER = $server;
        }
    }

    public function testWikiUserCookieDomainUsesNormalizedHost(): void
    {
        $server = $_SERVER;
        $config = isset($GLOBALS['Config']) ? $GLOBALS['Config'] : null;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['HTTP_HOST'] = 'Example.COM:12345';
        $_SERVER['REQUEST_URI'] = '/wiki.php';
        $_SERVER['HTTPS'] = '';
        $GLOBALS['Config'] = array();

        try {
            $user = new WikiUser('ValidUser');
            $cookie = $user->setCookie();
        } finally {
            $_SERVER = $server;
            if ($config === null) unset($GLOBALS['Config']);
            else $GLOBALS['Config'] = $config;
        }

        $this->assertStringContainsString('; Domain=example.com', $cookie);
        $this->assertStringNotContainsString('12345', $cookie);
    }

    public function testWikiUserCookieDomainSkipsInvalidHostAndNormalizesUnsetCookie(): void
    {
        $server = $_SERVER;
        $config = isset($GLOBALS['Config']) ? $GLOBALS['Config'] : null;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['HTTP_HOST'] = 'Example.COM:12345';
        $_SERVER['REQUEST_URI'] = '/wiki.php';
        $_SERVER['HTTPS'] = '';
        $GLOBALS['Config'] = array();

        try {
            $user = new WikiUser('ValidUser');
            $unset = $user->unsetCookie();

            $_SERVER['HTTP_HOST'] = "bad.example\r\nX-Test: 1";
            $invalid = $user->setCookie();
        } finally {
            $_SERVER = $server;
            if ($config === null) unset($GLOBALS['Config']);
            else $GLOBALS['Config'] = $config;
        }

        $this->assertStringContainsString('; Domain=example.com', $unset);
        $this->assertStringNotContainsString('12345', $unset);
        $this->assertStringNotContainsString('Domain=', $invalid);
        $this->assertStringNotContainsString('X-Test', $invalid);
    }

    public function testGotoMetaRefreshEscapesInternalUrl(): void
    {
        $GLOBALS['DBInfo'] = new stdClass();
        $GLOBALS['DBInfo']->charset = 'UTF-8';
        $GLOBALS['DBInfo']->interwikirule = 'NoSuchWiki';

        ob_start();
        do_goto(new stdClass(), array(
            'value'=>'',
            'url'=>'Self:" autofocus onfocus="alert(1)',
            'ie'=>'',
            'oe'=>'UTF-8',
        ));
        $out = ob_get_clean();

        $this->assertStringContainsString('URL=Self:&quot; autofocus onfocus=&quot;alert(1)', $out);
        $this->assertStringNotContainsString('URL=Self:" autofocus', $out);
    }

    public function testGotoRejectsScriptableUrl(): void
    {
        $GLOBALS['DBInfo'] = new stdClass();
        $GLOBALS['DBInfo']->charset = 'UTF-8';
        $GLOBALS['DBInfo']->interwikirule = 'NoSuchWiki';
        $formatter = $this->gotoFormatter();

        ob_start();
        do_goto($formatter, array(
            'value'=>'',
            'url'=>'javascript:alert(1)',
            'ie'=>'',
            'oe'=>'UTF-8',
        ));
        $out = ob_get_clean();

        $this->assertStringNotContainsString('URL=javascript:', $out);
        $this->assertStringNotContainsString('javascript:', implode("\n", $formatter->headers));
    }

    public function testGotoChecksUrlAfterDecoding(): void
    {
        $GLOBALS['DBInfo'] = new stdClass();
        $GLOBALS['DBInfo']->charset = 'UTF-8';
        $GLOBALS['DBInfo']->interwikirule = 'NoSuchWiki';
        $formatter = $this->gotoFormatter();

        ob_start();
        do_goto($formatter, array(
            'value'=>'',
            'url'=>'data%3Atext/plain,hello',
            'ie'=>'EUC-KR',
            'oe'=>'UTF-8',
        ));
        $out = ob_get_clean();

        $this->assertStringNotContainsString('URL=data', $out);
        $this->assertStringNotContainsString('data', implode("\n", $formatter->headers));
    }

    public function testGotoInterWikiFallsBackToPageForScriptableUrl(): void
    {
        $GLOBALS['DBInfo'] = new stdClass();
        $GLOBALS['DBInfo']->charset = 'UTF-8';
        $GLOBALS['DBInfo']->interwikirule = 'BAD';
        $GLOBALS['DBInfo']->interwiki = array('BAD'=>'javascript:alert(1)//');
        $formatter = $this->gotoFormatter();

        ob_start();
        do_goto($formatter, array('value'=>'BAD:Page', 'url'=>'', 'ie'=>'', 'oe'=>''));
        $out = ob_get_clean();

        $headers = implode("\n", $formatter->headers);
        $this->assertStringContainsString('Location: /wiki.php/BAD', $headers);
        $this->assertStringNotContainsString('javascript:', $headers.$out);
    }

    private function gotoFormatter()
    {
        return new class {
            public $headers = array();
            public $page;

            public function __construct()
            {
                $this->page = (object)array('urlname'=>'FrontPage');
            }

            public function link_url($pageurl, $query_string = '')
            {
                return '/wiki.php/'.$pageurl.$query_string;
            }

            public function header($header, $replace = true)
            {
            }

            public function send_header($header = '', $options = array())
            {
                $this->headers = array_merge($this->headers, (array)$header);
            }

            public function send_title($title = '', $link = '', $options = array())
            {
            }

            public function send_footer($args = '', $options = array())
            {
            }
        };
    }

    public function testUserPreferencesEscapesVerifiedEmailValue(): void
    {
        $server = $_SERVER;
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = '';

        $GLOBALS['DBInfo'] = new stdClass();
        $GLOBALS['DBInfo']->anonymous_friendly = true;
        $GLOBALS['DBInfo']->url_prefix = '';
        $GLOBALS['DBInfo']->user = new WikiUser();
        $GLOBALS['DBInfo']->user->id = 'Anonymous';
        $GLOBALS['DBInfo']->user->verified_email = '" autofocus onfocus="alert(1)';

        $formatter = new class {
            public $_macrocache = false;
            public $_dynamic_macros = array();
            public $page;

            public function __construct()
            {
                $this->page = (object)array('urlname'=>'UserPreferences');
            }

            public function link_url($pageurl, $query_string = '')
            {
                return '/wiki.php'.$query_string;
            }
        };

        try {
            $out = macro_UserPreferences($formatter, '', array());
        } finally {
            $_SERVER = $server;
        }

        $this->assertStringContainsString('value="&quot; autofocus onfocus=&quot;alert(1)"', $out);
        $this->assertStringNotContainsString('value="" autofocus', $out);
    }

    public function testInterWikiEscapesGeneratedAttributes(): void
    {
        $GLOBALS['DBInfo'] = new stdClass();
        $GLOBALS['DBInfo']->interwiki = array(
            'BAD'=>'https://example.com/\' onclick=\'alert(1)',
        );
        $GLOBALS['DBInfo']->interwikirule = 'BAD';
        $GLOBALS['DBInfo']->intericon = array(
            'BAD'=>array(16, 16, "https://example.com/icon.png' onerror='alert(1)"),
        );
        $GLOBALS['DBInfo']->imgs_url_interwiki = '/imgs/interwiki/';

        $out = macro_InterWiki((object)array('refresh'=>false), '', array());

        $this->assertStringContainsString('https://example.com/&#039; onclick=&#039;alert(1)', $out);
        $this->assertStringNotContainsString("src='https://example.com/icon.png' onerror", $out);
        $this->assertStringNotContainsString("href='https://example.com/' onclick", $out);
    }

    public function testInterWikiDropsScriptableLinks(): void
    {
        $GLOBALS['DBInfo'] = new stdClass();
        $GLOBALS['DBInfo']->interwiki = array('BAD'=>'javascript:alert(1)');
        $GLOBALS['DBInfo']->interwikirule = 'BAD';
        $GLOBALS['DBInfo']->intericon = array('BAD'=>array(16, 16, 'javascript:alert(2)'));
        $GLOBALS['DBInfo']->imgs_url_interwiki = '/imgs/interwiki/';

        $out = macro_InterWiki((object)array('refresh'=>false), '', array());

        $this->assertStringContainsString("href='#'", $out);
        $this->assertStringContainsString("src='/imgs/interwiki/bad-16.png'", $out);
        $this->assertStringNotContainsString('javascript:alert', $out);
    }

    public function testTableOfContentsTitleKeepsSafeMarkupAndFiltersScriptBoundaries(): void
    {
        $GLOBALS['DBInfo'] = new class {
            public function hasPage($page)
            {
                return false;
            }
        };

        $formatter = new class {
            public $text = "== Heading ==\n";
            public $wordrule = '';
            public $footrule = '';
            public $baserule = '/a^/';
            public $baserepl = '';
            public $toc;
            public $nomacro;

            public function macro_repl($macro, $value = '', $options = array())
            {
                return $value;
            }

            public function link_repl($match)
            {
                return is_array($match) ? $match[0] : $match;
            }

            public function link_url($pageurl, $query_string = '')
            {
                return '/wiki.php/'.$pageurl.$query_string;
            }
        };

        $out = macro_TableOfContents($formatter, 'title=<b>Contents</b><img src=x onerror="alert(1)">,');

        $this->assertStringContainsString('<b>Contents</b>', $out);
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('<script', $out);
    }
}
