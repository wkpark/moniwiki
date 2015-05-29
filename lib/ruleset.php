<?php

/**
 * Parse ruleset
 *
 * @author wkpark at kldp.org
 * @date 2015/05/18
 *
 * @param   string  $filename ruleset config file
 * @param   array   $params to return dependency files
 * @param   function $validator to check validity of ruleset
 * @return  array   ruleset
 */
function parse_ruleset($filename, $validator = null, $params = array()) {
    $lines = file($filename);

    $rules = array();
    for ($i = 0; $i < sizeof($lines); $i++) {
        if ($lines[$i][0] == '#')
            continue;
        $line = trim($lines[$i]);
        $line = preg_replace('@\s*#.*$@', '', $line); // trim out # comment
        if (!isset($line[0]))
            continue;

        $key = strtok($line, " \t");
        $val = strtok('');
        $key = strtolower($key);
        if (preg_match('@(options?)$@', $key, $m)) {
            $key = substr($key, 0, -strlen($m[1]));
            $vals = preg_split('/\s+/', trim($val));

            // set keyword options
            if (!isset($rules[$key.'option']))
                $rules[$key.'option'] = array();
            $rules[$key.'option'] = array_merge($rules[$key.'option'], $vals);
            continue;
        } else {
            if (isset($rules[$key.'option']) && in_array('raw', $rules[$key.'option']))
                $vals = array(trim($val));
            else
                $vals = preg_split('/\s+/', trim($val));
        }

        // FoobarFile
        if (preg_match('@file$@', $key)) {
            $key = substr($key, 0, -4);
            $file = $val;
            if (!file_exists($file)) {
                $file = dirname($filename).'/'.$val;
                if (!file_exists($file)) {
                    trigger_error(sprintf(_("File not found %s"), $file));
                    // file not found.
                    continue;
                }
            }
            // depend file
            $params['deps'][] = $file;

            $list = file($file);
            $vals = array();
            for ($k = 0; $k < sizeof($list); $k++) {
                $line = trim($list[$k]);
                if ($line[0] == '#')
                    continue;
                $line = preg_replace('@\s*#.*$@', '', $line); // trim out # comment
                if (!isset($line[0]))
                    continue;
                $vals[] = $line;
            }
        }
        // empty case
        if (sizeof($vals) == 0)
            continue;

        if (!isset($rules[$key]))
            $rules[$key] = array();
        $rules[$key] = array_merge($rules[$key], $vals);
    }

    if ($validator == null)
        return $rules;

    // check validity
    foreach ($rules as $k=>$v) {
        if (!isset($validator[$k]))
            continue;

        $valid = array();
        for ($i = 0; $i < sizeof($v); $i++) {
            if (($isvalid = 'is_valid_'.$validator[$k]) and $isvalid($v[$i])) {
                $valid[] = $v[$i];
            } else {
                trigger_error(sprintf(_("Fail to parse ruleset %s %s"), $isvalid, $v[$i]));
            }
        }
        $rules[$k] = $valid;
    }
    return $rules;
}

function is_valid_ip_ruleset($rule) {
    return preg_match('/^[0-9]{1,3}(\.(?:[0-9]{1,3})){0,3}
        (\/([0-9]{1,3}(?:\.[0-9]{1,3}){3}|[0-9]{1,2}))?$/x', $rule);
}

// vim:et:sts=4:sw=4:
