<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a scrap action plugin for the MoniWiki
//
// $Id: scrap.php,v 1.6 2010/04/19 11:26:46 wkpark Exp $

function macro_Scrap($formatter,$value='',$options=array()) {
  global $DBInfo;

  $user=&$DBInfo->user; # get cookie
  if ($user->id == 'Anonymous') return '';

  $userinfo=$DBInfo->udb->getUser($user->id);
  $pages = array();
  if (!empty($userinfo->info['scrapped_pages']))
    $pages=explode("\t",$userinfo->info['scrapped_pages']);

  $scrapped = 0;
  $pgname = '';
  if (!empty($formatter->page->name)) {
    $pgname = $formatter->page->name;
    if (!in_array($formatter->page->name,$pages))
      $pages[]=$options['page'];
    else
      $scrapped = 1;
  }

  $out='';

  if ($value == 'js') {
    // escape unscrap icon
    $unscrap_icon = str_replace("'", "\\'", $formatter->icon['unscrap']);
    // get the scrapped pages dynamically
    $script = get_scriptname() . $DBInfo->query_prefix;
    $pgname = _rawurlencode($pgname);
    $js = <<<JS
<script type="text/javascript">
/*<![CDATA[*/
(function() {
var script_name = "$script";
var page_name = "$pgname";
function get_scrap()
{
    var scrap = document.getElementById('scrap');
    if (scrap == null) {
        // silently ignore
        return;
    }
    var pgname = decodeURIComponent(page_name);
    var scrapped = false;

    // get the scrapped pages
    var qp = '?'; // query_prefix
    var loc = '//' + location.host;
    if (location.port) loc+= ':' + location.port;
    loc+= location.pathname + qp + 'action=scrap/ajax';

    var ret = HTTPGet(loc);
    if (ret) {
        var list = JSON.parse(ret);
        var html = '';
        for (i = 0; i < list.length; i++) {
            if (list[i] == pgname) scrapped = true;
            html+= '<li><a href="' + script_name + list[i] + '">' + list[i] + "</a></li>\\n";
        }
        if (html != '')
            scrap.innerHTML = "<ul>" + html + "</ul>";

        if (scrapped) {
            // change scrap icon
            var iconmenu = document.getElementById("wikiIcon");
            var icons = iconmenu.getElementsByTagName("A");
            for (i = 0; i < icons.length; i++) {
                if (icons[i].href.match(/action=scrap/)) {
                    icons[i].href = icons[i].href.replace(/=scrap/, '=scrap&unscrap=1');
                    if (icons[i].firstChild.firstChild.src) {
                        // image case
                        icons[i].firstChild.firstChild.src =
                            icons[i].firstChild.firstChild.src.replace('scrap', 'unscrap');
                    } else {
                        // non image case like as bootstrap etc.
                        icons[i].firstChild.innerHTML = '$unscrap_icon';
                    }
                    break;
                }
            }
        }
    }
}

// onload
var oldOnload = window.onload;
window.onload = function(ev) {
    try { oldOnload(); } catch(e) {};
    get_scrap();
}
})();
/*]]>*/
</script>\n
JS;
    #$formatter->register_javascripts('local/scrap.js');
    $formatter->register_javascripts($js);
    return '<i></i>'; // dummy
  }

  foreach ($pages as $p) {
    if ($DBInfo->hasPage($p))
      $out.='<li>'.($formatter->link_tag(_urlencode($p),'',$p)).'</li>';
    else if (!empty($p)) {
      $list = $formatter->macro_repl('PageList',$p,array('rawre'=>1));
      if (empty($list))
      	$out.=substr($list,4,-6);
    }
  }
  if (!empty($out))
    return '<ul>'.$out.'</ul>';
  return '';
}

function ajax_scrap($formatter, $options) {
  global $DBInfo;

  $user = &$DBInfo->user; # get cookie
  if ($user->id == 'Anonymous') {
    echo '[]';
    return;
  }

  $userinfo = $DBInfo->udb->getUser($user->id);
  $pages = array();
  if (!empty($userinfo->info['scrapped_pages']))
    $pages = explode("\t", $userinfo->info['scrapped_pages']);

  if (!empty($pages)) {
    require_once('lib/JSON.php');
    $json = new Services_JSON();
    $list = $json->encode($pages);
    echo $list;
  } else {
    echo '[]';
  }

  return;
}


function do_scrap($formatter,$options) {
  global $DBInfo;

  if ($options['id'] == 'Anonymous') {
    $title = _("Please login or make your ID.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("== "._("Goto UserPreferences")." ==\n");
    $formatter->send_footer();

    return;
  }

  $scrap_max = !empty($DBInfo->scrap_max) ? $DBInfo->scrap_max : 20;

  $udb=&$DBInfo->udb;
  $userinfo=$udb->getUser($options['id']);
  if (isset($options['scrapped_pages']) or (empty($DBInfo->scrap_manual) and empty($options['manual']))) {
    $pages = array();
    if (isset($options['scrapped_pages'])) {
        $pages = preg_replace("/\n\s*/","\n",$options['scrapped_pages']);
        $pages = preg_replace("/\s*\n/","\n",$pages);
        $pages = explode("\n",$pages);
        $pages = array_unique ($pages);
        $title = _("Scrap lists updated.");
    } else {
        $pages = array();
        if (!empty($userinfo->info['scrapped_pages']))
            $pages = explode("\t",$userinfo->info['scrapped_pages']);
        if (!empty($options['unscrap'])) {
            $tmp = array_flip($pages);
            if (isset($tmp[$formatter->page->name]))
                unset($tmp[$formatter->page->name]);
            $pages = array_flip($tmp);
            $title = sprintf(_("\"%s\" is unscrapped."), $formatter->page->name);
        } else {
            $pages[] = $formatter->page->name;
            $title = sprintf(_("\"%s\" is scrapped."), $formatter->page->name);

            // trash old
            if (sizeof($pages) > $scrap_max)
                array_shift($pages);
        }
        $pages = array_unique ($pages);
    }
    $page_list = _html_escape(join("\t",$pages));
    $userinfo->info['scrapped_pages'] = $page_list;
    $udb->saveUser($userinfo);

    $myrefresh = '';
    if (!empty($DBInfo->use_refresh)) {
      $sec = $DBInfo->use_refresh - 1;
      $lnk = $formatter->link_url($formatter->page->urlname,'?action=show');
      $myrefresh = 'Refresh: '.$sec.'; url='.qualifiedURL($lnk);
    }

    $formatter->send_header($myrefresh,$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("Goto [$options[page]]\n");
    $formatter->send_footer('', $options);
    return;
  }

  $pages=explode("\t",$userinfo->info['scrapped_pages']);
  if (!in_array($options['page'],$pages)) $pages[]=$options['page'];
  $page_lists=join("\n",$pages);

  $title = sprintf(_("Do you want to scrap \"%s\" ?"), $options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $msg = _("Scrapped pages");
  print "<form method='post'>
<table border='0'><tr>
<th>$msg :</th><td><textarea name='scrapped_pages' cols='40' rows='5' value='' />$page_lists</textarea></td></tr>
<tr><td></td><td>
    <input type='hidden' name='action' value='scrap' />
    <input type='submit' value='Scrap' />
</td></tr>
</table>
    </form>";
  $formatter->send_footer("",$options);
}

// vim:et:sts=4
?>
