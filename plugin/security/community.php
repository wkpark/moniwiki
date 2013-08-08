<?php
# a community security plugin for the MoniWiki
# $Id: community.php,v 1.7 2010/08/10 05:40:47 wkpark Exp $

class Security_community extends Security_base {
  var $DB;

  function Security_community($DB="") {
    $this->DB=$DB;
    $this->public_pages = array('WikiSandBox','WikiSandbox','GuestBook','SandBox');
    if (!empty($DB->public_pages))
      $this->public_pages = array_merge($DB->public_pages, $this->public_pages);
  }

# $options[page]: pagename
# $options[id]: user id

  function writable($options="") {
    return $this->DB->_isWritable($options['page']);
  }

  function may_edit($action,&$options) {
    if (!isset($options['page'][0])) return 0; # XXX
    if (in_array($options['page'],$this->public_pages)) return 1;
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      return 0;
    }
    return 1;
  }

  function may_blog($action,&$options) {
    if (!isset($options['page'][0])) return 0; # XXX
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      return 0;
    }
    return 1;
  }

  function may_uploadfile($action,&$options) {
    if (!isset($options['page'][0])) return 0; # XXX
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      return 0;
    }
    return 1;
  }

  function is_allowed($action='read',&$options) {
    $allowed_actions=array('read','theme','css','userform','bookmark','goto','dot',
      'trackback','rss_rc','rss','blogrss','urlencode','deletepage',
      'titlesearch','info','download','comment','notitle','fixmoin');
    $notallowed_actions=array('raw','recall','diff','info','rcs','deletepage',
      'fullsearch');
    if (in_array(strtolower($action),$allowed_actions)) return 1;
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page."),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      return 0;
    }
    $method='may_'.$action;
    if (method_exists($this, $method)) {
      return $this->$method ($action,$options);
    }
    return 1;
  }

  function is_protected($action="read",&$options) {
    # password protected POST actions
    $protected_actions=array("rcs","rcspurge","chmod","backup","restore","deletefile");
    $notprotected_actions=array("userform");
    $action=strtolower($action);

    if (in_array($action,$protected_actions)) return 1;
    if (in_array($action,$notprotected_actions)) return 0;
    if ($options['id']=='Anonymous') return 1;

    return 0;
  }
// vim:et:sts=2:
}

?>
