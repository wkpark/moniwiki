#!/usr/bin/perl 
# pmwe  (pmwikiedit)
#   Copyright 2002 Jonathan Scott Duff <duff at pobox.com>
# Distributable under the terms of the GNU General Public License
#
# wkpark slightly modified it to edit MoniWiki pages 2003/07/05
# $Id$

use LWP::Simple;
use LWP::UserAgent;
use HTTP::Request::Common qw(POST);
use Getopt::Std;
use Config;

$VIM = 'vim';
$TempDir = '/var/tmp/';
if ($Config{'osname'} =~ /win32/i) {
  $VIM = 'gvim.exe';
  $TempDir = 'c:\\windows\\temp\\';
}

$DefaultWikiUrl = 'http://MoniWiki.sf.net/wiki.php';
$DefaultWikiPage = 'WikiSandBox';
$DefaultWikiEditor = "$VIM \"+set syntax=moin\"";

package WikiAgent;

@ISA = qw(LWP::UserAgent);
 
sub new {
   my $self = LWP::UserAgent::new(@_);
   $self->agent("moniwikiedit");
   return $self;
}
 
sub get_basic_credentials
{
   my($self, $realm, $uri) = @_;
   print "Enter password for $realm: ";
   system("stty -echo"); chomp(my $password = <STDIN>); system("stty echo");
   print "\n";  # because we disabled echo
   return ("moniwikieditor", $password);
}

package main;

my %opts;
getopts('m:h', \%opts);

if ($opts{'h'}) {
  print <<EOF;
usage: $0 MoniWiki:WikiSandBox
       $0 WikiSandBox http://moniwiki.sf.net/wiki.php
       $0 -m intermap.txt MoinMoin:WikiSandBox
EOF
  exit;
}

$mapfile = $opts{'m'} || "$ENV{'HOME'}/.we.map";
if (open(MAP, $mapfile)) {
   while (<MAP>) {
    if (! /^(#|\s)/) { my ($n,$url) = split; $urlmap{$n} = $url; }
   }
   close MAP;
}

$editor = $ENV{'EDITOR'} || $DefaultWikiEditor;

($wikipage,$wikiurl) = @ARGV;

if ( -f $wikipage) {
  open(FILE, $wikipage);
  while (<FILE>) { $in.=$_; }
  close FILE;
  ($wikipage, $wikiurl) = split(/\s+/,$in);
} else {
  $wikiurl = $urlmap{$1} if $wikipage=~s/^(\w+):// && !$wikiurl && $urlmap{$1};
}

$wikipage ||= $DefaultWikiPage;
$outfile = $TempDir."moni$$";

$wikiurl ||= $DefaultWikiUrl;
$wikiurl .= "/" unless $wikiurl=~m!/$!;

$content = getstore("$wikiurl$wikipage?action=raw",$outfile);

$mtime0 = (stat($outfile))[9];
$time = time();
system("$editor $outfile");
$mtime1 = (stat($outfile))[9];

if ($mtime0 == $mtime1) 
   { print STDERR "content unchanged.\n"; unlink($outfile); exit; }

open(OUTFILE, "$outfile") or die;
{ local $/; $text = <OUTFILE>; }
close OUTFILE;

$text =~ s/\s*$//s;
my $ua = WikiAgent->new;
my $req = POST $wikiurl.$wikipage, [
   action	=> 'savepage',
   datestamp	=> $time,
   savetext	=> $text,
];

$ua->request($req);

print "$wikiurl$wikipage successfully modified\n";

unlink($outfile);

exit;
# batch file for windows
# perl g:\perl\moniedit.pl %1
