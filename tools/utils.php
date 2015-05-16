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
