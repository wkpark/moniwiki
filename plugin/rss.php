<?php
// from http://www.sitepoint.com/examples/phpxml/sitepointcover-oo.php.txt
// Public Domain ?
// $Id: rss.php,v 1.7 2010/08/23 09:15:23 wkpark Exp $
class WikiRSSParser {

   var $insideitem = false;
   var $tag = "";
   var $title = "";
   var $description = "";
   var $link = "";
   var $date = "";

   function WikiRSSParser() {
   }

   function startElement($parser, $tagName, $attrs) {
       if ($this->insideitem) {
           $this->tag = $tagName;
       } elseif ($tagName == "ITEM") {
           $this->insideitem = true;
       } elseif ($tagName == "IMAGE") {
           if (!empty($attrs['RDF:RESOURCE']))
           print "<img src=\"".$attrs['RDF:RESOURCE']."\"><br />";
       }
   }

   function endElement($parser, $tagName) {
       if ($tagName == "ITEM") {
           if ($this->status) print "[$this->status] ";
           printf("<a href='%s' target='_content'>%s</a>",
             trim($this->link),
             _html_escape(trim($this->title)));
           #printf("<p>%s</p>",
           #  _html_escape(trim($this->description)));
           if ($this->date) {
             $date=trim($this->date);
             $date[10]=" ";
             # 2003-07-11T12:08:33+09:00
             # http://www.w3.org/TR/NOTE-datetime
             $zone=str_replace(":","",substr($date,19));
             $time=strtotime(substr($date,0,19).$zone);
             $date=date("@ m-d [h:i a]",$time);
             printf(" %s<br />\n", _html_escape(trim($date)));
           } else
             printf("<br />\n");
           $this->title = "";
           $this->description = "";
           $this->link = "";
           $this->date = "";
           $this->status = "";
           $this->insideitem = false;
       }
   }

   function characterData($parser, $data) {
       if ($this->insideitem) {
           switch ($this->tag) {
               case "TITLE":
               $this->title .= $data;
               break;
               case "DESCRIPTION":
               $this->description .= $data;
               break;
               case "LINK":
               $this->link .= $data;
               break;
               case "DC:DATE":
               $this->date .= $data;
               break;
               case "WIKI:STATUS":
               $this->status .= $data;
               break;
           }
           #print $this->tag."/";
       }
   }
}

function do_Rss($formatter,$options) {
  if ($options['url']) {
    print '<font size="-1">';
    print macro_Rss($formatter,$options['url']);
    print '</font>';
  }
  return;
}

function macro_Rss($formatter,$value) {
  global $DBInfo;

  $xml_parser = xml_parser_create();

  $rss_parser = new WikiRSSParser();
  xml_set_object($xml_parser,$rss_parser);
  xml_set_element_handler($xml_parser, "startElement", "endElement");
  xml_set_character_data_handler($xml_parser, "characterData");

  $key=_rawurlencode($value);

  $cache= new Cache_text("rss");
  # reflash rss each 7200 second (60*60*2)
  if (!$cache->exists($key) or (time() > $cache->mtime($key) + 7200 )) {
    $fp = @fopen("$value","r");
    if (!$fp)
      return ("[[RSS(ERR: not a valid URL! $value)]]");

    while ($data = fread($fp, 4096)) $xml_data.=$data;
    fclose($fp);
    $cache->update($key,$xml_data);
  } else
    $xml_data=$cache->fetch($key);

  list($line,$dummy)=explode("\n",$xml_data,2);
  preg_match("/\sencoding=?(\"|')([^'\"]+)/",$line,$match);
  if ($match) $charset=strtoupper($match[2]);
  else $charset='UTF-8';
  # override $charset for php5
  if ((int)phpversion() >= 5) $charset='UTF-8';

  ob_start();
  $ret= xml_parse($xml_parser, $xml_data);

  if (!$ret)
    return (sprintf("[[RSS(XML error: %s at line %d)]]",  
      xml_error_string(xml_get_error_code($xml_parser)),  
      xml_get_current_line_number($xml_parser)));
  $out=ob_get_contents();
  ob_end_clean();
  xml_parser_free($xml_parser);

  #  if (strtolower(str_replace("-","",$options['oe'])) == 'euckr')
  if (function_exists('iconv') and strtoupper($DBInfo->charset) != $charset) {
    $new=iconv($charset,$DBInfo->charset,$out);
    if ($new !== false) return $new;
  }

  return $out;
}

?>
