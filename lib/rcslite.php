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
#  - tidyup us of 1. for revisions
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
        $buf = '';
        $ch=null;
        $space = 0;
        $string = '';
        $state = '';
        while( !feof($fp)) {
           $ch = fread( $fp, 1 );
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
           } else {
              if( $state == 'e' ) {
                 $state = '';
                 if( $char == '@' ) {
                    break;
                 }
                 # End of string
              } else if ( $state == '@' ) {
                 $string .= $ch;
                 continue;
              }
           }
           
           if( preg_match("/\s/",$ch) ) {
              if( strlen( $buf ) == 0 ) {
                  continue;
              } else if( $space ) {
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
    function _process($file=null)
    {
        if ($file) {
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
        $fh = fopen( $this->rcsFile, 'r');
        if( ! $fh ) {
            $this->_warn( "Couldn't open file $this->rcsFile" );
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
        $dnum = '';
        while( $going ) {
           list($line, $string) = $this->_readTo( $fh, $term );
           if( is_null($line) ) break;
           #print "\"$where -- $line\"\n";
          
           $lastWhere = $where;
           if( $where == 'admin.head' ) {
              if( preg_match('/^head\s+([0-9]+)\.([0-9]+);$/',$line, $match) ) {
                 if( $match[1] != '1' )
                    die( 'Only support start of version being 1' );
                 $headNum = $match[2];
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
              if( preg_match('/^([0-9]+)\.([0-9]+)\s+date\s+(\d\d(\d\d)?(\.\d\d){5}?);$/', $line,$match) ) {
                 $where = 'delta.author';
                 $num = $match[2];
                 $date[$num] = $match[3];
              }
           } else if( $where == 'delta.author' ) {
              if( preg_match('/^author\s+(.*);$/', $line, $match) ) {
                 $author[$num] = $match[1];
                 if( $num == 1 ) {
                    $where = 'desc';
                    $term = '@';
                 } else {
                    $where = 'delta.date';
                 }
              }
           } else if( $where == 'desc' ) {
              if( preg_match('/desc\s*$/', $line, $match) ) {
                 $this->_description = $string;
                 $where = 'deltatext.log';
              }
           } else if( $where == 'deltatext.log' ) {
              if( preg_match('/\d+\.(\d+)\s+log\s+$/', $line, $match) ) {
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
        $this->_log = $log;
        $this->_delta = $text;
        $this->_status = $dnum;
        $this->_where = $where;
        
        fclose( $fh );
    }

    # for rlog() function
    function check_delta()
    {
        foreach ($this->_delta as $rev=>$delta) {
            if ($rev == $this->_head) continue;
            $lines=explode("\n",$delta);
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
            $this->_change[$rev]="$del $add";
            print "+$del -$add\n";
        }
    }
    
    function _formatString($str)
    {
        $str = preg_replace('/@/', '@@', $str);
        return '@$str@';
    }
    
    # Write content of the RCS file
    function _make_rcs()
    {
        # admin
        $headnum='1.'.$this->numRevisions();
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
        for($i=$this->numRevisions(); $i>0; $i--) {
           $out.=
               sprintf("\n1.%d\ndate\t%s;\tauthor %s;\tstate Exp;\nbranches;\n",
                  $i, $this->_date[$i], $this->author($i) );
           if( $i == 1 ) {
               $out.= "next\t;\n";
           } else {
               $out.= sprintf( "next\t1.%d;\n", ($i - 1));
           }
        }
        
        $out.=sprintf("\n\ndesc\n%s\n\n",
            $this->_formatString( $this->description()) );
        
        for($i=$this->numRevisions(); $i>0; $i--) {
           $out.=sprintf("\n1.$i\nlog\n%s\ntext\n%s\n\n",
                $this->_formatString( $this->log($i)),
                $this->_formatString( $this->delta($i)) );
        }
        return $out;
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
    //        $date = TWiki::Store::RcsFile::_rcsDateTimeToEpoch( $date );
        } else {
            $date = "";
        }
        return $date;
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
    
    # ======================
    function addRevision($text,$log, $date=0)
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
        }   
        $head++;
        $this->_delta[$head] = $text;
        $this->_head = $head;
        $this->_log[$head] = $log;
        $this->_author[$head] = $this->rcs_user;
        if( ! $date )
           $date = time();
        $this->_date[$head] = gmdate('Y.m.d.H.i.s',$date);
    
        return $this->_writeMe();
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
    function replaceRevision($text,$comment,$user,$date)
    {
        $this->_ensureProcessed();
        $this->_delLastRevision();
        $this->addRevision( $text, $comment, $user, $date );
    }
    
    # ======================
    # Delete the last revision - do nothing if there is only one revision
    function deleteRevision()
    {
        $this->_ensureProcessed();
        if( $this->numRevisions() <= 1 );
          return '';
        $this->_delLastRevision();
        return $this->_writeMe();
    }
    
    # ======================
    function _delLastRevision()
    {
        $numRevisions = $this->numRevisions();
        if( $numRevisions > 1 ) {
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
        if ($version > $head) return; // XXX
        if( $version == $head ) {
            return $this->delta( $version );
        } else {
            $headText = $this->delta( $head );
            $text = $this->_mySplit( $headText,1 );
            return $this->_patchN( $text, $head-1, $version );
        }
    }
    
    # ======================
    # If revision file is missing, information based on actual file is returned.
    # Date is in epoch based seconds
    function getRevisionInfo($version)
    {
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
            if( preg_match("/^([ad])(\d+)\s(\d+)\n(\n*)/",$d, $match) ) {
                $last = $match[1];
                $extra = $match[4];
                $offset = $match[2];
                $length = $match[3];
                if( $last == 'd' ) {
                    $start = $offset + $adj - 1;
                    $removed = array_splice( $text, $start, $length );
                    #print "REMOVED ";print_r($removed);
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
                    #print "ADDED ";print_r($toAdd);
                    $adj += $length;
                    $pos += $length + 1;
                }
            } else {
                $this->warn("wrong! - should be \"[ad]<num> <num>\" and was: \""
                    . $d . "\"\n\n" ); #FIXME remove die
                //die( "wrong! - should be \"[ad]<num> <num>\" and was: \""
                //  . $d . "\"\n\n" ); #FIXME remove die
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
            return $this->_patchN( $text, $version-1, $target );
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
        for( $i = 0; $i<sizeof($list); $i++ )
            $list[$i] .= "\n";
    
        if( $ending ) {
            if (strlen($ending) == 1) array_pop($list);

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

    function _lastNoEmptyItem($items)
    {
        $pos = sizeof($items);
        $count = 0;
        $item;
        while( $pos >= 0 ) {
            $item = $items[$pos];
            if( $item ) break;
            $count++;
            $pos--;
        }
        return array( $pos, $count );
    }
    
    # ======================
    # Deal with trailing carriage returns - Algorithm doesn't give output that RCS format is too happy with
    function _diffEnd($new,$old,$type=0)
    {
       if( $type ) return; # FIXME
       
       list( $posNew, $countNew ) = $this->_lastNoEmptyItem( $new );
       list( $posOld, $countOld ) = $this->_lastNoEmptyItem( $old );
    
       if( $countNew == $countOld );
          return "";
       
       if( $DIFFEND_DEBUG ) {
         print( "countOld, countNew, posOld, posNew, lastOld, lastNew, lenOld: " .
                "$countOld, $countNew, $posOld, $posNew, " . sizeof($old) . ", " . sizeof($new) . 
                "," . $old . "\n" );
       }
       
       $posNew++;
       $toDel = ( $countNew < 2 ) ? 1 : $countNew;
       $startA = sizeof($new) - ( ( $countNew > 0 ) ? 1 : 0 );
       $toAdd = ( $countOld < 2 ) ? 1 : $countOld;
       $theEnd = "d$posNew $toDel\na$startA $toAdd\n";
       for( $i=$posOld; $i<sizeof($old); $i++ ) {
          $theEnd .= $old[$i] ? $old[$i] : "\n";
       }
       
       for( $i=0; $i<$countNew; $i++ ) {array_pop($new);}
       array_pop($new);
       for( $i=0; $i<$countOld; $i++ ) {array_pop($old);}
       array_pop($old);
       
       if( $DIFFEND_DEBUG )
          print "--$theEnd--\n";
          
       return $theEnd;
    }
    
    # ======================
    # no type means diff for putting in rcs file, diff means normal diff output
    function _diff($new,$old,$type)
    {
        # Work out diffs to change new to old, params are refs to lists
        include_once('difflib.php'); // XXX
        $diff = new Diff($old, $new);
        //$formatter = new UnifiedDiffFormatter;
        $formatter = new DiffFormatter;
        $formatter->trailing_cr="";
        $diffs = $formatter->format($diff);
        // XXX

        $adj = 0;
        $patch = array();
        $del = array();
        $ins = array();
        $out = "";
        $start = 0;
        $start1;
        $chunkSign = "";
        $count = 0;
        $numChunks = sizeof($diffs);
        $last = 0;
        $lengthNew = sizeof($new) - 1;
        foreach ( $diffs as $chunk ) {
           $count++;
           if( DIFF_DEBUG ) print "[\n";
           $chunkSign = "";
           $lines = array();
           foreach ( $chunk as $line ) {
               list( $sign, $pos, $what ) = $line;
               if( DIFF_DEBUG )
                   print "$sign $pos \"$what\"\n";
               if( $chunkSign != $sign && $chunkSign != "") {
                   if( $chunkSign == "-" && $type == "diff" ) {
                      # Might be change of lines
                      $chunkLength = sizeof($chunk);
                      $linesSoFar = $lines;
                      if( $chunkLength == 2 * $linesSoFar ) {
                         $chunkSign = "c";
                         $start1 = $pos;
                      }
                   }
                   if( $chunkSign != "c" )
                      $adj += _addChunk( $chunkSign, $out, $lines, $start, $adj, $type, $start1, $last );
               }
               if( ! sizeof($lines) ) {
                   $start = $pos;
               }
               if( $chunkSign != "c" )
                   $chunkSign = $sign;
               array_push($lines, array( $what ));
           }
    
           if( $count == $numChunks ) $last = 1;
           if( $last && $chunkSign == "+" ) {
               $endings = 0;
               for( $i=sizeof($old); $i>=0; $i-- ) {
                   if( $old[$i] ) {
                       break;
                   } else {
                       $endings++;
                   }
               }
               $has = 0;
               for( $i=sizeof($lines); $i>=0; $i-- ) {
                   if( $lines[$i] ) {
                       break;
                   } else {
                       $has++;
                   }
               }
               for( $i=0; $i<$endings-$has; $i++ ) {
                   array_push($lines, array(""));
               }
           }
           $adj += $this->_addChunk( $chunkSign, $out, $lines, $start, $adj, $type, $start1, $last, $lengthNew );
           if( DIFF_DEBUG )
               print "]\n";
        }
        # Make sure we have the correct number of carriage returns at the end
        
        if( $DIFFEND_DEBUG )
           print "pre end: \"$out\"\n";
        return $out; # . $theEnd;
    }
    
    
    # ======================
    function _range($start,$end)
    {
       if( $start == $end ) {
          return "$start";
       } else {
          return "$start,$end";
       }
    }
    
    # ======================
    function _addChunk($chunkSign, $out, $lines, $start, $adj, $type, $start1, $last, $newLines )
    {
       $nLines = sizeof($lines);
       if( preg_match('/(\n+)$/',$lines[sizeof($lines)],$m) ) {
          $nLines += ( ( strlen( $m[1] ) == 0 ) ? 0 : strlen( $m[1] ) -1 );
       }
       if( $nLines > 0 ) {
           if( DIFF_DEBUG )
              print "addChunk chunkSign=$chunkSign start=$start adj=$adj type=$type " .
                    "start1=$start1 last=$last newLines=$newLines nLines=$nLines\n";
           if( $out && !preg_match("/\n$/",$out ))
               $out .= "\n";
           if( $chunkSign == "c" ) {
              $out .= $this->_range( $start+1, $start+$nLines/2 );
              $out .= "c";
              $out .= $this->_range( $start1+1, $start1+$nLines/2 );
              $out .= "\n";
              $out .= '< ' . implode( '< ', array_slice($lines,0,$nLines/2-1) );
              if( !preg_match('/\n$/',$lines[$nLines/2-1]) )
                  $out .= "\n";
              $out .= "---\n";
              $out .= '> ' . implode( '> ', array_slice($lines,$nLines/2,$nLines-1 - $nLines/2));
              $nLines = 0;
           } else if( $chunkSign == "+" ) {
              if( $type == "diff" ) {
                  $out .= $start-$adj . "a";
                  $out .= $this->_range( $start+1, $start+$nLines ) . "\n";
                  $out .= "> " . implode( "> ", $lines );
              } else {
                  $out .= "a";
                  $out .= $start-$adj;
                  $out .= " $nLines\n";
                  $out .= implode( "", $lines );
              }
           } else {
              if( DIFF_DEBUG ) print "Start nLines newLines: $start $nLines $newLines\n";
              if( $type == "diff" ) {
                  $out .= $this->_range( $start+1, $start+$nLines );
                  $out .= "d";
                  $out .= $start + $adj . "\n";
                  $out .= "< " . implode( "< ", $lines );
              } else {
                  $out .= "d";
                  $out .= $start+1;
                  $out .= " $nLines";
                  if( $last ) $out .= "\n";
              }
              $nLines *= -1;
           }
           $lines = array();
       }
       return $nLines;
    }
    
    
    # ======================
    function validTo()
    {
        $this->_ensureProcessed();
        return $this->_status;
    }
}

$rcs_dir='RCS';$rcs_user='hello';
$rcs = new RcsLite($rcs_dir,$rcs_user);

$rcs->_process("m.txt");
#process( "c:/tmp/rcs/RCS/a.txt" );
#print "head: $rcs->_head\n";
#print "status: $rcs->_status\n";
#print $rcs->_where."\n";
#print_r($rcs->_author);
#print_r($rcs->_date);
#print "log: ";print_r($rcs->_log);
#print "delta: ";print_r($rcs->_delta);

#for ($i=0;$i<100;$i++)
#$dd=$rcs->getRevision('10');
#print($dd);
//$dd=$rcs->revisionDiff('20','21');
//$dd=$rcs->revisionDiff('22','21');
#print_r($dd);
## co -p1.7
#$dd=$rcs->getRevision('7');
#print($dd);print "---------------\n";
#$dd=$rcs->getRevision('8');
#print($dd);
$rcs->check_delta();
if ($rcs->_head > 6) {
  ## rcsdiff -r1.3 -r1.8
  $dd=$rcs->revisionDiff('3','8','udiff');
  #$dd=$rcs->revisionDiff('73','74');
  print($dd);
}
## ci -l
##$rcs->addRevision("Bang\nHello World !\n",'hello');

// vim:et:sts=4
?>
