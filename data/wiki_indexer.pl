#!/usr/bin/perl
# from http://www.heddley.com/edd/php/search.html
# slightly modified to adopt to the MoniWiki 2003/07/19 by wkpark
# Public Domain
# $Id$

require 5;
use DB_File;    # Access DB databases
use Fcntl;      # Needed for above...
use File::Find; # Directory searching
$DB_File::DB_BTREE->{cachesize} = 10_000_000; # 10meg cache
$DB_File::DB_BTREE->{psize} = 32*1024; # 32k pages
undef $/; # Don't obey line boundaries
$currentKey = 256;

############################################################################

# Delete old index.db and attach %indexdb to database
unlink("index.db");
tie(%indexdb,'DB_File',"index.db",
    O_RDWR | O_CREAT, 0644, $DB_File::DB_BTREE);
find(\&IndexFile,"text");
&FlushWordCache();
untie(%indexdb); # release database

###########################################################################

sub IndexFile {
    if(!-f) { return; }

    if(/,v$/) { return; }
    { # for WikiPages
	print "$File::Find::name\n";
	open(TXT_FILE,$_) || die "Can't open $_: $!";
	my($text) = <TXT_FILE>; # Read entire file
	# Index all the words under the current key
	my($wordsIndexed) = &IndexWords($text,$currentKey);
	# Map key to this filename
	$indexdb{"!?" . pack("n",$currentKey)} = $_;
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
    my(@words) = split(/[^a-zA-Z0-9\xa0-\xff\+\/\_]+/, lc $words);
    @words = grep { $worduniq{$_}++ == 0 } # Remove duplicates
	     grep { s/^[^a-zA-Z0-9\xa0-\xff]+//; $_ } # Strip leading punct
             grep { length > 1 } # Must be longer than one character
             grep { /[a-zA-Z0-9\xa0-\xff]/ } # must have an alphanumeric
             @words;

    # For each word, add key to word database
    foreach (sort @words) {
	my($a) = $wordcache{$_};
	$a .= pack "n",$fileKey;
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
    my(@unpackedList) = unpack("n*",$list); # Unpack into integers
    my(%uniq); # sort and unique-ify
    @unpackedList = grep { $uniq{$_}++ == 0 }
                    sort { $a <=> $b }
                    @unpackedList;
    return pack("n*",@unpackedList); # repack
}

###########################################################################
