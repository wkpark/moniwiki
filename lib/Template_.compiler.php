<?php
// Copyright 2008-2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// A modified version of the Template_ for MoniWiki
//
// Date: 2008-03-28
// Name: A modified Template_ for MoniWiki
// Description: Template_ module (division syntax is disabled)
// URL: MoniWiki:Template_
// Version: $Revision$
// Depend: 1.1.3
// License: LGPL
//
// Usage: please see http://www.xtac.net
//

/*---------------------------------------------------------------------------

  Program  : Template_
  Version  : 2.2.7
  Date     : 2012-07-20
  Author   : Hyeong-Gil Park
  Homepage : http://www.xtac.net
  License  : LGPL (Freeware)

  Idea of "PHP document templating system" is from "FastTemplate".
  Idea of "compiling PHP template and template plugin" is from "Smarty".
  Idea of "caching" is from "Smarty, PEAR Cache, and CachedFastTemplate".

  Special thanks to Seung-Min Kwon, Jun-Sung Lee, Jin-Wook Cho,
  Soo-Kyeong Hong, Yo-Han Kim, Weon-Soon Lee, Jae-Gyun Yu, Sang-Wook Kang,
  Jae-Sik Kim, Myung-Soo Kim, Jang-Sik Kim, Sam-Goo Lee, Yo-Han Yang,
  Yeong-Gyu Jeon, Byeong-Hoon Kang, and Neotec(Ltd)
  for good suggestion and feedback.

 ----------------------------------------------------------------------------

  Template_ : PHP document templating system
  Copyright (C) 2003-2012 Hyeong-Gil Park

  This library is free software; you can redistribute it and/or
  modify it under the terms of the GNU Library General Public
  License as published by the Free Software Foundation; either
  version 2 of the License, or (at your option) any later version.

  This library is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
  Library General Public License for more details.

  - http://www.gnu.org/copyleft/lgpl.html

  ---------------------------------------------------------------------------

  FastTemplate
    - http://www.thewebmasters.net/
  Smarty
    - http://smarty.php.net/
  Beyond Template Engine (using PHP as template language)
    - http://www.sitepoint.com/article/1218/
  BearTemplate
    - http://template.ze.to/
  Phemplate
    - http://pukomuko.esu.lt/phemplate/
  ASP.NET
    - http://www.asp.net/Tutorials/quickstart.aspx
  JSTL
    - http://java.sun.com/developer/technicalArticles/javaserverpages/faster/
  XSLT
    - http://www.w3.org/TR/xslt
  Velocity
    - http://jakarta.apache.org/velocity/user-guide.html

  ---------------------------------------------------------------------------*/

define('__TEMPLATE_UNDERSCORE_VER__','2.2.7-mw');

class Template_Compiler_
{
	function _compile_template($tpl, $source, $params=array())
	{
		$this->compile_dir   =$tpl->compile_dir;
		$this->compile_ext   =$tpl->compile_ext;
		$this->permission    =$tpl->permission or $this->permission = 0777;
		$this->prefilter     =$tpl->prefilter;
		$this->postfilter    =$tpl->postfilter;
		$this->prefilters    =array();
		$this->postfilters   =array();
		$this->safe_mode     =$tpl->safe_mode;
		$this->safe_mode_ini ='config/safe_mode.php';
		$this->auto_constant =empty($tpl->auto_constant) ? false : $tpl->auto_constant;
		$this->tpl_path      =$tpl_path;
		$this->params        =$params;
		$this->data_dir      =$tpl->data_dir;
		$this->plugin_dir    =$tpl->plugin_dir;
		$this->plugins       =array();
		$this->func_plugins  =array();
		$this->obj_plugins   =array();
		$this->func_list     =array(''=>array());
		$this->obj_list      =array(''=>array());
		$this->method_list   =array();
		$this->rsv_words     =array('index_', 'size_', 'key_', 'value_');
		$this->key_words     =array('true','false','null');
		$this->auto_globals  =array('_SERVER','_ENV','_COOKIE','_GET','_POST','_FILES','_REQUEST','_SESSION');
		$this->constants     =array_keys(get_defined_constants());
		$this->quoted_str	 ='(?:"(?:\\\\.|[^"])*")|(?:\'(?:\\\\.|[^\'])*\')';
		$this->on_ms		 =substr(__FILE__,0,1)!=='/';
		$functions           =get_defined_functions();
		$this->all_functions =array_merge(
			$functions['internal'],
			$functions['user'],
			array('isset','empty','eval','list','array','include','require','include_once','require_once')
		);


	// make compile directory

		if ($this->on_ms) {
			$cpl_base =  preg_replace('@\\\\+@', '/', $cpl_base);
			$this->compile_dir =  preg_replace('@\\\\+@', '/', $this->compile_dir);
		}

		$cpl_path	= $cpl_base.'.'.$this->compile_ext;	// absolute or relative path

		if (!@is_file($cpl_path)) {

			$cpl_rel_path	= substr($cpl_path, strlen($this->compile_dir)+1);
			$dirs = explode('/', $cpl_rel_path);
			
			$path = $this->compile_dir;
			$once_checked = false;
			
			for ($i=0, $s = count($dirs)-1; $i<$s; $i++) {
				
				$path .= '/'.$dirs[$i];
				
				if ($once_checked or !is_dir($path) and $once_checked=true) {

					if (false === mkdir($path, $this->permission)) {
						$this->report('Error #1', 'cannot create compile directory <b>'.$path.'</b>');
						$this->exit_();
					}
					if (!$this->on_ms) {
						@chmod($path, $this->permission);
					}
				}
			}
		}

		$this->register_plugins_all();

	// get safe mode functions
		if ($this->safe_mode) {
			$safe_list_file = $this->safe_mode_ini;
			if (@is_file($safe_list_file)) {
				$fp=fopen($safe_list_file, 'rb');
				$fc=fread($fp, filesize($safe_list_file));
				fclose($fp);
				$this->safe_mode_functions=preg_split('/\s+/', trim(strtolower(preg_replace('/;[^\n\r]*/','',$fc))));
			} else {
				$this->report('Warning #1', 'safe mode : cannot find safe function list file <b>'.$safe_list_file.'</b>', false, false);
			}
		}

	// get template
		if (empty($source) and $params['path'] and file_exists($params['path'])) {
			$source = '';
			if ($source_size = filesize($params['path'])) {
                       		$fp=fopen($params['path'],'rb');
                       		$source=fread($fp,filesize($params['path']));
                       		fclose($fp);
			}
               	}
	
	// remove UTF-8 BOM

		$source = preg_replace('/^\xEF\xBB\xBF/', '', $source);

	// find that php version is greater than or equal to 5.4

		$gt_than_or_eq_to_5_4 = defined('PHP_MAJOR_VERSION') and  5.4 <= (float)(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION);

	// disable php tag
		if ($this->safe_mode) {
			if (ini_get('short_open_tag')) $safe_map['/<\?/']='&lt;?';
			elseif ($gt_than_or_eq_to_5_4) $safe_map['/<\?(php|=)/i']='&lt;?$1';
			else $safe_map['/<\?(php)/i']='&lt;?$1';
			$safe_map['/(<script\s+language\s*=\s*)("php"|\'php\'|php)(\s*>)/i']='$1"SERVER-SIDE-SCRIPT-DISABLED"$3';
			if (ini_get('asp_tags')) $safe_map['/<%/']='&lt;?';
			$source=preg_replace(array_keys($safe_map),array_values($safe_map),$source);
		}

	// remove comments and get preprocessor
		$nl_cnt=1;
		$nl_del_sum=0;
		$this->nl_del[0]=0;
		$nl=preg_match('/\r\n|\n|\r/', $source, $match) ? $match[0] : "\r\n";
		$escape_map=array('\\\\'=>'\\', "\\'"=>"'", '\\"'=>'"', '\\n'=>$nl, '\\t'=>"\t", '\\>'=>'>', '\\g'=>'>');
		$split=preg_split('/(<!--{\*|\*}-->|{\*|\*})/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($j=0,$i=0,$s=count($split); $i<$s; $i++) {
			if (!($i%2)) {
				$nl_cnt+=substr_count($split[$i], $nl);
				continue;
			}
			switch ($split[$i]) {
			case'<!--{*':
			case    '{*':
				if (substr($split[$i+1],0,1)=='\\') $split[$i+1]=substr($split[$i+1],1);
				elseif (!$j) $j=$i;
				break;
			case '*}-->':
			case '*}'   :
				if (substr($split[$i-1],-1)==='\\')
					$split[$i-1]=substr($split[$i-1],0,-1);
				elseif ($j) {
					if ($j===1) {
						for ($def_area='',$k=2; $k<$i; $k++) $def_area.=$split[$k];
						preg_match_all('@(?:(?:^|\r\n|\n|\r)[ \t]*)\#(prefilter|postfilter)[ \t]+
							('.$this->quoted_str.'|(?:[^ \t\r\n]+))
						@ix', $def_area, $match, PREG_PATTERN_ORDER);
						for ($k=0,$t=count($match[0]); $k<$t; $k++) {
							if ($this->safe_mode) $match[2][$k]=preg_replace('/<\?(php)/i', '&lt;?$1', $match[2][$k]);
							if ($match[2][$k][0]==="'") $f_string=strtr(substr($match[2][$k],1,-1), $escape_map);
							elseif ($match[2][$k][0]==='"') $f_string=strtr(substr($match[2][$k],1,-1), $escape_map);
							else $f_string=$match[2][$k];
							if (!trim($f_string)) {
								$this->$match[1][$k]='';
							} else {
								$f_split=preg_split('@(?<!\\\\)\|@', $f_string);
								if (!trim($f_split[0])) $this->$match[1][$k].=$f_string;
								elseif (!trim($f_split[count($f_split)-1])) $this->$match[1][$k]=$f_string.$this->$match[1][$k];
								else $this->$match[1][$k]=$f_string;
							}
						}
						preg_match_all('@(?:(?:^|\r\n|\n|\r)[ \t]*)\#define[ \t]+
							('.$this->quoted_str.'|(?:\S+))[ \t]+
							('.$this->quoted_str.'|(?:\S+))
						@ix', $def_area, $match, PREG_PATTERN_ORDER);
						for ($k=0,$t=count($match[0]); $k<$t; $k++) {
							if ($match[1][$k][0]==="'") $key = strtr(substr($match[1][$k],1,-1), $escape_map);
							elseif ($match[1][$k][0]==='"') $key = strtr(substr($match[1][$k],1,-1), $escape_map);
							else $key=strtr($match[1][$k], $escape_map);
							if ($match[2][$k][0]==="'") $val = strtr(substr($match[2][$k],1,-1), $escape_map);
							elseif ($match[2][$k][0]==='"') $val = strtr(substr($match[2][$k],1,-1), $escape_map);
							else $val=strtr($match[2][$k], $escape_map);
							$macro[$key]=$val;
						}
					}
					for ($nl_sub_cnt=0,$k=$j; $k<=$i; $k++) {
						$nl_sub_cnt += substr_count($split[$k], $nl);
						$split[$k]='';
					}
					$split[$j-1]=preg_replace('/(^|\r\n|\n|\r)[ \t]*$/', '$1', $split[$j-1]);
					if (preg_match('/^[ \t]*(\r\n|\n|\r)/', $split[$i+1])) {
						$nl_del_sum++;
						$split[$i+1]=preg_replace('/^[ \t]*(\r\n|\n|\r)/', '', $split[$i+1]);
					}
					$nl_del_sum += $nl_sub_cnt;
					$nl_cnt -= $nl_sub_cnt;
					$this->nl_del[$nl_cnt] = $nl_del_sum;
					$j=0;
				}
			}
		}
		krsort($this->nl_del);
		$source=implode('',$split);
	
	// apply macro
		if (!empty($macro)) $source=strtr($source, $macro);
	
	// apply prefilter
		if (trim($this->prefilter)) $source=$this->_filter($source, 'pre');
	
	// parse template
		$this->_control_stack=array();
		$this->_loop_depth=0;
		$this->_loop_stack=array();
		$this->_loop_info=array();
		$this->_size_info=array();
		$this->_size_prefix='';
		$this->nl_cnt = 1;
		$this->nl = preg_match('/\r\n|\n|\r/', $source, $match) ? $match[0] : "\r\n";
	
		$divnames=array();
		$nl = $this->nl;
		if ($this->safe_mode) {
			$php_tag = '';
		} else {
			$php_tag = '<\?php|(?<!`)\?>';
			if (ini_get('short_open_tag')) $php_tag .= '|<\?(?!`)';
			elseif ($gt_than_or_eq_to_5_4) $php_tag .= '|<\?=';
			if (ini_get('asp_tags'))  $php_tag .= '|<%(?!`)|(?<!`)%>';
			$php_tag .= '|';
			$php_quote_or_comment = '@"(\\\\.|[^"])*"|\'(\\\\.|[^\'])*\'|//[^\r\n]*[\r\n]|/\*.*?\*/@s';
		}
		$this->_split=preg_split('/('.$php_tag.'<!--{(?!`)|(?<!`)}-->|{(?!`)|(?<!`)})/i', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($this->mark_php=0,$mark_tpl=0,$this->_index=0,$s=count($this->_split); $this->_index<$s; $this->_index++) {
			if (!($this->_index % 2)) {
				$this->nl_cnt += substr_count($this->_split[$this->_index], $nl);
				continue;
			}
			switch (strtolower($this->_split[$this->_index])) {
			case'<?php':
			case  '<?=':
			case   '<?':
			case   '<%':
				if (!$this->mark_php) $this->mark_php = $this->_index;
				break;
			case   '?>':
			case   '%>':
				if ($this->mark_php) {
					$phpcode=implode('', array_slice($this->_split, $this->mark_php+1, $this->_index-$this->mark_php-1));
					if (!preg_match('/"|\'/', implode('',preg_split($php_quote_or_comment, $phpcode)))) {
						$this->mark_php=0;
					}
				}
				break;
			case'<!--{':
			case    '{':
				$mark_tpl = $this->_index;
				break;
			case '}-->':
			case '}'   :
				if ($mark_tpl!==$this->_index-2) break;
				if (!$result=$this->_compile_statement($this->_split[$this->_index-1])) break;
				if (is_array($result)) {
					
					// 1:echo, 2:control, 4:include, 8:division, 16:escape
					
					if ($this->mark_php) {
						if ($result[0]===1) {
							$this->_split[$this->_index-1]=substr($result[1], 4);
							$this->_split[$mark_tpl]='';
							$this->_split[$this->_index]='';
						} else {
							$this->report('Error #5', 'template control statement <b>{'.$this->statement.'}</b> in php code is not available', true, true);
						}
					} elseif ($result[0]===8) {
					} elseif ($result[0]===16) {
						$this->_split[$this->_index-1]=$result[1];
					} else {
						if ($result[0]&6) $this->_split[$mark_tpl-1] = preg_replace('/(\r\n|\n|\r)[ \t]+$/', '$1', $this->_split[$mark_tpl-1]);
						if ($result[0]&5 and preg_match('/^[ \t]*(\r\n|\n|\r)/', $this->_split[$this->_index+1])) {
							$this->nl_cnt--;
							$this->_split[$this->_index+1] = preg_replace('/^[ \t]*(\r\n|\n|\r)/', '$1$1', $this->_split[$this->_index+1]);
						}
						if ($this->_size_prefix) {
							$result[1] = $this->_size_prefix . $result[1];
							$this->_size_prefix='';
						}
						$this->_split[$this->_index-1]='<?php '.$result[1].'?>';
						$this->_split[$mark_tpl]='';
						$this->_split[$this->_index]='';
					}
				} elseif ($result === -1) {
					$erlist[]=array(htmlspecialchars($this->_split[$this->_index-1]), $this->nl_cnt);
				} elseif ($result === -2 || $result === -3) {
					if ($result === -2) $this->report('Error #7', 'unexpected directive "<b>/</b>"', true, true);
					elseif ($result === -3) $this->report('Error #8', 'unexpected directive "<b>:</b>"', true, true);
					if (!empty($erlist)) foreach ($erlist as $er) $this->report('Warning #2', '<b>{'.$er[0].'}</b> may be syntax error', true, $er[1]);
					$this->exit_();
				}
			}
		}
		if (!empty($this->_control_stack)) {
			$this->report('Error #9', 'template loop or branch is not properly closed by <b>{/}</b>', true);
			$this->exit_();
		}

		$source=trim(implode('',$this->_split));
		$plugins = $this->_get_function().$this->_get_class();
		$size_of_top_loop = empty($this->_size_info[1]) ? '' : $this->_get_loop_size(1);

		return '<'.'?php '.$params['cache_head'].$nl.$plugins.$size_of_top_loop.'?'.'>'.$nl.$source;
	}

	function register_plugins_all()
	{
	// get plugin file info
		$plugins = array();
		$match = array();
		$mydir=$this->plugin_dir.'/function';
		$d = dir($mydir);
		if (false === $d) {
			$this->report('Error #2', 'cannot access plugin directory <b>'.$this->plugin_dir.'</b>');
			$this->exit_();
		}
		while ($plugin_file = $d->read()) {
			$plugin_path = $mydir .'/'. $plugin_file;
			if (!is_file($plugin_path) || !preg_match('/^(object|function|prefilter|postfilter)?\.?([^.]+)\.php$/i', $plugin_file, $match)) continue;
			$plugin =strtolower($match[2]);
			if ($match[1] === 'object') {
				if (!empty($this->obj_plugins[$plugin])) {
					$this->report('Error #3', 'plugin file <b>object.'.$match[2].'.php</b> is overlapped');
					$this->exit_();
				}
				$this->obj_plugins[$plugin] = $match[2];
			} else {
				switch ($match[1]) {
				case 'function'  : $this->func_plugins[$plugin]=$match[2]; break;
				case 'prefilter' : $this->prefilters[$plugin]  =$match[2]; break;
				case 'postfilter': $this->postfilters[$plugin] =$match[2]; break;
				default          : $this->func_plugins[$plugin]=$match[2]; break;
				}
				if (in_array($plugin, $plugins)) {
					$this->report('Error #4', 'plugin function <b>'.$plugin.'</b> is overlapped');
					$this->exit_();
				}
				$plugins[]=$plugin;
			}
		}
	}

	function _compile_statement($statement)
	{
		$match=array();
		preg_match('/^(\\\\*)\s*(:\?|[=#@?:\/+])?(.*)$/s', $statement, $match);
		$src=preg_split('/('.$this->quoted_str.')/', $match[3], -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i=0;$i<count($src);$i+=2) {
			if (($comment=strpos($src[$i],'//'))!==false) {
				$src[$i]=substr($src[$i], 0, $comment);
				break;
			}
		}
		$src=trim(implode('', array_slice($src, 0, $i+1)));
		$this->statement=htmlspecialchars($statement);
		if ($match[1]) {
			switch ($match[2]) {
			case '#': return preg_match('/^[A-Z_a-z\x7f-\xff][\w\x7f-\xff]*$/',$src) ? array(16, substr($statement,1)) : 0;
			case '+': return !strlen($src)||preg_match('/^[A-Z_a-z\x7f-\xff][\w\x7f-\xff]*$/',$src) ? array(16, substr($statement,1)) : 0;
			case '/': return !strlen($src) ? array(16, substr($statement,1)) : 0;
			case '?': return $this->_compile_branch($src,1,0)!==0 ? array(16, substr($statement,1)) : 0;
			case ':': return !strlen($src)||$this->_compile_branch($src,1,1)!==0 ? array(16, substr($statement,1)) : 0;
			case '' : return $this->_compile_expression($src,1,1)!==0 ? array(16, substr($statement,1)) : 0;
			default : return $this->_compile_expression($src,1,0)!==0 ? array(16, substr($statement,1)) : 0; // = @ :?
			}
		}
		switch ($match[2]) {
		case ''  : return (($xpr=$this->_compile_expression($src,0,1))===0) ? 0 : array(1, 'echo '.$xpr);
		case '=' : return (($xpr=$this->_compile_expression($src,0,0))===0) ? 0 : array(1, 'echo '.$xpr);
		case ':?': return (($xpr=$this->_compile_expression($src,0,0))===0) ? 0 : array(2, '}elseif('.$xpr.'){'); // deprecated
		#case '+' : return !strlen($src)||preg_match('/^[A-Z_a-z\x7f-\xff][\w\x7f-\xff]*$/', $src) ? array(8, $src) : 0;
		case '#' : return preg_match('/^[A-Z_a-z\x7f-\xff][\w\x7f-\xff]*$/',$src) ? array(4, '$this->print_("'.$src.'",$TPL_SCP,1);') : 0;
		case '@' :
			$xpr = preg_match('/^[A-Z_a-z\x7f-\xff][\w\x7f-\xff]*$/', $src) ? '' : $this->_compile_expression($src,0,0);
			if ($xpr===0) {
				return -1;
			}
			$d = ++$this->_loop_depth;
			$this->_control_stack[]='@';
			$this->_loop_info[$d]=array('index'=>$this->_index-1, 'foreach_bit'=>0);
			if ($xpr) {
				$this->_loop_stack[]='*';
				return array(2, 'if(is_array($TPL_R'.$d.'='.$xpr.')&&!empty($TPL_R'.$d.')){');
			}
			if ($d>1 && in_array($src, $this->_loop_stack)) {
				$this->report('Error #11', 'id of nested loop "<b>'.$src.'</b> in <b>{'.$this->statement.'}</b>" cannot be same as parent loop id', true, true);
				$this->exit_();
			}
			$this->_size_info[$d][$src] = 1;
			$this->_loop_stack[]=$src;
			$this->_loop_info[$src]=$d;
			return array(2, 'if($TPL_'.$src.'_'.$d.'){');
		case '?' :
			if (($stt=$this->_compile_branch($src,0,0))===0) return -1;
			$this->_control_stack[]= substr($stt,0,2)==='if' ? '?' : '$';
			return array(2, $stt);
		case ':' :
			if (strlen($src)) {
				if (($stt=$this->_compile_branch($src,0,1))===0) return 0;
				if (empty($this->_control_stack)) return -3;
				switch (array_pop($this->_control_stack)) {
				case '?':
					if (($xpr=$this->_compile_expression($src,0,0))===0) return 0;
					$this->_control_stack[]='?';
					return array(2, '}elseif('.$xpr.'){');
				case '$':
					$this->_control_stack[]='$';
					return array(2, 'break;'.$stt);
				case 'else':
					$this->report('Error #12', 'elseif statement "<b>{'.$this->statement.'}</b>" after else statement "{:}" is not available', true, true);
					$this->_control_stack[]='default';
					$this->exit_();
					break;
				case 'default':
					$this->report('Error #13', 'case statement "<b>{'.$this->statement.'}</b>" after default statement "{:}" is not available', true, true);
					$this->_control_stack[]='default';
					$this->exit_();
					break;
				case 'loopelse':
					$this->report('Error #14', 'elseif statement "<b>{'.$this->statement.'}</b>" after loopelse statement "{:}" is not available', true, true);
					$this->_control_stack[]='loopelse';
					$this->exit_();
					break;
				default : // loop
					$this->report('Error #15', '"<b>{'.$this->statement.'}</b>" is not in proper position', true, true);
					$this->_control_stack[]='@';
					$this->exit_();
				}
			} else {
				if (empty($this->_control_stack)) return -3;
				switch (array_pop($this->_control_stack)) {
				case '?':
					$this->_control_stack[]='else';
					return array(2, '}else{');
				case '$':
					$this->_control_stack[]='default';
					return array(2, 'break;default:');
				case 'else':
					$this->report('Error #16', 'else statement "<b>{'.$this->statement.'}</b>" after else statement "{:}" is not available', true, true);
					$this->_control_stack[]='else';
					$this->exit_();
					break;
				case 'default':
					$this->report('Error #17', 'default statement "<b>{'.$this->statement.'}</b>" after default statement "{:}" is not available', true, true);
					$this->_control_stack[]='default';
					$this->exit_();
					break;
				case 'loopelse':
					$this->report('Error #18', 'else statement "<b>{'.$this->statement.'}</b>" after loopelse statement "{:}" is not available', true, true);
					$this->_control_stack[]='default';
					$this->exit_();
					break;
				default : // loop
					$this->_close_loop();
					$this->_control_stack[]='loopelse';
					return array(2, '}}else{');
				}
			}
		case '/' :
			if (strlen($src)) return 0;
			if (empty($this->_control_stack)) return -2;
			if ('@'===array_pop($this->_control_stack)) {
				$this->_close_loop();
				return array(2,'}}');
			}
			return array(2,'}');
		}
	}
	function _compile_branch($source, $escape=0, $case=0)
	{
		$expression = $source;
		$case_pos=false;
		$split=preg_split('/('.$this->quoted_str.')/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i=0; $i<count($split); $i+=2) {
			if (($case_pos=strpos($split[$i],':'))!==false) break;
		}
		if ($case_pos!==false) {
			$expression = trim(implode('', array_slice($split, 0, $i))).substr($split[$i], 0, $case_pos);
			$added_case = trim(substr($split[$i], $case_pos+1) . (count($split)>$i+1 ? trim(implode('', array_slice($split, $i+1))) : ''));
		}
		$xpr=$this->_compile_expression($expression, $escape, 0);
		if ($xpr===0) return 0;
		if ($case_pos!==false) {
			$added_xpr=$this->_compile_branch($added_case, $escape, 1);
			if ($added_xpr===0) return 0;
			if ($escape) return 1;
			return $case ? 'case '.$xpr.':'.$added_xpr : 'switch('.$xpr.'){'.$added_xpr;
		}
		if ($escape) return 1;
		return $case ? 'case '.$xpr.':' : 'if('.$xpr.'){';
	}
	function _close_loop()
	{
		$loop_id = array_pop($this->_loop_stack);
		$depth = $this->_loop_depth--;
		$info = &$this->_loop_info[$depth];

		// 1: key_, 2: value_, 4: index_, 8: size_
		$_key = $info['foreach_bit']&1 ? '$TPL_K'.$depth.'=>' : '';
		if ($info['foreach_bit']&4) {
			$_idx1='$TPL_I'.$depth.'=-1;';
			$_idx2='$TPL_I'.$depth.'++;';
		} else {
			$_idx1='';
			$_idx2='';
		}
		$_sub_loop_size = empty($this->_size_info[$depth+1]) ? '' : $this->_get_loop_size($depth+1);
		$split = &$this->_split[$info['index']];
		$split = substr($split, 0, -2);
		if ($loop_id==='*') {
			$_size = $info['foreach_bit']&8 ? '$TPL_S'.$depth.'=count($TPL_R'.$depth.');' : '';
			$split.= $_size.$_idx1.'foreach($TPL_R'.$depth.' as '.$_key.'$TPL_V'.$depth.'){'.$_idx2.$_sub_loop_size.'?>';
		} else {
			$split .= $_idx1.'foreach('.$this->_get_loop_array($loop_id, $depth).' as '.$_key.'$TPL_V'.$depth.'){'.$_idx2.$_sub_loop_size.'?>';
		}
		unset($this->_size_info[$depth+1], $this->_loop_info[$depth], $this->_loop_info[$loop_id]);
	}
	function _get_loop_size($depth, $div='')
	{
		$size  = '';
		$array = $div ? $this->_size_info[$div] : $this->_size_info[$depth];
		foreach ($array as $loop_id => $val) {
			if (is_array($val)) {
				// $this->report('Warning #3', '<b>'.$loop_id.'.size_</b> in <b>{'.$val[0].'}</b> has not corresponding loop', true, $val[1]);
				// // For "size_" instead of count().
			}
			$loop_array = $this->_get_loop_array($loop_id, $depth);
			$size .= $this->nl.'$TPL_'.$loop_id.'_'.$depth.'=empty('.$loop_array.')||!is_array('.$loop_array.')?0:count('.$loop_array.');';
		}
		return $size;
	}
	function _get_loop_array($loop_id, $depth)
	{
		if ($depth===1) {
			if ($loop_id[0]==='_') {
				if ($this->safe_mode) {
					$this->report('Error #19', 'safe mode : global variable <b>'.$loop_id.'</b> in <b>{'.$this->statement.'}</b> is not available', true, true);
					$this->exit_();
				}
				return in_array($loop_id, $this->auto_globals) ? '$'.$loop_id : '$GLOBALS["'.substr($loop_id, 1).'"]';
			}
			return '$TPL_VAR["'.$loop_id.'"]';
		}
		return '$TPL_V'.($depth-1).'["'.$loop_id.'"]';
	}
	function _compile_expression($expression, $escape=0, $no_directive=0)
	{
		if (!strlen($expression)) return 0;
		$var_state=array(0,'');					// 0:
		$par_stack=array();
		$func_list=array();
		$this->exp_object =array();
		$this->exp_error  =array();
		$this->exp_loopvar=array();
		$this->_outer_size=array();
		$number_used=0;
		$prev_is_operand=0;
		$prev_is_func=0;
		$m=array();
		for ($xpr='',$i=0; strlen($expression); $expression=substr($expression, strlen($m[0])),$i++) {	// 
			if (!preg_match('/^
				((?:\.\s*)+)
				|(?:([A-Z_a-z\x7f-\xff][\w\x7f-\xff]*)\s*(\[|\.|\(|\-\>)?)
				|(?:(\])\s*(\-\>|\.|\[)?)
				|((?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+\-]?\d+)?)
				|('.$this->quoted_str.')
				|(===|!==|\+\+|--|\+\.|<<|>>|<=|>=|==|!=|&&|\|\||[,+\-*\/%&^~|<>()!])
				|(\s+)
				|(.+)
			/ix', $expression, $m)) return 0;
			if (!empty($m[10])) {	// (.+)
				return 0;
			} elseif ($m[1]) {		// ((?:\.\s*)+)         
				if ($prev_is_operand || $var_state[0]) return 0;
				$prev_is_operand = 1;
				$var_state=array(1,preg_replace('/\s+/','',$m[1]));
			} elseif ($m[2]) {		// ([A-Z_a-z\x7f-\xff][\w\x7f-\xff]*)
				if (empty($m[3])) $m[3]='';		// (\[|\.|\(|\-\>)
				switch ($m[3]) {
				case ''  :
					switch ($var_state[0]) {
					case 0:
						if ($prev_is_operand) return 0;
						$prev_is_operand = 1;
						if (in_array(strtolower($m[2]),$this->key_words) || $this->auto_constant && in_array($m[2], $this->constants)) {
							$xpr.= $m[2];
						} elseif ($m[2]==='this') {
							$xpr.= '$this';
						} elseif ($m[2]==='tpl_') {
							$xpr.= '$TPL_TPL';
						} elseif ($m[2][0]==='_') {
							if ($this->safe_mode) $this->exp_error[]=array(4, $m[2]);
							$xpr.= in_array($m[2], $this->auto_globals) ? '$'.$m[2] : '$GLOBALS["'.substr($m[2],1).'"]';
						} else {
							$xpr.= '$TPL_VAR["'.$m[2].'"]';
						}
						break;
					case 1:
						$xpr.=$this->_compile_array($var_state[1].$m[2], 'stop');
						break;
					case 2:
						$xpr.= $var_state[1]==='obj' ? $m[2] : '["'.$m[2].'"]';
						break;
					}
					$var_state=array(0,'');
					break;
				case '(' :
					$prefix='';
					$self='';
					if ($var_state[0]) {
						if ($var_state[1]!=='obj') return 0;
					} else {
						if ($no_directive) return 0;
						$func = strtolower($m[2]);
						if (!empty($this->func_plugins[$func])) {
							$func_list[$func] = $this->nl_cnt;
						} else {
							if ($this->safe_mode) in_array($func, $this->safe_mode_functions) or $this->exp_error[]=array(5, $m[2]);
							else in_array($func, $this->all_functions) or $this->exp_error[]=array(7, $m[2]);
						}
						if (!in_array(strtolower($m[2]),$this->all_functions)) {
                                                       $prefix='function_';
                                                       $self='$formatter,';
                                        	}

					}
					$prev_is_operand=0;
					$prev_is_func=1;
					$par_stack[]='f';
					$var_state=array(0,'');
					$xpr.=$prefix.$m[2].'('.$self;
					break;
				case '[' :
					switch ($var_state[0]) {
					case 0:
						if ($prev_is_operand) return 0;
						$xpr.=$this->_compile_array($m[2]).'[';
						break;
					case 1:
						$xpr.=$this->_compile_array($var_state[1].$m[2]).'[';
						break;
					case 2:
						$xpr.= $var_state[1]==='obj' ? $m[2].'[' : '["'.$m[2].'"][';
						break;
					}
					$par_stack[]='[';
					$prev_is_operand=0;
					$prev_is_func=0;
					$var_state=array(0, '');
					break;
				case '.' :
					switch ($var_state[0]) {
					case 0:
						if ($prev_is_operand) return 0;
						$prev_is_operand=1;
						$var_state=array(1, $m[2].'.');
						break;
					case 1:
						$xpr.=$this->_compile_array($var_state[1].$m[2]);
						$var_state=array(2, '');
						break;
					case 2:
						$xpr.= $var_state[1]==='obj' ? $m[2] : '["'.$m[2].'"]';
						break;
					}
					break;
				case '->':
					switch ($var_state[0]) {
					case 0:
						if ($prev_is_operand) return 0;
						$prev_is_operand = 1;
						if (in_array($m[2], $this->_loop_stack)) {
							$xpr .= '$TPL_V'.$this->_loop_info[$m[2]].'->';
							// need not check safe_mode.
						} elseif ($m[2]==='this') {
							if ($this->safe_mode) $this->exp_error[]=array(6, $m[2]);
							$xpr .= '$this->';
						} elseif ($m[2][0]==='_') {
							if ($this->safe_mode) $this->exp_error[]=array(4, $m[2]);
							$xpr .= '$GLOBALS["'.substr($m[2],1).'"]->';
						} else {
							$xpr .= '$TPL_VAR["'.$m[2].'"]->';
						}
						break;
					case 1:
						$xpr.=$this->_compile_array($var_state[1].$m[2], 'obj').'->';
						break;
					case 2:
						$xpr.=($var_state[1]==='obj' ? $m[2] : '["'.$m[2].'"]').'->';
						break;
					}
					$var_state=array(2,'obj');
					break;
				}
			} elseif ($m[4]) {	//	(\])
				if ($var_state[0] || !$prev_is_operand || empty($par_stack) || array_pop($par_stack)!=='[') return 0;
				if (empty($m[5])) $m[5]='';
				switch ($m[5]) {
				case ''  :
					$xpr.=']';
					break;
				case '->':
					$xpr.=']->';
					$var_state=array(2,'obj');
					break;
				case '.' :
					$xpr.=']';
					$var_state=array(2,'');
					break;
				case '[' :
					$xpr.='][';
					$par_stack[]='[';
					$prev_is_operand=0;
					$prev_is_func=0;
					break;
				}
			} elseif ($m[6]||$m[6]==='0') {			// ((?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+\-]?\d+)?)
				if ($prev_is_operand) return 0;
				$xpr .= ' '.$m[6];
				$prev_is_operand = 1;
				$number_used = 1;
			} elseif ($m[7]) {
				if ($prev_is_operand||preg_match('/ [+\-]$/',$xpr)) return 0;
				$xpr=preg_replace('/\+$/','.',$xpr) . strtr($m[7],array('``'=>'`', '{`'=>'{', '`}'=>'}', '<?`'=>'<?', '`?>'=>'?>', '<%`'=>'<%', '`%>'=>'%>'));
				$prev_is_operand = 1;
			} elseif ($m[8]) {
				if ($var_state[0]) return 0;
				switch ($m[8]) {
				case'++':
				case'--':
					return 0;
				case ',':
					if (!$prev_is_operand || empty($par_stack) || $par_stack[count($par_stack)-1]!=='f') return 0;
					$prev_is_operand=0;
					break;
				case '(':
					if ($prev_is_operand) return 0;
					$par_stack[]='p';
					break;
				case ')':
					$xpr=rtrim($xpr,',');
					if (!$prev_is_operand && !$prev_is_func || empty($par_stack) || array_pop($par_stack)==='[') return 0;
					$prev_is_operand=1;
					break;
				case '!':
				case '~':
					if ($prev_is_operand) return 0;
					break;
				case '-':
					if ($prev_is_operand) $prev_is_operand=0;
					else $m[8]=' -';
					break;
				case '+':
					if (preg_match('/["\']$/', $xpr)) {
						$m[8]='.';
						$prev_is_operand=0;
					} else {
						if ($prev_is_operand) $prev_is_operand=0;
						else $m[8]=' +';
					}
					break;
				case '+.':
					$m[8]='.';
				default	:
					if (!$prev_is_operand) return 0;
					$prev_is_operand=0;
				}
				$xpr .= $m[8];
				$prev_is_func=0;
			} else {
				continue;
			}
		}
		if (!empty($par_stack) || !$prev_is_operand || $var_state[0] || $no_directive && $i===1 && $number_used) return 0;
		if ($escape) return 1;
		if (!empty($this->exp_error)) {
			foreach ($this->exp_error as $error) {
				switch ($error[0]) {
				case 1:
					$this->report('Error #20', '<b>p.</b> in <b>{'.$this->statement.'}</b> is reserved variable for accessing object plugins',true,true);
					$this->exit_();
					break;
				case 2:
					$this->report('Error #21', '<b>c.</b> in <b>{'.$this->statement.'}</b> is reserved variable for accessing constants',true,true);
					$this->exit_();
					break;
				case 3:
					$this->report('Warning #4', 'loop var <b>'.$error[1].'</b> in <b>{'.$this->statement.'}</b> is not in proper loop',true,true);
					break;
				case 4:
					$this->report('Error #22', 'safe mode : global variable <b>'.$error[1].'</b> in <b>{'.$this->statement.'}</b> is not available',true,true);
					$this->exit_();
				case 5:
					$this->report('Error #23', 'safe mode : function <b>'.$error[1].'()</b> in <b>{'.$this->statement.'}</b> is not registered',true,true); 
					$this->exit_();
				case 6:
					$this->report('Error #24', 'safe mode : <b>this-></b> in <b>{'.$this->statement.'}</b> is not available',true,true);
					$this->exit_();
				case 7:
					$this->report('Error #25', 'call to undefined function <b>'.$error[1].'</b> in <b>{'.$this->statement.'}</b>',true,true);
					$this->exit_();
				case 8:
					$this->report('Error #26', 'cannot find plugin file for object <b>'.$error[1].'</b> in <b>{'.$this->statement.'}</b>',true,true);
					$this->exit_();
				}
			}
			return 0;
		}
		foreach ($this->_outer_size as $loop_id=>$depth) {
			if (empty($this->_size_info[$depth][$loop_id])) {
				$this->_size_info[$depth][$loop_id] = array($this->statement, $this->nl_cnt);
			}
		}
		foreach ($this->exp_loopvar as $depth=>$set) {
			$this->_loop_info[$depth]['foreach_bit'] |= $set;
		}
		if ($func_list) {
			$this->_set_function($func_list);
		}
		if ($this->exp_object) {
			$this->_set_class($this->exp_object);
		}
		return $xpr;
	}
	function _compile_array($subject, $end='')
	{
		if (preg_match('/^\.+/', $subject, $match)) { // ..loop
			$depth=strlen($match[0]);
			if ($this->_loop_depth < $depth) {
				$this->exp_error[]=array(3, $subject);
				return '';
			}
			$id=$this->_loop_stack[$depth-1];
			$var=substr($subject, $depth);
			$el='["'.$var.'"]';
		} else {
			if ($D=strpos($subject,'.')) { // id.var
				$id=substr($subject,0,$D);
				$var=substr($subject,$D+1);
				$el='["'.$var.'"]';
				if ($id==='p' || $id==='P') { // p.object
					if (!$end) {
						$this->exp_error[]=array(1, $subject);
						return '';
					}
					$obj = strtolower($var);
					if (!empty($this->obj_plugins[$obj])) {
						$this->exp_object[$obj] = $this->nl_cnt;
					} else {
						$this->exp_error[]=array(8, $subject);
					}
					return '$TPL_'.$obj.'_OBJ';
				} elseif ($id==='c' || $id==='C') { // c.constant
					if ($end!=='stop') $this->exp_error[]=array(2, $subject);
					return $var;
				} elseif (in_array($id, $this->_loop_stack)) { // loop.var
					$depth=$this->_loop_info[$id];
				} elseif ($var==='size_') { // outside.size_
					if ($end!=='stop') $this->exp_error[]=array(-1,$subject);
					$depth = $this->_loop_depth+1;
					$this->_outer_size[$id] = $depth;
					return '$TPL_'.$id.'_'.$depth;
				} elseif (in_array($var, $this->rsv_words)) { // array.key_ , value_ , index_
					$this->exp_error[]=array(3, $subject);
					return '';
				}
			} else { // id[
				$id=$subject;
				$var='';
				$el='';
				if (in_array($id, $this->_loop_stack)) $depth=$this->_loop_info[$id];
			}
			if (empty($depth)) { // not loop
				if ($id[0]==='_') {
					if ($id=='_config') return '$Config'.$el;
					if ($this->safe_mode) {
						$this->exp_error[]=array(4, $subject);
						return 0;
					}
					if (in_array($id, $this->auto_globals)) return '$'.$id.$el;
					return '$GLOBALS["'.substr($id,1).'"]'.$el;
				}
				return '$TPL_VAR["'.$id.'"]'.$el;
			}
		}
		switch ($var) {
		case 'key_':
			if ($end!=='stop') $this->exp_error[]=array(-1,$subject);
			elseif (isset($this->exp_loopvar[$depth])) $this->exp_loopvar[$depth] |= 1;
			else $this->exp_loopvar[$depth] = 1;
			return '$TPL_K'.$depth;
		case 'value_':
			if (isset($this->exp_loopvar[$depth])) $this->exp_loopvar[$depth] |= 2;
			else $this->exp_loopvar[$depth] = 2;
			return '$TPL_V'.$depth;
		case 'index_':
			if ($end!=='stop') $this->exp_error[]=array(-1,$subject);
			elseif (isset($this->exp_loopvar[$depth])) $this->exp_loopvar[$depth] |= 4;
			else $this->exp_loopvar[$depth] = 4;
			return '$TPL_I'.$depth;
		case 'size_':
			if ($end!=='stop') $this->exp_error[]=array(-1,$subject);
			elseif (isset($this->exp_loopvar[$depth])) $this->exp_loopvar[$depth] |= 8;
			else $this->exp_loopvar[$depth] = 8;
			return $id==='*' ? '$TPL_S'.$depth : '$TPL_'.$id.'_'.$depth;
		default :
			return '$TPL_V'.$depth.$el;
		}
	}
	function _set_function($func_list, $divname='')
	{
		$prev_list=array_keys($this->func_list[$divname]);
		foreach ($func_list as $func => $line) {
			if (!in_array($func, $prev_list)) $this->func_list[$divname][$func]=$line;
		}
	}
	function _get_function($divname='')
	{
		$functions=array();
		foreach ($this->func_list[$divname] as $func => $line) {
			$func_name = $this->func_plugins[$func];
			if (!function_exists($func)) {
				$func_path=$this->plugin_dir.'/function/'.$func_name.'.php';
				if (false===include $func_path) {
					$this->report('Error #27', 'error in plugin <b>'.$func_path.'</b>', true, $line);
					$this->exit_();
				} elseif (!function_exists('function_'.$func)) {
					$this->report('Error #28', 'cannot find function <b>'.$func.'()</b> in plugin <b>'.$func_path.'</b>', true, $line);
					$this->exit_();
				}
			}
			$functions[]='"'.$func_name.'"';
		}
		return $functions ? ' $formatter->include_functions('.implode(',',$functions).');' : '';
		#return $functions ? ' $this->include_('.implode(',',$functions).');' : '';
	}
	function _set_class($obj_list, $divname='')
	{
		$prev_list=array_keys($this->obj_list[$divname]);
		foreach ($obj_list as $obj => $line) {
			if (!in_array($obj, $prev_list)) $this->obj_list[$divname][$obj] = $line;
		}
	}
	function _get_class($divname='')
	{
		$init_obj = '';
		foreach ($this->obj_list[$divname] as $obj => $line) {
			$obj_name=$this->obj_plugins[$obj];
			$class = 'tpl_object_'.$obj_name;
			if (!class_exists($class, false)) {
				$class_path = $this->plugin_dir.'/object.'.$obj_name.'.php';
				if (false===include $class_path) {
					$this->report('Error #27', 'error in plugin <b>'.$class_path.'</b>', true, $line);
					$this->exit_();
				} elseif (!class_exists($class, false)) {
					$this->report('Error #29', 'cannot find class <b>'.$class.'()</b> in plugin <b>'.$class_path.'</b>', true, $line);
					$this->exit_();
				}
			}
			$init_obj .= '$TPL_'.$obj.'_OBJ=$this->new_("'.$obj_name.'");';
		}
		return $init_obj;
	}
	function _filter($source, $type)
	{
		$func_split=preg_split('/\s*(?<!\\\\)\|\s*/', trim($this->{$type.'filter'}));
		$func_sequence=array();
		for ($i=0,$s=count($func_split); $i<$s; $i++) if ($func_split[$i]) $func_sequence[]=str_replace('\\|', '|', $func_split[$i]);
		if (!empty($func_sequence)) {
			for ($i=0,$s=count($func_sequence); $i<$s; $i++) {
				$func_args=preg_split('/\s*(?<!\\\\)\&\s*/', $func_sequence[$i]);
				for ($j=1,$k=count($func_args); $j<$k; $j++) {
					$func_args[$j]=str_replace('\\&', '&', trim($func_args[$j]));
				}
				$func = strtolower(array_shift($func_args));
				$func_name   = $this->{$type.'filters'}[$func];
				array_unshift($func_args, $source, $this);
				$func_file = $this->plugin_dir.'/'.$type.'filter.'.$func_name.'.php';
				if (!in_array($func, $this->{$type.'filters'})) {
					$this->report('Error #30', 'cannot find '.$type.'filter file <b>'.$func_file.'</b>', true);
					$this->exit_();
				}
				if (!function_exists($func_name)) {
					if (false===include_once $func_file) {
						$this->report('Error #31', 'error in '.$type.'filter <b>'.$func_file.'</b>', true);
						$this->exit_();
					} elseif (!function_exists($func_name)) {
						$this->report('Error #32', 'filter function <b>'.$func_name.'()</b> is not found in <b>'.$func_file.'</b>');
						$this->exit_();
					}
				}
				$source=call_user_func_array($func_name, $func_args);
			}
		}
		return $source;
	}
	function report($type, $msg, $file=false, $line=false)
	{
		$report = "<br />\n".'<span style="font:12px tahoma,arial;color:#0071DC;background:white">Template_ Compiler '.$type.': '.$msg;
		if ($file) $report.=' in <b>'.(is_string($file)?$file:$this->params['path']).'</b>';
		if ($line) {
			$line=is_int($line)?$line:$this->nl_cnt;
			foreach ($this->nl_del as $key=>$val) if ($key<=$line) break;
			$report.=' on line <b>'.($line+$val).'</b>';
		}
		echo $report."</span><br />\n";
	}
	function exit_()
	{
		// Write code for printing out when compile fails.
		// e.g. echo "<input type=button value='go back' onClick='history.go(-1)'>";
		exit;
	}
}
?>
