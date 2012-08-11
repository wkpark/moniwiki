<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample whois plugin for the MoniWiki
// $Id: whois.php,v 1.3 2010/09/30 05:57:48 wkpark Exp $

function do_whois($formatter,$options) {
  $query=$options['q'];
  $whois_servers=array("whois.nic.or.kr"=>'euc-kr',"whois.internic.net"=>'iso-8859-1');

#$whois_servers_full = array(
#"kr" => "whois.nic.or.kr",
#"internic" => "whois.internic.net",
#"com" => "whois.networksolutions.com",
#"net" => "whois.networksolutions.com",
#"org" => "whois.networksolutions.com",
#"ac" => "whois.nic.ac",
#"al" => "whois.ripe.net",
#"am" => "whois.amnic.net",
#"as" => "whois.nic.as",
#"at" => "whois.nic.at",
#"au" => "whois.aunic.net",
#"az" => "whois.ripe.net",
#"ba" => "whois.ripe.net",
#"be" => "whois.dns.be",
#"bg" => "whois.ripe.net",
#"br" => "whois.registro.br",
#"by" => "whois.ripe.net",
#"ca" => "whois.cira.ca",
#"cc" => "whois.nic.cc",
#"ch" => "whois.nic.ch",
#"ck" => "whois.ck-nic.org.ck",
#"cn" => "whois.cnnic.net.cn",
#"cx" => "whois.nic.cx",
#"cy" => "whois.ripe.net",
#"cz" => "whois.nic.cz",
#"de" => "whois.denic.de",
#"dk" => "whois.dk-hostmaster.dk",
#"dz" => "whois.ripe.net",
#"edu" => "rs.internic.net",
#"ee" => "whois.ripe.net",
#"eg" => "whois.ripe.net",
#"fi" => "whois.ripe.net",
#"fj" => "whois.usp.ac.fj",
#"fo" => "whois.ripe.net",
#"fr" => "whois.nic.fr",
#"gb" => "whois.ripe.net",
#"ge" => "whois.ripe.net",
#"gov" => "whois.nic.gov",
#"gr" => "whois.ripe.net",
#"gs" => "whois.adamsnames.tc",
#"hk" => "whois.hknic.net.hk",
#"hm" => "whois.registry.hm",
#"hr" => "whois.ripe.net",
#"hu" => "whois.ripe.net",
#"id" => "whois.idnic.net.id",
#"ie" => "whois.domainregistry.ie",
#"int" => "whois.isi.edu",
#"il" => "whois.ripe.net",
#"is" => "whois.isnet.is",
#"it" => "whois.nic.it",
#"jp" => "whois.nic.ad.jp",
#"ke" => "whois.rg.net",
#"kg" => "whois.domain.kg",
#"kz" => "whois.domain.kz",
#"li" => "whois.nic.li",
#"lk" => "whois.nic.lk",
#"lt" => "whois.ripe.net",
#"lu" => "whois.ripe.net",
#"lv" => "whois.ripe.net",
#"ma" => "whois.ripe.net",
#"md" => "whois.ripe.net",
#"mil" => "whois.nic.mil",
#"mk" => "whois.ripe.net",
#"ms" => "whois.adamsnames.tc",
#"mt" => "whois.ripe.net",
#"mx" => "whois.nic.mx",
#"nl" => "whois.domain-registry.nl",
#"no" => "whois.norid.no",
#"nu" => "whois.nic.nu",
#"nz" => "whois.domainz.net.nz",
#"pl" => "whois.ripe.net",
#"pt" => "whois.ripe.net",
#"ro" => "whois.ripe.net",
#"ru" => "whois.ripn.ru",
#"se" => "whois.nic-se.se",
#"sg" => "whois.nic.net.sg",
#"si" => "whois.ripe.net",
#"sh" => "whois.nic.sh",
#"sk" => "whois.ripe.net",
#"sm" => "whois.ripe.net",
#"st" => "whois.nic.st",
#"su" => "whois.ripe.net",
#"tc" => "whois.adamsnames.tc",
#"tf" => "whois.adamsnames.tc",
#"tj" => "whois.nic.tj",
#"th" => "whois.thnic.net",
#"tn" => "whois.ripe.net",
#"to" => "whois.tonic.to",
#"tr" => "whois.ripe.net",
#"tw" => "whois.twnic.net",
#"ua" => "whois.ripe.net",
#"uk" => "whois.nic.uk",
#"us" => "whois.isi.edu",
#"va" => "whois.ripe.net",
#"vg" => "whois.adamsnames.tc",
#"ws" => "whois.nic.ws",
#"yu" => "whois.ripe.net",
#"za" => "whois.frd.ac.za");

  if(!strlen($query)) return '';

  $arg= rtrim($query)."\n";

  $result="";
  foreach ($whois_servers as $server=>$charset) {
    $fp= fsockopen($server,43);
    if(!$fp) {
      $error=sprintf(_("Could not connect to %s server"),$key);
      return $error;
    } else {
      fputs($fp,$arg);
      while(!feof($fp)) {
        $buf=fgets($fp,1024);
        if (!preg_match("/^No match/i",$buf)) $result.=$buf;
        else {
          $result.=$buf;
          $notfound=1;
          $result.="----\n";
          break;
        }
      }
      fclose($fp);
    }
    if (!$notfound) break;
  }
  if (!empty($result) and function_exists('iconv')) {
    if (strtolower($DBInfo->charset) != $charset) {
      $tmp = iconv('EUC-KR', $DBInfo->charset, $result);
      if (!empty($tmp)) $result = $tmp;
    }
  }
  $out ="=== ".sprintf(_("Whois search result for %s"), $query)." ===\n";
  $out.="{{{\n$server\n$result\n}}}";
  $out.= "hostname : [http://ws.arin.net/cgi-bin/whois.pl?queryinput=$query ".gethostbyaddr($query)."]";
  $formatter->get_javascripts();
  $formatter->send_page($out);
  return;
}

function macro_whois($formatter,$value='') {
  return <<<EOF
<form method='get'>
<input type="hidden" name="action" value="whois" />
<input type="text" name="q" size="15" /><input type="submit" value="Query" />
</form>
EOF;
}
?>
