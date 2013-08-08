<?php
# a mustlogin security plugin for the MoniWiki
# $Id: mustlogin.php,v 1.4 2006/07/07 14:36:02 wkpark Exp $

class Security_mustlogin extends Security_base {
  var $DB;

  function Security_mustlogin($DB="") {
    $this->DB=$DB;
  }

# $options[page]: pagename
# $options[id]: user id

  function help($formatter) {
    return $formatter->macro_repl('UserPreferences');
  }

  function writable($options="") {
    return $this->DB->_isWritable($options['page']);
  }

  function may_edit($action,&$options) {
    $public_pages=array('WikiSandBox','WikiSandbox','GuestBook','SandBox');
    if (!isset($options['page'][0])) return 0; # XXX
    if (in_array($options['page'],$public_pages)) return 1;
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      $options['help']='help';
      return 0;
    }
    return 1;
  }

  function may_blog($action,&$options) {
    if (!isset($options['page'][0])) return 0; # XXX
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      $options['help']='help';
      return 0;
    }
    return 1;
  }

  function may_uploadfile($action,&$options) {
    if (!isset($options['page'][0])) return 0; # XXX
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      $options['help']='help';
      return 0;
    }
    return 1;
  }

  function is_allowed($action="read",&$options) {
    $allowed_actions=array("userform",'ticket','bookmark');
    if (in_array($action,$allowed_actions)) return 1;
    $method='may_'.$action;
    if (method_exists($this, $method)) {
      return $this->$method ($action,$options);
    }
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page."),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      $options['help']='help';
      return 0;
    }
    return 1;
  }
}

?>
