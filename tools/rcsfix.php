<?php
/**
 * a broken RCS fixer by wkpark at gmail.com (2013/05/08)
 *
 * License: GPLv2
 */

set_time_limit(0); // no time limit

$rcsdir = isset($argv[1]) ? $argv[1] : '';

if (empty($rcsdir[0]) or !file_exists($rcsdir)) {
    echo "Usage: php $argv[0] <RCS path or RCS filename>\n";
} else if (is_dir($rcsdir)) {
    checkRCSdir($rcsdir, 1000);
} else {
    $ret = fixrcs($rcsdir);
    if ($ret < 0) {
        echo "Error !!\n";
    }
}

function fixrcs($rcsfile, $force = true) {
    chmod($rcsfile, 0666);
    $fp = fopen($rcsfile, 'rw');
    if (!is_resource($fp)) return -1;

    $mtime = filemtime($rcsfile);
    $filesize = filesize($rcsfile);

    fseek($fp, -20, SEEK_END);
    $end = fread($fp, 20);

    $looks_ok = false;
    if (preg_match("!@\n\s*$!", $end))
        $looks_ok = true;

    if (!$force and $looks_ok) {
        fclose($fp);
        echo "Looks OK\n";
        return 0; // no need to fix RCS file
    }

    $pagename = preg_replace("/,v$/", '', basename($rcsfile));
    $pagename = pagename($pagename);

    $bs = 512;
    $pos = 0;
    $state = 'unknown';
    $buf = '';

    while (true) {
        echo '.';

        if ($filesize < $bs) {
            echo "No version info!!\n";
            return -1;
        }
        //if ($filesize + $pos - $bs < 0)
        //    $bs = $filesize + $pos;
        //echo "\n".$filesize,' - ',$pos,' - ',$bs."\n";
        fseek($fp, $pos - $bs, SEEK_END);
        $buf = fread($fp, $bs);
	if (!isset($buf[0])) {
	    fclose($fp);
            echo "Empty!!\n";
	    return -1;
	}
        //echo $buf."\n";
        if ($buf[0] == '@') { $bs+= 512; $state = 'unknown'; continue; }

        if (($p = strrpos($buf, '@')) !== false) {
            if ($buf[$p - 1] == '@') {
                if (($p >= 6 && substr($buf, $p - 6, 4) == 'text')) {
                    // empty text case. \ntext\n@@
                    $state = 'text';
                    $buf = substr($buf, 0, $p - 2);
                } else if (($p >= 5 && substr($buf, $p - 5, 3) == 'log')) {
                    // invalid case
                    $state = 'unknown';
                    break;
                } else if (($p >= 6 && substr($buf, $p - 6, 4) == 'desc')) {
                    return -3;
                    break;
                } else {
                    $buf = substr($buf, 0, $p - 2);
                    $state = 'text';
                }
            } else if ($buf[$p - 1] != '@') {
                $buf = substr($buf, 0, $p - 1);
                $state = $state != 'text' ? 'text' : 'log';
            }
            //echo "===".$state."\n";
        }
        if ($p === false or $state == 'unknown' or strlen($buf) < strlen($state)) {
            $bs+= 512;
            //echo "inc block size\n";
            $state = 'unknown';
            continue;
        }

        while ($state == 'text' || $state == 'log') {
            if (strlen($buf) > strlen($state) and substr($buf, -strlen($state)) == $state) {
                if ($state == 'log') {
                    break;
                }
                // broken delta cases, state == 'text' or 'log'
                if (($p = strrpos($buf, '@')) !== false) {
                    $buf = substr($buf, 0, $p - 1);
                    if ($state == 'text')
                        $state = 'log';
                    else
                        break;
                    if ($state == 'log' and strlen($buf) < strlen($state)) {
                        break;
                    }
                }
                //echo "******".$buf."*******\n";
            }

            while (($p = strrpos($buf, '@')) !== false) {
                if ($buf[$p - 1] == '@') {
                    $buf = substr($buf, 0, $p - 2);
                } else {
                    $buf = substr($buf, 0, $p - 1);
                    if ($state != 'text') {
                        break 2;
                    } else {
                        break;
                    }
                }
                //echo "^^^***".$buf."*******\n";
            }
            if ($p === false)
                break;
        }

        if (($state != 'text' and $state != 'log') or strlen($buf) < strlen($state)) {
            $bs+= 512;
            $state = 'unknown';
            continue;
        }

        if (strlen($buf) > 3 and substr($buf, -3) == 'log') {
            if (($p = strrpos($buf, '@')) !== false) {
                $last = substr($buf, $p + 1);
                $buf = substr($buf, 0, $p + 1);
                $buf.= "\n"; // OK
                $state = 'OK';
            }
        }
        if ($state != 'OK' or $p == false) {
            $bs+= 512;
            $state = 'unknown';
            continue;
        }

        break;
    }

    echo "Done\n";
    preg_match('/^(\d.\d+)$/m', $last, $match);
    $last = $match[1];
    if (!$looks_ok) {
        echo "Broken revision is $last\n";
    } else {
        echo "Last revision is $last\n";
    }
    //echo "\tsearched position $pos, block size = $bs\n";

    $tmpname = tempnam('.', 'RCS');
    if (!$looks_ok) {
        fseek($fp, $pos - $bs, SEEK_END);
        $sz = ftell($fp);
        fseek($fp, 0, SEEK_SET);
        $fixed = fread($fp, $sz + strlen($buf));
        file_put_contents($tmpname, $fixed);
    } else {
        fseek($fp, 0, SEEK_SET);
        $all = fread($fp, $filesize);
        file_put_contents($tmpname, $all);
    }
    fclose($fp);

    // fix revision info
    $fp = fopen($tmpname, 'r');
    if (!is_resource($fp)) {
        echo "Can't open $tmpname\n";
        return false;
    }

    $fixed = '';
    $state = '';

    // search empty string
    while (($line = fgets($fp, 1024)) !== false) {
        $fixed.= $line;
        $l = trim($line);
        if (preg_match('/^\s*$/', $l)) {
            $state = 'found';
            // empty string found
            break;
        }
    }

    if ($state != 'found') {
        // empty string not found!
        echo "empty string not found\n";
        fclose($fp);
        return -2;
    }

    // search version string
    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        // version string
        while (!preg_match('/^(\d\.\d+)$/', $line, $match)) {
            if (preg_match('/^desc\s$/', $line))
                break;
            $fixed.= $line;
            $line = fgets($fp, 1024);
        }
        $tmp = explode('.', $match[1]);
        $next = $tmp[0].'.'.($tmp[1] - 1);

        $fixed.= $line;
        $line = fgets($fp, 1024);
        if (preg_match('@^date\s+([^;]+);(.*)$@', $line, $m)) {
            $t = explode('.', $m[1]);
            $str = $t[0].'/'.$t[1].'/'.$t[2].' '.$t[3].':'.$t[4].':'.$t[5];
            $atime = strtotime($str);
            $remain = $m[2];
        }
        $fixed.= $line;
        $fixed.= fgets($fp, 1024);
        $line = fgets($fp, 1024);
        if (preg_match('/^next\s+(\d\.\d+)?;/', $line, $match)) {
            // is it the last version string ?
            if (!empty($match[1]) && $match[1] === $last) {
                // include last revision info.
                if ($looks_ok) {
                    //echo 'last ver',"\n";
                    $fixed.= $line;
                    $fixed.= fgets($fp, 1024); // empty line
                    while (($line = fgets($fp, 1024)) !== false) {
                        if (preg_match('/^(\d\.\d+)$/', $line, $match)) {
                            $fixed.= $line;
                            break;
                        }
                        $fixed.= $line;
                    }

                    $fixed.= fgets($fp, 1024);
                    $fixed.= fgets($fp, 1024);
                    $line = fgets($fp, 1024);
                }
                $line = "next\t;\n";
                $fixed.= $line;
                $fixed.= fgets($fp, 1024);
                $state = 'fixed';

                break;
            } else {
                if (!empty($match[1])) {
                    $tmp = explode('.', $match[1]);
                    $next = $tmp[0].'.'.($tmp[1] - 1);
                } else {
                    echo "Broken RCS header. try to fill up\n";
                    if (empty($next)) break;
                    while ($next !== $last) {
                        $fixed.= "next\t$next;\n\n$next\n";
                        $tmp = explode('.', $next);
                        if ($tmp[1] == 1) break;
                        $next = $tmp[0].'.'.($tmp[1] - 1);
                        $atime-= 60*60*2;
                        $fixed.= "date\t".date("Y.m.d.H.i.s", $atime).';'.$remain."\n";
                        $fixed.= "branches;\n";
                    }
                    if ($next === $last) {
                        $fixed.= "next\t$next;\n\n$next\n";
                        $atime-= 60*60*2;
                        $fixed.= "date\t".date("Y.m.d.H.i.s", $atime).';'.$remain."\n";
                        $fixed.= "branches;\n";
                        $fixed.= "next\t;\n";
                        $state = "fixed";
                    }
                    break;
                }
            }
        } else {
            break;
        }
        $fixed.= $line;
        $fixed.= fgets($fp, 1024);
    }

    if ($state != 'fixed') {
        // the last version string not found
        echo "The last version not found\n";
        fclose($fp);
        return -2;
    }

    // trash the last remaing version info
    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if (!preg_match('/^\d\.\d+$/', $line)) {
            // broken case. simply ignore
            $fixed.= $line;
            break;
        }
        fgets($fp, 1024);
        fgets($fp, 1024);
        $line = fgets($fp, 1024);
        if (preg_match('/^next\s;/', $line, $match)) {
            fgets($fp, 1024);
            break;
        }
        fgets($fp, 1024);
    }

    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if (preg_match("/^desc\n$/", $line)) {
            $fixed.= $line;
            $line = fgets($fp, 8192);
            if ($line[0] == '@') {
                $fixed.= '@'.str_replace('@', '@@', $pagename)."\n@\n";
                if ($line[1] == '@' && $line[2] == "\n")
                    break;
                // trash remaining desc info.
                while (!feof($fp)) {
                    $line = fgets($fp, 8192);
                    if ($line == "@\n")
                        break;
                }
            }
            break;
        }
        $fixed.= $line;
    }

    // read the remaining version
    while (!feof($fp))
        $fixed.= fread($fp, 4096);
    fclose($fp);

    echo "Fixed!!\n";
    //file_put_contents('tmp,v', $fixed);
    file_put_contents($rcsfile, $fixed);
    touch($rcsfile, $mtime);
    unlink($tmpname);
    return 0;
}

function checkRCSdir($dir, $usleep = 0) {
    $dir = rtrim($dir, '/');
    $handle = opendir($dir);
    if (!is_resource($handle))
      return false;

    set_time_limit(0);
    $count = 0;
    while (($file = readdir($handle)) !== false) {
        if ((($p = strpos($file, '.')) !== false or $file == 'RCS' or $file == 'CVS') and is_dir($dir.'/'.$file)) continue;
        if (substr($file, -2) != ',v') continue; // ignore non rcs files
        $fp = fopen($dir.'/'.$file, 'r');
        if (!is_resource($fp)) continue; // just ignore

        $mtime = filemtime($dir.'/'.$file);

        fseek($fp, -20, SEEK_END);
        $end = fread($fp, 20);
        fclose($fp);
        if (true) {
            if (!preg_match("!@\n\s*$!", $end)) {
                echo "Looks good, anyway try to check/fix... $dir/$file...\n";
            }
            //echo "RCS file for page ".pagename($file).": $dir/$file is broken.\n";
            echo "FIX $dir/$file ... ";

            $ret = fixrcs($dir.'/'.$file, true);
            switch($ret) {
            case 0:
                //
                break;
            case -1:
                @mkdir('bad1');
                echo "ERROR: Buffer not found\n";
                rename($dir.'/'.$file, 'bad1/'.$file);
                touch('bad1/'.$file, $mtime);
                break;
            case -2:
                @mkdir('bad2');
                echo "ERROR: Invalid header\n";
                rename($dir.'/'.$file, 'bad2/'.$file);
                touch('bad2/'.$file, $mtime);
                break;
            case -3:
                @mkdir('empty');
                echo "ERROR: empty\n";
                rename($dir.'/'.$file, 'empty/'.$file);
                touch('empty/'.$file, $mtime);
                break;
            }
        }
        if ($usleep > 0) usleep($usleep);
    }
    closedir($handle);
}

// FIXME
function pagename($key) {
    $key = strtr($key, '_', '%');
    return rawurldecode($key);
}

// vim:et:sts=4:sw=4:
