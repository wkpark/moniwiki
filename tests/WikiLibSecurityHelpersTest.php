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
}
