<?php

class Formatter_xml {

  function Formatter_xml() {  
    $this->in_p='';
    $this->level=0;
    $this->padding='';

    $this->baserule=array("/<([^\s<>])/","/`([^`]*)`/",
                     "/'''([^']*)'''/","/(?<!')'''(.*)'''(?!')/",
                     "/''([^']*)''/","/(?<!')''(.*)''(?!')/",
                     "/\^([^ \^]+)\^(?:\s)/","/,,([^ ,]+),,(?:\s)/",
                     "/__([^ _]+)__(?:\s)/","/^-{4,}/");
    $this->baserepl=array("&lt;\\1","<constant>\\1</constant>",
                     "<emphasis>\\1</emphasis>","<emphasis>\\1</emphasis>",
                     "<i>\\1</i>","<i>\\1</i>",
                     "<superscript>\\1</superscript>","<subscript>\\1</subscript>",
                     "<u>\\1</u>","<hr />\n");

    #$punct="<\"\'}\]\|;,\.\!";
    $punct="<\'}\]\|;\.\)\!"; # , is omitted for the WikiPedia
    $url="wiki|http|https|ftp|nntp|news|irc|telnet|mailto|file";
    $urlrule="((?:$url):([^\s$punct]|(\.?[^\s$punct]))+)";
    #$urlrule="((?:$url):(\.?[^\s$punct])+)";
    #$urlrule="((?:$url):[^\s$punct]+(\.?[^\s$punct]+)+\s?)";
    # solw slow slow
    #(?P<word>(?:/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})
    $this->wordrule=
    # single bracketed rule [http://blah.blah.com Blah Blah]
    "(\[($url):[^\s\]]+(\s[^\]]+)?\])|".
    # InterWiki
    # strict but slow
    #"\b(".$DBInfo->interwikirule."):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    "\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+)|".
  # "(?<!\!|\[\[)\b(([A-Z]+[a-z0-9]+){2,})\b|".
  # "(?<!\!|\[\[)((?:\/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})\b|".
    # WikiName rule: WikiName ILoveYou (imported from the rule of NoSmoke)
    # protect WikiName rule !WikiName
    "(?<![a-z])\!?(?:\/?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b|".
    # single bracketed name [Hello World]
    "(?<!\[)\[([^\[:,<\s][^\[:,>]+)\](?!\])|".
    # bracketed with double quotes ["Hello World"]
    "(?<!\[)\[\\\"([^\\\"]+)\\\"\](?!\])|".
  # "(?<!\[)\[\\\"([^\[:,]+)\\\"\](?!\])|".
    "($urlrule)|".
    # single linkage rule ?hello ?abacus
    "(\?[A-Z]*[a-z0-9]+)";

  }

  function _table_span($str) {
    $len=strlen($str)/2;
    if ($len > 1)
      return " align='middle' morerows='$len'";
    return "";
  }

  function _table($on,$attr="") {
    if ($on)
      return "<table $attr>\n";
    return "</table>\n";
  }

  function _img($url) {
    return "<inlinemediaobject>\n  <imageobject>\n   <imagedata fileref='$url' />\n  </imageobject>\n</inlinemediaobject>\n";
  }

  function _a($url,$text='',$attr='') {
    if (!$text) $text=$url;
    return "<ulink url='$url'>$text</ulink>\n";
  }

  function link_repl($url,$attr='') {
    global $DBInfo;

    $url=str_replace('\"','"',$url);
    if ($url[0]=="[") {
      $url=substr($url,1,-1);
      $force=1;
    }
    if ($url[0]=="{") {
      $url=substr($url,3,-3);
      return "<constant>$url</constant>"; # No link
    } else if ($url[0]=="[") {
      $url=substr($url,1,-1);
      #return $this->macro_repl($url); # No link
      return $url;
    }

    if ($url[0]=="!") {
      $url[0]=" ";
      return $url;
    } else
    if (strpos($url,":")) {
      if (preg_match("/^mailto:/",$url)) {
        $url=str_replace("@","_at_",$url);
        $name=substr($url,7);
        return $this->_a($url,$name);
      } else
      if (preg_match("/^(w|[A-Z])/",$url)) { # InterWiki or wiki:
        if (strpos($url," ")) { # have a space ?
          $dum=explode(" ",$url,2);
          return $this->interwiki_repl($dum[0],$dum[1]);
        }
        return $this->interwiki_repl($url);
      } else
      if ($force or strpos($url," ")) { # have a space ?
        list($url,$text)=explode(" ",$url,2);
        if (!$text) $text=$url;
        else if (preg_match("/^(http|ftp).*\.(png|gif|jpeg|jpg)$/i",$text))
          return $this->_a($url,$this->_img($text));
        list($icon,$dummy)=explode(":",$url,2);
        return $this->_img($DBInfo->imgs_dir."/$icon.png"). $this->_a($url,$text);
      } else # have no space
      if (preg_match("/^(http|https|ftp)/",$url)) {
        if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$url))
          return $this->_img($url);
        return $this->_a($url);
      }
      return $this->_a($url);
    } else {
      if ($url[0]=="?") $url=substr($url,1);
      return $this->word_repl($url);
    }
  }

  function interwiki_repl($url,$text="") {
    global $DBInfo;

    if ($url[0]=="w")
      $url=substr($url,5);
    $dum=explode(":",$url,2);
    $wiki=$dum[0]; $page=$dum[1];
#    if (!$page) { # wiki:Wiki/FrontPage
#      $dum1=explode("/",$url,2);
#      $wiki=$dum1[0]; $page=$dum1[1];
#    }

    if (!$page) {
      # wiki:FrontPage(not supported in the MoinMoin
      # or [wiki:FrontPage Home Page]
      $page=$dum[0];
      if (!$text)
        return $this->word_repl($page,'','',1);
      return $this->word_repl($page,$text,'',1);
    }

    $url=$DBInfo->interwiki[$wiki];
    # invalid InterWiki name
    if (!$url)
      return $dum[0].":".$this->word_repl($dum[1],$text);

    $urlpage=_urlencode(trim($page));
    #$urlpage=trim($page);
    if (strpos($url,'$PAGE') === false)
      $url.=$urlpage;
    else {
      # GtkRef http://developer.gnome.org/doc/API/2.0/gtk/$PAGE.html
      # GtkRef:GtkTreeView#GtkTreeView
      # is rendered as http://...GtkTreeView.html#GtkTreeView
      $page_only=strtok($urlpage,'#?');
      $query= substr($urlpage,strlen($page_only));
      #if ($query and !$text) $text=strtok($page,'#?');
      $url=str_replace('$PAGE',$page_only,$url).$query;
    }

    $img=$this->_img($DBInfo->imgs_dir."/".strtolower($wiki)."-16.png");
#"<a href='$url' target='wiki'><img border='0' src='$DBInfo->imgs_dir/".
#         strtolower($wiki)."-16.png' align='middle' height='16' width='16' ".
#         "alt='$wiki:' title='$wiki:' /></a>";
    if (!$text) $text=str_replace("%20"," ",$page);
    else if (preg_match("/^(http|ftp).*\.(png|gif|jpeg|jpg)$/i",$text)) {
      $text= $this->_a($text);
      $img='';
    }

    if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$url))
      #return "<img border='0' alt='$text' src='$url' />";
      return $this->_a($url);

    return $img. $this->_a($url,$text);
  }

  function word_repl($url) {
    return $url;
  }


  function _list($on,$list_type,$numtype="",$closetype="") {
    if ($list_type=="dd") {
      if ($on)
         #$list_type="dl><dd";
         $list_type="para class='indent'";
      else
         #$list_type="dd></dl";
         $list_type="para";
      $numtype='';
    } else if ($list_type=="dl") {
      if ($on)
         $list_type="dl";
      else
         $list_type="dd></dl";
      $numtype='';
    } if (!$on and $closetype and $closetype !='dd')
      $list_type=$list_type."></listitem";
    if ($on) {
      if ($numtype) {
        $start=substr($numtype,1);
        if ($start)
          return "<$list_type type='$numtype[0]' start='$start'>\n";
        return "<$list_type type='$numtype[0]'>\n";
      }
      return "<$list_type>\n";
    } else {
      return "$this->padding</$list_type>\n";
    }
  }

  function _check_p() {
    if ($this->in_p) {
      $this->in_p='';
      return "</para>\n"; #close
    }
    return '';
  }

  function head_repl($left,$head,$right) {
    $dep=strlen($left);
    if ($dep != strlen($right)) return "$left $head $right";

    $head=str_replace('\"','"',$head); # revert \\" to \"

    if (!$this->depth_top) {
      $this->depth_top=$dep; $depth=1;
    } else {
      $depth=$dep - $this->depth_top + 1;
      if ($depth <= 0) $depth=1;
    }

    $num="".$this->head_num;
    $odepth=$this->head_dep;

    if ($head[0] == '#') {
      # reset TOC numberings
      if ($this->toc_prefix) $this->toc_prefix++;
      else $this->toc_prefix=1;
      $head[0]=' ';
      $dum=explode(".",$num);
      $i=sizeof($dum);
      for ($j=0;$j<$i;$j++) $dum[$j]=1;
      $dum[$i-1]=0;
      $num=join($dum,".");
    }
    $open="";
    $close="";

    $close=$this->_check_p();
    $this->level=$depth;

    if (!$odepth) {
      $open.="<sect$depth>\n"; # <section>
    } else if ($odepth && ($depth > $odepth)) {
      $open.="$this->padding<sect$depth>\n"; # <section>
      $num.=".1";
    } else if ($odepth) {
      $dum=explode(".",$num);
      $i=sizeof($dum)-1;
      if ($depth == $odepth) $close.="$this->padding</sect$depth>\n$this->padding<sect$depth>\n"; # </section><section>
      while ($depth < $odepth && $i > 0) {
         unset($dum[$i]);
         $i--;
         $close.="$this->padding</sect$odepth>\n"; # </section>
         $odepth--;
      }
      $dum[$i]++;
      $num=join($dum,".");
    }

    $this->head_dep=$depth; # save old
    $this->head_num=$num;

    return "$close$open$this->padding<title>$head</title>\n";
  }

  function _set_padding($level) {
    $this->level=$level;
    $this->padding=str_repeat("    ",$level);
  }

}

  function processor_text_xml($formatter,$value) {
    global $DBInfo;

    if ($value[0]=='#' and $value[1]=='!')
      list($line,$value)=explode("\n",$value,2);

    $lines=explode("\n",$value);

    $xml=new Formatter_xml();

    # have no contents
    if (!$lines) return;

    $text="";
    $in_pre=0;
    $in_li=0;
    $li_open=0;
    $in_table=0;
    $indent_list[0]=0;
    $indent_type[0]="";

    $wordrule="({{{([^}]+)}}})|".
              "\[\[([A-Za-z0-9]+(\(((?<!\]\]).)*\))?)\]\]|"; # macro
    if ($DBInfo->enable_latex) # single line latex syntax
      $wordrule.="\\$\s([^\\$]+)\\$(?:\s|$)|".
                 "\\$\\$\s([^\\$]+)\\$\\$(?:\s|$)|";
    $wordrule.=$formatter->wordrule;

    foreach ($lines as $line) {

      # empty line
      #if ($line=="") {
      if (!strlen($line)) {
        if ($in_pre) { $xml->pre_line.="\n";continue;}
        if ($in_li) { $text.="\n"; continue;}
        if ($in_table) {
          $text.=$xml->_table(0)."\n";$in_table=0; continue;
        } else {
          if ($xml->in_p) { $text.="$xml->padding</para>\n"; $xml->in_p="";}
          else if ($xml->in_p=='') { $text.="\n";}
          continue;
        }
      } else if ($xml->in_p=='') {
        $text.="$xml->padding<para>\n";
        $xml->in_p= $line;
      }
      if ($line[0]=='#' and $line[1]=='#') continue; # comments

      if ($in_pre) {
         if (strpos($line,"}}}")===false) {
           $xml->pre_line.=$line."\n";
           continue;
         } else {
           $p=strrpos($line,"}}}");
           if ($p>2 and $line[$p-3]=='\\') {
             $xml->pre_line.=substr($line,0,$p-3).substr($line,$p-2)."\n";
             continue;
           }
           $len=strlen($line);
           $xml->pre_line.=substr($line,0,$p-2);
           $line=substr($line,$p+1);
           $in_pre=-1;
         }
      #} else if ($in_pre == 0 && preg_match("/{{{[^}]*$/",$line)) {
      } else if (!(strpos($line,"{{{")===false) and 
                 preg_match("/{{{[^}]*$/",$line)) {
         $p=strpos($line,"{{{");
         $len=strlen($line);

         $processor="";
         $in_pre=1;

         # check processor
         if ($line[$p+3] == "#" and $line[$p+4] == "!") {
            list($tag,$dummy)=explode(" ",substr($line,$p+5),2);

            if (function_exists("processor_".$tag)) {
              $processor=$tag;
            } else if ($pf=getProcessor($tag)) {
              include_once("plugin/processor/$pf.php");
              $processor=$pf;
            }
         } else if ($line[$p+3] == ":") {
            # new formatting rule for a quote block (pre block + wikilinks)
            $line[$p+3]=" ";
            $in_quote=1;
         }

         $xml->pre_line=substr($line,$p+3);
         if (trim($xml->pre_line))
           $xml->pre_line.="\n";
         $line=substr($line,0,$p);
      }

      $line=preg_replace($xml->baserule,$xml->baserepl,$line);

      # bullet and indentation
      if ($in_pre != -1 && preg_match("/^(\s*)/",$line,$match)) {
      #if (preg_match("/^(\s*)/",$line,$match)) {
         $open="";
         $close="";
         $indtype="dd";
         $indlen=strlen($match[0]);
         if ($indlen > 0) {
           $line=substr($line,$indlen);
           #if (preg_match("/^(\*\s*)/",$line,$limatch)) {
           if ($line[0]=='*') {
             $limatch[1]='*';
             $line=preg_replace("/^(\*\s?)/","$xml->padding<listitem>",$line);
             if ($indent_list[$in_li] == $indlen) $line="</listitem>\n".$line;
             $numtype="";
             $indtype="itemizedlist";
           } elseif (preg_match("/^((\d+|[aAiI])\.)(#\d+)?\s/",$line,$limatch)){
             $line=preg_replace("/^((\d+|[aAiI])\.(#\d+)?)/","$xml->padding<listitem>",$line);
             if ($indent_list[$in_li] == $indlen) $line="</listitem>\n".$line;
             $numtype=$limatch[2];
             if ($limatch[3])
               $numtype.=substr($limatch[3],1);
             $indtype="orderedlist";
           } elseif (preg_match("/^([^:]+)::\s/",$line,$limatch)) {
             $line=preg_replace("/^[^:]+::\s/",
                     "<dt class='wiki'>".$limatch[1]."</dt><dd>",$line);
             if ($indent_list[$in_li] == $indlen) $line="</dd>\n".$line;
             $numtype="";
             $indtype="dl";
           }
         }
         if ($indent_list[$in_li] < $indlen) {
            $in_li++;
            $indent_list[$in_li]=$indlen; # add list depth
            $indent_type[$in_li]=$indtype; # add list type
            $open.=$xml->_list(1,$indtype,$numtype);
         } else if ($indent_list[$in_li] > $indlen) {
            while($in_li >= 0 && $indent_list[$in_li] > $indlen) {
               if ($indent_type[$in_li]!='dd' && $li_open == $in_li)
                 $close.="</listitem>\n";
               $close.=$xml->_list(0,$indent_type[$in_li],"",$indent_type[$in_li-1]);
               unset($indent_list[$in_li]);
               unset($indent_type[$in_li]);
               $in_li--;
            }
         }
         if ($indent_list[$in_li] <= $indlen || $limatch) $li_open=$in_li;
         else $li_open=0;
      }

      #if (!$in_pre && !$in_table && preg_match("/^\|\|.*\|\|$/",$line)) {
      if (!$in_pre && $line[0]=='|' && !$in_table && preg_match("/^\|\|.*\|\|$/",$line)) {
         $open.=$xml->_table(1);
         $in_table=1;
      #} elseif ($in_table && !preg_match("/^\|\|.*\|\|$/",$line)){
      } elseif ($in_table && $line[0]!='|' && !preg_match("/^\|\|.*\|\|$/",$line)){
         $close=$xml->_table(0).$close;
         $in_table=0;
      }
      if ($in_table) {
         $line=preg_replace('/^((?:\|\|)+)(.*)\|\|$/e',"'<row><entry '.\$xml->_table_span('\\1').'>\\2</entry></row>'",$line);
         $line=preg_replace('/((\|\|)+)/e',"'</entry><entry '.\$xml->_table_span('\\1').'>'",$line);
         $line=str_replace('\"','"',$line); # revert \\" to \"
      }
      $line=$close.$open.$line;
      $open="";$close="";

      # InterWiki, WikiName, {{{ }}}, !WikiName, ?single, ["extended wiki name"]
      # urls, [single bracket name], [urls text], [[macro]]
      $line=preg_replace("/(".$wordrule.")/e","\$xml->link_repl('\\1')",$line);

      # Headings
      $line=preg_replace("/(?<!=)(={1,5})\s+(.*)\s+(={1,5})\s?$/e",
                         "\$xml->head_repl('\\1','\\2','\\3')",$line);

      $line=preg_replace("/&(?:\s)/","&amp;",$line);

      $xml->_set_padding($xml->level);

      if ($in_pre==-1) {
         $in_pre=0;
         if ($processor) {
           $value=$xml->pre_line;
           $out= call_user_func("processor_$processor",&$formatter,$value,$options);
           $line="<screen><![CDATA[\n".$out."\n]]></screen>\n".$line;
         } else if ($in_quote) {
            # htmlfy '<'
            $pre=str_replace("&","&amp;",$xml->pre_line);
            $pre=str_replace("<","&lt;",$pre);
            $pre=preg_replace($xml->baserule,$xml->baserepl,$pre);
            $pre=preg_replace("/(".$wordrule.")/e","\$xml->link_repl('\\1')",$pre);
            $line="<quote>\n".$pre."</quote>\n".$line;
            $in_quote=0;
         } else {
            # htmlfy '<'
            $pre=str_replace("&","&amp;",$xml->pre_line);
            $pre=str_replace("<","&lt;",$pre);
            $line="<screen><![CDATA[\n".$pre."]]></screen>\n".$line;
         }
      }
      $text.=$xml->padding.$line."\n";
    }

    # close all tags
    $close="";
    # close pre,table
    if ($in_pre) $close.="</screen>\n";
    if ($in_table) $close.="</table>\n";
    # close indent
    while($in_li >= 0 && $indent_list[$in_li] > 0) {
      if ($indent_type[$in_li]!='dd' && $li_open == $in_li)
        $close.="</listitem>\n";
      $close.=$xml->_list(0,$indent_type[$in_li]);
      unset($indent_list[$in_li]);
      unset($indent_type[$in_li]);
      $in_li--;
    }
    # close div
    if ($xml->in_p) $close.="</para>\n";

    if ($xml->head_dep) {
      $odepth=$xml->head_dep;
      $dum=explode(".",$xml->head_num);
      $i=sizeof($dum)-1;
      while (0 <= $odepth && $i >= 0) {
         $i--;
         $close.="</sect$odepth>\n"; # </section>
         $odepth--;
      }
    }

    $text.=$close;

    $pagename=$formatter->page->name;
    $header=<<<HEAD
<?xml version="1.0" encoding="$DBInfo->charset"?>
<!-- <?xml-stylesheet href="DocbookKoXsl" type="text/xml"?> -->
<!-- <!DOCTYPE article PUBLIC "-//OASIS//DTD DocBook XML V4.1.2//EN"
                  "http://www.docbook.org/xml/4.1.2/docbookx.dtd"> -->

<article lang="ko">

<articleinfo>
<title>$pagename</title>
</articleinfo>

HEAD;
    $footer="</article>\n";

    print $header.$text.$footer;
  }
?>
