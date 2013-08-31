<?php
//
// Default MoniWiki Configuations
//

function WikiConfig($conf) {
    $frontpage = 'FrontPage';
    $sitename = 'UnnamedWiki';
    $upload_dir = 'pds';
    $data_dir = './data';
    $query_prefix = '/';
    $umask = 0770;
    $charset = 'utf-8';
    $lang = 'auto';
    $dba_type = "db3";

    $text_dir = $data_dir.'/text';
    $cache_dir = $data_dir.'/cache';
    $user_dir = $data_dir.'/user';
    $vartmp_dir = '/var/tmp';
    $intermap = $data_dir.'/intermap.txt';
    $interwikirule = '';
    $editlog_name = $data_dir.'/editlog';
    $shared_intermap = $data_dir."/text/InterMap";
    $shared_metadb = $data_dir."/metadb";

    $url_prefix = '/moniwiki';
    $imgs_dir = $url_prefix.'/imgs';
    $css_dir = 'css';
    $css_url = $url_prefix.'/css/default.css';
    $kbd_script = $url_prefix.'/css/kbd.js';
    $logo_img = $imgs_dir.'/moniwiki-logo.png';
    $logo_page = $frontpage;
    $logo_string = '<img src="'.$logo_img.'" alt="[logo]" class="wikiLogo" />';
    $metatags = '<meta name="robots" content="noindex,nofollow" />';
    $doctype = <<<EOS
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
EOS;
    $hr = "<hr class='wikiHr' />";
    $date_fmt = 'Y-m-d';
    $date_fmt_rc = 'D d M Y';
    $date_fmt_blog = 'M d, Y';
    $datetime_fmt = 'Y-m-d H:i:s';
    $default_markup = 'wiki';
    //$changed_time_fmt = ' . . . . [h:i a]';
    $changed_time_fmt = ' [h:i a]'; # used by RecentChanges macro
    $admin_passwd = 'daEPulu0FLGhk'; # default value moniwiki
    $purge_passwd = '';
    $rcs_user = 'root';
    $actions = array('DeletePage', 'LikePages');
    $show_hosts = TRUE;
    $iconset = 'moni';
    $css_friendly = '0';
    $goto_type = '';
    $goto_form = '';
    $template_regex = '[a-z]Template$';
    $category_regex = '^Category[A-Z]';
    $notify = 0;
    $trail = 0;
    $origin = 0;
    $arrow = " &#x203a; ";
    $home = 'Home';
    $diff_type = 'fancy';
    $hr_type = 'simple';
    $nonexists = 'simple';

    $use_smileys = 1;
    $smiley = 'wikismiley';
    $use_counter = 0;
    $use_category = 1;
    $use_camelcase = 1;
    $use_sistersites = 1;
    $use_singlebracket = 1;
    $use_twinpages = 1;
    $use_hostname = 1;
    $use_group = 1;

    $email_guard = 'hex';
    $pagetype = array();
    $convmap = array(0xac00, 0xd7a3, 0x0000, 0xffff); /* for euc-kr */
    $theme = '';

    $inline_latex = 0;
    $processors = array();

    $perma_icon = '#';
    $purple_icon = '#';
    $use_purple = 0;
    $version_class = 'RCS';
    $titleindexer_class = 'text';
    $title_rule = '((?<=[a-z0-9]|[B-Z]{2})([A-Z][a-z]))';
    $login_strict = 1;
    $use_fakemtime = 0; // dir mtime emulation for FAT filesytem.
    $purge_passwd = $admin_passwd;

    $default = get_defined_vars();
    unset($default['conf']);

    $config = new StdClass;

    if (is_array($conf)) {
        // override config with user-specified configurations
        $conf = array_merge($default, $conf);   
        // read configurations
        foreach ($conf as $k=>$v) {
            if ($k[0] == '_') continue; // ignore internal variables
            $config->$k = $v;
        }
    } else {
        foreach ($default as $k=>$v) {
            $config->$k = $v;
        }
    }

    //
    // set default config variables
    //
    if (!empty($config->use_wikiwyg) and empty($config->sectionedit_attr))
        $config->sectionedit_attr = 1;

    if (empty($config->menu)) {
        $config->menu = array($config->frontpage=>"accesskey='1'",'FindPage'=>"accesskey='4'",'TitleIndex'=>"accesskey='3'",'RecentChanges'=>"accesskey='2'");
        $config->menu_bra = '';
        $config->menu_cat = '|';
        $config->menu_sep = '|';
    }

    // for backward compatibility
    empty($config->imgs_dir_url) ? $config->imgs_dir_url = $config->imgs_dir.'/' : null;
    $config->imgs_url_interwiki = $config->imgs_dir_url;

    if (empty($config->upload_dir_url))
        $config->upload_dir_url = $config->url_prefix . '/' . $config->upload_dir;

    if (empty($config->imgs_real_dir)) {
        if (function_exists('apache_lookup_uri')) {
            $info = apache_lookup_uri($config->imgs_dir_url);

            if (isset($info->filename)) {
                if (preg_match('@/$@', $info->filename) or is_dir($info->filename))
                    $config->imgs_real_dir = $info->filename;
                else
                    $config->imgs_real_dir = dirname($info->filename);
            }
        } else {
            // fix for nginx etc.
            $config->imgs_real_dir = substr($config->imgs_dir, strlen($config->url_prefix) + 1);
        }
    }

    if (is_dir($config->imgs_real_dir.'/interwiki/'))
        $config->imgs_url_interwiki = $config->imgs_dir_url.'/interwiki/';

    if (empty($config->icon)) {
        $iconset = $config->iconset;

        // for backward compatibility
        $ext = 'png';
        if (is_dir($config->imgs_real_dir.'/'.$iconset)) $iconset.= '/';
        else $iconset.= '-';

        if (file_exists($config->imgs_real_dir.'/'.$iconset.'http.png'))
            $config->imgs_url = $config->imgs_dir_url.'/'.$iconset;

        $imgdir = rtrim($config->imgs_dir_url, '/');

        if (!file_exists($config->imgs_real_dir.'/'.$iconset.'home.png')) $ext = 'gif';

        $config->icon['upper'] = "<img src='$imgdir/${iconset}upper.$ext' alt='U' class='wikiIcon' />";
        $config->icon['edit'] = "<img src='$imgdir/${iconset}edit.$ext' alt='E' class='wikiIcon' />";
        $config->icon['diff'] = "<img src='$imgdir/${iconset}diff.$ext' alt='D' class='wikiIcon' />";
        $config->icon['del'] = "<img src='$imgdir/${iconset}deleted.$ext' alt='(del)' class='wikiIcon' />";
        $config->icon['info'] = "<img src='$imgdir/${iconset}info.$ext' alt='I' class='wikiIcon' />";
        $config->icon['rss'] = "<img src='$imgdir/${iconset}rss.$ext' alt='RSS' class='wikiIcon' />";
        $config->icon['show'] = "<img src='$imgdir/${iconset}show.$ext' alt='R' class='wikiIcon' />";
        $config->icon['find'] = "<img src='$imgdir/${iconset}search.$ext' alt='S' class='wikiIcon' />";
        $config->icon['help'] = "<img src='$imgdir/${iconset}help.$ext' alt='H' class='wikiIcon' />";
        $config->icon['pref'] = "<img src='$imgdir/${iconset}pref.$ext' alt='C' class='wikiIcon' />";
        $config->icon['backlinks'] = "<img src='$imgdir/${iconset}backlinks.$ext' alt=',' class='wikiIcon' />";
        $config->icon['random'] = "<img src='$imgdir/${iconset}random.$ext' alt='A' class='wikiIcon' />";
        $config->icon['www'] = "<img src='$imgdir/${iconset}www.$ext' alt='www' class='wikiIcon' />";
        $config->icon['mailto'] = "<img src='$imgdir/${iconset}email.$ext' alt='M' class='wikiIcon' />";
        $config->icon['create'] = "<img src='$imgdir/${iconset}create.$ext' alt='N' class='wikiIcon' />";
        $config->icon['new'] = "<img src='$imgdir/${iconset}new.$ext' alt='U' class='wikiIcon' />";
        $config->icon['updated'] = "<img src='$imgdir/${iconset}updated.$ext' alt='U' class='wikiIcon' />";
        $config->icon['user'] = "UserPreferences";
        $config->icon['home'] = "<img src='$imgdir/${iconset}home.$ext' alt='M' class='wikiIcon' />";
        $config->icon['main'] = "<img src='$imgdir/${iconset}main.$ext' class='icon' alt='^' class='wikiIcon' />";
        $config->icon['print'] = "<img src='$imgdir/${iconset}print.$ext' alt='P' class='wikiIcon' />";
        $config->icon['scrap'] = "<img src='$imgdir/${iconset}scrap.$ext' alt='S' class='wikiIcon' />";
        $config->icon['unscrap'] = "<img src='$imgdir/${iconset}unscrap.$ext' alt='S' class='wikiIcon' />";
        $config->icon['attach'] = "<img src='$imgdir/${iconset}attach.$ext' alt='@' class='wikiIcon' />";
        $config->icon['locked'] = "<img src='$imgdir/${iconset}locked.$ext' alt='E' class='wikiIcon' />";
        $config->icon['external'] = "<img class='externalLink' src='$imgdir/${iconset}external.$ext' alt='[]' class='wikiIcon' />";
        $config->icon_sep = " ";
        $config->icon_bra = " ";
        $config->icon_cat = " ";
    }

    if (empty($config->icons)) {
        $config->icons = array(
                'edit' =>array("","?action=edit",$config->icon['edit'],"accesskey='e'"),
                'diff' =>array("","?action=diff",$config->icon['diff'],"accesskey='c'"),
                'show' =>array("","",$config->icon['show']),
                'backlinks' =>array("","?action=backlinks", $config->icon['backlinks']),
                'random' =>array("","?action=randompage", $config->icon['random']),
                'find' =>array("FindPage","",$config->icon['find']),
                'info' =>array("","?action=info",$config->icon['info']));
        if (!empty($config->notify))
            $config->icons['subscribe'] = array("","?action=subscribe",$config->icon['mailto']);
        $config->icons['help'] = array("HelpContents","",$config->icon['help']);
        $config->icons['pref'] = array("UserPreferences","",$config->icon['pref']);
    }

    // some alias
    if (!empty($config->use_captcha))
      $config->use_ticket = $config->use_captcha;

    return get_object_vars($config);
}

// vim:et:sts=4:sw=4:
