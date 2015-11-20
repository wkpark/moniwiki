<?php
// Copyright 2003 Sung Kim <hunkim at cs.ucsc.edu>
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// distributable under GPLv2 see COPYING
//
// Since: 2003/12/26
// Modified: 2015/11/19
// Author: Sung Kim <hunkim at cs.ucsc.edu>
// Author: Won-Kyu Park <wkpark at kldp.org>
//
// Param: wikifarm_hostrule='*.wikicore.xyz'
// Param: wikifarm_sitename="Sitename for %2"
// Param: wikifarm_farm_dir=$data_dir.'/wikifarm/%1'
// Param: wikifarm_autocreate=1
// Param: wikifarm_autoseed=0

class WikiFarm_base {
    /**
     * Function - get wikifarm string
     *
     * @param String hostrule (example: f*.kldp.org)
     * @param String hostname (example: foo.kldp.org)
     * @param String str (/var/%1/data)
     * @return String replaced string
     */
    static function format_string($trans, $str) {
        // No sutable arguments
        if (empty($trans) || empty($str)) {
            return false;
        }

        // Replace %0, %1, %2, %3 to real name
        return strtr($str, $trans);
    }

    /**
     * Function - Create wikifarm directory if available
     *
     * @param String data directory
     * @param Boolean copy wikiseed or not
     * @param String wikiseed src directory
     * @return Boolean or String
     */
    static function create_dirs($farm_dir, $conf = array()) {
        // Nothing to do
        if (is_dir($farm_dir)) {
            return true;
        }

        $ret = true;
        if (!is_dir($farm_dir))
            $ret = _mkdir_p($farm_dir);
        if ($ret === false)
            return sprintf("Fail to mkdir(%s)", $farm_dir);

        // set text_dir
        $text_dir = $farm_dir . '/text';

        // create dirs
        $ret = true;
        $dirs = array('text', 'text/RCS', 'cache');
        foreach ($dirs as $d) {
            $dir = $farm_dir .'/'.$d;
            if (!is_dir($dir)) {
                $ret = mkdir($dir);
                if ($ret === false)
                    return sprintf("Fail to mkdir(%s)", $dir);
            }
        }

        // touch editlog
        $ret = touch(self::get_editlog($farm_dir));
        if ($ret === false)
            return "Fail to touch editlog";

        // check wikiseed directory
        if (!empty($conf['wikifarm_seed_dir']))
            $seeddir = $conf['wikifarm_seed_dir'];
        else
            $seeddir = 'wikiseed';

        if (!is_dir($seeddir))
            return true;

        if (!empty($conf['wikifarm_autoseed']))
            $wikiseed = $conf['wikifarm_autoseed'];
        else
            $wikiseed = false;

        // skip to sow wikiseed
        if (!$wikiseed) {
            // base pages
            $files = array('FrontPage', 'RecentChanges', 'FindPage', 'TitleIndex', 'UserPreferences');
        } else {
            // copy wikiseed
            $handle = opendir($seeddir);
            $files = array();
            while ($file = readdir($handle)) {
                // We don't copy directories
                if (is_dir($seeddir.'/'.$file)) {
                    continue;
                }

                $files[] = $file;
            }
            closedir($handle);
        }

        foreach ($files as $file) {
            // FIXME: Handle errors
            copy($seeddir.'/'.$file, $text_dir.'/'.$file);

            // preserve seed mtime
            $mtime = filemtime($seeddir.'/'.$file);
            touch($text_dir.'/'.$file, $mtime);
        }

        return true;
    }

    /**
     * Function - Return editlog for this wiki
     *
     * @param String farm_dir
     * @return String editlog
     */
    static function get_editlog($farm_dir) {
        return $farm_dir.'/editlog';
    }

    /**
     * Function - Check if it is valid wikifarm host
     *
     * @param String hostrule
     * @param String hostname
     * @return true if it is valid host. Otherwise false.
     */
    static function is_valid_farmname($hostrule, $hostname) {
        // Check if it is my host
        if (empty($hostname)) {
            $hostname = $_SERVER['HTTP_HOST'];
        }

        $test = @preg_match($hostrule, 'TeSt');
        if ($test !== false) {
            if (preg_match($hostrule, $hostname))
                return self::_get_translate_table($hostname);
            return false;
        }

        // split hostname
        $host_arr = explode('.', $hostname); // foo.bar.com
        $host_arr = array_reverse($host_arr); // com, bar, foo

        $tmp = explode('.', $hostrule); // wiki, *, bar, com
        $farm_arr = array_reverse($tmp); // com, bar, *, wiki
        if (count($farm_arr) != count($host_arr))
            return false;

        for ($i = 0; $i < count($host_arr); $i++) {
            $farm_one_name = $farm_arr[$i];
            // We expecting * or real name
            if ($farm_one_name == '*') { // Assume match
                continue;
            }

            $host_one_name = $host_arr[$i];

            // Compare each name
            if (!empty($host_one_name) && strcasecmp($farm_one_name, $host_one_name)) {
                return false;
            }
        }

        // Passed all match tests
        return self::_get_translate_table($hostname);
    }

    static function _get_translate_table($hostname) {
        // Let's make a translate table
        $chunks = explode('.', $hostname);
        $trs = array('%0'=>$hostname);
        for ($i = 1; $i <= count($chunks); $i++) {
            $trs['%'.$i] = $chunks[$i - 1];
        }

        return $trs;
    }

    static function setup($host, $conf = array()) {
        // setup Virtual directory and name
        if ($conf['wikifarm_hostrule'] && $conf['wikifarm_farm_dir'] &&
                false !== ($trans = self::is_valid_farmname($conf['wikifarm_hostrule'], $host))) {
            $farm_dir = self::format_string($trans, $conf['wikifarm_farm_dir']);

            // check already saved config file
            if (file_exists($farm_dir.'/config.php')) {
                $vars = _load_php_vars($farm_dir.'/config.php', $conf);
                if (!empty($vars['text_dir']))
                    return $vars;
            }
        } else {
            return false;
        }

        // create directory structure automatically.
        if ($conf['wikifarm_autocreate'] && !is_dir($farm_dir)) {
            $ret = self::create_dirs($farm_dir, $conf);
            if (is_string($ret))
                return $ret;
        }

        // Assing dirs for wikifarm host
        $text_dir = $farm_dir.'/text';
        $cache_dir = $farm_dir.'/cache';

        // Fill wikifarm sitename if it is defined.
        if (!empty($conf['wikifarm_sitename'])) {
            $sitename = self::format_string($trans, $conf['wikifarm_sitename']);
        }

        // Set the editlog file
        $editlog_name = self::get_editlog($farm_dir);

        if (is_dir($farm_dir)) {
            // Assing config variables
            $vars = array();
            $vars['farm_dir'] = $farm_dir;
            $vars['text_dir'] = $text_dir;
            $vars['cache_dir'] = $cache_dir;
            $vars['sitename'] = $sitename;
            $vars['editlog_name'] = $editlog_name;

            $date = date('Y-m-d h:i:s');
            $lines = array();
            $lines[] = '<'.'?php'."\n";
            $lines[] = <<<HEADER
// This is a config.php file for this wiki farm
// $date by monisetup.php\n
HEADER;
            foreach ($vars as $k=>$v) {
                if ($k != 'farm_dir')
                    $v = str_replace($farm_dir, '$farm_dir', $v);
                $lines[] = '$'.$k.'="'.$v.'"'.";\n";
            }

            // write config.php
            $fp = fopen($farm_dir.'/config.php', 'w');
            if (is_resource($fp)) {
                fwrite($fp, implode('', $lines));
                fclose($fp);
            }

            return $vars;
        } else {
            // No farm_dir for wikifarm host
            return "The directories for this Wiki is not created yet.<br />\n".
                    "Make sure there is no typo in the URL and ask the administrator";
        }
        return false;
    }
}

// vim:et:sts=4:sw=4:
