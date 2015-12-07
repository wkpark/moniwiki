<?php
//
// from nforge project
// 2015/05/16
//

define("VERBOSE", TRUE);
define("GREEN", "\033[01;32m");
define("NORMAL", "\033[00m");
define("MAGENTA", "\033[1;35m");
define("WARN", "\033[1;33m");
define("RED", "\033[01;31m");
define("SUCCESS", "\033[01;32m");
define("BLUE", "\033[01;34m");
define("WHITE", "\033[01;37m");

function ask($question, $default = null, $validator = null) {
    global $STDIN, $STDOUT;

    if (!is_resource($STDIN))
        $STDIN = fopen('php://stdin', 'r');
    if (!is_resource($STDOUT))
        $STDOUT = fopen('php://stdout', 'w');

    $input_stream = $STDIN;

    while (true) {
        print WARN . "- $question";
        if ($default !== null) {
            print MAGENTA . "[$default]";
        }
        print NORMAL . ": ";

        $answer = stream_get_line($input_stream, 10240, "\n");
        if (!$answer && $default !== null) {
            return $default;
        }

        if ($answer && (!$validator || $validator($answer))) {
            return $answer;
        }
    }
}

/**
 * Simple routine to concat lines and normailize SQL query.
 * @author Won-Kyu Park <wkpark@kldp.org>
 * @since 2009-05-16
 * @param string $sql SQL string
 * @return string
 */
function normSQL($sql, $cr = 0) {
    $out = '';
    $oline = '';
    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $line = rtrim($line);
        // skip sql comments
        if ($line{1} == '-' and $line{0} == '-') {
            if ($line == '--')
                continue;
            $out .= $line."\n";
            continue; // XXX
        }

        if (!preg_match('/;$/',$line)) {
            $oline .= empty($oline) ? $line : ' '.$line;
            $oline = preg_replace('/\s*;\s*/', '; ', $oline);
        } else {
            $oline .= empty($oline) ? $line : ' '.$line;
            $oline = preg_replace('/\s+/', ' ', $oline);
            $oline = preg_replace('/\s*=\s*/', ' = ', $oline);
            $oline = preg_replace('/\s*(,)\s*/', '\1 ', $oline);
            $oline = preg_replace('/\s*(\))(?!>\s*\w)/', '\1', $oline);

            $out .= $oline."\n";
            $oline = '';
        }
    }
    if ($cr)
        $out = preg_replace('/\s*(SELECT|FROM|JOIN|UNION|CASE|WHEN|ELSE|WHERE|END)\s*/', "\n\t$1 ", $out);

    return $out;
}

/**
 * A very basic SQL converter.
 * @todo Add more keywords detection
 * @author Won-Kyu Park <wkpark@kldp.org>
 * @since 2009-05-16
 * @param string $sqlfile File containing sql strings
 * @param string $sql SQL strings
 * @param string $type Database type
 * @return string
 */
function make_sql($sqlfile, $sql = '', $type = 'mysql') {
    $sql_type = 'PGSQL'; // default SQL

    if (empty($sql) and file_exists($sqlfile))
        $sql = file_get_contents($sqlfile);
    if ($sql[0] == '-' && $sql[1] == '-' && $sql[2] == ' ') {
        $mytype = substr($sql, 3, strlen($type));
        $sqltype = strtoupper($type);
        if ($sqltype == $mytype) {
            $sql = preg_replace("/^-- ([^ ]+)?,?".$sqltype.",?([^ ]+)? -- /m", '', $sql);
            return normSQL($sql);
        }
    }

    switch ($type) {
    case 'sqlite':
        $sql = preg_replace('/(")([^"]+)\1/','`$2`', $sql);
        $sql = preg_replace('/\.(name|count|time|date)/i','.`\1`', $sql);
        $sql = preg_replace('/(int\(\d+\))/i','integer', $sql);
        $sql = preg_replace('/\s(binary)\s/i',' ', $sql);
        $sql = preg_replace('/\s(auto_increment)(?=\s)?/i',' AUTOINCREMENT ', $sql);
        $sql = preg_replace('/::(text|integer|unknown)\s*/',' ', $sql);
        $sql = preg_replace("/^-- ([^ ]+)?,?SQLITE,?([^ ]+)? -- /m", '', $sql); // FIXME
        break;
    case 'mysql':
        // change quotes
        $sql = preg_replace('/(")([^"]+)\1/','`$2`', $sql);
        // quote keywords
        $sql = preg_replace('/\.(name|count|time|date|mtime|timestamp)/','.`\1`', $sql);
        $sql = preg_replace('/::(text|integer|unknown)\s*/',' ', $sql);
        $sql = preg_replace(array('/\b(?!<\')(int4|int)(?!\')\b/i'),array('unsigned'), $sql); // XXX
        $sql = preg_replace(array('/\b(?!<\')(integer)(?!\')\b/i'),array('bigint'), $sql); // XXX
        $sql = preg_replace('/as bigint\s*/i','AS decimal ', $sql);
        $sql = preg_replace('/as text\s*/i','AS char ', $sql);
        $sql = preg_replace('/as varchar\s*/i','AS char ', $sql);
        // MySQL specific commands
        $sql = preg_replace("/^-- ([^ ]+)?,?MYSQL,?([^ ]+)? -- /m", '', $sql); // FIXME
        break;
    case 'pgsql':
        // quote keywords
        $sql = preg_replace('/(`)([^`]+)\1/','"$2"', $sql);
        $sql = preg_replace('/\.`(name|count|time|date)`/','."\1"', $sql);
        // PostgreSQL specific commands
        $sql = preg_replace("/^-- ([^ ]+)?,?PGSQL,?([^ ]+)? -- /m", '', $sql); // FIXME
        break;
    case 'cubrid':
        $sql = preg_replace('/\.(name|count|time|date|file)\b/','."\1"', $sql);
        $sql = preg_replace('/::(text|integer|unknown)\s*/',' ', $sql);
        $sql = preg_replace('/(add|drop)\s+column\s/i','\1 ATTRIBUTE ', $sql);
        $sql = preg_replace(array('/(int4)/'),array('integer'), $sql);
        // CUBRID specific commands
        $sql = preg_replace("/^-- ([^ ]+)?,?CUBRID,?([^ ]+)? -- /m", '', $sql); // FIXME
        break;
    }

    return normSQL($sql);
}

// http://php.net/manual/kr/function.mysql-real-escape-string.php#101248 by feedr
function mysql_escape_mimic($inp) {
    if(is_array($inp))
        return array_map(__METHOD__, $inp);

    if(!empty($inp) && is_string($inp)) {
        return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"),
                array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
    }

    return $inp;
}

function _escape_string($type, $str) {
    switch($type) {
    case 'sqlite':
        return str_replace(array("'", "\r", "\n"), array("''", "\\r", "\\n"), $str);
    case 'mysql':
        if (!isset($GLOBALS['_dummy_connect_'])) {
            mysql_connect(); // FIXME deprecated
            $GLOBALS['_dummy_connect_'] = 1;
        }
        return mysql_real_escape_string($str); // FIXME deprecated
    default:
        return mysql_escape_mimic($str); // alternative
    }
}

// vim:et:sts=4:sw=4:
