<?php

class Security_sample extends Security {
  var $DB;

  function Security_sample($DB="") {
    $this->DB=$DB;
  }

# $options[page]: pagename
# $options[id]: user id

  function writable($options="") {
    return $this->DB->_isWritable($options['page']);
  }

  function is_allowed($action="read",$options) {
    # basic allowed actions
    $allowed_actions=array("edit","savepage","read","diff","info","likepages","uploadfile","uploadedfiles","css","theme","deletepage");
    $action=strtolower($action);
    if (in_array($action,$allowed_actions))
      return 1;
    return 0;
  }
}

?>
