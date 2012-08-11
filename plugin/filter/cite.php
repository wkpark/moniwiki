<?php
// Copyright 2005 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a cite filter plugin for the MoniWiki
//
// $Id: cite.php,v 1.3 2005/12/10 02:46:29 wkpark Exp $

function filter_cite($formatter,$value,$options) {
  $cite_rule=array(
    '/J\.\s*Chem\.\s*Phys\.,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/J\.\s*Phys\.\s*Chem\.,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/J\.\s*Phys\.\s*Chem\.\s*A,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/Chem\.\s*Rev\.\s,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/Chem\.\s*Phys\.\s*Lett\.,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/J\.\s*Am\.\s*Chem\.\s*Soc\.\s,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/Rev\.\s*Mod\.\s*Phys\.\s,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/Phys\.\s*Rev\.\s*Lett\.\s,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/Phys\.\s*Rev\.\s,?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    '/Phys\.\s*Rev\.\s([A-E]{1}),?\s*([0-9]+)\s*,\s*([0-9]+)(?>\s)/',
    );
  $cite_repl=array(
    '[[Cite(JCP \\1,\\2)]]', 
    '[[Cite(JPC \\1,\\2)]]', 
    '[[Cite(JPCA \\1,\\2)]]', 
    '[[Cite(ChemRev \\1,\\2)]]', 
    '[[Cite(CPL \\1,\\2)]]', 
    '[[Cite(JACS \\1,\\2)]]', 
    '[[Cite(RMP \\1,\\2)]]', 
    '[[Cite(PRL \\1,\\2)]]', 
    '[[Cite(PR \\1,\\2)]]', 
    '[[Cite(PR\\1 \\2,\\3)]]', 
    );
  $value=strtr($value,"\t",' ');
  return preg_replace($cite_rule,$cite_repl,$value);
}
// vim:et:sts=4:
?>
