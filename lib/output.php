<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * output utils extracted from the Formatter class.
 *
 * @since 2015/12/19
 * @since 1.3.0
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2a
 *
 */

/**
 * send header
 *
 * @since 2015/12/19
 * @since 1.3.0
 */
function send_header($formatter, $header = '', $params = array()) {
    global $DBInfo, $Config;

    $plain = 0;

    if (empty($params['is_robot']) && isset($formatter->pi['#redirect'][0]) && !empty($params['pi'])) {
        $params['value'] = $formatter->pi['#redirect'];
        $params['redirect'] = 1;
        $formatter->pi['#redirect']='';
        do_goto($formatter,$params);
        return true;
    }
    $header = !empty($header) ? $header:(!empty($params['header']) ? $params['header']:null) ;

    if (!empty($header)) {
        foreach ((array)$header as $head) {
            $formatter->header($head);
            if (preg_match("/^content\-type: text\//i",$head))
                $plain = 1;
        }
    }

    $is_page = is_object($formatter->page) && $formatter->page->exists();
    $is_show = empty($params['action']) || strtolower($params['action']) == 'show';

    // use conditional get
    $use_conditional_get = true;
    if (isset($params['mtime'])) {
        // force mtime
        $mtime = $params['mtime'];
    } else if ($is_page) {
        $mtime = $formatter->page->mtime();
        if (!empty($Config['use_conditional_get']) and
                empty($params['nolastmod']) and $formatter->page->is_static)
            $use_conditional_get = true;
    }

    if ($mtime > 0) {
        $modified = $mtime > 0 ? gmdate('Y-m-d\TH:i:s', $mtime).'+00:00' : null;
        $lastmod = gmdate('D, d M Y H:i:s', $mtime).' GMT';
        $meta_lastmod = '<meta http-equiv="last-modified" content="'.$lastmod.'" />'."\n";
    }

    if (is_static_action($params) or $use_conditional_get) {
        header('Last-Modified: '.$lastmod);
        $etag = $formatter->page->etag($params);
        if (!empty($params['etag']))
            $formatter->header('ETag: "'.$params['etag'].'"');
        else
            $formatter->header('ETag: "'.$etag.'"');
    }

    // custom headers
    if (!empty($Config['site_headers'])) {
        foreach ((array)$Config['site_headers'] as $head) {
            $formatter->header($head);
        }
    }

    $content_type =
        isset($Config['content_type'][0]) ? $Config['content_type'] : 'text/html';

    $force_charset = '';
    if (!empty($Config['force_charset']))
        $force_charset = '; charset='.$Config['charset'];

    if (!$plain)
        $formatter->header('Content-type: '.$content_type.$force_charset);

    if (!empty($params['action_mode']) and $params['action_mode'] =='ajax')
        return true;

    if ($plain)
        return;

    $media = 'media="screen"';
    if (isset($params['action'][0]) and $params['action'] == 'print')
        $media = '';

    # disabled
    #$formatter->header("Vary: Accept-Encoding, Cookie");
    #if (strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') and function_exists('ob_gzhandler')) {
    #  ob_start('ob_gzhandler');
    #  $etag.= '.gzip';
    #}

    if (!empty($params['metatags']))
        $metatags = $params['metatags'];
    else
        $metatags = $Config['metatags'];

    if ($is_page) {
        if (!empty($params['noindex']) || !empty($formatter->pi['#noindex']) ||
                (!empty($mtime) and !empty($Config['delayindex']) and ((time() - $mtime) < $Config['delayindex'])))
        {
            // delay indexing like as dokuwiki
            if (preg_match("/<meta\s+name=('|\")?robots\\1[^>]+>/i", $metatags)) {
                $metatags = preg_replace("/<meta\s+name=('|\")?robots\\1[^>]+>/i",
                        '<meta name="robots" content="noindex,nofollow" />',
                        $metatags);
            } else {
                $metatags.= '<meta name="robots" content="noindex,nofollow" />'."\n";
            }
        }
    }
    if (isset($Config['metatags_extra']))
        $metatags.= $Config['metatags_extra'];

    $js = !empty($Config['js']) ? $Config['js'] : '';

    $keywords = '';
    if ($is_page) {
        if (isset($params['trail']))
            call_macro($formatter, 'Trailer', $formatter->page->name, $params);
        else if ($Config['origin'])
            call_macro($formatter, 'Origin', $formatter->page->name);
    }

    if ($is_page && $is_show) {
        # find upper page
        $up_separator = '/';
        if (!empty($formatter->use_namespace))
            $up_separator .= '|\:';
        $pos = 0;
        // NameSpace/SubPage or NameSpace:SubNameSpacePage
        preg_match('@(' . $up_separator . ')@', $formatter->page->name, $sep);
        if (isset($sep[1]))
            $pos = strrpos($formatter->page->name, $sep[1]);
        if ($pos > 0)
            $upper = substr($formatter->page->urlname, 0, $pos);
        else if ($formatter->group)
            $upper = _urlencode(substr($formatter->page->name, strlen($formatter->group)));

        // setup keywords
        if (!empty($formatter->pi['#keywords'])) {
            $keywords = _html_escape($formatter->pi['#keywords']);
        } else {
            $keys = array();
            $dummy = strip_tags($formatter->page->title);
            $keys = explode(' ', $dummy);
            $keys[] = $dummy;
            $keys = array_unique($keys);
            $keywords = implode(', ', $keys);
        }

        // add redirects as keywords
        if (!empty($Config['use_redirects_as_keywords'])) {
            $r = new Cache_Text('redirects');
            $redirects = $r->fetch($formatter->page->name);
            if ($redirects !== false) {
                sort($redirects);
                $keywords.= ', '._html_escape(implode(', ', $redirects));
            }
        }

        // add site specific keywords
        if (!empty($Config['site_keywords']))
            $keywords .= ', '.$Config['site_keywords'];
        $keywords = "<meta name=\"keywords\" content=\"$keywords\" />\n";

        # find sub pages
        if ($is_show and !empty($Config['use_subindex'])) {
            $scache = new Cache_text('subpages');
            if (!($subs = $scache->exists($formatter->page->name))) {
                if (($p = strrpos($formatter->page->name, '/')) !== false)
                    $rule = _preg_search_escape(substr($formatter->page->name, 0, $p));
                else
                    $rule = _preg_search_escape($formatter->page->name);
                $subs = $Config['getLikePages']('^'.$rule.'\/',1);
                if ($subs) $scache->update($formatter->page->name,1);
            }
            if (!empty($subs)) {
                $subindices = '';
                if (empty($Config['use_ajax'])) {
                    $subindices = '<div>'.call_macro($formatter, 'PageList', '', array('subdir'=>1)).'</div>';
                    $btncls = 'class="close"';
                } else
                    $btncls = '';
                $formatter->subindex = "<fieldset id='wikiSubIndex'>".
                "<legend title='[+]' $btncls onclick='javascript:toggleSubIndex(\"wikiSubIndex\")'></legend>".
                $subindices."</fieldset>\n";
            }
        }
    }

    if (!empty($params['.title'])) {
        // low level title. not escaped.
        $params['title'] = $params['.title'];
    } else if ($is_page && empty($params['title'])) {
        $params['title'] = !empty($formatter->pi['#title']) ? $formatter->pi['#title']:
            $formatter->page->title;
        $params['title'] = _html_escape($params['title']);
    } else {
        // strip tags.
        $params['title'] = strip_tags($params['title']);
    }
    $theme_type = !empty($formatter->_newtheme) ? $formatter->_newtheme : '';
    if (empty($params['css_url'])) $params['css_url'] = $Config['css_url'];
    if (empty($formatter->pi['#nodtd']) and !isset($params['retstr']) and $theme_type != 2) {
        if (!empty($formatter->html5)) {
            if (is_string($formatter->html5))
                echo $formatter->html5;
            else
                echo '<!DOCTYPE html>',"\n",
                     '<html xmlns="http://www.w3.org/1999/xhtml">',"\n";
        } else {
            echo $Config['doctype'];
        }
    }
    if ($theme_type == 2 or isset($params['retstr']))
        ob_start();
    else
        echo "<head>\n";

    echo '<meta http-equiv="Content-Type" content="'.$content_type.
        ';charset='.$Config['charset']."\" />\n";
    echo <<<JSHEAD
<script type="text/javascript">
/*<![CDATA[*/
_url_prefix="$Config[url_prefix]";
/*]]>*/
</script>
JSHEAD;
    echo $metatags,$js,"\n";
    echo $formatter->get_javascripts();
    echo $keywords;
    if (!empty($meta_lastmod)) echo $meta_lastmod;

    $sitename = !empty($Config['title_sitename']) ? $Config['title_sitename'] : $Config['sitename'];
    if (!empty($Config['title_msgstr']))
        $site_title = sprintf($Config['title_msgstr'], $sitename, $params['title']);
    else
        $site_title = $params['title'].' - '.$sitename;

    if ($is_page && $is_show) {
        // set OpenGraph information
        $is_frontpage = $formatter->page->name == get_frontpage($Config['lang']);
        if (!$is_frontpage && !empty($Config['frontpages']) && in_array($formatter->page->name, $Config['frontpages']))
            $is_frontpage = true;

        if (!empty($Config['canonical_url'])) {
            if (($p = strpos($Config['canonical_url'], '%s')) !== false)
                $page_url = sprintf($Config['canonical_url'], $formatter->page->urlname);
            else
                $page_url = $Config['canonical_url'] . $formatter->page->urlname;
        } else {
            $page_url = qualifiedUrl($formatter->link_url($formatter->page->urlname));
        }

        $oc = new Cache_text('opengraph');
        if ($formatter->refresh || ($val = $oc->fetch($formatter->page->name, $formatter->page->mtime())) === false) {
            $val = array('description'=> '', 'image'=> '');

            if (!empty($formatter->pi['#redirect'])) {
                $desc = '#redirect '.$formatter->pi['#redirect'];
            } else {
                $raw = $formatter->page->_get_raw_body();
                if (!empty($formatter->pi['#description'])) {
                    $desc = $formatter->pi['#description'];
                } else {
                    $cut_size = 2000;
                    if (!empty($Config['get_description_cut_size']))
                        $cut_size = $Config['get_description_cut_size'];
                    $cut = mb_strcut($raw, 0, $cut_size, $Config['charset']);
                    $desc = get_description($cut);
                    if ($desc !== false)
                        $desc = mb_strcut($desc, 0, 200, $Config['charset']).'...';
                    else
                        $desc = $formatter->page->name;
                }
            }

            $val['description'] = _html_escape($desc);

            if (!empty($formatter->pi['#image'])) {
                if (preg_match('@^(ftp|https?)://@', $formatter->pi['#image'])) {
                    $page_image = $formatter->pi['#image'];
                } else if (preg_match('@^attachment:("[^"]+"|[^\s]+)@/', $formatter->pi['#image'], $m)) {
                    $image = call_macro($formatter, 'attachment', $m[1], array('link_url'=>1));
                    if ($image[0] != 'a') $page_image = $image;
                }
            }

            if (empty($page_image)) {
                // extract the first image
                $punct = '<>"\'}\]\|\!';
                if (preg_match_all('@(?<=\b)((?:attachment:(?:"[^'.$punct.']+"|[^\s'.$punct.'?]+)|'.
                                '(?:https?|ftp)://(?:[^\s'.$punct.']+)\.(?:png|jpe?g|gif)))@', $raw, $m)) {
                    foreach ($m[1] as $img) {
                        if ($img[0] == 'a') {
                            $img = substr($img, 11); // strip attachment:
                            $image = call_macro($formatter, 'attachment', $img, array('link_url'=>1));
                            if ($image[0] != 'a' && preg_match('@\.(png|jpe?g|gif)$@i', $image)) {
                                $page_image = $image;
                                break;
                            }
                        } else {
                            $page_image = $img;
                            break;
                        }
                    }
                }
            }

            if (empty($page_image) && $is_frontpage) {
                $val['image'] = qualifiedUrl($Config['logo_img']);
            } else if (!empty($page_image)) {
                $val['image'] = $page_image;
            }

            $oc->update($formatter->page->name, $val, time());
        }

        if (empty($formatter->no_ogp)) {
            // for OpenGraph
            echo '<meta property="og:url" content="'. $page_url.'" />',"\n";
            echo '<meta property="og:site_name" content="'.$sitename.'" />',"\n";
            echo '<meta property="og:title" content="'.$params['title'].'" />',"\n";
            if ($is_frontpage)
                echo '<meta property="og:type" content="website" />',"\n";
            else
                echo '<meta property="og:type" content="article" />',"\n";
            if (!empty($val['image']))
                echo '<meta property="og:image" content="',$val['image'],'" />',"\n";
            if (!empty($val['description']))
                echo '<meta property="og:description" content="'.$val['description'].'" />',"\n";
        }

        // twitter card
        echo '<meta name="twitter:card" content="summary" />',"\n";
        if (!empty($Config['twitter_id']))
            echo '<meta name="twitter:site" content="',$Config['twitter_id'],'">',"\n";
        echo '<meta name="twitter:domain" content="',$sitename,'" />',"\n";
        echo '<meta name="twitter:title" content="',$params['title'],'">',"\n";
        echo '<meta name="twitter:url" content="',$page_url,'">',"\n";
        if (!empty($val['description']))
            echo '<meta name="twitter:description" content="'.$val['description'].'" />',"\n";
        if (!empty($val['image']))
            echo '<meta name="twitter:image:src" content="',$val['image'],'" />',"\n";

        // support google sitelinks serachbox
        if (!empty($Config['use_google_sitelinks'])) {
            if ($is_frontpage) {
                if (!empty($Config['canonical_url']))
                    $site_url = $Config['canonical_url'];
                else
                    $site_url = qualifiedUrl($formatter->link_url(''));

                echo <<<SITELINK
<script type='application/ld+json'>
{"@context":"http://schema.org",
 "@type":"WebSite",
 "url":"$site_url",
 "name":"$sitename",
 "potentialAction":{
  "@type":"SearchAction",
  "target":"$site_url?goto={search_term}",
  "query-input":"required name=search_term"
 }
}
</script>\n
SITELINK;
            }
        }

        echo <<<SCHEMA
<script type='application/ld+json'>
{"@context":"http://schema.org",
 "@type":"WebPage",
 "url":"$page_url",
 "dateModified":"$modified",
 "name":"{$params['title']}"
}
</script>\n
SCHEMA;
        if (!empty($val['description']))
            echo '<meta name="description" content="'.$val['description'].'" />',"\n";
    }
    echo '  <title>',$site_title,"</title>\n";

    if ($is_show)
        echo '  <link rel="canonical" href="',$page_url,'" />',"\n";

    if ($is_page) {
        # echo '<meta property="og:title" content="'.$params['title'].'" />',"\n";
        if (!empty($upper))
            echo '  <link rel="Up" href="',$formatter->link_url($upper),"\" />\n";
        $raw_url=$formatter->link_url($formatter->page->urlname,"?action=raw");
        $print_url=$formatter->link_url($formatter->page->urlname,"?action=print");
        echo '  <link rel="Alternate" title="Wiki Markup" href="',
            $raw_url,"\" />\n";
        echo '  <link rel="Alternate" media="print" title="Print View" href="',
            $print_url,"\" />\n";
    }

    $css_html = '';
    if (!empty($params['css_url'])) {
        $css_url = _html_escape($params['css_url']);
        $css_html = '  <link rel="stylesheet" type="text/css" '.$media.' href="'.
            $css_url."\" />\n";
        if (!empty($Config['custom_css']) && file_exists($Config['custom_css']))
            $css_html .= '  <link rel="stylesheet" media="screen" type="text/css" href="'.
                $Config['url_prefix'].'/'.$Config['custom_css']."\" />\n";
        else if (file_exists('./css/_user.css'))
            $css_html .= '  <link rel="stylesheet" media="screen" type="text/css" href="'.
                $Config['url_prefix']."/css/_user.css\" />\n";
    }

    echo kbd_handler(!empty($params['prefix']) ? $params['prefix'] : '');

    if ((isset($formatter->_newtheme) and $formatter->_newtheme == 2) or isset($params['retstr'])) {
        $ret = ob_get_contents();
        ob_end_clean();
        if (isset($params['retstr']))
            $params['retstr'] = $ret;
        $formatter->header_html = $ret;
        $formatter->css_html = $css_html;
    } else {
        echo $css_html;
        echo "</head>\n";
    }
    return true;
}

/**
 * send title
 *
 * @since 2015/12/19
 * @since 1.3.0
 */
function send_title($formatter, $msgtitle = '', $link = '', $params = array()) {
    // Generate and output the top part of the HTML page.
    global $DBInfo, $Config;
    $self = &$formatter;

    if (!empty($params['action_mode']) and $params['action_mode']=='ajax') return;

    $name = $formatter->page->urlname;
    $action = $formatter->link_url($name);
    $saved_pagelinks = $formatter->pagelinks;

    # find upper page
    $up_separator = '/';
    if (!empty($formatter->use_namespace)) $up_separator .= '|\:';
    $pos = 0;
    preg_match('@(' . $up_separator . ')@', $name, $sep); # NameSpace/SubPage or NameSpace:SubNameSpacePage
        if (isset($sep[1])) $pos = strrpos($name,$sep[1]);
    $mypgname = $formatter->page->name;
    $upper_icon = '';
    if ($pos > 0) {
        $upper = substr($name,0,$pos);
        $upper_icon = $formatter->link_tag($upper, '', $formatter->icon['upper'])." ";
    } else if (!empty($formatter->group)) {
        $group = $formatter->group;
        $mypgname = substr($formatter->page->name, strlen($group));
        $upper = _urlencode($mypgname);
        $upper_icon = $formatter->link_tag($upper, '', $formatter->icon['main'])." ";
    }

    $title = '';
    if (isset($formatter->pi['#title']))
        $title=_html_escape($formatter->pi['#title']);

    // change main title
    if (!empty($params['.title'])) $title = _html_escape($params['.title']);
    if (!empty($msgtitle)) {
        $msgtitle = _html_escape($msgtitle);
    } else if (isset($params['msgtitle'])) {
        $msgtitle = $params['msgtitle'];
    }

    if (empty($msgtitle) and !empty($params['title'])) $msgtitle = $params['title'];
    $groupt = '';
    if (empty($title)) {
        if (!empty($group)) { # for UserNameSpace
            $title = $mypgname;
            $groupt = substr($group, 0, -1).' &raquo;'; // XXX
            $groupt =
                "<span class='wikiGroup'>$groupt</span>";
        } else {
            $groupt = '';
            $title = $formatter->page->title;
        }
        $title = _html_escape($title);
    }
    # setup title variables
    #$heading=$formatter->link_to("?action=fullsearch&amp;value="._urlencode($name),$title);

    // follow backlinks ?
    if (!empty($Config['backlinks_follow']))
        $attr = 'rel="follow"';
    else
        $attr = '';

    $qext = '';
    if (!empty($Config['use_backlinks'])) $qext = '&amp;backlinks=1';
    if (isset($link[0]))
        $title = "<a href=\"$link\">$title</a>";
    else if (empty($params['.title']) and empty($params['nolink']))
        $title = $formatter->link_to("?action=fullsearch$qext&amp;value="._urlencode($mypgname), $title, $attr);

    if (isset($formatter->pi['#notitle']))
        $title = '';
    else
        $title = $groupt."<h1 class='wikiTitle'>$title</h1>";

    $logo = $formatter->link_tag($Config['logo_page'],'',$Config['logo_string']);
    $goto_form = $Config['goto_form'] ?
        $Config['goto_form'] : goto_form($action,$Config['goto_type']);

    if (!empty($params['msg']) or !empty($msgtitle)) {
        $msgtype = isset($params['msgtype']) ? ' '.$params['msgtype']:' warn';
        $msgs = array();
        if (!empty($params['msg'])) $msgs[] = $params['msg'];
        if (!empty($params['notice'])) $msgs[] = $params['notice'];
        $mtitle0 = implode("<br />", $msgs);
        $mtitle = !empty($msgtitle) ? "<h3>".$msgtitle."</h3>\n":"";
        $msg = <<<MSG
<div class="message" id="wiki-message"><span class='$msgtype'>
$mtitle$mtitle0</span>
</div>
MSG;
        if (isset($Config['hide_log']) and $Config['hide_log'] > 0 and preg_match('/timer/', $msgtype)) {
            $time = intval($Config['hide_log'] * 1000); // sec to ms
            $msg.=<<<MSG
<script type="text/javascript">
/*<![CDATA[*/
setTimeout(function() {\$('#wiki-message').fadeOut('fast');}, $time);
/*]]>*/
</script>
MSG;
        }
    }

    # navi bar
    $menu = array();
    if (!empty($params['quicklinks'])) {
        # get from the user setting
        $quicklinks = array_flip(explode("\t", $params['quicklinks']));
    } else {
        # get from the config.php
        $quicklinks = $formatter->menu;
    }

    $sister_save = $formatter->sister_on;
    $formatter->sister_on = 0;
    $titlemnu = 0;
    if (isset($quicklinks[$formatter->page->name])) {
        #$attr.=" class='current'";
        $titlemnu = 1;
    }

    if (!empty($Config['use_userlink']) and isset($quicklinks['UserPreferences']) and $params['id'] != 'Anonymous') {
        $tmpid= 'wiki:UserPreferences '.$params['id'];
        $quicklinks[$tmpid]= $quicklinks['UserPreferences'];
        unset($quicklinks['UserPreferences']);
    }

    $formatter->forcelink = 1;
    foreach ($quicklinks as $item=>$attr) {
        if (strpos($item,' ') === false) {
            if (strpos($attr,'=') === false) $attr = "accesskey='$attr'";
            # like 'MoniWiki'=>'accesskey="1"'
            $menu[$item] = $formatter->word_repl($item, _($item), $attr);
            # $menu[]=$formatter->link_tag($item,"",_($item),$attr);
        } else {
            # like a 'http://moniwiki.sf.net MoniWiki'
            $menu[$item] = $formatter->link_repl($item, $attr);
        }
    }
    if (!empty($Config['use_titlemenu']) and $titlemnu == 0 ) {
        $len = $Config['use_titlemenu'] > 15 ? $Config['use_titlemenu']:15;
        #$attr="class='current'";
        $mnuname=_html_escape($formatter->page->name);
        if ($DBInfo->hasPage($formatter->page->name)) {
            if (strlen($mnuname) < $len) {
                $menu[$formatter->page->name]=$formatter->word_repl($mypgname,$mnuname,$attr);
            } else if (function_exists('mb_strimwidth')) {
                $my=mb_strimwidth($mypgname,0,$len,'...', $Config['charset']);
                $menu[$formatter->page->name]=$formatter->word_repl($mypgname,_html_escape($my),$attr);
            }
        }
    }
    $formatter->forcelink = 0;
    $formatter->sister_on = $sister_save;
    if (empty($formatter->css_friendly)) {
        $menu = $formatter->menu_bra.implode($formatter->menu_sep,$menu).$formatter->menu_cat;
    } else {
        $cls = 'first';
        $mnu = '';
        foreach ($menu as $k=>$v) {
            if (preg_match('/current/', $v)) {
                $cls .=' current';
            }
            # set current page attribute.
            $mnu.='<li'.(!empty($cls) ? ' class="'. $cls .'"' : '').'>'.$menu[$k]."</li>\n";
            $cls = '';
        }

        // action menus
        $action_menu = '';
        if (!empty($Config['menu_actions'])) {
            $actions = array();
            foreach ($Config['menu_actions'] as $action) {
                if (strpos($action, ' ') !== false) {
                    list($act, $text) = explode(' ', $action, 2);
                    if ($params['page'] == $formatter->page->name) {
                        $actions[] = $formatter->link_to($act, _($text));
                    } else {
                        $actions[] = $formatter->link_tag($params['page'], $act, _($text));
                    }
                } else {
                    $actions[] = $formatter->link_to("?action=$action", _($action), " rel='nofollow'");
                }
            }
            $action_menu = '<ul class="dropdown-menu"><li>'.implode("</li>\n<li>", $actions).'</li></ul>'."\n";
        }
        $menu = '<div id="wikiMenu"><ul>'.$mnu."</ul></div>\n";
    }
    $formatter->topmenu = $menu;

    # submenu XXX
    if (!empty($formatter->submenu)) {
        $smenu = array();
        $mnu_pgname = (!empty($group) ? $group.'~':'').$formatter->submenu;
        if ($DBInfo->hasPage($mnu_pgname)) {
            $pg = $DBInfo->getPage($mnu_pgname);
            $mnu_raw = $pg->get_raw_body();
            $mlines=explode("\n",$mnu_raw);
            foreach ($mlines as $l) {
                if (!empty($mk) and preg_match('/^\s{2,}\*\s*(.*)$/',$l,$m)) {
                    if (isset($smenu[$mk]) and !is_array($smenu[$mk])) $smenu[$mk]=array();
                    $smenu[$mk][]=$m[1];
                    if (isset($smenu[$m[1]])) $smenu[$m[1]]=$mk;
                } else if (preg_match('/^ \*\s*(.*)$/',$l,$m)) {
                    $mk=$m[1];
                }
            }

            # make $submenu, $submain
            $cmenu = null;
            if (isset($smenu[$formatter->page->name])) {
                $cmenu = &$smenu[$formatter->page->name];
            }

            $submain = '';
            if (isset($smenu['Main'])) {
                $submenus = array();
                foreach ($smenu['Main'] as $item) {
                    $submenus[] = $formatter->link_repl($item);
                }
                $submain = '<ul><li>'.implode("</li><li>",$submenus)."</li></ul>\n";
            }

            $submenu = '';
            if ($cmenu and ($cmenu != 'Main' or !empty($Config['submenu_showmain']))) {
                if (is_array($cmenu)) {
                    $smenua = $cmenu;
                } else {
                    $smenua = $smenu[$cmenu];
                }

                $submenus = array();
                foreach ($smenua as $item) {
                    $submenus[] = $formatter->link_repl($item);
                }
                #print_r($submenus);
                $submenu = '<ul><li>'.implode("</li><li>",$submenus)."</li></ul>\n";
                # set current attribute.
                $submenu = preg_replace("/(li)>(<a\s[^>]+current[^>]+)/",
                        "$1 class='current'>$2",$submenu);
            }
        }
    }

    # icons
    #if ($upper)
    #  $upper_icon=$formatter->link_tag($upper,'',$formatter->icon['upper'])." ";

    if (empty($formatter->icons)) {
        $formatter->icons = array(
                'edit' =>array('', '?action=edit', $formatter->icon['edit'], 'accesskey="e"'),
                'diff' =>array('', '?action=diff', $formatter->icon['diff'], 'accesskey="c"'),
                'show' =>array('', '', $formatter->icon['show']),
                'backlinks' =>array('', '?action=backlinks', $formatter->icon['backlinks']),
                'random' =>array('', '?action=randompage', $formatter->icon['random']),
                'find' =>array('FindPage', '', $formatter->icon['find']),
                'info' =>array('','?action=info', $formatter->icon['info']));
        if (!empty($formatter->notify))
            $formatter->icons['subscribe'] = array('', '?action=subscribe', $formatter->icon['mailto']);
        $formatter->icons['help'] = array('HelpContents', '', $formatter->icon['help']);
        $formatter->icons['pref'] = array('UserPreferences', '', $formatter->icon['pref']);
    }

    # UserPreferences
    if ($params['id'] != "Anonymous") {
        $user_link=$formatter->link_tag("UserPreferences","",$params['id']);
        if (empty($Config['no_wikihomepage']) and $DBInfo->hasPage($params['id'])) {
            $home=$formatter->link_tag($params['id'],"",$formatter->icon['home'])." ";
            unset($formatter->icons['pref']); // insert home icon
            $formatter->icons['home']=array($params['id'],"",$formatter->icon['home']);
            $formatter->icons['pref']=array("UserPreferences","",$formatter->icon['pref']);
        } else
            $formatter->icons['pref']=array("UserPreferences","",$formatter->icon['pref']);
        if (isset($params['scrapped'])) {
            if (!empty($Config['use_scrap']) && $Config['use_scrap'] != 'js' && $params['scrapped'])
                $formatter->icons['scrap']=array('','?action=scrap&amp;unscrap=1',$formatter->icon['unscrap']);
            else
                $formatter->icons['scrap'] = array('','?action=scrap',$formatter->icon['scrap']);
        }

    } else
        $user_link = $formatter->link_tag("UserPreferences", "", _($formatter->icon['user']));

    if (!empty($Config['check_editable'])) {
        if (!$DBInfo->security->is_allowed('edit', $params))
            $formatter->icons['edit'] = array('', '?action=edit', $formatter->icon['locked']);
    }

    if (!empty($formatter->icons)) {
        $icon = array();
        $myicons = array();

        if (!empty($formatter->icon_list)) {
            $inames=explode(',', $formatter->icon_list);
            foreach ($inames as $item) {
                if (isset($formatter->icons[$item])) {
                    $myicons[$item] = $formatter->icons[$item];
                } else if (isset($formatter->icon[$item])) {
                    $myicons[$item] = array("",'?action='.$item,$formatter->icon[$item]);
                }
            }
        } else {
            $myicons = &$formatter->icons;
        }
        foreach ($myicons as $item) {
            if (!empty($item[3])) $attr=$item[3];
            else $attr = '';
            $icon[] = $formatter->link_tag($item[0],$item[1], $item[2], $attr);
        }
        $icons = $formatter->icon_bra.implode($formatter->icon_sep, $icon).$formatter->icon_cat;
    }

    $rss_icon = $formatter->link_tag("RecentChanges", "?action=rss_rc", $formatter->icon['rss'])." ";
    $formatter->_vars['rss_icon'] = &$rss_icon;
    $formatter->_vars['icons'] = &$icons;
    $formatter->_vars['title'] = $title;
    $formatter->_vars['menu'] = $menu;
    $formatter->_vars['action_menu'] = $action_menu;
    isset($upper_icon) ? $formatter->_vars['upper_icon'] = $upper_icon : null;
    isset($home) ? $formatter->_vars['home'] = $home : null;
    if (!empty($params['header']))
        $formatter->_vars['header'] = $header = $params['header'];
    else if (isset($formatter->_newtheme) and $formatter->_newtheme == 2 and !empty($formatter->header_html))
        $formatter->_vars['header'] = $header = $formatter->header_html;

    if ($mtime = $formatter->page->mtime()) {
        $tz_offset = $formatter->tz_offset;
        $lastedit = gmdate("Y-m-d", $mtime + $tz_offset);
        $lasttime = gmdate("H:i:s", $mtime + $tz_offset);
        $datetime = gmdate('Y-m-d\TH:i:s', $mtime).'+00:00';
        $formatter->_vars['lastedit'] = $lastedit;
        $formatter->_vars['lasttime'] = $lasttime;
        $formatter->_vars['datetime'] = $datetime;
    }

    # print the title
    if (empty($formatter->_newtheme) or $formatter->_newtheme != 2) {
        if (isset($formatter->_newtheme) and $formatter->_newtheme != 2)
            echo '<body'.(!empty($params['attr']) ? ' ' . $params['attr'] : '' ) .">\n";
        echo '<div><a id="top" name="top" accesskey="t"></a></div>'."\n";
    }

    if (file_exists($formatter->themedir."/header.php")) {
        if (!empty($formatter->trail))
            $trail = &$formatter->trail;
        if (!empty($formatter->origin))
            $origin = &$formatter->origin;

        $subindex = !empty($formatter->subindex) ? $formatter->subindex : '';
        $themeurl = $formatter->themeurl;
        include($formatter->themedir."/header.php");
    } else { #default header
        $header = "<table width='100%' border='0' cellpadding='3' cellspacing='0'>";
        $header .= "<tr>";
        if ($Config['logo_string']) {
            $header .= "<td rowspan='2' style='width:10%' valign='top'>";
            $header .= $logo;
            $header .= "</td>";
        }
        $header .= "<td>$title</td>";
        $header .= "</tr><tr><td>\n";
        $header .= $goto_form;
        $header .= "</td></tr></table>\n";

        # menu
        echo "<".$formatter->tags['header']." id='wikiHeader'>\n";
        echo $header;
        if (!$formatter->css_friendly)
            echo $menu." ".$user_link." ".$upper_icon.$icons.$rss_icon;
        else {
            echo "<div id='wikiLogin'>".$user_link."</div>";
            echo "<div id='wikiIcon'>".$upper_icon.$icons.$rss_icon.'</div>';
            echo $menu;
        }
        if (!empty($msg))
            echo $msg;
        echo "</".$formatter->tags['header']."\n";
    }

    // send header only
    if ($options['.header'])
        return;

    if (empty($formatter->popup) and (empty($themeurl) or empty($formatter->_newtheme))) {
        echo $Config['hr'];
        if ($params['trail']) {
            echo "<div id='wikiTrailer'><p>\n";
            echo $formatter->trail;
            echo "</p></div>\n";
        }
        if (!empty($formatter->origin)) {
            echo "<div id='wikiOrigin'><p>\n";
            echo $formatter->origin;
            echo "</p></div>\n";
        }
        if (!empty($formatter->subindex))
            echo $formatter->subindex;
    }
    echo "\n<".$formatter->tags['article']." id='wikiBody' class='entry-content'>\n";
    #if ($formatter->subindex and !$formatter->popup and (empty($themeurl) or !$formatter->_newtheme))
    #  echo $formatter->subindex;
    $formatter->pagelinks = $saved_pagelinks;
}

/**
 * send footer
 *
 * @since 2015/12/19
 * @since 1.3.0
 */
function send_footer($formatter, $args = array(), $params = array()) {
    global $DBInfo, $Config;

    $self = &$formatter;

    $params = empty($params) ? array('id'=>'Anonymous',
                              'tz_offset'=>$formatter->tz_offset,
                              'page'=>$formatter->page->name) : null;

    if (!empty($params['action_mode']) and $params['action_mode'] =='ajax') return;

    echo "<!-- wikiBody --></".$formatter->tags['article'].">\n";
    echo $Config['hr'];
    if (!empty($args['editable']) and !$DBInfo->security->writable($params))
        $args['editable']=-1;

    $key = $DBInfo->pageToKeyname($params['page']);
    if (!in_array('UploadedFiles',$formatter->actions) and is_dir($Config['upload_dir']."/$key"))
        $formatter->actions[] = 'UploadedFiles';

    $menus = $formatter->get_actions($args, $params);

    $hide_actions = !empty($Config['hide_actions']) ? $Config['hide_actions'] : 0;
    $hide_actions += $formatter->popup;
    $menu = '';
    if (!$hide_actions or
            ($hide_actions and $params['id']!='Anonymous')) {
        if (!$formatter->css_friendly) {
            $menu=$formatter->menu_bra.implode($formatter->menu_sep,$menus).$formatter->menu_cat;
        } else {
            $menu = "<div id='wikiAction'>";
            $menu .= '<ul><li class="first">'.implode("</li>\n<li>\n",$menus)."</li></ul>";
            $menu .= "</div>";
        }
    }

    if ($mtime = $formatter->page->mtime()) {
        $lastedit = gmdate("Y-m-d", $mtime + $params['tz_offset']);
        $lasttime = gmdate("H:i:s", $mtime + $params['tz_offset']);
        $datetime = gmdate('Y-m-d\TH:i:s', $mtime).'+00:00';
    }

    $validator_xhtml = !empty($Config['validator_xhtml']) ? $Config['validator_xhtml']
        : 'http://validator.w3.org/check/referer';
    $validator_css = !empty($Config['validator_css']) ? $Config['validator_xhtml']
        : 'http://jigsaw.w3.org/css-validator';

    $banner = <<<FOOT
 <a href="$validator_xhtml"><img
  src="$formatter->imgs_dir/valid-xhtml10.png"
  style="border:0;vertical-align:middle" width="88" height="31"
  alt="Valid XHTML 1.0!" /></a>

 <a href="$validator_css"><img
  src="$formatter->imgs_dir/vcss.png"
  style="border:0;vertical-align:middle" width="88" height="31"
  alt="Valid CSS!" /></a>

 <a href="http://moniwiki.sourceforge.net/"><img
  src="$formatter->imgs_dir/moniwiki-powered.png"
  style="border:0;vertical-align:middle" width="88" height="31"
  alt="powered by MoniWiki" /></a>
FOOT;

    $timer = '';
    if (isset($params['timer']) and is_object($params['timer'])) {
        $params['timer']->Check();
        $timer = $params['timer']->Total();
    }

    if (file_exists($formatter->themedir."/footer.php")) {
        $themeurl = $formatter->themeurl;
        $formatter->_vars['mainmenu'] = $formatter->_vars['menu'];
        $formatter->_vars['menus'] = $menus;
        unset($formatter->_vars['menu']);
        // extract variables
        extract($formatter->_vars);
        include($formatter->themedir."/footer.php");
    } else {
        echo "<div id='wikiFooter'>";
        echo $menu;
        if (!$formatter->css_friendly)
echo $banner;
        else echo "<div id='wikiBanner'>$banner</div>\n";
        echo "\n</div>\n";
    }
    if (empty($formatter->_newtheme) or $formatter->_newtheme != 2)
        echo "</body>\n</html>\n";
    #include "prof_results.php";
}

/**
 * set theme
 *
 * @since 2015/12/19
 * @since 1.3.0
 */
function set_theme($formatter, $theme = '', $params = array()) {
    global $DBInfo, $Config;

    $self = &$formatter;

    if (!empty($theme)) {
        $formatter->themedir .= "/theme/$theme";
        $formatter->themeurl .= "/theme/$theme";
    }

    $data = array();
    if (file_exists(dirname(__FILE__).'/theme.php')) {
        $used = array('icons', 'icon');
        $options['themedir'] = '.';
        $options['themeurl'] = $Config['url_prefix'];
        $options['frontpage'] = $Config['frontpage'];
        $data = getConfig(dirname(__FILE__).'/theme.php',$options);

        foreach ($data as $k=>$v)
            if (!in_array($k, $used)) unset($data[$k]);
    }
    $options['themedir'] = $formatter->themedir;
    $options['themeurl'] = $formatter->themeurl;
    $options['frontpage'] = $Config['frontpage'];

    $formatter->icon=array();
    if (file_exists($formatter->themedir."/theme.php")) {
        $data0 = getConfig($formatter->themedir."/theme.php", $options);
        if (!empty($data0))
            $data = array_merge($data0,$data);
    }

    if (!empty($data)) {
        # read configurations
        while (list($key, $val) = each($data)) $formatter->$key = $val;
    }

    if (!empty($Config['icon']))
        $formatter->icon = array_merge($Config['icon'],$formatter->icon);

    if (!isset($formatter->icon_bra)) {
        $formatter->icon_bra = $Config['icon_bra'];
        $formatter->icon_cat = $Config['icon_cat'];
        $formatter->icon_sep = $Config['icon_sep'];
    }

    if (empty($formatter->menu)) {
        $formatter->menu = $Config['menu'];
    }

    if (!isset($formatter->menu_bra)) {
        $formatter->menu_bra = !empty($Config['menu_bra']) ? $Config['menu_bra'] : '';
        $formatter->menu_cat = !empty($Config['menu_cat']) ? $Config['menu_cat'] : '';
        $formatter->menu_sep = !empty($Config['menu_sep']) ? $Config['menu_sep'] : '';
    }

    if (!$formatter->icons)
        $formatter->icons = array();

    if (!empty($Config['icons']))
        $formatter->icons = array_merge($Config['icons'],$formatter->icons);

    if (empty($formatter->icon_list)) {
        $formatter->icon_list = !empty($Config['icon_list']) ? $Config['icon_list']:null;
    }
    if (empty($formatter->purple_icon)) {
        $formatter->purple_icon = $Config['purple_icon'];
    }
    if (empty($formatter->perma_icon)) {
        $formatter->perma_icon = $Config['perma_icon'];
    }
}

/**
 * include theme
 *
 * @since 2015/12/19
 * @since 1.3.0
 */
function include_theme($formatter, $theme, $file = 'default', $params = array()) {
    $self = &$formatter;

    $theme = trim($theme,'.-_');
    $theme = preg_replace(array('/\/+/', '/\.+/'), array('/', ''), $theme);
    if (preg_match('/_tpl$/', $theme)) {
        $type = 'tpl';
    } else {
        $type = 'php';
    }

    $theme_dir = 'theme/'.$theme;

    if (file_exists($theme_dir."/theme.php")) {
        $formatter->_vars['_theme'] = _load_php_vars($theme_dir."/theme.php", $params);
    }

    $theme_path = $theme_dir.'/'.$file.'.'.$type;
    if (!file_exists($theme_path)) {
        trigger_error(sprintf(_("File '%s' does not exist."), $file), E_USER_NOTICE);
        return '';
    }

    switch($type) {
        case 'tpl':
            $params['path'] = $theme_path;
            $out = $formatter->processor_repl('tpl_', '', $params);
            break;
        case 'php':
            global $Config;
            $TPL_VAR = &$formatter->_vars;
            if (isset($TPL_VAR['_theme']) and is_array($TPL_VAR['_theme']) and $TPL_VAR['_theme']['compat'])
                extract($TPL_VAR);
            if ($params['print']) {
                $out = include $theme_path;
            } else {
                ob_start();
                include $theme_path;
                $out = ob_get_contents();
                ob_end_clean();
            }
            break;

        default:
            break;
    }
    return $out;
}

// vim:et:sts=4:sw=4:
