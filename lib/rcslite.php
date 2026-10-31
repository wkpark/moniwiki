<?php
# PHP RcsLite module
# Copyright (c) 2004 Won-kyu Park <wkpark at kldp.org>
#
# this module is ported from the RcsLite.pm of the TWiki by wkpark.
# $Id$

# Original notice:
#
# Module of TWiki Collaboration Platform, http://TWiki.org/
#
# Copyright (C) 2002 John Talintyre, john.talintyre@btinternet.com
#
# For licensing info read license.txt file in the TWiki root.
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details, published at 
# http://www.gnu.org/copyleft/gpl.html
#
#
# Functions used by both Rcs and RcsFile - they both inherit from this Class
#
# Simple interface to RCS.  Doesn't support:
#    branches
#    locking
#
# This modules doesn't know anything about the content of the topic e.g.
# it doesn't know about the meta data.
#
# FIXME:
#  - need to tidy up dealing with \n for differences
#  - still have difficulty on line ending at end of sequences, consequence of
#    doing a line based diff
#  - most serious is when having multiple line ends on one seq but not other -
#    this needs fixing
#  - cleaner dealing with errors/warnings

define('RCSLITE_FORCE', 1);
define('RCSLITE_QUICK', 2);
#[\AllowDynamicProperties]
Class RcsLite {
    var $rcs_dir='RCS';
    var $rcs_user='root';
    function __construct($rcs_dir='RCS',$rcs_user='root')
    {
        #$class = ref($proto) || $proto;
        #bless( $this, $class );
        #$this->_init();

        $this->rcs_dir=$rcs_dir ? $rcs_dir:'RCS';
        $this->rcs_user=$rcs_user ? $rcs_user:'root';
        $this->_head = 0;
        $this->_next = array();
    }
  
    function _trace($text)
    {
    #   TWiki::writeDebug( $text );
    }

    function _readString($fp)
    {
        $string='';
        while (($part = stream_get_line($fp, 65536, '@')) !== false) {
            $string .= $part;
            if (strlen($part) === 65536) continue;
            $ch = fgetc($fp);
            if ($ch != '@') return array($string, $ch);
            $string .= '@';
        }
        return array($string, false);
    }

    function _readTo($fp, $char)
    {
        $ch = null;
        $buf = '';
        $string = '';
        $state = '';

        while( false !== ($ch = fgetc($fp)) )
            if ( !ctype_space($ch) ) break;

        do {
/*
            if ($state) {
                if ($ch == '@') {
                    $ch=fgetc($fp);
                    if ($ch == '@') {
                        $string.=$ch;
                        continue;
                    } else {
                        $state='';
                        if ($char == '@') break;
                    }
                } else {
                    $string.=$ch;
                    continue;
                }
            } else if( $ch == '@' ) {
                $state = 1;
                continue;
            }
//*/
//*
            if( $ch == '@' ) {
                list($str,$ch)=$this->_readString($fp);
                $string.=$str;
                if ($char == '@') break;
            }
//*/
            $buf .= $ch;
            if( $ch == $char ) break;
        } while ( false !== ($ch = fgetc($fp)) );

        $buf = preg_replace('/\s+/',' ',$buf);
        if ($buf == '') $buf=null;
        return array( $buf, $string );
    }
    
    # Called by routines that must make sure RCS file has been read in
    function _ensureProcessed()
    {
        if( empty($this->_where) ) {
            $this->_process();
        }
    }
    
    function _warn($message)
    {
        print "Warning: $message\n";
    }
    
    # Read in the whole RCS file
    function _process($file='', $limit = 0, $flag = 0)
    {
        if (!empty($this->_where) && !($flag & RCSLITE_FORCE) &&
            (!$file || $file === $this->filename)) return;

        $this->_head = 0;
        $this->_next = $this->_date = $this->_author = $this->_log = $this->_delta = array();
        $this->_access = $this->_symbols = $this->_description = '';
        unset($this->_strict, $this->_comment, $this->_expand);

        if ($file) {
            $this->filename=$file;
            $dirname=dirname($file);
            $dirname=$dirname ? $dirname:'.';
            $rcsname=basename($file);
            if (substr($rcsname,-2) != ',v')
                $rcsname.=',v';
            if (file_exists($dirname.'/'.$rcsname))
                $this->rcsFile=$dirname.'/'.$rcsname;
            else 
                $this->rcsFile=$dirname.'/'.$this->rcs_dir.'/'.$rcsname;

        }
        $fh = @fopen( $this->rcsFile, 'r');
        if( ! $fh ) {
            //$this->_warn( "Couldn't open file $this->rcsFile" );
            $this->_where = file_exists($this->rcsFile) ? 'unreadable' : 'nofile';
            return;
        }
        $where = 'admin.head';
        $lastWhere = '';
        $going = 1;
        $term = ';';
        $string = '';
        $num = '';
        $headNum = '';
        $date = array();
        $author = array();
        $log = array();
        $text = array();
        $next = array();
        $dnum = '';
        $count = 0;
        while( $going ) {
            list($line, $string) = $this->_readTo( $fh, $term );
            if( is_null($line) ) break;
            #print "\"$where -- $line\"\n";
          
            $lastWhere = $where;
            if( $where == 'admin.head' ) {
                if( preg_match('/^head\s+([0-9]+\.[0-9]+);$/',$line, $match) ) {
                    $headNum = $match[1];
                    $where = 'admin.access'; # Don't support branch
                } else {
                    break;
                }
            } else if ( $where == 'admin.access' ) {
                if( preg_match('/^access\s*(.*);$/',$line, $match) ) {
                    $where = 'admin.symbols';
                    $this->_access = $match[1];
                } else {
                    break;
                }
            } else if( $where == 'admin.symbols' ) {
                if( preg_match('/^symbols(.*);$/',$line, $match) ) {
                    $where = 'admin.locks';
                    $this->_symbols = $match[1];
                } else {
                    break;
                }
            } else if( $where == 'admin.locks' ) {
                if( preg_match('/^locks.*;$/', $line, $match) ) {
                    $where = 'admin.postLocks';
                } else {
                    break;
                }
            } else if( $where == 'admin.postLocks' && preg_match('/^strict\s*;/', $line) ) {
                $this->_strict = true;
                $where = 'admin.postStrict';
            } else if( $where == 'admin.postStrict' &&
                    preg_match('/^comment\s.*$/', $line) ) {
                $where = 'admin.postComment';
                $this->_comment = $string;
            } else if( ( $where == 'admin.postStrict' || $where == 'admin.postComment' )  &&
                    preg_match('/^expand\s/', $line) ) {
                $where = 'admin.postExpand';
                $this->_expand = $string;         
            } else if( $where == 'admin.postStrict' || $where == 'admin.postComment' ||
                $where == 'admin.postLocks' ||
                $where == 'admin.postExpand' || $where == 'delta.date') {
                if( preg_match('/^([0-9]+\.[0-9]+)\s+date\s+(\d\d(\d\d)?(\.\d\d){5}?);$/', $line,$match) ) {
                    $where = 'delta.author';
                    $num = $match[1];
                    $date[$num] = $this->rcsDateToTime($match[2]);
                }
            } else if( $where == 'delta.next' ) {
                if( preg_match('/^next\s+([^;]*);$/',$line,$match) ){
                    $where = 'delta.date';
                    $next[$num] = $match[1];
                    if ($next[$num] == "") {
                        $where = 'desc';
                        $term = '@';
                        if (($flag & RCSLITE_QUICK)) {
                            break;
                        }
                    }
                }
            } else if( $where == 'delta.author' ) {
                if( preg_match('/^author\s+(.*);$/', $line, $match) ) {
                    $author[$num] = $match[1];
                    $where = 'delta.next';
                }
            } else if( $where == 'desc' ) {
                if( preg_match('/desc\s*$/', $line, $match) ) {
                    $this->_description = $string;
                    $where = 'deltatext.log';
                }
            } else if( $where == 'deltatext.log' ) {
                if( preg_match('/^(\d+\.\d+)\s+log\s+$/', $line, $match) ) {
                    $dnum = $match[1];
                    $log[$dnum] = $string;
                    $where = 'deltatext.text';
                }
            } else if( $where == 'deltatext.text' ) {
                if( preg_match('/text\s*$/', $line) ) {
                    $where = 'deltatext.log';
                    $text[$dnum] = $string;
                    $count++;
                    if( $dnum == 1 || $limit > 0 && $count >= $limit) {
                        $where = 'done';
                        break;
                    }
                }
            }
        }
        
        $this->_head = $headNum;
        $this->_author = $author;
        $this->_date = $date;
        $this->_next = $next;
        $this->_log = $log;
        $this->_delta = $text;
        $this->_status = $dnum;
        $this->_where = $where;
        if (!($flag & RCSLITE_QUICK) &&
            (!$headNum || !isset($next[$headNum]) || count($text) !== count($next)))
            $this->_where = 'invalid';
        if ($this->_where !== 'invalid' && !($flag & RCSLITE_QUICK)) {
            $seen = array();
            $rev = $headNum;
            while ($rev !== '') {
                if (isset($seen[$rev]) || !isset($next[$rev], $text[$rev], $log[$rev])) {
                    $this->_where = 'invalid';
                    break;
                }
                $seen[$rev] = true;
                $rev = $next[$rev];
            }
            if (count($seen) !== count($next)) $this->_where = 'invalid';
        }
        
        fclose( $fh );
    }

    # for rlog() function
    function check_delta($selected = null)
    {
        foreach ($this->_next as $rev=>$next) {
            if ($next=="") continue;
            if ($selected !== null && !isset($selected[$rev])) continue;
            $lines=explode("\n",$this->_delta[$next]);
            $add=0;
            $del=0;
            $sz=sizeof($lines);
            for ($i=0;$i<$sz;$i++) {
                if (preg_match('/^([ad])+\d+\s(\d+)$/',$lines[$i],$m)) {
                    if ($m[1] == 'd') {
                        $del+=$m[2];
                    } else {
                        $add+=$m[2];
                        $i+=$m[2];
                    }
                }
            }
            # delta has reversed info:
            $this->_change[$rev]="+$del -$add";
            #print "+$del -$add\n";
        }
    }
    
    function _formatString($str)
    {
        $str = preg_replace('/@/', '@@', $str);
        return '@'.$str.'@';
    }
    
    # Write content of the RCS file
    function _make_rcs()
    {
        # admin
        $headnum=$this->_head;
        $out = "head\t" . $headnum . ";\n";
        $out.= "access" . $this->access() . ";\n";
        $out.= "symbols" . $this->_symbols . ";\n";
        $out.= "locks\n\t$this->rcs_user:$headnum;";
        $out.= !empty($this->_strict) ? " strict;\n" : "\n";
        if (!empty($this->_comment))
            $out.=sprintf("comment\t%s;\n", $this->_formatString($this->comment()));
        if ( isset($this->_expand) ) {
           $out.=sprintf("expand\t@%s@;\n", $this->_expand );
        }
        
        $out.="\n";
        
        # delta
        for($n=$headnum; $n != "";) {
           $out.=
              sprintf("\n%s\ndate\t%s;\tauthor %s;\tstate Exp;\nbranches;\n",
                 $n, gmdate("Y.m.d.H.i.s",$this->_date[$n]), $this->author($n));
           $n=$this->_next[$n];
           $out.= sprintf( "next\t%s;\n", $n);
        }
        
        $out.=sprintf("\n\ndesc\n%s\n\n",
            $this->_formatString( $this->description()) );
        
        for($n=$headnum; $n != ""; $n=$this->_next[$n]) {
           $out.=sprintf("\n%s\nlog\n%s\ntext\n%s\n\n",
                $n,
                $this->_formatString( $this->log($n)),
                $this->_formatString( $this->delta($n)) );
        }
        return $out;
    }

    function rlog($rev="",$option="",$oldopt="")
    {
        $this->_ensureProcessed();
        if ($this->_where === 'invalid' || empty($this->_head)) return '';

        $ordered = array();
        for ($number = $this->_head; $number !== ''; $number = $this->_next[$number])
            $ordered[$number] = $this->_next[$number];

        $revs=array();
        if ($rev) {
            if (isset($this->_next[$rev]))
                $revs=array($rev=>'1');
            else
                return '';
        } else if (preg_match('/^-r(.*)$/', $option, $match)) {
            $range = str_replace('\\;', ';', $match[1]);
            if ($range === '') {
                $revs = array($this->_head => $this->_next[$this->_head]);
            } else {
                $ranges = explode(';', $range);
                foreach ($ranges as $part) {
                    if (!preg_match('/^1\.(\d+)(?::1\.(\d+))?$/', $part, $bounds)) return '';
                    $low = (int)$bounds[1];
                    $high = isset($bounds[2]) ? (int)$bounds[2] : $low;
                    foreach ($ordered as $number=>$next) {
                        $index = (int)substr($number, 2);
                        if ($index >= $low && $index <= $high) $revs[$number] = $next;
                    }
                }
                $revs = array_intersect_key($ordered, $revs);
            }
        } else
            $revs=$ordered;

        $this->check_delta($revs);
        $rlog = 'total revisions: '.count($this->_next).'; selected revisions: '.count($revs)."\n";
        foreach ($revs as $rev=>$next) {
            $log=$this->_log[$rev];
            if (!preg_match("/\n$/",$log)) $log.="\n";
            $rlog.= "----------------------------\n";
            $rlog.= "revision $rev\n";
            $change = isset($this->_change[$rev]) ? $this->_change[$rev] : '';
            $rlog.= "date: ".gmdate("Y/m/d H:i:s",$this->_date[$rev]).";  author: ". $this->_author[$rev].";  state: Exp;  lines: ".$change."\n";
            $rlog.= $log;
        }
        if (!empty($rlog)) $rlog.= str_repeat("=",71)."\n";
        return $rlog;
    }
    
    function _binaryChange()
    {
        # Nothing to be done but note for re-writing
        if ($this->_binary )
            $this->_expand = "b";
        # FIXME: unless we have to not do diffs for binary files
    }
    
    function numRevisions()
    {
        $this->_ensureProcessed();
        return $this->_head;
    }
    
    function access()
    {
        $this->_ensureProcessed();
        return $this->_access;
    }
    
    function comment()
    {
        $this->_ensureProcessed();
        return isset($this->_comment[0]) ? $this->_comment : '';
    }
    
    function date($version)
    {
        $this->_ensureProcessed();
        $date = $this->_date[$version];
        if( $date ) {
            $date = gmdate("Y/m/d H:i:s", $date );
        } else {
            $date = "";
        }
        return $date;
    }

    function rcsDateToEpoch($date)
    {
        $dum=explode('.',$date);
        return gmdate("Y/m/d H:i:s",
            gmmktime($dum[3],$dum[4],$dum[5],$dum[1],$dum[2],$dum[0]));
    }

    function rcsDateToTime($date)
    {
        $dum=explode('.',$date);
        return gmmktime($dum[3],$dum[4],$dum[5],$dum[1],$dum[2],$dum[0]);
    }
    
    function description()
    {
        $this->_ensureProcessed();
        return $this->_description;
    }
    
    function author($version)
    {
        $this->_ensureProcessed();
        return $this->_author[$version];
    }
    
    function log($version)
    {
        $this->_ensureProcessed();
        return $this->_log[$version];
    }
    
    function delta($version)
    {
        $this->_ensureProcessed();
        return $this->_delta[$version];
    }

    function incRev($rev) {
        $dum=explode('.',$rev);
        return $dum[0].'.'.sprintf("%s",$dum[1]+1);
    }
    
    function addRevisionText($text, $log, $date = null, $write = true)
    {
        $this->_ensureProcessed();
        
        #$this->_save( $this->file(), $text );
        #if( $this->{attachment} )
        #   $text = $this->_readFile( $this->{file} )
        $head = $this->numRevisions();
        if( $head ) {
            $delta = $this->_diffText( $this->delta($head),$text);
            $this->_delta[$head] = $delta;
            $nhead=$this->incRev($head);
        } else {
            $nhead='1.1';
            $head='';
        }

        $this->_next[$nhead] = $head;
        $this->_delta[$nhead] = $text;
        $this->_head = $nhead;
        if (!preg_match("/\n$/",$log)) $log.="\n";
        $this->_log[$nhead] = $log;
        $this->_author[$nhead] = $this->rcs_user;
        if( ! $date )
           $date = time();
        $this->_date[$nhead] = $date;

        if ($write)
            return $this->_writeMe();
        return true;
    }

    function addRevisionPage($log, $date=0)
    {
        $this->_ensureProcessed();

        $fp=@fopen($this->filename,'r');
        if (!$fp) return false;
        $text='';
        while(!feof($fp))
            $text.=fgets($fp,2048);
        fclose($fp);

        return $this->addRevisionText($text, $log, $date);
    }

    function _writeMe()
    {
        $dataError = '';

        // ignore
        if (empty($this->rcsFile))
            return $dataError;

        # FIXME move permission to config or similar
        $tmp = @tempnam(dirname($this->rcsFile), '.rcs-');
        if (!$tmp) return 'Problem creating temporary RCS file';
        $data = $this->_make_rcs();
        if (file_put_contents($tmp, $data) !== strlen($data) ||
            !chmod($tmp, 0444) || !rename($tmp, $this->rcsFile)) {
            @unlink($tmp);
            $dataError = 'Problem writing ' . $this->rcsFile;
        }
        return $dataError;
    }
    
    # Replace the top revision
    # Return non empty string with error message if there is a problem
    function replaceRevision($text, $comment, $date)
    {
        $this->_ensureProcessed();
        $this->_delLastRevision();
        $this->addRevisionText( $text, $comment, $date );
    }
    
    # Delete the last revision - do nothing if there is only one revision
    function deleteRevision()
    {
        $this->_ensureProcessed();
        if( sizeof($this->_author) <= 1 );
          return '';
        $this->_delLastRevision();
        return $this->_writeMe();
    }
    
    function _delLastRevision() // XXX
    {
        $numRevisions = $this->numRevisions();
        if( $numRevisions != 0 ) {
            # Need to recover text for last revision
            $lastText = $this->getRevision( $numRevisions - 1 );
            $numRevisions--;
            $this->_delta[$numRevisions] = $lastText; // XXX
        } else {
            $numRevisions--;
        }
        $this->_head = $numRevisions;
    }
    
    function revisionDiff($rev1, $rev2, $type='diff')
    {
        $this->_ensureProcessed();

        if (!$rev2) $rev2=$this->_head;
        $text1 = $this->getRevision( $rev1 );
        $text2 = $this->getRevision( $rev2 );

        $diff = $this->_diffText( $text2, $text1, $type );
        return $diff;
    }
    
    function getRevision($version)
    {
        $this->_ensureProcessed();
        $head = $this->numRevisions();
        if ($this->_where === 'invalid' || !$head) return '';
        if ( $version != $head && !array_key_exists($version,$this->_next))
            return '';
        if( strcmp($version,$head) == 0 ) {
            return $this->delta( $version );
        } else {
            $headText = $this->delta( $head );
            $text = $this->_mySplit( $headText );
            return $this->_patchN( $text, $this->_next[$head], $version );
        }
    }
    
    # If revision file is missing, information based on actual file is returned.
    # Date is in epoch based seconds
    function getRevisionInfo($version)
    {
        // XXX ???
        $this->_ensureProcessed();
        if( ! $version )
            $version = $this->numRevisions();
        if( $this->_where && $this->_where != 'nofile' ) {
            return array( "", $version, $this->date( $version ),
                $this->author( $version ), $this->comment( $version ) );
        } else {
            return $this->_getRevisionInfoDefault();
        }
    }
    
    # Apply delta (patch) to text. Note that RCS stores reverse deltas,
    # the text for revision 1.x is patched to produce text for revision 1.x-1.
    # It is fiddly dealing with differences in number of line breaks after the
    # end of the text.
    function _patch(&$text,$delta)
    {
        # Both params are references to arrays
        $adj = 0;
        $pos = 0;
        $last = '';
        $extra = '';

        $ds=sizeof($delta);
        while( $pos < $ds ) {
            $d = $delta[$pos];
            if( preg_match("/^([ad])(\d+)\s(\d+)$/",$d, $match) ) {
                $last = $match[1];
                $extra = '';
                $offset = $match[2];
                $length = $match[3];
                if( $last == 'd' ) {
                    $start = $offset + $adj - 1;
                    $removed = array_splice( $text, $start, $length );
                    $adj -= $length;
                    $pos++;
                } else if( $last == 'a' ) {
                    $toAdd = array_slice($delta, $pos+1,$length);
                    if( $extra ) {
                        if( $toAdd ) {
                            $toAdd[sizeof($toAdd)] .= $extra;
                        } else {
                            $toAdd = array( $extra );
                        }
                    }
                    array_splice( $text, $offset + $adj, 0, $toAdd );
                    $adj += $length;
                    $pos += $length + 1;
                }
            } else {
                if (trim($d))
                    $this->_warn("wrong delta! :\"" . $d . "\"\n\n" );
                return;
            }
        }
    }
    
    function _patchN(&$text,$version,$target)
    {
        while ($version !== '' && isset($this->_next[$version])) {
            $delta = $this->_mySplit($this->delta($version));
            $this->_patch($text, $delta);
            if ($version === $target) return implode('', $text);
            $version = $this->_next[$version];
        }
        return '';
    }
    
    # Split and make sure we have trailing carriage returns
    function _mySplit($text)
    {
        if ($text === '') return array();
        return preg_split('/(?<=\n)/', $text, -1, PREG_SPLIT_NO_EMPTY);
    }
    
    # Way of dealing with trailing \ns feels clumsy
    function _diffText($new,$old,$type="")
    {
        $lNew = $this->_mySplit( $new );
        $lOld = $this->_mySplit( $old );
        return $this->_diffN( $lNew, $lOld, $type );
    }

    # no type means diff for putting in rcs file, diff means normal diff output
    function _diffN($new,$old,$type="")
    {
        include_once('difflib.php');
        $diff = new Diff($old, $new);
        if ($type == "diff")
           $formatter = new DiffFormatter;
        else if ($type == "udiff")
           $formatter = new UnifiedDiffFormatter;
        else
           $formatter = new DeltaDiffFormatter;
        $formatter->trailing_cr="";
        $diffs = $formatter->format($diff);
        return $diffs;
    }

    function validTo()
    {
        $this->_ensureProcessed();
        return $this->_status;
    }
}

// vim:et:sts=4
