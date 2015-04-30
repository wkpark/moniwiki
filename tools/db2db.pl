#!/usr/bin/perl -w
# db2db: converts any DB to DB
use strict;

use DB_File;
use GDBM_File;

unless (@ARGV == 2) {
    die "usage: db2db infile outfile\n";
}

my ($infile, $outfile) = @ARGV;                     
my (%db_in, %db_out);                               

# open the files
tie %db_in, 'DB_File', $infile, O_RDONLY, 0666, $DB_HASH or die "Can't tie $infile: $!";
#tie %hash, 'DB_File', $infile, O_RDONLY, 0666, $DB_BTREE;
tie %db_out, "DB_File", $outfile, O_CREAT|O_RDWR, 0666, $DB_HASH;
#tie(%db_out, 'GDBM_File', $outfile, GDBM_WRCREAT, 0666)

# copy (don't use %db_out = %db_in because it's slow on big databases)
while (my($k, $v) = each %db_in) {
    $db_out{$k} = $v;
}

# these unties happen automatically at program exit
untie %db_in;
untie %db_out;
