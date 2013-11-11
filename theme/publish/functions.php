<?php
/* publish custom functions */

function get_pb_icon($classname, $ko, $en)
{
    return '<span class="fa-stack"><i class="fa fa-circle fa-stack-2x"></i><i class="fa ' . $classname . ' fa-stack-1x fa-inverse"></i></span><span class="tooltip">'.$en.'</span>';
}

function update_icons($icons)
{
    global $DBInfo;
    $imgs_dir = $DBInfo->imgs_dir;
    $iconset = $DBInfo->iconset;
    $icons = str_replace("<img src='" . $imgs_dir . "/" . $iconset . "/edit.png' alt='E' class='wikiIcon' />", get_pb_icon('fa-pencil-square-o','수정','Edit'), $icons);
    $icons = str_replace("<img src='" . $imgs_dir . "/" . $iconset . "/diff.png' alt='D' class='wikiIcon' />", get_pb_icon('fa-code-fork','비교', 'Diff'), $icons);
    $icons = str_replace("<img src='" . $imgs_dir . "/" . $iconset . "/show.png' alt='R' class='wikiIcon' />", get_pb_icon('fa-refresh','새로고침', 'Refresh'), $icons);
    $icons = str_replace("<img src='" . $imgs_dir . "/" . $iconset . "/backlinks.png' alt=',' class='wikiIcon' />", get_pb_icon('fa-reply','백링크', 'Backlink'), $icons);
    $icons = str_replace("<img src='" . $imgs_dir . "/" . $iconset . "/random.png' alt='A' class='wikiIcon' />", get_pb_icon('fa-random','무작위', 'Random'), $icons);
    $icons = str_replace("<img src='" . $imgs_dir . "/" . $iconset . "/search.png' alt='S' class='wikiIcon' />", get_pb_icon('fa-search','검색', 'Search'), $icons);
    $icons = str_replace("<img src='" . $imgs_dir . "/" . $iconset . "/info.png' alt='I' class='wikiIcon' />", get_pb_icon('fa-info','역사', 'History'), $icons);
    $icons = str_replace("<img src='" . $imgs_dir . "/" . $iconset . "/help.png' alt='H' class='wikiIcon' />", get_pb_icon('fa-question','도움말', 'Help'), $icons);
    $icons = str_replace("<img src='" . $imgs_dir . "/" . $iconset . "/pref.png' alt='C' class='wikiIcon' />", get_pb_icon('fa-gear','설정', 'Setting'), $icons);
    return $icons;
}

/* end of the file */