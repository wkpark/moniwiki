#!/usr/bin/perl
# convert moinmoin backup datas to rcs files used by the MoniWiki
# $Id$
use POSIX qw(strftime);

$admin_id='Admin';
#$admin_id='YourWikiId';
$mylog='127.0.0.1;;'.$admin_id.';;';

open(LOG,'editlog') or die "could not open editlog: $!";
@log=<LOG>;
close LOG;

#
# PageName     203.xxx.xx.xxx  1041999970      hello.org        Anonymous               SAVE
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
  if ( -f "text/$name" ) {
     $list[$#list + 1]="text/$name\n";
  }
  foreach $backup (@list) {
    chop $backup;
    $time=0;
    if ($backup =~ /\.(\d+)$/) {
      $time=$1;
    } else {
      $time = (stat($backup))[9];
    }
     
    $pagename=$name;
    $pagename=~ s/_([a-f0-9]{2})/chr(hex($1))/eg;
    $pagename =~ s/\"/\\\"/g;
    print "cp $backup backup/$name\n";
    $date = strftime ("%Y%m%d%H%M", gmtime($time));
    print "touch -t $date backup/$name\n";
    if ($logs{$time}) {
      #print $logs{$1}."\n";
      print "ci -q -d -l -t-\"$pagename\" -m\"".$logs{$time}."\" backup/$name\n";
    } else {
      print "ci -q -d -l -t-\"$pagename\" -m\"".$mylog."\" backup/$name\n";
    }
    print "rm backup/$name\n";
  }
}
