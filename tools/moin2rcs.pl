#!/usr/bin/perl
# convert moinmoin backup datas to rcs files used by the MoniWiki
# $Id$
use POSIX qw(strftime);

open(LOG,'editlog') or die "could not open editlog: $!";
@log=<LOG>;
close LOG;

# GLE     203.252.48.205  1041999970      chem6.skku.ac.kr        Anonymous               SAVE
foreach $line (@log) {
  ($page, $ip, $time, $host, $id, $comment,$action)=split(/\t/,$line);
  chomp($comment);
  $comment =~ s/\"/\\\"/g;
  if (!$logs{$time}) {
    if ($id eq 'Anonymous' or $id eq '¾Æ¹«°³' or $id =~ /^\d+\.\d/) {
      $logs{$time}=$ip.";;Anonymous;;$comment";
    } else {
      $logs{$time}=$ip.";;$id;;$comment";
    }
  }
  #print $logs{$time}."\n";
}

opendir(BACKUP, 'backup') || die "can't opendir backup/: $!";
@backups = grep { /\.\d+/ && -f "backup/$_" } readdir BACKUP;
closedir BACKUP;

foreach $_ (@backups) {
  if (/^([^.]+)\.\d+$/) {
    $pages{$1}=1;
  }
}

#print %pages;


foreach $name (keys %pages) {
  @list=`ls backup/$name.*`;
  sort @list;
  foreach $backup (@list) {
    chop $backup;
    if ($backup =~ /\.(\d+)$/) {
      if ($logs{$1}) {
        #print $logs{$1}."\n";
        $time=$1;
        $pagename=$name;
        $pagename=~ s/_([a-f0-9]{2})/chr(hex($1))/eg;
        $pagename =~ s/\"/\\\"/g;
        print "cp $backup backup/$name\n";
        $date = strftime ("%Y%m%d%H%M", gmtime($time));
        print "touch -t $date backup/$name\n";
        print "ci -q -d -l -t-\"$pagename\" -m\"".$logs{$time}."\" backup/$name\n";
        print "rm backup/$name\n";
      }
    }
  }
}
