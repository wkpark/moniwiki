<?php
/**
 * Copyright 2003-2016 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2. see COPYING
 *
 * @author  Won-Kyu Park <wkpark@gmail.com
 * @desc    raw config.php parser.
 */

class Config_base {
    function Config_base($configfile = 'config.php', $vars = array())
    {
        if (file_exists($configfile)) {
            $this->config = $this->_getConfig($configfile, $vars);
            $this->rawconfig = $this->_rawConfig($configfile);
            $this->configdesc = $this->_getConfigDesc($configfile);
        } else {
            $this->config = array();
            $this->rawconfig = array();
        }
    }

    function _rawConfig($configfile, $options = array())
    {
        $lines = file($configfile);
        $key = '';
        $tag = '';
        foreach ($lines as $line) {
            $line = rtrim($line)."\n"; // for Win32

            if (!$key and $line[0] != '$') continue;
            if ($key) {
                $val .= $line;
                if (!preg_match("/$tag\s*;(\s*#.*)?\s*$/", $line))
                    continue;
            } else {
                list($key, $val) = explode('=', substr($line, 1), 2);
                $key = trim($key);
                if (!preg_match('/\s*;(\s*#.*)?$/', $val)) {
                    if (substr($val, 0, 3) == '<<<') {
                        $tag = '^'.substr(rtrim($val), 3);
                    } else {
                        $val = ltrim($val);
                        $tag = '';
                    }
                    continue;
                }
            }

            if ($key) {
                $val = preg_replace(array('@<@', '@>@'), array('&lt;', '&gt;'), $val);
                $val = rtrim($val);
                $val = preg_replace('/\s*;(\s*#.*)?$/', '', $val);
                $config[$key] = $val;
                $key = '';
                $tag = '';
            }
        }
        return $config;
    }

    function _getConfig($configfile, $vars = array()) {
        if (!file_exists($configfile))
            return array();

        extract($vars);
        unset($vars);
        include($configfile);
        unset($configfile);
        $config = get_defined_vars();

        return $config;
    }

    function _getConfigDesc($configfile)
    {
        $lines = file($configfile);
        $key = '';
        $desc = array();
        $multi = array();
        foreach ($lines as $line) {
            $line = rtrim($line)."\n"; // for Win32
            if (!$key && $line[0] != '$') {
                if (!isset($line[1]) || $line[1] != '$')
                    continue;
            }
            if ($key) {
                $val .= $line;
                if (!preg_match("/$tag\s*;(\s*#.*)?\s*$/", $line))
                    continue;
            } else {
                list($key, $val) =explode('=', substr($line, 1), 2);
                $key = trim($key);
                if ($key[0] == '$')
                    $key = substr($key, 1);

                if (!preg_match('/\s*;\s*(#.*)?$/', $val)) {
                    if (substr($val, 0, 3) == '<<<')
                        $tag = '^'.substr(rtrim($val), 3);
                    else
                        $tag = '';
                    continue;
                }
            }

            if ($key) {
                preg_match('/\s*;\s*#(.*)?$/', rtrim($val), $match);
                if (!empty($match[1])) {
                    if (!isset($multi[$key])) {
                        $multi[$key] = 0;
                    } else {
                        $multi[$key]++;
                        $key .= $multi[$key];
                    }
                    $desc[$key] = '#'.$match[1];
                }
                $key = '';
                $tag = '';
            }
        }
        return $desc;
    }

    function _quoteConfig($config) {
        foreach ($config as $k=>$v) {
            if (is_string($v)) {
                $v='"'.$v.'"'; // XXX need to check quotes
            } else if (is_bool($v)) {
                if ($v) $nline="true";
                else $v="false";
            }
            $config[$k] = $v;
        }
        return $config;
    }

    function _genRawConfig($newconfig, $mode = 0, $configfile = 'config.php', $default = 'config.php.default')
    {
        if (!empty($newconfig['admin_passwd']))
            $newconfig['admin_passwd'] = crypt($newconfig['admin_passwd'], md5(time()));
        if (!empty($newconfig['purge_passwd']))
            $newconfig['purge_passwd'] = crypt($newconfig['purge_passwd'], md5(time()));

        if ($mode == 1) {
            $newconfig = $this->_quoteConfig($newconfig);
        } else {
            if (isset($newconfig['admin_passwd']))
                $newconfig['admin_passwd'] = "'".$newconfig['admin_passwd']."'";
            if (isset($newconfig['purge_passwd']))
                $newconfig['purge_passwd'] = "'".$newconfig['purge_passwd']."'";
        }

        if (file_exists($configfile))
            $conf_file = $configfile;
        else if (file_exists($default))
            $conf_file = $default;
        else
            return $this->_genRawConfigSimple($newconfig);

        $lines = file($conf_file);

        $config = array();
        $multi = array();
        $desc = array();
        $nlines = '';
        $key = '';
        $tag = '';
        foreach ($lines as $line) {
            $line = rtrim($line)."\n"; // for Win32

            if (!$key) {
                // first line
                if ($line{0} == '<' and $line{1} == '?') {
                    $date = date('Y-m-d h:i:s');
                    $nlines = [];
                    $nlines[] = '<'.'?php'."\n";
                    $nlines[] = <<<HEADER
# This is a config.php file
# $date updated\n
HEADER;
                    continue;
                } else if (preg_match('/^(#{1,}\s*)?\$[a-zA-Z][a-zA-Z0-9_]*\s*=/', $line, $m)) {
                    $marker = isset($m[1]) ? $m[1] : '';
                    if ($marker != '')
                        $mre = '#{1,}';
                    else
                        $mre = '';
                    $mlen = strlen($marker.'$');
                } else {
                    $nlines[] = $line;
                    continue;
                }
            }

            if ($key) {
                $val .= $line;
                if (!preg_match("/$tag\s*;(\s*(?:#|\/\/).*)?\s*$/", $line, $m)) continue;
                $mre = '';
                if (!isset($multi[$key])) {
                    $multi[$key] = 0;
                    $keyid = $key;
                } else {
                    $multi[$key]++;
                    $keyid = $key.$multi[$key];
                }

                $desc[$keyid] = isset($m[1]) ? rtrim($m[1]) : '';
            } else {
                list($key, $val) = explode('=', substr($line, $mlen), 2);
                $key = trim($key);
                if (!preg_match('/(\s*;(\s*(?:#|\/\/).*)?)$/', $val, $match)) {
                    if (substr($val, 0, 3) == '<<<') {
                        $tag = '^'.$mre.substr(rtrim($val), 3);
                    } else {
                        $val = ltrim($val);
                        $tag = '';
                    }
                    continue;
                } else {
                    $val = substr($val, 0, -strlen($match[1]) - 1);
                    if (isset($match[2])) {
                        if (!isset($multi[$key])) {
                            $multi[$key] = 0;
                            $keyid = $key;
                        } else {
                            $multi[$key]++;
                            $keyid = $key.$multi[$key];
                        }
                        $desc[$keyid] = rtrim($match[2]);
                    } else {
                        $multi[$key] = 0;
                        $keyid = $key;
                    }
                }
            }

            if (trim($key)) {
                $t = true;
                if (isset($newconfig[$key])) {
                    if (!isset($config[$key])) {
                        $val = $newconfig[$key];
                        $newconfig[$key] = NULL;
                        $marker = ''; # uncomment marker
                    }
                } else {
                    $val = preg_replace(array('@<@', '@>@'), array('&lt;', '&gt;'), $val);
                    $val = rtrim($val);
                    if (empty($marker))
                        $val = preg_replace('/\s*;(\s*(?:#|\/\/).*)?$/','',$val);
                }
                $val = str_replace(array('&lt;', '&gt;'), array('<', '>'), $val);
                if (isset($config[$key])) {
                    $val = rtrim($val);
                    $val = str_replace("\n", "\n#", $val);
                    if (!$marker) $marker = '#';
                    $nline = $marker."\$$key=$val;"; # XXX
                    if (isset($this->configdesc[$keyid]))
                        $nline .= ' '.$this->configdesc[$keyid];
                    else if (isset($desc[$keyid]))
                        $nline .= $desc[$keyid];
                    $nline .= "\n";
                    $t = NULL;
                } else if (empty($marker) and preg_match("/^<{3}([A-Za-z0-9]+)\s.*\\1\s*$/s", $val, $m)) {
                    $config[$key] = $val;
                    $save_val = $val;
                    $val = str_replace("$m[1]",'',substr($val,3));
                    $val = str_replace('"','\"',$val);
                    $t = eval("\$$key=\"$val\";");
                    $val = $save_val;
                    $nline = "\$$key=$val;\n";
                } else if ($marker) {
                    $val = str_replace('&gt;','>',$val);
                    $nline = $marker."\$$key=$val";
                    if (empty($tag)) $nline .=';';
                    if (isset($this->configdesc[$keyid]))
                        $nline .= ' '.$this->configdesc[$keyid];
                    else if (isset($desc[$keyid]))
                        $nline .= $desc[$keyid];
                    $nline .= "\n";
                    $config[$key] = $val;
                    $t = NULL;
                } else if (is_string($val)) {
                    $val = str_replace('&gt;', '>', $val);
                    if (strpos($val, "\n") === false) {
                        $t = eval("\$$key=$val;");
                    } else {
                        $t = @eval("\$$key=$val;");
                    }
                    $nline = "\$$key=$val;";
                    if (isset($this->configdesc[$keyid]))
                        $nline .= ' '.$this->configdesc[$keyid];
                    else if (isset($desc[$keyid]))
                        $nline .= $desc[$keyid];
                    $nline .= "\n";
                } else {
                    $t = @eval("\$$key=$val;");
                }
                if ($t === NULL) {
                    $nlines[] = $nline;
                    $config[$key] = $val;
                }
                else
                    print "ERROR: \$$key =$val;\n";
                $key = '';
                $tag = '';
            }
        }
        if (!empty($newconfig)) {
            foreach ($newconfig as $k=>$v) {
                if ($v != NULL)
                    $nlines[] = '$'.$k.'='.$v.";\n";
            }
        }

        return join('', $nlines);
    }

    function _genRawConfigSimple($config)
    {
        $lines = array("<?php\n", "# automatically generated\n");
        while (list($key, $val) = each($config)) {
            if ($key == 'admin_passwd' or $key == 'purge_passwd')
                $val = "'".crypt($val,md5(time()))."'";
            $val = str_replace('&lt;', '<', $val);
            if (preg_match("/^<{3}([A-Za-z0-9]+)\s.*\\1\s*$/s", $val, $m)) {
                $save_val = $val;
                $val = str_replace("$m[1]", '', substr($val, 3));
                $val = preg_quote($val, '"');
                $t = @eval("\$$key=\"$val\";");
                $val = $save_val;
            } else if (is_string($val)) {
                $val = str_replace('&gt;','>',$val);
                if (strpos($val,"\n") === false) {
                    $t = eval("\$$key=$val;");
                } else {
                    $t = @eval("\$$key=$val;");
                }
            } else {
                $t = @eval("\$$key=$val;");
            }
            if ($t === NULL)
                $lines[] = "\$$key=$val;\n";
            else
                print "<font color='red'>ERROR:</font> <tt>\$$key=$val;</tt><br/>";
        }
        $lines[] = "\n";
        return implode('',$lines);
    }
}

// vim:et:sts=4:sw=4:
