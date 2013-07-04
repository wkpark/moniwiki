<?php
# a sample security plugin for the MoniWiki
# $Id: sample.php,v 1.4 2006/01/14 03:25:24 wkpark Exp $

class Security_sample extends Security_base {
    var $DB;

    function Security_sample($DB="") {
        $this->DB=$DB;
    }

# $options[page]: pagename
# $options[id]: user id

    function writable($options="") {
        return $this->DB->_isWritable($options['page']);
    }

    function is_allowed($action="read",&$options) {
        # basic allowed actions
        $allowed_actions=array("edit","savepage","read","diff","info","likepages","uploadfile","uploadedfiles","css","theme","deletepage");
        $action=strtolower($action);
        if (in_array($action,$allowed_actions))
            return 1;
        return 0;
    }
}

// vim:et:sts=4:
?>
