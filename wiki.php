<?
# $Id$

$Globals[interwiki][]=array();
$Globals[wikis]="";

function get_scriptname() {
  // Return full URL of current page.
  global $SCRIPT_NAME;
  //return $SCRIPT_NAME;
  //global $PHP_SELF;
  //return $PHP_SELF;
  return $SCRIPT_NAME;
}

function get_intermap() {
   global $Globals;
#   if ($Globals[interwiki]) return;
      
   # intitialize interwiki map
   $map=file("intermap.txt");

   for ($i=0;$i<sizeof($map);$i++) {
      $dum=split("[[:space:]]",$map[$i]);
      $Globals[interwiki][$dum[0]]=trim($dum[1]);
      $Globals[wikis].="$dum[0]|";
   }
   $Globals[wikis].="Self";

}

get_intermap();

function http_header() {

}

class WikiDB {
  function WikiDB() {
    $this->url_prefix = '/wiki';
    $this->data_dir = '/home/httpd/wiki/data';
    $this->text_dir = $this->data_dir . '/text';
    $this->editlog_name = $this->data_dir . '/editlog';
    $this->umask = 02;

    $this->logo_string = '<img src="/wiki/moinmoin.gif" border=0>';
    $this->show_hosts = TRUE;

    $this->date_fmt = 'D d M Y';
    $this->datetime_fmt = 'D d M Y h:i a';
    $this->changed_time_fmt = ' . . . . [h:i a]';

    // Number of lines output per each flush() call.
    $this->lines_per_flush = 10;

    // Is mod_rewrite being used to translate 'WikiWord' to
    // 'phiki.php3?WikiWord'?  Default:  false.
    $this->rewrite = true;
  }

  function getPageKey($pagename) {
    # normalize a pagename to uniq key
    return $this->text_dir . '/' . $pagename;
  }

  function hasPage($page) {
    $name=$this->getPageKey($pagename);

    return file_exists($name); 
  }

  function getPage($page,$options="") {
    return new WikiPage($page,$options);
  }
}

class WikiPage {
  var $fp;
  var $filename;
  function WikiPage($name,$options="") {
    if ($options[rev])
      $this->rev=$options[rev];
    else
      $this->rev=0; # current rev.
    $this->name = $name;
    $this->filename = $this->_filename($name);
    $this->body = "";
  }

  function _filename($name) {
    // Return filename where this word/page should be stored.
    global $DBInfo;
    $filename = $DBInfo->text_dir . '/' . $name;
    return $filename;
  }

  function exists() {
    // Does a page for the given word already exist?
    return file_exists($this->filename);
  }
  function mtime () {
    return @filemtime($this->filename);
  }

  function get_raw_body() {
    if ($this->body)
       return $this->body;

    if ($this->rev) {
       $fp=popen("co -q -p'".$this->rev."' ".$this->filename,"r");
       if (!$fp)
          return "";
       while (!feof($fp)) {
          $line=fgets($fp,1024);
          $out .= $line;
       }
       pclose($fp);
       return $out;
    }

    $fp=fopen($this->filename,"r");
    $body="";
    if ($fp) {
       while($line=fgets($fp, 4096)) {
          $body.=$line;
       }
    }
    fclose($fp);
    $this->body=$body;

    return $body;
  }

  function set_raw_body($body) {
    $this->body=$body;
  }

  function update() {
    if ($this->body)
       $this->write($this->body);
  }

  function write($body,$comment="") {
    global $REMOTE_ADDR;
#
    $this->body=$body;
# get gmtime

    $fp=fopen($this->filename,"w");
    fwrite($fp, $body);
    fclose($fp);
    system("ci -q -t-".$this->name." -l -m'".$REMOTE_ADDR.";;".$comment."' ".$this->filename);
  }
}


class Formatter {

 function Formatter($page) {
   $this->page=$page;

 }

 function interlink($wiki,$page) {
   global $DBInfo;
   global $Globals;

   if (!$Globals[interwiki]["$wiki"]) return "$wiki:$page";

   $page=trim($page);
   $img=strtolower($wiki);
   return "<img src='$DBInfo->url_prefix/imgs/$img-16.png' width=16 height=16 align=absmiddle>"
          ."<a href='".$Globals[interwiki][$wiki]."$page' title='$wiki:$page'>$page</a>";
 }

 function link_tag($query_string, $text='') {
   // Return a link with given query_string.
   if (! $text)
     $text = $query_string;
   return sprintf('<a href="%s/%s">%s</a>',
                 get_scriptname(), $query_string, $text);
 }

 function link_to($query_string,$text="") {
   return $this->link_tag($this->page->name."$query_string",$text);
 }

 function _list($on,$list_type,$numtype="") {
   if ($on) {
      if ($numtype)
         return "<$list_type type=$numtype>";
      return "<$list_type>";
   } else {
      return "</$list_type>\n";
   }
 }

 function _table_span($str) {
   $len=strlen($str)/2;
   if ($len > 1) {
      return " align='center' colspan='$len'";
   }
   return "";
 }

 function _table($on,$attr="") {
   if ($on)
      return "<table class='wiki' border=1 cellpadding=3 $attr>\n";
   else
      return "</table>\n";
 }

 function send_page() {
   global $Globals;
   $lines=explode("\n",$this->page->get_raw_body());

   $text="";
   $in_pre=0;
   $in_p=0;
   $in_li=0;
   $in_table=0;
   $pre="";
   $indent_list[0]=0;
   $indent_type[0]="";
   $list_type=array("ul"=>"ul","ol"=>"ol","dl"=>"dl");

   $headrule=array ("/= (.*) =$/",
		    "/== (.*) ==$/",
		    "/=== (.*) ===$/",
		    "/==== (.*) ====$/",
		    "/===== (.*) =====$/",);
   $headrepl=array ("<h1>\\1</h1>\n",
                     "<h2>\\1</h2>\n",
                     "<h3>\\1</h3>\n",
                     "<h4>\\1</h4>\n",
                     "<h5>\\1</h5>\n",);

   $wordrule=array ("/(?<!:|\!)(([A-Z]+[a-z0-9]+){2,})(?!(:|[a-z0-9]))/",
                    "/\!(([A-Z]+[a-z0-9]+){2,})(?!(:|[a-z0-9]))/",
                    "/\?(([a-z0-9]+)+)/");

   $wordrepl=array ("<a href='\\1'>\\1</a>",
                    "\\1",
                    "<a href='\\1'>\\1</a>");

   foreach ($lines as $line) {
      #$line=preg_replace("/\r?\n$|\r[^\n]$/", "", $line);
      $line=preg_replace("/\n$/", "", $line);
      if (!$in_table && $in_p && $line=="") {$in_p=0; $text.="<p>\n";continue;}
      if ($line=="") {$in_p=1;continue;}
      if (substr($line,0,2)=="##") continue;
      $line=preg_replace("/{{{(.*)}}}/","<tt class='wiki'>\\1</tt>",$line);

      if (!$in_pre && preg_match("/{{{/",$line)) {
         $line=substr_replace($line,"<pre class=wiki>",strpos($line,"{{{"),3);
         $in_pre=1;
      } else if ($in_pre && preg_match("/}}}/",$line)) {
         $line=substr_replace($line,"</pre>",strrpos($line,"}}}")-2,3);
         $in_pre=-1;
      } else if ($in_pre) {
         $line=preg_replace("/</","&lt;",$line);
      }
      if (!$in_pre) {
      $line=preg_replace($headrule,$headrepl,$line);
      $line=preg_replace("/`(.*)`/","<tt class='wiki'>\\1</tt>",$line);
      $line=preg_replace("/'''''(.*)'''''/","<b><i>\\1</i></b>",$line);
      $line=preg_replace("/'''(.*)'''/","<b>\\1</b>",$line);
      $line=preg_replace("/''(.*)''/","<i>\\1</i>",$line);
      $line=preg_replace("/^-{4,}/","<hr>\n",$line);

      # bullet
      if (!$in_pre && preg_match("/^(\s*)/",$line,$match)) {
         $open="";
         $close="";
         $indtype="ul";
         $indlen=strlen($match[0]);
         #print "<!-- indlen=$indlen -->\n";
         if ($indlen > 0) {
            $line=substr($line,$indlen,strlen($line));
            if (preg_match("/^(\*\s)/",$line,$match)) {
               $line=preg_replace("/^(\*\s)/","<li>",$line);
               $numtype="";
               $indtype="ul";
            } else if (preg_match("/^((\d+|[aAiI])\.\s)/",$line,$match)) {
               $line=preg_replace("/^((\d+|[aAiI])\.\s)/","<li>",$line);
               $numtype=$match[2];
               $indtype="ol";
            }
         }
         if ($indent_list[$in_li] < $indlen) {
            $in_li++;
            $indent_list[$in_li]=$indlen; # add list depth
            $indent_type[$in_li]=$indtype; # add list type
            $open.=$this->_list(1,$indtype,$numtype);
         } else if ($indent_list[$in_li] > $indlen) {
            while($in_li >= 0 && $indent_list[$in_li] > $indlen) {
               $close.=$this->_list(0,$indent_type[$in_li]);
               unset($indent_list[$in_li]);
               unset($indent_type[$in_li]);
               $in_li--;
            }
         }
      }

      if (!$in_pre && !$in_table && preg_match("/^\|\|.*\|\|$/",$line)) {
         $open.=$this->_table(1);
         $in_table=1;
      } else if ($in_table && !preg_match("/^\|\|.*\|\|$/",$line)) {
         $close.=$this->_table(0);
         $in_table=0;
      }
      if ($in_table) {
         $line=preg_replace("/^((?:\|\|)+)(.*)\|\|$/e","'<tr class=\"wiki\"><td class=\"wiki\"'.\$this->_table_span('\\1').'>\\2</td></tr>'",$line);
         $line=preg_replace("/((\|\|)+)/e","'</td><td class=\"wiki\"'.\$this->_table_span('\\1').'>'",$line);
      }
      $line=$close.$open.$line;
      $open="";$close="";

#      # WikiName
#      $line=preg_replace("/(?<!:|\!)(([A-Z]+[a-z0-9]+){2,})(?!(:|[a-z0-9]))/","<a href='\\1'>\\1</a>",$line);
#      $line=preg_replace("/\!(([A-Z]+[a-z0-9]+){2,})(?!(:|[a-z0-9]))/","\\1",$line);
#      # Single WikiWord
#      $line=preg_replace("/\?(([a-z0-9]+)+)/","<a href='\\1'>\\1</a>",$line);
      $line=preg_replace($wordrule,$wordrepl,$line);

      # Extended Wikiname
      $line=preg_replace("/(?<!\[)\[([^\[]*)\](?!\])/","<a href='\\1'>\\1</a>",$line);


      $rule="/(".$Globals[wikis]."):([^<>\s\']+[\s]{0,1})/e";
      $repl="\$this->interlink('\\1','\\2')";
      $line=preg_replace($rule, $repl, $line);

      }
      $text.=$line."\n";
      if ($in_pre==-1) $in_pre=0;
   }
   # close all tags

   $close="";
   if ($in_pre) {
      $close.="</pre>\n";
   }
   if ($in_table) {
      $close.="</table>\n";
   }
   # close indent
   while($in_li >= 0 && $indent_list[$in_li] > 0) {
      $close.=$this->_list(0,$indent_type[$in_li]);
      unset($indent_list[$in_li]);
      unset($indent_type[$in_li]);
      $in_li--;
   }
   $text.=$close;
   
   # process interwiki
#   $rule="/($this->wikis):([^<>\s\']+[\s]{0,1})/e";
#   $repl="\$this->interlink('\\1','\\2')";
#
#   $text=preg_replace($rule, $repl, $text);
   print $text;
 }

 function _parse_rlog($log) {
   $lines=explode("\n",$log);
   $state=0;
   $flag=0;

   $out="<h1>Revision History</h1>\n";
   $out.="<table border=1 cellpadding=3>\n";
   $out.="<th>Rev.</th><th>file information</th><th>IP</th>".
         "<th>actions</th>";
   $out.= "</tr>\n";
   
   foreach ($lines as $line) {
      if (!$state) {
        if (!preg_match("/^---/",$line)) { continue;}
        else {$state=1; continue;}
      }
      
      switch($state) {
        case 1:
            preg_match("/^revision ([0-9\.]*)/",$line,$match);
            $rev=$match[1];
            $state=2;
            break;
        case 2:
            $inf=preg_replace("/author:.*;\s+state:.*;/","",$line);
            $state=3;
            break;
        case 3:
            $dummy=explode(";;",$line);
            $ip=$dummy[0];
            $user=$dummy[1];
            $comment=$dummy[2];
            $state=4;
            break;
        case 4:
            $out.="<tr>\n";
            $out.="<td rowspan=2># $rev</td><td>$inf</td><td>$ip&nbsp;</td>".
                  "<td>".$this->link_to("?action=recall&rev=$rev","view").
                  " ".$this->link_to("?action=raw&rev=$rev","raw");
            if ($flag) {
               $out.= " ".$this->link_to("?action=diff&rev=$rev","diff");
            }
            $out.="</td>";
            $out.="</tr>\n";
            $out.="<tr><td colspan=3>$comment&nbsp;</td></tr>\n";
            $state=1;
            $flag=1;
            break;
      }
   }
   $out.="</table>\n";
   return $out; 
 }

 function show_info() {
   $fp=popen("rlog ".$this->page->filename,"r");
   if (!$fp)
      return "";
   while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out .= $line;
   }
   pclose($fp);
   print $this->_parse_rlog($out);
 }

 function _parse_diff($diff) {
   $lines=explode("\n",$diff);
   $out="";
   foreach ($lines as $line) {
      $marker=$line[0];
      $line=substr($line,1,strlen($line));
      if ($marker=="@") $line='<hr style="color:#FF3333">';
      else if ($marker=="-") $line='<div class="diff-removed">'.$line.'</div>';
      else if ($marker=="+") $line='<div class="diff-added">'.$line.'</div>';
      else if ($marker=="\\" && $line==" No newline at end of file") continue;
      $out.=$line."\n";
   }
   return $out;
 }

 function get_diff($rev1="",$rev2="") {
   $option="";
   if ($rev1) $option="-r$rev1 ";
   if ($rev2) $option.="-r$rev2 ";

   $fp=popen("rcsdiff -u $option ".$this->page->filename,"r");
   if (!$fp)
      return "";
   while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out .= $line;
   }
   pclose($fp);
   print $this->_parse_diff($out);
 }

 function send_header() {
   print <<<EOF
<html><head>
<style  type="text/css">
<!--
body {font-family:Verdana,Lucida;font-size:14px;}
.title {font-family:Verdana,Lucida;font-size:30px;font-weight:bold;}
tt.wiki {font-family:Lucida Typewriter,fixed,lucida;font-size:14px;}
pre.wiki {
  padding-left:6px;
  padding-top:6px; 
  font-family:Lucida TypeWriter,monotype,fixed,lucida;font-size:14px;
  background-color:#000000;
  color:gold;
 }
table.wiki {
  background-color:#E2ECE2;
/*  border-collapse: collapse; */
/*  border: 1px solid silver; */
/*  border: 1px; */
/*  border-color:silver; */
}
h1,h2,h3,h4,h5 {font-family:Tahoma;background-color:#B8B88E;padding-left:6px;}

div.diff-added {
   font-family:Lucida Console,fixed;
   background-color:#61FF61;
   color:black;
}

div.diff-removed {
   font-family:Lucida Console,fixed;
   background-color:#E9EAB8;
   color:black;
}
//-->

</style>
</head>
<body>
EOF;
 }

 function send_footer($footer="") {
   if ($footer) {
      print "$footer";
   } else {
      print "<hr>";
      print $this->link_to("?action=edit",'EditText')."|";
      print $this->link_tag("FindPage");
   }
   
   print "</body></html>";

 }

 function send_title($text="", $link=0, $msg=0) {
   // Generate and output the top part of the HTML page.
   global $DBInfo;

   if (!$text)
     $text=$this->page->name;

   print "<head><title>$text</title></head>";
   print "<body>\n  <font class=title>";
   if ($DBInfo->logo_string) {
     print $this->link_tag('RecentChanges', $DBInfo->logo_string);
     print '&nbsp;&nbsp;&nbsp;';
   }
   if ($link)
     print "<a href=\"$link\">$text</a>";
   else
     print $text;
   print "</font>";
   if ($msg) print($msg);
   print "<hr>\n";
   flush();
 }

 function send_editor($text="",$preview=0) {
    print "<a name=editor>\n";
    printf('<form method="POST" action="%s/%s#preview">', get_scriptname(),$this->page->name);
    printf("<br>\n");
    print $this->link_tag('HelpOnEditing')."|";
    print $this->link_tag('InterWiki')."|";
    print $this->link_tag('HelpOnEditing');
    if ($preview)
       print "|".$this->link_to('#preview',"Skip to preview");
    printf("<br>\n");
    if ($text) {
      $raw_body = str_replace('\r\n', '\n', $text);
    } else if ($this->page->exists()) {
      $raw_body = str_replace('\r\n', '\n', $this->page->get_raw_body());
    } else
      $raw_body = sprintf("Describe %s here", $this->page->name);

    $COLS_MSIE = 70;
    $COLS_OTHER = 80;
    $cols = preg_match('/MSIE/', $HTTP_USER_AGENT) ? $COLS_MSIE : $COLS_OTHER;

    printf('<textarea id="content" wrap="virtual" name="savetext" rows="16"
           cols="%s">%s</textarea>', $cols, $raw_body);
    printf("\n<br>\n");
    printf('<input type=hidden name="action" value="savepage">'."\n");
    printf('<input type=submit value="Save">&nbsp;&nbsp;');
    printf('<input type=reset value="Reset">&nbsp;');
    printf('<input type=submit name="button_preview" value="Preview">');
    print "</form>\n";

    print <<<EOF
<font class="hint">
<b>Emphasis:</b> ''<i>italics</i>''; '''<b>bold</b>'''; '''''<b><i>bold italics</i></b>''''';
    ''<i>mixed '''<b>bold</b>''' and italics</i>''; ---- horizontal rule.<br>
<b>Headings:</b> = Title 1 =; == Title 2 ==; === Title 3 ===;
    ==== Title 4 ====; ===== Title 5 =====.<br>
<b>Lists:</b> space and one of * bullets; 1., a., A., i., I. numbered items;
    1.#n start numbering at n; space alone indents.<br>
<b>Links:</b> JoinCapitalizedWords; ["brackets and double quotes"];
    url; [url]; [url label].<br>
<b>Tables</b>: || cell text |||| cell text spanning two columns ||;
    no trailing white space allowed after tables or titles.<br>
</font>
<a name='preview'>
EOF;

 }

}

# extra utilities

#function quoteW(filename) {
#    safe = string.letters + string.digits + '_-'
#    res = list(filename)
#    for i in range(len(res)):
#        c = res[i]
#        if c not in safe:
#            res[i] = '_%02x' % ord(c)
#    return string.joinfields(res, '')

$DBInfo = new WikiDB;

if (!empty($PATH_INFO)) {
   if ($PATH_INFO[0] == '/')
      $pagename=substr($PATH_INFO,1,strlen($PATH_INFO));

   $result = preg_match('/\b[A-Z][a-z]*(?:[A-Z][a-z]+){1,}[0-9]*\b/',$pagename,$matches);
   if ($result)
      $pagename = $matches[0];
   else
      $pagename = "FrontPage";
}


if ($REQUEST_METHOD=="POST") {
   if ($action=="savepage") {
   $page = $DBInfo->getPage($pagename);
   $formatter = new Formatter($page);
   $formatter->send_header();
   if ($page->exists()) {
      # check difference
      $body=$page->get_raw_body();
      $body=str_replace("\r\n", "\n", $body);
      $orig=md5($body);
   }
   $savetext=str_replace("\r\n", "\n", $savetext);
   $savetext=stripslashes($savetext);
   $new=md5($savetext);

   if ($orig == $new) {
      $formatter->send_title("No difference found","",$formatter->link_tag($page->name,""));
      $formatter->send_footer(" ");

      return;
   }
   $formatter->page->set_raw_body($savetext);

   if ($button_preview) {
      $formatter->send_title("","",$formatter->link_tag($page->name,""));
      
      $formatter->send_editor($savetext,1);
      print "<hr>\n";
      print $formatter->link_tag('HelpOnEditing')."|";
      print $formatter->link_tag('InterWiki')."|";
      print $formatter->link_tag('HelpOnEditing')."|";
      print $formatter->link_to("#editor","Goto Editor");
      print "<table border=1 width=90%><tr><td>\n";
      $formatter->send_page();
      print "</td></tr></table><hr>\n";
   } else {
      $page->write($savetext);
      $formatter->send_title("","",$formatter->link_tag($page->name,""));
      $formatter->send_page();
   }
   $formatter->send_footer(" ");

   exit;
   }
}

if (!empty($QUERY_STRING))
   $query= $QUERY_STRING;

if ($pagename) {

   if ($action=="recall" && $rev) {
       $options[rev]=$rev;
       $page = $DBInfo->getPage($pagename,$options);
   } else
       $page = $DBInfo->getPage($pagename);

   $formatter = new Formatter($page);

   if (!$action) {
      if (!$page->exists()) {
        $formatter->send_header();
        $formatter->send_title($page->name." Not Found");
        $formatter->send_footer();
        return;
      }
      $formatter->send_header();
      $formatter->send_title();
      $formatter->send_page();
      $formatter->send_footer();
      return;
   }

   if ($action=="diff") {
      $formatter->send_header();
      $formatter->send_title("Diff for $rev ".$page->name);
      print $formatter->get_diff($rev);
      $formatter->send_footer();

      return;
   }
   if ($action=="recall" || $action=="raw") {
     if ($action=="raw") {
     } else {
        $formatter->send_header();
        $formatter->send_title("Rev. $rev ".$page->name);
     }
     if (!$page->exists() || !$page->get_raw_body()) {
        if ($action=="raw") {
        } else {
           $formatter->send_footer();
        }
        return;
     }
     if ($action=="raw") {
        print $page->get_raw_body();
     } else {
        $formatter->send_page();
        $formatter->send_footer();
     }
     return;
   } else if ($action=="edit") {
     $formatter->send_header();
     $formatter->send_title("Edit ".$page->name);
     $formatter->send_editor();
     $formatter->send_footer();
   } else if ($action=="info") {
     $formatter->send_header();
     $formatter->send_title("Info. for ".$page->name);
     $formatter->show_info();
     $formatter->send_footer();
   }
}

?>
