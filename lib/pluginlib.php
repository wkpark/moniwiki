<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * plugin utils extracted from the Formatter class or wiki.php
 *
 * @since 2015/12/19
 * @since 1.3.0
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2a
 *
 */

/**
 * get macro/action plugins
 *
 * @param macro/action name
 * @return a basename of the plugin or null or false(disabled)
 */
function getPlugin($pluginname, $property = false) {
    static $plugins;
    static $properties;

    if ($pluginname === true)
        return sizeof($plugins);

    if (empty($plugins)) {
        global $Config;

        $cp = new Cache_text('settings', array('depth'=>0));

        $plugins = $cp->fetch('plugins');
        $properties = $cp->fetch('properties');
        if ($plugins === false || $properties === false) {
            initPlugin();
            $plugins = $cp->fetch('plugins');
            $properties = $cp->fetch('properties');
        }

        if (!empty($Config['myplugins']) and is_array($Config['myplugins']))
            $plugins = array_merge($plugins, $Config['myplugins']);

        // set dummy plugin for empty case
        if (empty($plugins)) {
            $plugins['_dummy'] = '_dummy';
            $properties['_dummy'] = null;
        }
    }

    $name = strtolower($pluginname);
    if ($property)
        return isset($properties[$name]) ? $properties[$name] : null;
    return isset($plugins[$name]) ? $plugins[$name] : null;
}

/**
 * get Hook
 * @since 1.3.0
 * @param string hook name
 * @return array or false plugins
 */
function getHooks($hook) {
    static $hooks;

    if (empty($hooks)) {
        global $Config;

        $cp = new Cache_text('settings', array('depth'=>0));

        $hooks = $cp->fetch('hooks');
        if ($hooks === false) {
            initPlugin();
            $hooks = $cp->fetch('hooks');
        }

        // set dummy hook for empty case
        if (empty($hooks))
            $hooks['_dummy'] = null;
    }

    if (isset($hooks[$hook]))
        return $hooks[$hook];
    return false;
}

/**
 * Parse plugin files directly to get plugin informations.
 *
 * @author Won-Kyu Park
 * @since 1.3.0
 */
function initPlugin() {
    global $Config;

    // FIXME
    require_once(dirname(__FILE__).'/../plugin/admin.php');

    $cp = new Cache_text('settings', array('depth'=>0));

    $plugins = array();
    $properties = array();
    $hooks = array();

    if (!empty($Config['include_path']))
        $dirs = explode(':', $Config['include_path']);
    else
        $dirs = array('.');

    $deps = array();
    foreach ($dirs as $dir) {
        $handle = @opendir($dir.'/plugin');
        if (!is_resource($handle)) continue;
        if (file_exists($dir.'/plugin/.stamp'))
            $deps[] = $dir.'/plugin/.stamp'; // for dir mtime not supported case
        else
            $deps[] = $dir.'/plugin/.'; // dir mtime
        while ($file = readdir($handle)) {
            if (is_dir($dir."/plugin/$file")) continue;

            // parse php file directly
            $fp = fopen($dir.'/plugin/'.$file, 'r');
            if (!is_resource($fp)) continue;

            $name = substr($file, 0, -4);

            $type = 0;

            while(!feof($fp)) {
                $line = fgets($fp, 1024);
                if (!($type & 1) and preg_match('/^\s*function\smacro_'.$name.'\s*\(/i', $line)) {
                    $type |= 1; // macro
                } else if (!($type & 2) and preg_match('/^\s*function\sdo_'.$name.'\s*\(/i', $line)) {
                    $type |= 2; // plugin
                } else if (!($type & 4) and preg_match('/^\s*function\shook_'.$name.'\s*\(/i', $line)) {
                    $type |= 4; // hook since 1.2.6
                }
            }
            fclose($fp);

            if ($type > 0) {
                $plugins[strtolower($name)] = $name;
                $properties[strtolower($name)] = array('name'=>$name, 'type'=>$type);
                if ($type & 4) {
                    // get hook information.
                    $info = get_plugin_info($dir.'/plugin/'.$file);
                    if (isset($info['Hooks'])) {
                        $hook = preg_split('/\s*,\s*/', $info['Hooks']);
                        foreach ($hook as $h) {
                            if (!isset($hooks[$h]))
                                $hooks[$h] = array();
                            $hooks[$h][strtolower($name)] = isset($info['Priority']) ? $info['Priority'] : 500;
                        }
                    }
                }
            }
        }
    }

    // get predefined or already included macros
    $tmp = get_defined_functions();
    foreach ($tmp['user'] as $u) {
        if (preg_match('@^(macro|do|hook)_(.*)$@', $u, $m)) {
            if (!isset($properties[strtolower($m[2])])) {
                $properties[strtolower($m[2])] = array('name'=>$m[2], 'type'=>0);
            }
            if (!isset($plugins[strtolower($m[2])]))
                $plugins[strtolower($m[2])] = $m[2];
            $properties[strtolower($m[2])]['type'] |= ($m[1] == 'macro') ? 1 : (($m[1] == 'do') ? 2 : 4);
        }
    }

    if (!empty($plugins)) {
        $params = array('deps'=>$deps);
        $cp->update('plugins', $plugins, 0, $params);
        $cp->update('properties', $properties, 0, $params);
        $cp->update('hooks', $hooks, 0, $params);
    }
}

/**
 * get processor
 *
 * @since 2003/05/16
 */
function getProcessor($pro_name) {
    static $processors = array();

    if (is_bool($pro_name) and $pro_name)
        return sizeof($processors);
    $prog = strtolower($pro_name);
    if (!empty($processors))
        return isset($processors[$prog]) ? $processors[$prog] : '';

    global $Config;

    $cp = new Cache_text('settings', array('depth'=>0));

    if ($processors = $cp->fetch('processors')) {
        if (is_array($Config['myprocessors']))
            $processors = array_merge($processors,$Config['myprocessors']);
        return isset($processors[$prog]) ? $processors[$prog]:'';
    }
    if (!empty($Config['include_path']))
        $dirs = explode(':', $Config['include_path']);
    else
        $dirs = array('.');

    foreach ($dirs as $dir) {
        $handle = @opendir($dir.'/plugin/processor');
        if (!$handle) continue;
        while ($file = readdir($handle)) {
            if (is_dir($dir."/plugin/processor/$file")) continue;
            $name = substr($file, 0, -4);
            $processors[strtolower($name)] = $name;
        }
    }

    if ($processors)
        $cp->update('processors', $processors);
    if (is_array($Config['myprocessors']))
        $processors = array_merge($processors, $Config['myprocessors']);

    return isset($processors[$prog]) ? $processors[$prog] : '';
}

/**
 * get Filer
 * @since 2005-04-12
 *
 */
function getFilter($filtername) {
    static $filters = array();

    if (!empty($filters))
        return $filters[strtolower($filtername)];

    global $Config;
    if (!empty($Config['include_path']))
        $dirs = explode(':', $Config['include_path']);
    else
        $dirs = array('.');

    foreach ($dirs as $dir) {
        $handle = @opendir($dir.'/plugin/filter');
        if (!$handle) continue;
        while ($file = readdir($handle)) {
            if (is_dir($dir."/plugin/filter/$file"))
                continue;
            $name = substr($file, 0, -4);
            $filters[strtolower($name)] = $name;
        }
    }

    if (!empty($Config['myfilters']) and is_array($Config['myfilters']))
        $filters = array_merge($filters, $Config['myfilters']);

    return $filters[strtolower($filtername)];
}

/**
 * Call macro
 * @since 1.3.0
 *
 */
function call_macro($formatter, $macro, $value = '', $params = array()) {
    preg_match("/^([^\(]+)(\((.*)\))?$/", $macro, $match);
    if (empty($value) and isset($match[2])) { #strpos($macro,'(') !== false)) {
        $name = $match[1];
        $args = empty($match[3]) ? true : $match[3];
    } else {
        $name = $macro;
        $args = $value;
    }

    // check alias
    $myname = getPlugin($name);
    if (empty($myname)) return '[['.$macro.']]';
    $macro_name = '';
    if (strtolower($name) != strtolower($myname))
        $macro_name = strtolower($name);
    $name = $myname;

    if (isset($macro_name[0]) and is_array($params))
        $params['macro_name'] = $macro_name;

    // macro ID
    $formatter->mid = !empty($params['mid']) ? $params['mid']:
        (!empty($formatter->mid) ? ++$formatter->mid : 1);

    $bra = '';
    $ket = '';
    if (!empty($formatter->wikimarkup) and $macro != 'attachment' and empty($params['nomarkup'])) {
        $markups = str_replace(array('=', '-', '<'), array('==', '-=', '&lt;'), $macro);
        $markups = preg_replace('/&(?!#?[a-z0-9]+;)/i', '&amp;', $markups);
        $bra = "<span class='wikiMarkup'><!-- wiki:\n[[$markups]]\n-->";
        $ket = '</span>';
        $params['nomarkup'] = 1; // for the attachment macro
    }

    if (!function_exists('macro_'.$name)) {
        $np = getPlugin($name);
        if (empty($np))
            return '[['.$macro.']]';
        include_once(dirname(__FILE__).'/../plugin/'.$np.'.php');
        if (!function_exists('macro_'.$np))
            return '[['.$macro.']]';
        $name = $np;
    }

    // FIXME
    //$ret = call_user_func_array('macro_'.$name, array($formatter, $args, $params));
    $ret = call_user_func_array('macro_'.$name, array(&$formatter, $args, &$params));
    if ($ret === false)
        return false;
    if (is_array($ret))
        return $ret;
    return $bra.$ret.$ket;
}

/**
 * Call macro
 * @since 1.3.0
 *
 */
function call_processor($formatter, $processor, $value, $params = array()) {
    $bra = '';
    $ket = '';
    if (!empty($formatter->wikimarkup) and empty($params['nomarkup'])) {
        if (!empty($params['type']) and $params['type'] == 'inline') {
            $markups = str_replace(array('=', '-', '&', '<'), array('==', '-=', '&amp;', '&lt;'), $value);
            $bra = "<span class='wikiMarkup' style='display:inline'><!-- wiki:\n".$markups."\n-->";
        } else {
            if (!empty($params['nowrap']) and
                !empty($formatter->pi['#format']) and $processor == $formatter->pi['#format'])
            {
                $btag = '';
                $etag = '';
            } else {
                $btag = '{{{';
                $etag = '}}}';
            }
            $notag = '';
            if ($value{0}!='#' and $value{1}!='!')
                $notag = "\n";
            $markups = str_replace(array('=', '-', '&', '<'), array('==', '-=', '&amp;', '&lt;'), $value);
            $bra = "<span class='wikiMarkup'><!-- wiki:\n".$btag.$notag.$markups.$etag."\n-->";
        }
        $ket = '</span>';
    }

    $pf = $processor;
    if (!($f = function_exists('processor_'.$processor)))
        $pf = getProcessor($processor);
    if (empty($pf)) {
        $ret = call_user_func('processor_plain', $formatter, $value, $params);
        return $bra.$ret.$ket;
    }
    if (!$f and !($c = class_exists('processor_'.$pf))) {
        include_once(dirname(__FILE__)."/../plugin/processor/$pf.php");
        $name = 'processor_'.$pf;
        if (!($f = function_exists($name)) and !($c = class_exists($name))) {
            $processor = 'plain';
            $f = true;
        }
    }

    if ($f) {
        // FIXME.
        if (!empty($formatter->use_smartdiff) and
                preg_match("/\006|\010/", $value))
            $pf = 'plain';

        $ret = call_user_func_array("processor_$pf", array(&$formatter, $value, $params));
        if (!is_string($ret))
            return $ret;
        return $bra.$ret.$ket;
    }

    $classname = 'processor_'.$pf;
    $myclass = new $classname($formatter, $params);
    $ret = call_user_func(array($myclass, 'process'), $value, $params);
    if (!empty($params['nowrap']) and !empty($myclass->_type) and $myclass->_type == 'wikimarkup')
        return $ret;
    return $bra.$ret.$ket;
}

/**
 * call filter
 *
 * @since 1.3.0
 */
function call_filter($formatter, $filter, $value, $params = array()) {
    if (!function_exists('filter_'.$filter)) {
        $ff = getFilter($filter);
        if (!$ff)
            return $value;
        include_once(dirname(__FILE__)."/../plugin/filter/$ff.php");
    }
    if (!function_exists('filter_'.$filter))
        return $value;

    return call_user_func('filter_'.$filter, $formatter, $value, $params);
}

/**
 * call postfilter
 *
 * @since 1.3.0
 */
function call_postfilter($formatter, $filter, $value, $params = array()) {
    if (!function_exists('postfilter_'.$filter) and !function_exists('filter_'.$filter)) {
        $ff = getFilter($filter);
        if (!$ff)
            return $value;
        include_once(dirname(__FILE__)."/../plugin/filter/$ff.php");
    }
    if (!function_exists('postfilter_'.$filter))
        return $value;

    return call_user_func('postfilter_'.$filter, $formatter, $value, $params);
}

/**
 * call plugin
 *
 * @since 1.3.0
 */
function call_plugin($formatter, $plugin, $params = array()) {
    $action_mode = $params['action_mode'];

    $mode = $action_mode;
    if (empty($action_mode))
        $mode = 'do';

    if (!in_array($mode, array('do', 'ajax', 'macro')))
        return do_invalid($formatter, $params);

    $load = false;
    if ($mode != 'do' && !function_exists($mode.'_'.$plugin) && !function_exists('do_'.$plugin)) {
        $load = true;
    } else if (!function_exists('do_'.$plugin)) {
        $load = true;
    }

    if ($load) {
        $ff = getPlugin($plugin);
        if (!$ff) {
            if ($mode == 'ajax')
                $func = $mode.'_invalid';
            else
                $func = 'do_invalid';
            return $func($formatter, array('title'=>sprintf(_("Invalid %s plugin."), $mode)));
        }

        include_once(dirname(__FILE__)."/../plugin/$ff.php");
    }

    if (!function_exists($mode.'_'.$plugin)) {
        if ($mode != 'do') {
            if (function_exists('do_'.$plugin)) {
                return call_user_func('do_'.$plugin, $formatter, $params);
            } else if (function_exists('macro_'.$plugin)) {
                $ret = call_user_func_array('macro_'.$plugin,array($formatter, '', $params));
                if (is_bool($ret)) {
                    return true;
                }
                if (is_array($ret)) {
                    echo json_encode($ret);
                    return true;
                }
                echo $ret;
                return true;
            }
        }
        if ($mode == 'ajax') {
            $func = $mode.'_invalid';
        } else {
            $func = 'do_invalid';
        }
        return $func($formatter, array('title'=>sprintf(_("Invalid %s action."), $mode)));
    }

    if ($mode == 'do')
        return call_user_func($mode.'_'.$plugin, $formatter, $params);
    $ret = call_user_func($mode.'_'.$plugin, $formatter, '', $params);
    echo $ret;
    return true;
}

/**
 * call action
 *
 * @since 1.3.0
 */
function call_action($formatter, $action, $params = array()) {
    global $DBInfo, $Config;

    // action=foobar, action=foobar/macro, action=foobar/json etc.
    // e.g.) action=foo/bar
    //  $action = 'foo';
    //  $action_mode = 'bar';
    //  $full_action = 'foo-bar';
    $action_mode = $params['action_mode'];
    $full_action = $params['full_action'];

    // prepare to call is_allowed()
    $args = $params;
    $args['noindex'] = true;
    $args['custom'] = '';
    $args['help'] = '';

    $a_allow = $DBInfo->security->is_allowed($action, $args);
    if (!empty($action_mode)) {
        // full action case
        // check full_action 'hello/ajax' is allowed explicitly.
        $args['explicit'] = 1;
        $f_allow = $DBInfo->security->is_allowed($full_action, $args);
        if ($f_allow === false && $a_allow)
            $f_allow = $a_allow; # follow action permission if it is not defined explicitly.
        if (!$f_allow) {
            $args = array('action'=>$action);
            $args['allowed'] = $params['allowed'] = $f_allow;

            if ($f_allow === false)
                $title = sprintf(_("%s action is not found."), $action);
            else
                $title = sprintf(_("Invalid %s action."), $action_mode);
            if ($action_mode == 'ajax') {
                $func = 'ajax_invalid';
            } else {
                $func = 'do_invalid';
            }
            $args['title'] = $title;
            return $func($formatter, $args);
        }
    } else if (!$a_allow) {
        // normal action case
        $params['allowed'] = $a_allow;
        if ($args['custom'] != '' and
                method_exists($DBInfo->security, $args['custom'])) {
            // FIXME be carefull
            $params['action'] = $action;
            return call_user_func(array($DBInfo->security, $args['custom']), $formatter, $params);
        }

        return do_invalid($formatter, $params);
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' and
            $DBInfo->security->is_protected($act, $params) and
            !$DBInfo->security->is_valid_password($_POST['passwd'], $params))
    {
        // check some password protected POST actions.

        $title = sprintf(_("Fail to \"%s\" !"), $action);
        $formatter->send_header('', $params);
        $formatter->send_title($title, '', $params);
        $formatter->send_page('== '._("Please enter the valid password").' ==');
        $formatter->send_footer('', $params);
        return true;
    }

    // full action case.
    $params['action_mode'] = '';
    if (!empty($action_mode) and in_array($action_mode, array('ajax', 'macro'))) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST')
            $params = array_merge($_POST, $params);
        else
            $params = array_merge($_GET, $params);
        $params['action_mode'] = $action_mode;
        if ($action_mode != 'ajax' && empty($Config['use_macro_as_action']))
            return do_invalid($formatter, $params);

        // call swiss knife function
        return call_plugin($formatter, $action, $params);
    }

    // normal action.
    // is it valid action ?
    $plugin = $action;
    if (!function_exists('do_post_'.$plugin) and
            !function_exists('do_'.$plugin)) {
        include_once(dirname(__FILE__).'/../plugin/'.$plugin.'.php');
    }

    if (function_exists('do_'.$plugin)) {
        if ($_SERVER['REQUEST_METHOD']=='POST')
            $params = array_merge($_POST, $params);
        else
            $params = array_merge($_GET, $params);

        return call_user_func('do_'.$plugin, $formatter, $params);
    } else if (function_exists('do_post_'.$plugin)) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $params = array_merge($_POST, $params);
        }
        return call_user_func('do_post_'.$plugin, $formatter, $params);
    }

    return do_invalid($formatter, $params);
}

// vim:et:sts=4:sw=4:
