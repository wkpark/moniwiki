<?php
# a needtologin security plugin for the MoniWiki
# $Id$

class Security_needtologin extends Security {
  var $DB;

  function Security_needtologin($DB="") {
    $this->DB=$DB;
  }

# $options[page]: pagename
# $options[id]: user id

  function writable($options="") {
    return $this->DB->_isWritable($options['page']);
  }

  function may_edit($action,&$options) {
    $public_pages=array('WikiSandBox','WikiSandbox','GuestBook','SandBox');
    if (!$options['page']) return 0; # XXX
    if (in_array($options['page'],$public_pages)) return 1;
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      return 0;
    }
    return 1;
  }

  function may_blog($action,&$options) {
    if (!$options['page']) return 0; # XXX
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      return 0;
    }
    return 1;
  }

  function may_uploadfile($action,&$options) {
    if (!$options['page']) return 0;
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
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
