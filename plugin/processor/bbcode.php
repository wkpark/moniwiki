<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple BBCode processor for MoniWiki
//
// imported from the Soojung project http://soojung.kldp.net
// - with some modification and simley disabled.
//
// $Id: bbcode.php,v 1.3 2006/08/17 08:01:30 wkpark Exp $
/**
 * @author  Soojung http://soojung.kldp.net
 * @date    2006-01-09
 * @name    BBCode
 * @desc    BBCode Procssor
 * @url     MoniWiki:BBCodeProcessor
 * @version $Revision: 1.3 $
 * @depend  1.1.3
 * @license GPL
 */

class processor_bbcode {

  function __listing($mode, $str) {
    $str=str_replace("\\'","'",$str);
    $item = explode("[*]", $str);
    $rstr = trim($item[0]);
    for($i=1,$sz=count($item);$i<$sz;$i++) {
      $rstr .= "<li>".trim($item[$i])."</li>";
    }
    switch($mode) {
    case "A": return "<ol style=\"list-style-type:upper-alpha\">$rstr</ol>";
    case "a": return "<ol style=\"list-style-type:lower-alpha\">$rstr</ol>";
    case "1": return "<ol style=\"list-style-type:decimal\">$rstr</ol>";
    case "I": return "<ol style=\"list-style-type:upper-roman\">$rstr</ol>";
    case "i": return "<ol style=\"list-style-type:lower-roman\">$rstr</ol>";
    default: return "<ul>$rstr</ul>";
    }
  }

  function __escape($str) {
    $str=str_replace("\\'","'",$str);
    return strtr($str, array("@"=>"\0@", "://"=>"\0://", "["=>"[\0"));
  }

  function process($str) {
    static
      $rule1 = array(
        '#\[i](.+)\[/i]#iU',
        '#\[b](.+)\[/b]#iU',
        '#\[u](.+)\[/u]#iU',
	'#\[s](.+)\[/s]#iU',
        '#\[\+\+](.+)\[/\+\+]#iU',
        '#\[--](.+)\[/--]#iU',
        '#\[color=((?:&quot;)?)([^\[\]]+)\1](.+?)\[/color]#i',
        '#\[size=((?:&quot;)?)([\d.]+)\1](.+?)\[/size]#i',
        '#\[quote](.+)\[/quote]#isU',
        '#\[quote=((?:&quot;)?)([^\[\]]+)\1](.+?)\[/quote]#is',
        '#\[list(?:=([Aa1Ii]))?](.+)\[/list]#iseU',
        '#\[url](.+)\[/url]#ieU',
        '#\[url=((?:&quot;)?)([^\[\]]+)\1](.+?)\[/url]#ie',
        '#\[img](.+)\[/img]#ieU',
        '#\[img=((?:&quot;)?)([^\[\]]+)\1](.+?)\[/img]#ie',
        '#\[email](.+)\[/email]#ieU'),
      $repl1 = array(
        '<i>\1</i>',
        '<b>\1</b>',
        '<span style="text-decoration:underline;">\1</span>',
	'<span style="text-decoration:line-through;">\1</span>',
        '<ins>\1</ins>',
        '<del>\1</del>',
        '<span style="color:\2;">\3</span>',
        '<span style="font-size:\2pt;">\3</span>',
        '<blockquote><div>\1</div></blockquote>',
        '<blockquote><p class="quotetitle">\2</p><div>\3</div></blockquote>',
        '$this->__listing("\1","\2")',
        '$this->__escape("<a href=\\"\1\\">\1</a>")',
        '$this->__escape("<a href=\\"\2\\">\3</a>")',
        '$this->__escape("<img src=\\"".htmlspecialchars("\1")."\\" alt=\\"\1\\\" class=\\"bbcode\\" />")',
        '$this->__escape("<img src=\\"".htmlspecialchars("\2")."\\" alt=\\"\3\\\" class=\\"bbcodd\\" />")',
        '$this->__escape("<a href=\\"mailto:\1\\">\1</a>")'),
      $rule2 = array(
        '#(?<![\/~"\'])http://(?:[-0-9a-z_.@:~\\#%=+?/]|&amp;)+(?!(?:</a>|"|\'>))#i',
        '#[-0-9a-z_.]+@[-0-9a-z_.]+#i'),
      $repl2 = array(
        '<a href="\0">\0</a>',
        '<a href="mailto:\0">\0</a>'),
      $_smiley = array(
        ":D" => "icon_biggrin.gif",
        ":)" => "icon_smile.gif",
        ":(" => "icon_sad.gif",
        ":shock:" => "icon_eek.gif",
        "8)" => "icon_cool.gif",
        ":lol:" => "icon_lol.gif",
        ":x" => "icon_mad.gif",
        ":p" => "icon_razz.gif",
        ":cry:" => "icon_cry.gif",
        ":evil:" => "icon_evil.gif",
        ":twisted:" => "icon_twisted.gif",
        ":roll:" => "icon_rolleyes.gif",
        ";)" => "icon_wink.gif",
        ":wink:" => "icon_wink.gif",
        ":!:" => "icon_exclaim.gif",
        ":idea:" => "icon_idea.gif",
        ":arrow:" => "icon_arrow.gif",
        ":|" => "icon_neutral.gif",
        ":mrgreen:" => "icon_mrgreen.gif",
        ":oops:" => "icon_redface.gif",
        ":o" => "icon_surprised.gif",
        ":?:" => "icon_question.gif",
        ":?" => "icon_confused.gif"),
      $smiley = null;
    #global $blog_baseurl;
    if ($str[0]=='#' and $str[1]=='!')
      list($line,$str)=explode("\n",$str,2);

    #if(is_null($smiley)) {
    #  foreach($_smiley as $k => $v) {
    #    $smiley_rule[] = "#(?<!alt=\")" . preg_quote(htmlspecialchars($k)) . "#i";
    #    $smiley_repl[] = '<img src="'.$blog_baseurl.'/libs/bbcode/smiles/'.$v.'" width="15" height="15" alt="'.htmlspecialchars($k).'" />';
    #  }
    #}

    $option = array("smiley" => false);
    #$option = array("smiley" => true);
    #if(preg_match("/^#pragma(.*?)(?:(?:\r\n?|\n)+|$)/i", $str, $m)) {
    #  $str = str_replace($m[0], "", $str);
    #  $options = explode(" ", strtolower(trim($m[1])));
    #  foreach($options as $v) {
    #    if($v == "nosmiley") $option["smiley"] = false;
    #  }
    #}

    $str = explode("\0", preg_replace('#\[code](.*?)\[/code](?:\r\n|\r|\n)?#is', "\0\\1\0", $str));
    $rstr = "";
    for($i=0,$sz=count($str);$i<$sz;$i++) {
      if($i % 2 == 0) {
        $temp = htmlspecialchars($str[$i]);
        if($option["smiley"]) {
          $temp = preg_replace($smiley_rule, $smiley_repl, $temp);
        }
        $temp = preg_replace('#\[literal](.*)\[/literal]#ieU', '$this->__escape("\1")', $temp);
        $temp = preg_replace($rule2, $repl2, preg_replace($rule1, $repl1, $temp));
        $rstr .= nl2br(str_replace("\0", "", $temp));
      } else {
        $rstr .= "<pre>".htmlspecialchars(trim($str[$i], "\r\n"))."</pre>";
      }
    }
    return "<div class=\"format_bbcode\">$rstr</div>";
  }
}

#if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {
#if (basename($_SERVER['argv'][0]) == basename(__FILE__)) {
#  $f=&new processor_bbcode();
#  print $f->process($text);
#}
// vim:et:sts=2:
?>
