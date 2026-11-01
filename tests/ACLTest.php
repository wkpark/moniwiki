<?php

use PHPUnit\Framework\TestCase;

if (!defined('INC_MONIWIKI')) define('INC_MONIWIKI', 1);
require_once MONIWIKI_TEST_ROOT.'/wikilib.php';
require_once MONIWIKI_TEST_ROOT.'/wiki.php';
require_once MONIWIKI_TEST_ROOT.'/plugin/security/acl.php';
require_once MONIWIKI_TEST_ROOT.'/plugin/aclinfo.php';

final class ACLTest extends TestCase
{
    private $dir;
    private $globals;

    protected function setUp(): void
    {
        foreach (array('Config', 'DBInfo', '_SERVER', '_POST') as $key)
            $this->globals[$key] = $GLOBALS[$key] ?? null;
        $this->dir = sys_get_temp_dir().'/moniwiki-acl-'.bin2hex(random_bytes(8));
        mkdir($this->dir);
        $GLOBALS['Config'] = array('cache_dir'=>$this->dir);
        $GLOBALS['DBInfo'] = (object)array('config_dir'=>$this->dir,
            'owners'=>array('Owner'), 'wikimasters'=>array(), 'security_class'=>'acl',
            'user'=>(object)array('id'=>'Tester', 'is_member'=>true, 'groups'=>array()));
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = array();
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($this->dir);
        foreach ($this->globals as $key=>$value) {
            if ($value === null) unset($GLOBALS[$key]);
            else $GLOBALS[$key] = $value;
        }
    }

    private function security($lines = array('* @ALL allow *'))
    {
        $security = new Security_ACL($GLOBALS['DBInfo']);
        list($pages, $rules, $groups) = $security->parse_acl($lines);
        $security->AUTH_ACL = $lines;
        $security->group = $groups;
        $security->default = $rules['*'] ?? array();
        $security->rules = array();
        $patterns = array();
        foreach ($rules as $pattern=>$acl) {
            if ($pattern == '*') continue;
            $patterns[] = '('.$pattern.')';
            $security->rules[count($patterns)] = $acl;
        }
        $security->rule = $patterns ? implode('|', $patterns) : '(?!)';
        $security->cache = new Cache_Text('acl');
        $security->aux_cache = new Cache_Text('aux_acl');
        foreach ($pages as $page=>$acl) $security->cache->update($page, $acl, 0);
        $GLOBALS['DBInfo']->security = $security;
        return $security;
    }

    private function decision($security, $page, $action, $id = 'Tester')
    {
        $security->_acl_ok = 0;
        $options = array('page'=>$page, 'id'=>$id);
        return $security->is_allowed($action, $options);
    }

    public function testDefaultExactPatternAndUserPriorities(): void
    {
        $s = $this->security(array('* @ALL allow *', 'Secret* @ALL deny read',
            'SecretPage Tester allow read', '@Staff Tester 20', 'SecretPage @Staff deny savepage'));
        $this->assertEquals(1, $this->decision($s, 'Public', 'read'));
        $this->assertEquals(0, $this->decision($s, 'SecretOther', 'read'));
        $this->assertEquals(1, $this->decision($s, 'SecretPage', 'read'));
        $this->assertEquals(0, $this->decision($s, 'SecretPage', 'savepage'));
    }

    public function testUserProfileGroupsAreAppliedOnlyToThatUser(): void
    {
        $s = $this->security(array('* @ALL allow *', '* @Staff deny read'));
        $GLOBALS['DBInfo']->user->groups = array('@Staff');
        $this->assertEquals(0, $this->decision($s, 'Page', 'read'));
        $this->assertEquals(1, $this->decision($s, 'Page', 'read', 'Other'));
    }

    public function testAuxiliaryMetadataOnDeletedPageDoesNotBecomeActions(): void
    {
        $s = $this->security();
        $acl = array('@ALL'=>array('deny'=>array('deletepage'), 'ttl'=>3600,
            'mtime'=>time(), '.editor'=>'Tester'));
        $s->add_page_acl('DeletedPage', $acl, array('ttl'=>3600));
        $this->assertEquals(1, $this->decision($s, 'DeletedPage', 'read'));
        $this->assertEquals(0, $this->decision($s, 'DeletedPage', 'deletepage'));
        $this->assertSame($acl, $s->get_page_acl('DeletedPage'));
    }

    public function testExpiredGroupIsIgnoredEvenWithoutBaseACL(): void
    {
        $s = $this->security();
        $s->aux_cache->update('Page', array('@ALL'=>array('deny'=>array('read'),
            'ttl'=>10, 'mtime'=>time()-100, '.editor'=>'Tester')), 0);
        $this->assertEquals(1, $this->decision($s, 'Page', 'read'));
    }

    public function testAuxiliaryGroupCanBeReplacedAndRemovedWithoutAffectingOthers(): void
    {
        $s = $this->security();
        $s->add_page_acl('Page', array('@ALL'=>array('deny'=>array('read'))));
        $s->add_page_acl('Page', array('@User'=>array('deny'=>array('savepage'))));
        $s->add_page_acl('Page', array('@ALL'=>array('allow'=>array('read'))));
        $this->assertEquals(1, $this->decision($s, 'Page', 'read'));
        $s->add_page_acl('Page', array('@ALL'=>null));
        $this->assertArrayNotHasKey('@ALL', $s->get_page_acl('Page'));
        $this->assertEquals(0, $this->decision($s, 'Page', 'savepage'));
        $s->add_page_acl('Page', null);
        $this->assertFalse($s->get_page_acl('Page'));
    }

    public function testUnlistedActionRetainsDefaultAllowWithoutWarnings(): void
    {
        $s = $this->security(array('* @ALL deny deletepage'));
        $this->assertEquals(1, $this->decision($s, 'Page', 'read'));
        $this->assertEquals(0, $this->decision($s, 'Page', 'deletepage'));
    }

    public function testOwnersBypassACL(): void
    {
        $s = $this->security(array('* @ALL deny *'));
        $this->assertEquals(1, $this->decision($s, 'Page', 'read', 'Owner'));
    }

    public function testProtectionAndActionNormalization(): void
    {
        $s = $this->security(array('* @ALL allow *', '* @ALL protect restore', '* @ALL deny macro/test'));
        $this->assertEquals(0, $this->decision($s, 'Page', 'MACRO-TEST'));
        $options = array('page'=>'Page', 'id'=>'Tester');
        $this->assertEquals(1, $s->is_protected('restore', $options));
        $this->assertEquals(0, $s->is_protected('read', $options));
    }

    public function testAclInfoFormReflectsMemberAndAnonymousPermissions(): void
    {
        $s = $this->security(array('* @ALL allow *', '* Anonymous deny savepage'));
        $formatter = (object)array('page'=>(object)array('name'=>'Page'));
        $form = macro_AclInfo($formatter, '', array('.call'=>1));
        $this->assertSame(" checked='checked'", $form['@ALL']);
        $this->assertSame('', $form['Anonymous']);
    }
    private function aclInfo($options)
    {
        $formatter = new class {
            public $page;
            function __construct() { $this->page = (object)array('name'=>'Page'); }
            function send_header($title, $options) {}
            function send_title($title, $link, $options) {}
            function send_footer($title, $options) {}
        };
        ob_start();
        try {
            do_aclinfo($formatter, array_merge(array('id'=>'Tester', 'page'=>'Page'), $options));
            return ob_get_contents();
        } finally { ob_end_clean(); }
    }

    public function testAclInfoAdvancedPostPersistsAndRemovesPermissions(): void
    {
        $s = $this->security();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $out = $this->aclInfo(array('group'=>'@ALL', 'type'=>'deny', 'act'=>array('savepage'), 'ttl'=>1));
        $this->assertStringContainsString('savepage', $out);
        $acl = $s->get_page_acl('Page');
        $this->assertSame(86400, $acl['@ALL']['ttl']);
        $this->assertSame('Tester', $acl['@ALL']['.editor']);
        $this->assertEquals(0, $this->decision($s, 'Page', 'savepage'));
        $this->aclInfo(array('value'=>'Page', 'remove'=>array('@ALL'=>'Delete')));
        $this->assertEquals(1, $this->decision($s, 'Page', 'savepage'));
    }

    public function testAclInfoSimplePostUsesDefaultActionsAndTTL(): void
    {
        $s = $this->security();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->aclInfo(array('control'=>'Change', 'group'=>array()));
        $acl = $s->get_page_acl('Page');
        $this->assertSame(3600, $acl['@ALL']['ttl']);
        $this->assertContains('savepage', $acl['@ALL']['deny']);
        $this->assertEquals(0, $this->decision($s, 'Page', 'savepage'));
    }

    public function testAclInfoRejectsUnknownActionsAndGroups(): void
    {
        $s = $this->security();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->aclInfo(array('group'=>'@ALL', 'type'=>'deny', 'act'=>array('unknown'), 'ttl'=>1));
        $this->assertFalse($s->get_page_acl('Page'));
        $this->aclInfo(array('group'=>'@Unknown', 'type'=>'deny', 'act'=>array('savepage'), 'ttl'=>1));
        $this->assertFalse($s->get_page_acl('Page'));
    }

    public function testAclInfoOwnerCanRenderPage(): void
    {
        $this->security();
        $GLOBALS['DBInfo']->user->id = 'Owner';
        $this->assertStringContainsString('Add ACL', $this->aclInfo(array('id'=>'Owner')));
    }

    public function testExpiredAuxiliaryDoesNotOverrideStaticRule(): void
    {
        $s = $this->security(array('* @ALL allow *', 'Page @ALL deny read'));
        $s->aux_cache->update('Page', array('@ALL'=>array('allow'=>array('read'),
            'ttl'=>10, 'mtime'=>time()-100)), 0);
        $this->assertEquals(0, $this->decision($s, 'Page', 'read'));
    }

    public function testGuestAndNamedGroupMembership(): void
    {
        $s = $this->security(array('* @ALL allow *', '@Staff Tester,Other 10',
            '@Guest Anonymous', '* @Guest deny savepage', '* @Staff deny deletepage'));
        $this->assertEquals(0, $this->decision($s, 'Page', 'savepage', 'Anonymous'));
        $this->assertEquals(1, $this->decision($s, 'Page', 'savepage'));
        $this->assertEquals(0, $this->decision($s, 'Page', 'deletepage'));
    }

    public function testAclChecksFollowPageAndUserChanges(): void
    {
        $s = $this->security(array('* @ALL allow *', 'Secret @ALL deny read', '* Anonymous deny savepage'));
        $options = array('page'=>'Public', 'id'=>'Tester');
        $this->assertEquals(1, $s->is_allowed('read', $options));
        $options['page'] = 'Secret';
        $this->assertEquals(0, $s->is_allowed('read', $options));
        $options['id'] = 'Anonymous';
        $this->assertEquals(0, $s->is_allowed('savepage', $options));
        $this->assertEquals(0, $s->is_allowed('savepage', $options));
    }

    public function testAclInfoPreviewDoesNotChangeCurrentUsersPermissions(): void
    {
        $s = $this->security(array('* @ALL allow *', '* Tester deny savepage'));
        $formatter = (object)array('page'=>(object)array('name'=>'Page'));
        macro_AclInfo($formatter, '', array('.call'=>1));
        $options = array('page'=>'Page', 'id'=>'Tester');
        $this->assertEquals(0, $s->is_allowed('savepage', $options));
    }

    public function testConfigurationLoadsAndReloadsFromDiskCache(): void
    {
        file_put_contents($this->dir.'/acl.default.php', "* @ALL allow *\nSecret* @ALL deny read\nSecretPage Tester allow read\n");
        foreach (array(1, 2) as $pass) {
            $s = new Security_ACL($GLOBALS['DBInfo']);
            $this->assertEquals(0, $this->decision($s, 'SecretOther', 'read'));
            $this->assertEquals(1, $this->decision($s, 'SecretPage', 'read'));
        }
    }

    public function testAuxiliaryMutationInvalidatesCachedDecision(): void
    {
        $s = $this->security();
        $options = array('page'=>'Page', 'id'=>'Tester');
        $this->assertEquals(1, $s->is_allowed('read', $options));
        $s->add_page_acl('Page', array('@ALL'=>array('deny'=>array('read'))));
        $this->assertEquals(0, $s->is_allowed('read', $options));
        $s->add_page_acl('Page', null);
        $this->assertEquals(1, $s->is_allowed('read', $options));
    }

    public function testProtectionFollowsPageChanges(): void
    {
        $s = $this->security(array('* @ALL allow *', 'Secret @ALL protect read'));
        $options = array('page'=>'Public', 'id'=>'Tester');
        $this->assertEquals(0, $s->is_protected('read', $options));
        $options['page'] = 'Secret';
        $this->assertEquals(1, $s->is_protected('read', $options));
    }

    public function testAclInfoNonMemberCannotPersistChanges(): void
    {
        $s = $this->security();
        $GLOBALS['DBInfo']->user->is_member = false;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->aclInfo(array('group'=>'@ALL', 'type'=>'deny', 'act'=>array('savepage'), 'ttl'=>1));
        $this->assertFalse($s->get_page_acl('Page'));
    }

    public function testSpecificDenyOverridesWildcardAllowAndExplicitCheckIsTriState(): void
    {
        $s = $this->security(array('* @ALL allow *', '* @User deny savepage'));
        $this->assertEquals(0, $this->decision($s, 'Page', 'savepage'));
        $options = array('page'=>'Page', 'id'=>'Tester', 'explicit'=>1);
        $this->assertFalse($s->acl_check('read', $options));
        $this->assertSame(0, $s->acl_check('savepage', $options));
    }

    public function testRemovingMissingAuxiliaryGroupIsIdempotent(): void
    {
        $s = $this->security();
        $s->add_page_acl('Page', array('@ALL'=>null));
        $s->add_page_acl('Page', array('@ALL'=>null));
        $this->assertFalse($s->get_page_acl('Page'));
    }

    public function testExpiredAuxiliaryFallsBackToPatternRule(): void
    {
        $s = $this->security(array('* @ALL allow *', 'Secret* @ALL deny read'));
        $s->aux_cache->update('SecretPage', array('@ALL'=>array('allow'=>array('read'),
            'ttl'=>10, 'mtime'=>time()-100)), 0);
        $this->assertEquals(0, $this->decision($s, 'SecretPage', 'read'));
    }

}
