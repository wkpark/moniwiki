<?php
# a user based security plugin for the MoniWiki
# $Id$

class Security_wikimaster extends Security {
  var $DB;

  function Security_wikimaster($DB='') {
    $this->DB=$DB;
    $this->allowed_users=array_merge($DB->wikimasters,$DB->owners);
  }

  function writable($options='') {
    return $this->DB->_isWritable($options['page']);
  }

  function may_edit($action,$options) {
    if (!$options['page']) return 0;
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

  function may_rcspurge($action,$options) {
    if (!$options['page']) return 0;
    if (in_array($options['id'],$this->DB->owners)) return 1;
    return 0;
  }

  function is_allowed($action='read',$options) {
    $allowed_actions=array('theme','css','userform','bookmark','goto','dot',
      'trackback','rss_rc','rss','blogrss','urlencode');
    if (in_array(strtolower($action),$allowed_actions)) return 1;

    $method='may_'.$action;
    if (method_exists($this, $method)) {
      return $this->$method ($action,&$options);
    }
    return 1;
  }

  function is_protected($action="read",$options) {
    # password protected POST actions
    $protected_actions=array("rcs","chmod","backup","restore");
    $action=strtolower($action);

    if (in_array($action,$protected_actions)) {
      return 1;
    }
    return 0;
  }
}

?>
