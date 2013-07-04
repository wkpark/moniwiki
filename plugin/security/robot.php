<?php
# a robot protection security plugin for the MoniWiki
# $Id: robot.php,v 1.1 2008/12/16 13:45:30 wkpark Exp $

class Security_robot extends Security_base {
  var $DB;

  function Security_robot($DB="") {
    $this->DB=$DB;
  }

# $options[page]: pagename
# $options[id]: user id

  function writable($options="") {
    return $this->DB->_isWritable($options['page']);
  }

  function is_allowed($action="read",&$options) {
    $allowed=array('read','show','ticket','titleindex','rss_rc');
    if (in_array($action, $allowed)) {
      return 1;
    }
    return 0;
  }
}

?>
