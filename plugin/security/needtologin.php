<?php
# a needtologin security plugin for the MoniWiki
# $Id: needtologin.php,v 1.7 2006/01/04 16:51:37 wkpark Exp $

class Security_needtologin extends Security_base {
  var $DB;

  function Security_needtologin($DB="") {
    $this->DB=$DB;
  }

  function help($formatter) {
    return $formatter->macro_repl('UserPreferences');
  }

# $options[page]: pagename
# $options[id]: user id

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
    if (!isset($options['page'][0])) return 0;
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      $options['help']='help';
      return 0;
    }
    return 1;
  }

  function is_allowed($action="read",&$options) {
    $method='may_'.$action;
    if (method_exists($this, $method)) {
      return $this->$method ($action,$options);
    }
    if ($options['id']!='Anonymous')
      return 1;

    // XXX
    return 1;
  }
}

?>
