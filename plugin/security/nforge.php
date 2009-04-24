<?php
#
# nFORGE plugin by semtlnori
#
# based on needtologin security plugin.
#
# $Id$

class Security_nforge extends Security {
  var $DB;

  function Security_nforge($DB='') {
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
    # $public_pages=array('WikiSandBox','WikiSandbox','GuestBook','SandBox');
    if (!$options['page']) return 0; # XXX
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
    if (!$options['page']) return 0; # XXX
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      $options['help']='help';
      return 0;
    }
    return 1;
  }

  function may_uploadfile($action,&$options) {
    if (!$options['page']) return 0;
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
      if (!$this->$method($action,$options)) {
        header('Location: /account/login.php?return_to='.$_SERVER['SCRIPT_URI']);
        exit;
      }
    }
    return 1;
  }

}

?>
