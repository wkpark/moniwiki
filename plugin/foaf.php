<?php
// from http://www.sitepoint.com/examples/phpxml/sitepointcover-oo.php.txt
// Public Domain ?
// and modified for the FOAF.
// See http://moniwiki.sourceforge.net/wiki.php/FoafOnMoniWiki
// $Id: foaf.php,v 1.6 2010/09/07 14:03:08 wkpark Exp $
//
class FoafParser {

   var $insideitem = false;
   var $tag = "";
   var $home = "";
   var $depict = "";
   var $name = "";

   function FoafParser($info) {
     $this->info=$info;
   }

   function startElement($parser, $tagName, $attrs) {
       if (!empty($this->return)) return;
       if ($this->insideitem) {
           $this->tag = $tagName;
       } elseif ($tagName == "FOAF:PERSON") {
           $this->insideitem = true;
       } elseif ($tagName == "PERSON") {
           $this->insideitem = true;
       } elseif ($tagName == "FOAF:PROJECT") {
           $this->insideitem = true;
       }
       if ($tagName == "HOMEPAGE" || $tagName == "FOAF:HOMEPAGE") {
           $this->home = $attrs['RDF:RESOURCE'];
       } else if ($tagName == "DEPICTION" || $tagName == "FOAF:DEPICTION") {
           $this->depict = $attrs['RDF:RESOURCE'];
       }

       if ($tagName == "DC:IDENTIFIER") {
           $this->link = $attrs['RDF:RESOURCE'];
       }
       #print $tagName."\n";
   }

   function endElement($parser, $tagName) {
       if ($tagName == "RDFS:SEEALSO" and $this->insideitem) {
           $this->info['name']=$this->name;
           $this->info['homepage']=$this->home;
           $this->info['picture']=$this->depict;
           $this->insideitem = false;
           $this->return = 1;
       } elseif ($tagName == "FOAF:PROJECT") {
           $this->info['project'][$this->title]=$this->summary;
           $this->project= 0;
       }
   }

   function characterData($parser, $data) {
       if ($this->insideitem) {
           switch ($this->tag) {
               case "FOAF:NAME":
               $this->name .= $data;
               break;
               case "NAME":
               $this->name .= $data;
               break;
               case "RDF:DESCRIPTION":
               $this->project=1;
               break;
           }
       }
       if (!empty($this->project)) {
           switch ($this->tag) {
               case "DC:TITLE":
               $this->title = $data;
               break;
               case "DC:DESCRIPTION":
               $this->summary = $data;
               break;
           }
       }
   }
}

function macro_foaf($formatter,$value) {
  global $DBInfo;

  $xml_parser = xml_parser_create();

  preg_match("/([^,]+)?(?:\s*,\s*)?(.*)?$/",$value,$match);
  if ($match) {
    $value=$match[1];
    $key=_rawurlencode($match[1]);
    $args=explode(",",str_replace(" ","",$match[2]));

    if (in_array ("project", $args)) $options['project']=1;
    if (in_array ("picture", $args)) $options['picture']=1;
    if (in_array ("homepage", $args)) $options['homepage']=1;
    if (in_array ("comment", $args)) $options['comment']=1;
  }
  $info = array(); // XXX
  $rss_parser = new FoafParser($info);
  xml_set_object($xml_parser,$rss_parser);
  xml_set_element_handler($xml_parser, "startElement", "endElement");
  xml_set_character_data_handler($xml_parser, "characterData");

  $cache= new Cache_text("foaf");
  if (!empty($_GET['update']) or !$cache->exists($key)) {
    $fp = @fopen("$value","r");
    if (!$fp)
      return ("[[FOAF(ERR: not a valid URL! $value)]]");

    while ($data = fread($fp, 4096)) $xml_data.=$data;
    fclose($fp);
    $cache->update($key,$xml_data);
  } else 
    $xml_data=$cache->fetch($key);

  $ret= xml_parse($xml_parser, $xml_data);
  if (!$ret)
    return (sprintf("[[FOAF(XML error: %s at line %d)]]",  
      xml_error_string(xml_get_error_code($xml_parser)),  
      xml_get_current_line_number($xml_parser)));

  xml_parser_free($xml_parser);

  ##
  $out="<a href='".$DBInfo->interwiki['FOAF']."$value'><img align='middle' border='0' alt='FOAF:' title='FOAF:' src='$DBInfo->imgs_url_interwiki"."foaf-16.png'>";

  if ($options['homepage']) $out.="</a><a href='$info[homepage]'>$info[name]</a>";
  else $out.="$info[name]</a>";

  $br="&nbsp;";
  if ($options['picture']) {
    $out.="<br /><img src='$info[picture]' />";
    $br="<br />";
  }
  if ($options['homepage']) $out.="$br<a href='$info[homepage]'>$info[homepage]</a>";

  if (strtoupper($DBInfo->charset) != 'UTF-8')
    $out=iconv('utf-8',$DBInfo->charset,$out);

  return $out;
}

?>
