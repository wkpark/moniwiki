<?php
//
// Metadata module for MoniWiki
//
// $Id$

function getMetadata($raw, $mode = 1, $params = false) {

    $metas = explode("\n", $raw);
    $meta = array();
    foreach ($metas as $line) {
        if (!trim($line)) break;
        if (($p = strpos($line, ':')) !== false) {
            list($mykey, $val) = explode(':', $line, 2);
            $val = trim($val);
            # strip leading bullet' * '
            $mykey = preg_replace('/^\s*(\*|\d.)?\s*/', '', $mykey);
            if (strpos($mykey,' ') !== false)
                $mykey=str_replace(' ', '-', ucwords($mykey));

            $mymeta = array();
            if (trim($val)) {
                $vals = explode(',', $val);
                foreach ($vals as $v) {
                    $v = trim($v);
                    if ($v) $mymeta[] = trim($v);
                }
                $meta[$mykey] = $mymeta;
            }

#            if (strpos($val,' ')!== false) {
#                #$val=str_replace('"','\"',$val);
#                $val=str_replace('"','',$val);
#                $val='"'.trim($val).'"';
#            }
#            $meta[$mykey][]=$val;
        }
    }
    if ($mode == 1) {
        foreach ($meta as $k => $v) {
            array_unique($v);
            $val = implode(', ', $v);
            $metadata[$k] = $val;
        }
        return $metadata;
    }
    return ($meta);
}


function _get_metadata($body) {
    $key = '';
    $val = '';
    $meta = array();
    $metaok = 0;
    while(!empty($body)) {
        list($line, $body) = explode("\n", $body, 2);
        if ($key and ($line[0] == ' ' or $line[0] == "\t")) {
            $meta[$key].= $line . "\n";
            continue;
        }
        if (!trim($line)) {
            $metaok = 1;
            break;
        }
        $key = strtok($line, ' ');
        $val = rtrim(strtok(''));
        if ($key == 'From') { # mail type
            if (empty($meta['magic'])) {
                $meta['magic'] = $line; continue;
            } else break;
        }
        if (preg_match('/(^[A-Z][a-zA-Z0-9\-]+):$/', $key, $m))
            $meta[$m[1]] = $val;
        else
            break;
    }

    if ($metaok)
        return array($meta, $body);

    return array(null, null);
}

// vim:et:sts=4:sw=4:
?>
