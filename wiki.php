<?

function get_scriptname() {
  // Return full URL of current page.
  global $SCRIPT_NAME;
  //return $SCRIPT_NAME;
  //global $PHP_SELF;
  //return $PHP_SELF;
  return $SCRIPT_NAME;
}

function http_header() {

}

class WikiConfig {
  function WikiConfig() {
    $this->url_prefix = '/wiki';
    $this->data_dir = '/home/httpd/wiki/data';
    $this->text_dir = $this->data_dir . '/text';
    $this->editlog_name = $this->data_dir . '/editlog';
    $this->umask = 02;

    $this->logo_string = '<img src="phiki.gif" border=0>';
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
}

class WikiPage {
  var $fp;
  var $filename;
  function WikiPage($name) {
    $this->name = $name;
    $this->filename = $this->_filename($name);
    $this->body = "";
  }

  function _filename($name) {
    // Return filename where this word/page should be stored.
    global $Config;
    $filename = $Config->text_dir . '/' . $name;
    return $filename;
  }

  function exists() {
    // Does a page for the given word already exist?
    return file_exists($this->filename);
  }
  function mtime () {
    return @filemtime($this->filename);
  }

  function get_body() {
    return file($this->filename);
  }

  function get_raw_body() {
    if ($this->body)
        return $this->body;

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

  function write($body) {
    $this->body=$body;
    $fp=fopen($this->filename,"w");
    fwrite($fp, $body);
  }
}


class Formatter {

 function Formatter($page) {
   $this->page=$page;

   # intitialize interwiki map
   $map=file("intermap.txt");

   $this->wikis="";
   for ($i=0;$i<sizeof($map);$i++) {
      $dum=split("[[:space:]]",$map[$i],2);
      $this->interwiki[$dum[0]]=trim($dum[1]);
      $this->wikis.="$dum[0]|";
   }
   $this->wikis.="Self";
 }

 function interlink($wiki,$page) {
   global $Config;

   $page=trim($page);
   $img=strtolower($wiki);
   return "<img src='$Config->url_prefix/imgs/$img-16.png' width=16 height=16 align=absmiddle>"
          ."<a href='".$this->interwiki[$wiki]."$page' title='$wiki:$page'>$page</a>";
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


      $rule="/($this->wikis):([^<>\s\']+[\s]{0,1})/e";
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
   global $Config;

   if (!$text)
     $text=$this->page->name;

   print "<head><title>$text</title></head>";
   print "<body>\n  <font class=title>";
   if ($Config->logo_string) {
     print $this->link_tag('RecentChanges', $Config->logo_string);
     print '&nbsp;&nbsp;&nbsp;&nbsp;';
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

$Config = new WikiConfig;

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
   $page = new WikiPage($pagename);
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
   $page = new WikiPage($pagename);
   $formatter = new Formatter($page);
   $formatter->send_header();
   if ($page->exists() && $action!="edit") {
     $formatter->send_title();
     $formatter->send_page();
   } else {
     $formatter->send_title("Edit ".$page->name);
     $formatter->send_editor();
   }
   $formatter->send_footer();
}

#$text=process($lines);


?>
