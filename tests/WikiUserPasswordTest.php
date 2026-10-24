<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/wikilib.php';

final class WikiUserPasswordTest extends TestCase
{
    public function testSetPasswdStoresHashThatCheckPasswdAccepts(): void
    {
        $user = new WikiUser('TestUser');
        $user->info = array();

        $this->assertGreaterThan(0, $user->setPasswd('Abcdef12345'));
        $this->assertNotSame('Abcdef12345', $user->info['password']);
        $this->assertTrue($user->checkPasswd('Abcdef12345'));
        $this->assertFalse($user->checkPasswd('Abcdef12346'));
    }

    public function testCheckPasswdKeepsAcceptingLegacyCryptHash(): void
    {
        $user = new WikiUser('TestUser');
        $user->info = array('password'=>crypt('legacy-pass', 'ab'));

        $this->assertTrue($user->checkPasswd('legacy-pass'));
        $this->assertFalse($user->checkPasswd('other-pass'));
    }
}
