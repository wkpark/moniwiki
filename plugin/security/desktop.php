<?php
# $Id: desktop.php,v 1.1 2003/08/10 08:22:44 wkpark Exp $

class Security_desktop extends Security_base {
  var $DB;

  function Security_desktop($DB="") {
    $this->DB=$DB;
  }

  function writable($options="") {
    return $this->DB->_isWritable($options['page']);
  }

  function is_allowed($action="read",&$options) {
    return 1;
  }

  function is_protected($action="read",$options) {
    return 0;
  }
}

?>
