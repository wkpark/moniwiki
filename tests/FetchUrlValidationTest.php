<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/plugin/fetch.php';

final class FetchUrlValidationTest extends TestCase
{
    public function testRejectsPrivateAndLocalUrls(): void
    {
        $this->assertTrue(_fetch_is_private_url('http://127.0.0.1/image.png'));
        $this->assertTrue(_fetch_is_private_url('http://localhost/image.png'));
        $this->assertTrue(_fetch_is_private_url('http://10.0.0.1/image.png'));
        $this->assertTrue(_fetch_is_private_url('http://169.254.169.254/latest/meta-data/'));
    }

    public function testAllowsPublicIpUrls(): void
    {
        $this->assertFalse(_fetch_is_private_url('http://8.8.8.8/image.png'));
    }
}
