<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/wikilib.php';
require_once MONIWIKI_TEST_ROOT.'/plugin/processor/monimarkup.php';
require_once MONIWIKI_TEST_ROOT.'/plugin/processor/blog.php';

final class MonimarkupBlogTest extends TestCase
{
    private $savedGlobals;

    protected function setUp(): void
    {
        foreach (array('Config', 'DBInfo', 'inline', 'block', 'btype', 'options') as $name) {
            $this->savedGlobals[$name] = array(array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null);
        }
        $GLOBALS['Config'] = array('inline_latex'=>0, 'hr_type'=>'plain');
        $GLOBALS['DBInfo'] = new class {
            public $use_trackback = false;
            public $date_fmt_blog = 'Y-m-d';
            public function hasPage($name) { return false; }
        };
        $GLOBALS['options'] = array('noaction'=>1);
    }

    protected function tearDown(): void
    {
        foreach ($this->savedGlobals as $name=>$saved) {
            if ($saved[0]) $GLOBALS[$name] = $saved[1];
            else unset($GLOBALS[$name]);
        }
    }

    public static function blogCases(): array
    {
        return array('without comments'=>array(false), 'with comments'=>array(true));
    }

    /** @dataProvider blogCases */
    public function testAllBlogEntriesSurviveNestedRendering(bool $comments): void
    {
        $formatter = new MonimarkupBlogTestFormatter();
        $processor = new processor_monimarkup($formatter);
        $source = '';
        for ($i = 1; $i <= 4; $i++) {
            $source .= "{{{#!blog Author 2025-06-07T10:00:00 Title$i\nBody$i\n";
            if ($comments) $source .= "----\nComment$i\n";
            $source .= "}}}\n\n";
        }
        // The inline placeholder must also survive rendering the blog bodies.
        $source .= '{{{inline tail}}}';

        $html = $processor->process($source);

        $this->assertSame(4, substr_count($html, "class='blog'"));
        for ($i = 1; $i <= 4; $i++) {
            $this->assertStringContainsString("Title$i", $html);
            $this->assertStringContainsString("Body$i", $html);
            if ($comments) $this->assertStringContainsString("Comment$i", $html);
        }
        $this->assertStringContainsString('<code>inline tail</code>', $html);
        $this->assertStringNotContainsString("\007", $html);
        $this->assertStringNotContainsString("\035", $html);
    }

    public function testProcessorFailureRestoresOuterState(): void
    {
        $GLOBALS['inline'] = array(1=>'outer inline');
        $GLOBALS['block'] = array(1=>'outer block');
        $GLOBALS['btype'] = array(1=>'blog');
        $GLOBALS['options'] = array('noaction'=>1);
        $formatter = new class {
            public function processor_repl($type, $text, $options) {
                $GLOBALS['inline'] = $GLOBALS['block'] = $GLOBALS['btype'] = $GLOBALS['options'] = array();
                throw new RuntimeException('nested rendering failed');
            }
        };
        $processor = new processor_monimarkup($formatter);

        try {
            $processor->_block(array("\0071\007", '1'));
            $this->fail('The processor exception must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('nested rendering failed', $exception->getMessage());
        }
        $this->assertSame(array(1=>'outer inline'), $GLOBALS['inline']);
        $this->assertSame(array(1=>'outer block'), $GLOBALS['block']);
        $this->assertSame(array(1=>'blog'), $GLOBALS['btype']);
        $this->assertSame(array('noaction'=>1), $GLOBALS['options']);
    }
}

// Render blog bodies through the real processor without a wiki database.
final class MonimarkupBlogTestFormatter
{
    public $text = '';
    public $sect_num = 0;
    public $pi = array();
    public $baserule = array();
    public $baserepl = array();
    public $wordrule = '(?!)';
    public $footrule = '(?!)';
    public $auto_linebreak = true;
    public $use_smileys = false;
    public $smiley_rule = '';
    public $preview = false;
    public $trackback_list = array();
    public $perma_icon = '#';
    public $tz_offset = 0;
    public $page;

    public function __construct()
    {
        $this->page = (object) array('name'=>'BlogTest');
    }

    public function processor_repl($type, $text, $options)
    {
        return processor_blog($this, $text, $options);
    }

    public function send_page($text, $options)
    {
        $processor = new processor_monimarkup($this);
        echo $processor->process($text, $options);
    }

    public function link_repl($text)
    {
        return '<code>'.htmlspecialchars(substr($text, 3, -3)).'</code>';
    }
}
