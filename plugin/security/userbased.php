<?php
# a user based security plugin for the MoniWiki
# $Id$

class Security_userbased extends Security {
  var $DB;

  function Security_userbased($DB='') {
    $this->DB=$DB;
    $this->allowed_users=array_merge($DB->wikimasters,$DB->owners);
    $this->public_pages=array_merge($DB->public_pages,array(
      'WikiSandBox','WikiSandbox','GuestBook','SandBox'));
  }

  function writable($options='') {
    return $this->DB->_isWritable($options['page']);
  }

  function may_edit($action,$options) {
    if (!$options['page']) return 0;
    if (in_array($options['page'],$this->public_pages)) return 1;
    return 1;
  }

  function may_deletepage($action,$options) {
    if (!$options['page']) return 0;
    if (in_array($options['id'],$this->allowed_users)) return 1;
    $options['err']=sprintf(_("You are not allowed to '%s' on this page."),$action);
    $options['err'].=" "._("Please contact to WikiMaster");
    return 0;
  }

  function may_deletefile($action,$options) {
    if (!$options['page']) return 0;
    if (in_array($options['id'],$this->allowed_users)) return 1;
    $options['err']=sprintf(_("You are not allowed to '%s' on this page."),$action);
    $options['err'].=" "._("Please contact to WikiMaster");
    return 0;
  }

  function may_rename($action,$options) {
    if (!$options['page']) return 0;
    if (in_array($options['id'],$this->allowed_users)) return 1;
    $options['err']=sprintf(_("You are not allowed to '%s' on this page."),$action);
    $options['err'].=" "._("Please contact to WikiMaster");
    return 0;
  }

  function may_uploadfile($action,$options) {
    if (!$options['page']) return 0;
    return 1;
  }

  function is_allowed($action='read',$options) {
    $allowed_actions=array('theme','css','userform','bookmark','goto','dot',
      'trackback','rss_rc','rss','blogrss','urlencode');
    if (in_array(strtolower($action),$allowed_actions)) return 1;
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page."),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      return 0;
    }
    $method='may_'.$action;
    if (method_exists($this, $method)) {
      return $this->$method ($action,&$options);
    }
    return 1;
  }

  function is_protected($action="read",$options) {
    # password protected POST actions
    $protected_actions=array("rcspurge","chmod","backup","restore");
    $action=strtolower($action);

    if (in_array($action,$protected_actions)) {
      return 1;
    }
    return 0;
  }
}

?>
