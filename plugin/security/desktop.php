<?php
# $Id$

class Security_desktop extends Security {
  var $DB;

  function Security_desktop($DB="") {
    $this->DB=$DB;
  }

  function writable($options="") {
    return $this->DB->_isWritable($options['page']);
  }

  function is_allowed($action="read",$options) {
    return 1;
  }

  function is_protected($action="read",$options) {
    return 0;
  }
}

?>
