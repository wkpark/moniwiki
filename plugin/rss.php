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
   var $date_fmt = '[m-d h:i a]';
   var $title_width = 0; // do not cut title

   function WikiRSSParser($charset = 'UTF-8') {
       $this->charset = $charset;
   }

   function setDateFormat($format) {
       $this->date_fmt = $format;
   }

   function setTitleWidth($width) {
       $this->title_width = $width;
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
           //if ($this->status) print "[$this->status] ";

           $title = trim($this->title);
           if (!empty($this->title_width) && function_exists('mb_strimwidth')) {
             $title = mb_strimwidth($title, 0, $this->title_width, '...', $this->charset);
           }
           $title = sprintf("<a href='%s' title='%s' target='_content'>%s</a>",
             trim($this->link),
             _html_escape($this->title),
             _html_escape($title));
           #printf("<p>%s</p>",
           #  _html_escape(trim($this->description)));
           if ($this->date) {
             $date=trim($this->date);
             $date[10]=" ";
             # 2003-07-11T12:08:33+09:00
             # http://www.w3.org/TR/NOTE-datetime
             $zone=str_replace(":","",substr($date,19));
             $time=strtotime(substr($date,0,19).$zone);
             $date = date($this->date_fmt, $time);
           }
           echo '<li><span data-timestamp="'.$time.'" class="date">', $date, '</span> ', $title, '</li>',"\n";

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
               case "PUBDATE":
               $data = trim($data);
               if (empty($data))
                   break;
               $time = strtotime($data);
               $date = gmdate("Y-m-d\TH:i:s", $time).'+00:00';
               $this->date = $date;
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

  $rsscache = new Cache_text('rsscache');

  $key=_rawurlencode($value);
  // rss TTL = 7200 sec (60*60*2)
  if (!$formatter->refresh && $rsscache->exists($key) && time() < $rsscache->mtime($key) + 7200) {
    return $rsscache->fetch($key);
  }

  if ($rsscache->exists($key.'.lock')) {
    return '';
  }

  $args = explode(',', $value);

  // first arg assumed to be a date fmt arg
  $date_fmt = '';
  $title_width = 0;
  if (count($args) > 1) {
    if (preg_match("/^[\s\/\-:aABdDFgGhHiIjmMOrSTY\[\]]+$/", $args[0])) {
      $date_fmt = array_shift($args);
    }
    if (is_numeric($args[0])) {
      $title_width = array_shift($args);
      // too small or too big
      if ($title_width < 10 || $title_width > 40)
        $title_width = 0;
    }
    $value = implode(',', $args);
  }

  $cache= new Cache_text("rssxml");
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

  // detect charset
  $charset = 'UTF-8';
  list($line,$dummy)=explode("\n",$xml_data,2);
  preg_match("/\sencoding=?(\"|')([^'\"]+)/",$line,$match);
  if ($match) $charset=strtoupper($match[2]);

  // override $charset for php5
  if ((int)phpversion() >= 5) $charset='UTF-8';

  $xml_parser = xml_parser_create();

  $rss_parser = new WikiRSSParser($charset);
  if (!empty($date_fmt))
    $rss_parser->setDateFormat($date_fmt);
  if (!empty($title_width))
    $rss_parser->setTitleWidth($title_width);

  xml_set_object($xml_parser,$rss_parser);
  xml_set_element_handler($xml_parser, "startElement", "endElement");
  xml_set_character_data_handler($xml_parser, "characterData");

  ob_start();
  $ret= xml_parse($xml_parser, $xml_data);

  if (!$ret)
    return (sprintf("[[RSS(XML error: %s at line %d)]]",  
      xml_error_string(xml_get_error_code($xml_parser)),  
      xml_get_current_line_number($xml_parser)));
  $out=ob_get_contents();
  ob_end_clean();
  xml_parser_free($xml_parser);
  $out = '<ul class="rss">'.$out.'</ul>';

  #  if (strtolower(str_replace("-","",$options['oe'])) == 'euckr')
  if (function_exists('iconv') and strtoupper($DBInfo->charset) != $charset) {
    $new=iconv($charset,$DBInfo->charset,$out);
    if ($new !== false) return $new;
  }

  $rsscache->remove($key.'.lock');
  $rsscache->update($key, $out);

  return $out;
}

?>
