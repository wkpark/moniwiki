#!/usr/bin/perl
# from http://www.heddley.com/edd/php/search.html
# slightly modified to adopt to the MoniWiki 2003/07/19 by wkpark
# Public Domain
# $Id$
#
# Usage:
# $ cd data
# $ ls
# ... text/ cache/
# $ perl wiki_indexer.pl
# ....
# $ mkdir cache/index or rm cache/index/fullsearch.db
# $ chmod a+rw fullsearch.db
# $ mv fullsearch.db cache/index
#

require 5.8.0;

#$charset="euc-kr";
$charset="utf8";
$type='n';
###########################################################################
if ($charset eq "utf8") {
  use encoding "utf8";
  #$delim=' \t\n,:"\'~`!@#\$%\^&\*\(\)\-_\+=\[\]:;<>,\.\?\/';
  $delim='\p{IsPunct}\p{IsSpace}';
} else {
  $delim='\t\n\s[:blank:][:punct:][:space:]';
}

use DB_File;    # Access DB databases
use Fcntl;      # Needed for above...
use File::Find; # Directory searching
$DB_File::DB_BTREE->{cachesize} = 10_000_000; # 10meg cache
$DB_File::DB_BTREE->{psize} = 32*1024; # 32k pages
undef $/; # Don't obey line boundaries
$currentKey = 256;

############################################################################

# Delete old index.db and attach %indexdb to database
unlink("fullsearch.db");
tie(%indexdb,'DB_File',"fullsearch.db",
    O_RDWR | O_CREAT, 0644, $DB_File::DB_BTREE);
find(\&IndexFile,"text");
&FlushWordCache();

$indexdb{"!!"}=$currentKey; # save currentKey

untie(%indexdb); # release database

###########################################################################

sub IndexFile {
    if(!-f) { return; }

    #if(/,v$/) { return; }
    if(substr($_,-2) eq ',v') { return; }
    { # for WikiPages
        print "$File::Find::name\n";
        if ($charset eq "utf8") {
            open(TXT_FILE,"<:utf8",$_) || die "Can't open $_: $!";
        } else {
            open(TXT_FILE,$_) || die "Can't open $_: $!";
        }
        my($text) = <TXT_FILE>; # Read entire file
        $text=~ s/([a-z0-9]+)([A-Z])/\\1 \\2/g;
        # Index all the words under the current key
        my($wordsIndexed) = &IndexWords($text,$currentKey);
        # Map key to this filename
        $indexdb{"!?" . pack($type,$currentKey)} = $_;
        $indexdb{"!?" . $_} = pack($type,$currentKey);
        $currentKey++; if ($currentKey % 256 ==0) { $currentKey++; }

        $fileCount++;
        if($fileCount > 500) {
            &FlushWordCache();
            $fileCount=0;
        }
    }
}

###########################################################################

sub IndexWords {
    my($words, $fileKey) = @_;
    my(%worduniq); # for unique-ifying word list
    # Split text into Array of words
    my(@words) = split(/[$delim]+/, lc $words);
    @words = grep { $worduniq{$_}++ == 0 } # Remove duplicates
             grep { s/^[$delim]+//; $_ } # Strip leading punct
             grep { length > 1 } # Must be longer than one character
             grep { /[^$delim]/ } # must have an alphanumeric
             @words;

    # For each word, add key to word database
    foreach (sort @words) {
        #print $_."\n";
        my($a) = $wordcache{$_};
        $a .= pack $type,$fileKey;
        $wordcache{$_} = $a;
    }

    # Return count of words indexed
    return scalar(@words);
}

###########################################################################
# Flush temporary in-memory %wordcache to disk database %indexdb

sub FlushWordCache {
    my($word,$entry);
    # Do merge in sorted order to improve cache response of on-disk DB
    foreach $word (sort keys %wordcache) {
        $entry = $wordcache{$word};
        if(defined $indexdb{$word}) {
            my($codedList);
            $codedList = $indexdb{$word};
            $entry = &MergeLists($codedList,$entry);
        }

        # Store merged list into database
        $indexdb{$word} = $entry;
    }
    %wordcache = (); # Empty the holding queue
}

###########################################################################

sub MergeLists {
    my($list);
    # Simply append all the lists
    foreach (@_) { $list .= $_; }
    # Now, remove any duplicate entries
    my(@unpackedList) = unpack($type."*",$list); # Unpack into integers
    my(%uniq); # sort and unique-ify
    @unpackedList = grep { $uniq{$_}++ == 0 }
                    sort { $a <=> $b }
                    @unpackedList;
    return pack($type."*",@unpackedList); # repack
}

###########################################################################
# vim:et:sts=4
