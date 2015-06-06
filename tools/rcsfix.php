<?php
/**
 * a simple broken RCS fixer by wkpark at gmail.com (2013/05/08)
 *
 * License: GPLv2
 */

function fixrcs($rcsfile) {
    $fp = fopen($rcsfile, 'rw');
    if (!is_resource($fp)) return -1;

    fseek($fp, -2, SEEK_END);
    $end = fread($fp, 2);

    if ($end == "@\n") {
        fclose($fp);
        return 0; // no need to fix RCS file
    }

    $bs = 1024;
    $pos = 0;
    $state = 'unknown';
    $buf = '';

    while(1) {
        echo '.';
        fseek($fp, $pos - $bs, SEEK_END);
        $buf = fread($fp, 1024);
        if ($buf[0] == '@') { $bs+= 512; continue; }

        if (($p = strrpos($buf, '@')) !== false and $buf[$p - 1] != '@') {
            $buf = substr($buf, 0, $p - 1);
            $state = 'found';
        }
        if ($state != 'found' or $p == false or strlen($buf) < 3) {
            $pos-= $bs;
            $bs+= 1024;
            continue;
        }

        if (substr($buf, -4) == 'text') {
            if (($p = strrpos($buf, '@')) !== false) {
                $buf = substr($buf, 0, $p - 1);
            }
            if (($p = strrpos($buf, '@')) !== false and $buf[$p - 1] != '@') {
                $buf = substr($buf, 0, $p - 1);
                $state = 'next';
            } else {
                $state = 'fail';
            }
        }
        if ($state != 'found' and $state != 'next' or $p == false or strlen($buf) < 3) {
            $pos-= $bs;
            $bs+= 1024;
            continue;
        }

        if (strlen($buf) > 3 and substr($buf, -3) == 'log') {
            if (($p = strrpos($buf, '@')) !== false) {
                $last = substr($buf, $p+1);
                $buf = substr($buf, 0, $p+1);
                $buf.= "\n"; // OK
                $state = 'OK';
            }
        }
        if ($state != 'OK' or $p == false) {
            $pos-= $bs;
            $bs+= 1024;
            continue;
        }

        break;
    }

    echo "Done\n";
    preg_match('/^(\d.\d+)$/m', $last, $match);
    $last = $match[1];
    echo "broken revision is $last\n";
    echo "\tsearched position $pos, block size = $bs\n";

    fseek($fp, $pos - $bs, SEEK_END);
    $sz = ftell($fp);
    fseek($fp, 0, SEEK_SET);
    $fixed = fread($fp, $sz + strlen($buf));
    file_put_contents('tmp,v', $fixed);
    fclose($fp);

    // fix revision info
    $fp = fopen('tmp,v', 'r');
    if (!is_resource($fp)) return false;

    $fixed = '';
    $state = '';
    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        $fixed.= $line;
        if (preg_match('/^\d\.\d+$/', $line)) {
            $fixed.= fgets($fp, 1024);
            $fixed.= fgets($fp, 1024);
            $fixed.= fgets($fp, 1024);
            $fixed.= fgets($fp, 1024);
            $state = 'found';
            break;
        }
    }
    if ($state != 'found') {
        fclose($fp);
        return -2;
    }

    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if (!preg_match('/^(\d\.\d+)$/', $line, $match)) {
            break;
        }
        $fixed.= $line;
        $fixed.= fgets($fp, 1024);
        $fixed.= fgets($fp, 1024);
        $line = fgets($fp, 1024);
        if (preg_match('/^next\s(\d\.\d+)?;/', $line, $match)) {
            if ($match[1] == $last) {
                $line = "next\t;\n";
                $fixed.= $line;
                $fixed.= fgets($fp, 1024);
                $state = 'fixed';
                break;
            }
        } else {
            break;
        }
        $fixed.= $line;
        $fixed.= fgets($fp, 1024);
    }

    if ($state != 'fixed') {
        fclose($fp);
        return -2;
    }

    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if (!preg_match('/^\d\.\d+$/', $line)) {
            fclose($fp);
            return -2;
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

    while (!feof($fp))
        $fixed.= fgets($fp, 1024);
    fclose($fp);

    echo "Fixed!!\n";
    file_put_contents('fixed,v', $fixed);
    return 0;
}

//require_once("config.php");
//$rcsfile = './data/text/RCS/_ec_97_84_ec_83_81_ed_98_84,v';
//$rcsfile = './data/text/RCS/_ed_8b_b0_ec_95_84_eb_9d_bc_28_ec_95_84_ec_9d_b4_eb_8f_8c_29,v';
//echo $rcsfile."\n";
array_shift($argv);

if (count($argv) == 0) {
    echo "Usage: php $argv[0] <broken rcs file path>...\n";
    exit;
}

foreach ($argv as $file) {
    if (empty($file[0]) or !file_exists($file)) {
        echo "ERROR: file $file does not found !\n";
        continue;
    }

    $ret = fixrcs($file);
    if ($ret == -2) {
        echo "ERROR: Invalid header\n";
    }
}

// vim:et:sts=4:sw=4:
