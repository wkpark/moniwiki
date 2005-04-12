<?php
// Copyright 2005 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a cite filter plugin for the MoniWiki
//
// $Id$

function filter_cite($formatter,$value,$options) {
  $cite_rule=array(
    '/J\.\s*Chem\.\s*Phys\.,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/J\.\s*Phys\.\s*Chem\.,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/Chem\.\s*Rev\.\s,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/J\.\s*Am\.\s*Chem\.\s*Soc\.\s,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    );
  $cite_repl=array(
    '[[Cite(JCP \\1,\\2)]]', 
    '[[Cite(JPC \\1,\\2)]]', 
    '[[Cite(ChemRev \\1,\\2)]]', 
    '[[Cite(JACS \\1,\\2)]]', 
    );
  $value=strtr($value,"\t",' ');
  return preg_replace($cite_rule,$cite_repl,$value);
}
// vim:et:sts=4:
?>
