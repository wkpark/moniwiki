<?php
// from http://www.sitepoint.com/examples/phpxml/sitepointcover-oo.php.txt
// Public Domain ?
// $Id$
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
           print "<img src=\"".$attrs['RDF:RESOURCE']."\"><br />";
       }
   }

   function endElement($parser, $tagName) {
       if ($tagName == "ITEM") {
           if ($this->status) print "[$this->status] ";
           printf("<a href='%s'>%s</a>",
             trim($this->link),
             htmlspecialchars(trim($this->title)));
           #printf("<p>%s</p>",
           #  htmlspecialchars(trim($this->description)));
           $date=$this->date;
           $date[10]=" ";
           $time=strtotime(substr($date,0,-6));
           $date=date("[h:i a]",$time);
           printf(" %s<br />\n",
             htmlspecialchars(trim($date)));
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

function macro_Rss($formatter,$value) {
  global $DBInfo;

  $xml_parser = xml_parser_create();

  $rss_parser = new WikiRSSParser();
  xml_set_object($xml_parser,&$rss_parser);
  xml_set_element_handler($xml_parser, "startElement", "endElement");
  xml_set_character_data_handler($xml_parser, "characterData");

  $fp = fopen("$value","r");
  if (!$fp)
    return ("[[RSS(ERR: not a valid URL! $value)]]");

  ob_start();
  while ($data = fread($fp, 4096))
    $ret= xml_parse($xml_parser, $data, feof($fp));
  if (!$ret)
    return (sprintf("[[RSS(XML error: %s at line %d)]]",  
      xml_error_string(xml_get_error_code($xml_parser)),  
      xml_get_current_line_number($xml_parser)));
  fclose($fp);
  $out=ob_get_contents();
  ob_end_clean();
  xml_parser_free($xml_parser);

#  if (strtoupper($DBInfo->charset) != 'UTF-8')
#    $out=iconv('utf-8',$DBInfo->charset,$out);

  return $out;
}

?>
