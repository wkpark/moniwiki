<?php
# PHP RcsLite module
# Copyright (c) 2004 Won-kyu Park <wkpark at kldp.org>
#
# this module is ported from the RcsLite.pm of the TWiki by wkpark.
# $Id$

#
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

define(DIFF_DEBUG,0);
define(DIFFEND_DEBUG,0);

Class RcsLite {
    var $rcs_dir='RCS';
    var $rcs_user='root';
    function RcsLite($rcs_dir='RCS',$rcs_user='root')
    {
        #$class = ref($proto) || $proto;
        #bless( $this, $class );
        #$this->_init();

        $this->rcs_dir=$rcs_dir ? $rcs_dir:'RCS';
        $this->rcs_user=$rcs_user ? $rcs_user:'root';
        $this->_head = 0;
        $this->_next = array();
    }
  
    # ======================
    function _trace($text)
    {
    #   TWiki::writeDebug( $text );
    }

# Process an RCS file

# File format information:
#
#rcstext    ::=  admin {delta}* desc {deltatext}*
#admin      ::=  head {num};
#                { branch   {num}; }
#                access {id}*;
#                symbols {sym : num}*;
#                locks {id : num}*;  {strict  ;}
#                { comment  {string}; }
#                { expand   {string}; }
#                { newphrase }*
#delta      ::=  num
#                date num;
#                author id;
#                state {id};
#                branches {num}*;
#                next {num};
#                { newphrase }*
#desc       ::=  desc string
#deltatext  ::=  num
#                log string
#                { newphrase }*
#                text string
#num        ::=  {digit | .}+
#digit      ::=  0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9
#id         ::=  {num} idchar {idchar | num }*
#sym        ::=  {digit}* idchar {idchar | digit }*
#idchar     ::=  any visible graphic character except special
#special    ::=  $ | , | . | : | ; | @
#string     ::=  @{any character, with @ doubled}*@
#newphrase  ::=  id word* ;
#word       ::=  id | num | string | :
#
# Identifiers are case sensitive. Keywords are in lower case only. The sets of
# keywords and identifiers can overlap.
# In most environments RCS uses the ISO 8859/1 encoding: 
# visible graphic characters are codes 041-176 and 240-377, and white space
# characters are codes 010-015 and 040. 
#
# Dates, which appear after the date keyword, are of the form Y.mm.dd.hh.mm.ss, 
# where Y is the year, mm the month (01-12), dd the day (01-31), hh 
# the hour(00-23), mm the minute (00-59), and ss the second (00-60).
# Y contains just the last two digits of the year for years from 1900 through
# 1999, and all the digits of years thereafter. 
# Dates use the Gregorian calendar; times use UTC. 
#
# The newphrase productions in the grammar are reserved for future extensions
# to the format of RCS files.
# No newphrase will begin with any keyword already in use. 

#
    function _readTo($fp, $char)
    {
        $ch = null;
        $buf = '';
        $string = '';
        $state = '';
        $space = 0;
        while( false !== ($ch = fgetc($fp)) ) {
            if( $ch == '@' ) {
                if( $state == '@' ) {
                    $state = 'e';
                    continue;
                } else if( $state == 'e' ) {
                    $state = '@';
                    $string .= '@';
                    continue;
                } else {
                    $state = '@';
                    continue;
                }
            } else if ( $state ) {
                if ( $state == '@' ) {
                    $string .= $ch;
                    continue;
                } else if( $state == 'e' ) {
                    $state = '';
                    if( $char == '@' ) break;
                    # End of string
                }
            }
           
            if ( preg_match('/\s/',$ch) ) {
                if( $space or $buf == '' ) {
                    continue;
                } else {
                    $space = 1;
                    $ch = ' ';
                }
            } else {
                $space = 0;
            }
            $buf .= $ch;
            if( $ch == $char ) break;
        }
        if ($ch == null) $buf=null;
        return array( $buf, $string );
    }
    
    # Called by routines that must make sure RCS file has been read in
    function _ensureProcessed()
    {
        if( ! $this->_where ) {
            $this->_process();
        }
    }
    
    function _warn($message)
    {
        print "Warning: $message\n";
    }
    
    # Read in the whole RCS file
    function _process($file='', $force=0)
    {
        if( $this->_where && !$force) return;

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
            $this->_where = 'nofile';
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
                if( preg_match('/^locks.*;$/', $line) ) {
                    $where = 'admin.postLocks';
                } else {
                    break;
                }
            } else if( $where == 'admin.postLocks' ) {
                if( preg_match('/^strict\s*;/', $line) ) {
                    $where = 'admin.postStrict';
                }
            } else if( $where == 'admin.postStrict' &&
                    preg_match('/^comment\s.*$/', $line) ) {
                $where = 'admin.postComment';
                $this->_comment = $string;
            } else if( ( $where == 'admin.postStrict' || $where == 'admin.postComment' )  &&
                    preg_match('/^expand\s/', $line) ) {
                $where = 'admin.postExpand';
                $this->_expand = $string;         
            } else if( $where == 'admin.postStrict' || $where == 'admin.postComment' || 
                $where == 'admin.postExpand' || $where == 'delta.date') {
                if( preg_match('/^([0-9]+\.[0-9]+)\s+date\s+(\d\d(\d\d)?(\.\d\d){5}?);$/', $line,$match) ) {
                    $where = 'delta.author';
                    $num = $match[1];
                    $date[$num] = $this->rcsDateToTime($match[2]);
                }
            } else if( $where == 'delta.next' ) {
                if( preg_match('/^next ([^;]*);$/',$line,$match) ){
                    $where = 'delta.date';
                    $next[$num] = $match[1];
                    if ($next[$num] == "") {
                        $where = 'desc';
                        $term = '@';
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
                    if( $dnum == 1 ) {
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
        
        fclose( $fh );
    }

    # for rlog() function
    function check_delta()
    {
        foreach ($this->_next as $rev=>$next) {
            if ($next=="") break;
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
        $out.= "locks\n\t$this->rcs_user:$headnum; strict;\n";
        $out.=sprintf("comment\t%s;\n", $this->_formatString($this->comment()));
        if ( $this->_expand ) {
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
        $this->check_delta();

        $revs=array();
        if ($rev) {
            if ($rev == $this->_head or in_array($rev,$this->_next))
                $revs=array($rev=>'1');
            else
                return '';
        } else
            $revs=&$this->_next;

        foreach ($revs as $rev=>$next) {
            $log=$this->_log[$rev];
            if (!preg_match("/\n$/",$log)) $log.="\n";
            $rlog.= "----------------------------\n";
            $rlog.= "revision $rev\n";
            $rlog.= "date: ".date("Y/m/d H:i:s",$this->_date[$rev]).";  author: ". $this->_author[$rev].";  state: Exp;  lines: ".$this->_change[$rev]."\n";
            $rlog.= $log;
        }
        $rlog.= str_repeat("=",71)."\n";
        return $rlog;
    }
    
    # ======================
    function _binaryChange()
    {
        # Nothing to be done but note for re-writing
        if ($this->_binary )
            $this->_expand = "b";
        # FIXME: unless we have to not do diffs for binary files
    }
    
    # ======================
    function numRevisions()
    {
        $this->_ensureProcessed();
        return $this->_head;
    }
    
    # ======================
    function access()
    {
        $this->_ensureProcessed();
        return $this->_access;
    }
    
    # ======================
    function comment()
    {
        $this->_ensureProcessed();
        return $this->_comment;
    }
    
    # ======================
    function date($version)
    {
        $this->_ensureProcessed();
        $date = $this->_date[$version];
        if( $date ) {
            $date = date("Y/m/d H:i:s", $date );
        } else {
            $date = "";
        }
        return $date;
    }

    function rcsDateToEpoch($date)
    {
        $dum=explode('.',$date);
        return date("Y/m/d H:i:s",strtotime("$dum[0]/$dum[1]/$dum[2] $dum[3]:$dum[4]:$dum[5]"));
    }

    function rcsDateToTime($date)
    {
        $dum=explode('.',$date);
        return strtotime("$dum[0]/$dum[1]/$dum[2] $dum[3]:$dum[4]:$dum[5]");
    }
    
    # ======================
    function description()
    {
        $this->_ensureProcessed();
        return $this->_description;
    }
    
    # ======================
    function author($version)
    {
        $this->_ensureProcessed();
        return $this->_author[$version];
    }
    
    # ======================
    function log($version)
    {
        $this->_ensureProcessed();
        return $this->_log[$version];
    }
    
    # ======================
    function delta($version)
    {
        $this->_ensureProcessed();
        return $this->_delta[$version];
    }

    function incRev($rev) {
        $dum=explode('.',$rev);
        return $dum[0].'.'.sprintf("%s",$dum[1]+1);
    }
    
    # ======================
    function addRevisionText($text,$log, $date=0)
    {
        $this->_ensureProcessed();
        
        #$this->_save( $this->file(), $text );
        #if( $this->{attachment} )
        #   $text = $this->_readFile( $this->{file} )
        $head = $this->numRevisions();
        if( $head ) {
            //$delta = $this->_diffText( $text, $this->delta($head));
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

        return $this->_writeMe();
    }

    # ======================
    function addRevisionPage($log, $date=0)
    {
        $this->_ensureProcessed();

        $fp=fopen($this->filename,'r');
        if (!$fp) return;
        $text='';
        while(!feof($fp))
            $text.=fgets($fp,2048);
        fclose($fp);

        $this->addRevisionText($text, $log, $date);
    }

    # ======================
    function _writeMe()
    {
        $dataError = '';
        
        # FIXME move permission to config or similar
        @chmod($this->rcsFile,0644 );
        $fp=fopen($this->rcsFile, 'w'); 
        if( ! $fp ) {
           $dataError = 'Problem opening ' . $this->rcsFile . ' for writing';
        } else {
           #binmode( $out );
           fwrite( $fp, $this->_make_rcs() );
           fclose( $fp );
        }
        chmod($this->rcsFile,0444  ); # FIXME
        return $dataError;
    }
    
    # ======================
    # Replace the top revision
    # Return non empty string with error message if there is a problem
    function replaceRevision($text, $comment, $date)
    {
        $this->_ensureProcessed();
        $this->_delLastRevision();
        $this->addRevisionText( $text, $comment, $date );
    }
    
    # ======================
    # Delete the last revision - do nothing if there is only one revision
    function deleteRevision()
    {
        $this->_ensureProcessed();
        if( sizeof($this->_author) <= 1 );
          return '';
        $this->_delLastRevision();
        return $this->_writeMe();
    }
    
    # ======================
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
    
    # ======================
    function revisionDiff($rev1, $rev2, $type='diff')
    {
        $this->_ensureProcessed();

        if (!$rev2) $rev2=$this->_head;
        $text1 = $this->getRevision( $rev1 );
        $text2 = $this->getRevision( $rev2 );
        $diff = $this->_diffText( $text2, $text1, $type );
        return $diff;
    }
    
    # ======================
    function getRevision($version)
    {
        $this->_ensureProcessed();
        $head = $this->numRevisions();
        if ( $version != $head && !in_array($version,$this->_next))
            return;
        if( $version == $head ) {
            return $this->delta( $version );
        } else {
            $headText = $this->delta( $head );
            $text = $this->_mySplit( $headText,1 );
            return $this->_patchN( $text, $this->_next[$head], $version );
        }
    }
    
    # ======================
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
    
    
    # ======================
    # Apply delta (patch) to text. Note that RCS stores reverse deltas,
    # the text for revision x is patched to produce text for revision x-1.
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
        while( $pos <= $ds ) {
            $d = $delta[$pos];
            if( preg_match("/^([ad])(\d+)\s(\d+)$/",$d, $match) ) {
                $last = $match[1];
                $extra = $match[4];
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
    
    
    # ======================
    function _patchN(&$text,$version,$target)
    {
        $deltaText= $this->delta( $version );
        $delta = $this->_mySplit( $deltaText );
        $this->_patch( $text, $delta );
        if( $version == $target ) {
            return implode('', $text );
        } else {
            return $this->_patchN( $text, $this->_next[$version], $target );
        }
    }
    
    # ======================
    # Split and make sure we have trailing carriage returns
    function _mySplit($text,$addEntries=0,$add_cr=1)
    {
        $ending = '';
        if( preg_match_all("/(\n)$/", $text,$match) )
            $ending = $match[1][0]; // XXX
   
        $list = preg_split( "/\n/", $text );
        $sz=sizeof($list);
        #print $sz.':';
        for( $i = 0; $i<$sz; $i++ )
            $list[$i] .= "\n";
    
        if( $ending ) {
            if (strlen($ending) == 1) array_pop($list);
/*
            if( $addEntries ) {
                $len = strlen($ending);
                if( $list ) {
                   $len--;
                # ??? $list[sizeof($list)] .= "\n";
                }
                for($i=0; $i<$len; $i++ ) {
                    array_push($list,"\n");
                }
            } else {
                if( $list ) {
                # ??? $list[sizeof($list)] .= $ending;
                } else {
                    $list = array( $ending );
                }
            }
*/
        }
        # TODO: deal with Mac style line ending??
    
        return $list; # FIXME would it be more efficient to return a reference?
    }
    
    
    # ======================
    # Way of dealing with trailing \ns feels clumsy
    function _diffText($new,$old,$type="")
    {
        $lNew = $this->_mySplit( $new );
        $lOld = $this->_mySplit( $old );
        return $this->_diffN( $lNew, $lOld, $type );
    }

    # ====================== 
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

    # ======================
    function validTo()
    {
        $this->_ensureProcessed();
        return $this->_status;
    }
}

function test() {
    $rcs_dir='RCS';$rcs_user='hello';
    $rcs = new RcsLite($rcs_dir,$rcs_user);

    $rcs->_process("m.txt");
    #process( "c:/tmp/rcs/RCS/a.txt" );
    print "head: $rcs->_head\n";
    print_r($rcs->_next);

    #for ($i=0;$i<10;$i++) {
    #   $dd=$rcs->getRevision('1.'.$i);
    #   print($dd);
    #}
    ## co -p1.7
    #$dd=$rcs->getRevision('1.7');
    #print($dd);
    ## rcsdiff -r1.3 -r1.8
    $dd=$rcs->revisionDiff('1.3','1.8','udiff');
    print($dd);
    ## ci -l
    $rcs->addRevisionText("Bang\n\t\tHello World !\n",'hello');
    #print $rcs->rlog("1.11");
}

// vim:et:sts=4
?>
