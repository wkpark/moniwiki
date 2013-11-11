<?php /* MoniWiki Theme by wkpark at kldp.org */
if($this->_doctype_notify != 1 && substr($DBInfo->doctype,0,21) == '<!DOCTYPE html PUBLIC'){
?>
<div class='notify'>
<h1>Setup almost done... 설정이 거의 완료되었습니다.</h1>
<p>If you change the <a href="http://en.wikipedia.org/wiki/Document_type_definition" target="_blank">Document type definition</a> for HTML5, you can use better look and feel theme. Please add below code for config.php file.</p>
<p>HTML5를 위해 <a href="http://ko.wikipedia.org/wiki/%EB%AC%B8%EC%84%9C_%ED%98%95%EC%8B%9D_%EC%84%A0%EC%96%B8" target="_blank">문서형식선언</a>을 변경하면 더 나은 테마를 사용할 수 있습니다. 아래의 코드를 config.php 에 알맞게 추가해주세요.</p>
<pre>
$doctype = &lt;&lt;&lt;doctype
&lt;!DOCTYPE html&gt;
&lt;html lang="ko-KR"&gt;
&lt;head&gt;
&lt;meta charset="UTF-8" /&gt;
doctype;
</pre>
</div>
<?php
}

if (!empty($DBInfo->use_scrap))
include_once("plugin/scrap.php");
if (!empty($this->_no_urlicons))
echo <<<EOF
<style type='text/css'>
img.url { /* display: none; /* */ }

a.externalLink.unnamed {
background: url($this->themeurl/imgs/http.png) no-repeat 0 center;
padding: 0 0 0 14px;
opacity: .8;
filter: alpha(opacity=80);
}

a.externalLink.unnamed[target="_blank"]:after {
content: "";
background: url($this->themeurl/imgs/external.png) no-repeat 0 center;
display: inline-block;
width: 14px;
height: 14px;
vertical-align: middle;
margin: -2px 0 0 1px;
opacity: .7;
filter: alpha(opacity=70);
}

img.externalLink { display: none; }
</style>\n
EOF;
?>
<div id="page" class="hfeed site">
<header id="masthead" class="site-header" role="banner">
<a class="site-logo" href="<?php echo $DBInfo->url_prefix;?>/wiki.php" rel="home">
<img class="no-grav" src="<?php echo $DBInfo->logo_img;?>" height="100" width="100" alt="<?php echo $DBInfo->sitename;?>">
</a>
<hgroup>
<h1 class="site-title"><a href="<?php echo $DBInfo->url_prefix;?>/wiki.php" rel="home"><?php echo $DBInfo->sitename;?></a> <?php echo $rss_icon;?></h1>
<?php if(isset($this->_site_description)){?>
<h2 class="site-description"><?php echo $this->_site_description;?></h2>
<?php }?>
</hgroup>

<nav role="navigation" class="site-navigation main-navigation">
<h1 class="assistive-text">Menu</h1>
<div class="assistive-text skip-link">
<a href="#content" title="Skip to content">Skip to content</a></div>

<div class="menu-main-menu-container">
<?php
$menu = str_replace('<ul>', '<ul id="menu-main-menu" class="menu">', $menu);
echo $menu;?>
</div>
</nav><!-- .site-navigation .main-navigation -->

</header>

<div id="main" class="site-main">
    <div id="primary" class="content-area">
        <div id="content" class="site-content" role="main">
            <article>
                <div class="entry-header-wrapper">
                    <header class="entry-header">
                        <?php
                            $title = str_replace("<span class='wikiTitle'>", "", $title);
                            $title = str_replace("<span>", "", $title);
                            $title = str_replace("</span>", "", $title);
                        ?>
                        <h1 class="entry-title"><?php echo $title;?></h1>
                    </header><!-- .entry-header -->
                    <nav class="entry-nav">
                        <?php
                        $menu_hide = '';
                        $menu_hide_icon = '';
                        $menu_show_icon = '';
                        if($_COOKIE['show-nav'] == '0'){
                            $menu_hide = ' style="display:none"';
                            $menu_show_icon = ' style="display:inline"';
                        }else if($_COOKIE['show-nav'] == '1'){
                            $menu_hide_icon = ' style="display:inline"';
                        }
                        ?>
                        <span class="entry-nav-menu" <?php echo $menu_hide;?>>
                            <?php echo $upper_icon . update_icons($icons); ?>
                        </span>
                        <a href="#" class="menu-control menu-hide" <?php echo $menu_hide_icon;?>>
                            <?php echo get_pb_icon('fa-caret-square-o-up','메뉴 닫기','Hide');?>
                        </a href="#">
                        <a href="#" class="menu-control menu-show" <?php echo $menu_show_icon;?>>
                            <?php echo get_pb_icon('fa-caret-square-o-down','메뉴 열기','Show');?>
                        </a href="#">
                    </nav>
                </div>
                <div class="entry-content">
                    <?php empty($msg) ? '' : print $msg; ?>
                    <?php // echo '<div id="wikiTrailer"><p><span>'.$trail.'</span></p></div>';?>
                    <?php echo $subindex;?>
                    <?php
                    if (empty($options['action']) and !empty($DBInfo->use_scrap)) {
                        $scrap = macro_Scrap($this, 'js');
                        if (!empty($scrap)) {
                            echo "<div id='scrap'>";
                            echo $scrap;
                            echo "</div>";
                            $js = $this->get_javascripts();
                            if ($js) {
                                echo $js;
                            }
                        }
                    }
                    ?>
