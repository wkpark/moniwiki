<?php
// a BlogRss wrapper plugin for the MoniWiki

include_once(dirname(__FILE__)."/rss_blog.php");

function do_blogrss($formatter,$options) {
  do_rss_blog($formatter,$options);
}

?>
