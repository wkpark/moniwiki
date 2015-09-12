<?php
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2015-08-27
// Name: CopyCat plugin
// Description: CopyCat dectector
// URL: MoniWiki:CopyCatPlugin
// Version: $Revision: 1.0 $
// License: BSD
//
// Usage: ?action=copycat

/**
 * from https://github.com/wikigit/Duplication-Detector
 *
 * Copyright (c) 2011, Derrick Coetzee (User:Dcoetzee)
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 
 *  * Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 * 
 *  * Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

function do_copycat($formatter, $params = array()) {
    global $DBInfo;
    $charset = $DBInfo->charset;

    //ini_set('display_errors',1);
    //error_reporting(E_ALL|E_STRICT);

    // Get starting time to measure time elapsed later.
    $time_start = microtime_float();

    $source1 = 'Downloaded';
    if (!empty($params['source'])) {
        $source1 = 'Text';
        $name1 = 'source';
        $filecontents1 = $params['source'];
    } else if (!empty($params['url0']) && preg_match("@^https?://@", $params['url0'])) {
        $name1 = $params['url0'];
        $filecontents1 = wget($params['url0']);
    } else if (isset($_FILES['file0']) && $_FILES['file0']['tmp_name']) {
        $source1 = 'Uploaded';
        $name1 = $_FILES['file0']['name'];
        $filecontents1 = read_file_contents($_FILES['file0']['tmp_name']);
    } else {
        $source1 = 'Wiki';
        $name1 = $formatter->page->name;
        $filecontents1 = $formatter->page->_get_raw_body();
    }
    $source2 = 'Downloaded';
    if (!empty($params['url']) && preg_match("@^https?://@", $params['url'])) {
        $name2 = $params['url'];
        $filecontents2 = wget($params['url']);
    } else if (isset($_FILES['file']) && $_FILES['file']['tmp_name']) {
        $source2 = 'Uploaded';
        $name2 = $_FILES['file']['name'];
        $filecontents2 = read_file_contents($_FILES['file']['tmp_name']);
    } else if (!empty($params['target'])) {
        $source2 = 'Text';
        $name2 = 'target';
        $filecontents2 = $params['target'];
    } else {
        $params['.title'] = _("Copycat detector");
        $formatter->send_header('', $params);
        $formatter->send_title('', '', $params);

        $comp_btn = _("Detect");
        $diff_btn = _("Diff");
        $raw_check = _("Raw");
        $source_lab = _("Source (URL or PageName)");
        $target_lab = _("Target (URL)");
        $min_num_words = _("Minimum number of words");
        $min_num_chars = _("Minimum number of characters");
        $remove_quotes = _("Remove quotations");
        $remove_number = _("Remove numbers");
        $or = _("or");
        echo <<<FORM
<form name="comparesimple" method="post">
<input type="hidden" name="action" value="copycat" />
$source_lab: <input style="width: 99%" type="text" name="url0" value="$name1" size="120"><br/>
$or <br />
<textarea style="width: 100%; height: 408px; box-sizing: border-box" name="source" cols="80" rows="10">
</textarea>
<br/>
$target_lab: <input style="width: 99%" type="text" name="url" size="120"><br />
$or <br />
<textarea style="width: 100%; height: 408px; box-sizing: border-box" name="target" cols="80" rows="10">
</textarea>
<br/>
$min_num_words: <input type="text" name="minwords" value="2"><br/>
$min_num_chars: <input type="text" name="minchars" value="13"><br/>
<input type="checkbox" name="removequotations" checked="checked"> $remove_quotes<br/>
<input type="checkbox" name="removenumbers" checked="checked"> $remove_number<br/>
<br/>
<input type="submit" name="btn_compare" value="$comp_btn" /> $or
<input type="submit" name="btn_diff" value="$diff_btn" /> <input type="checkbox" name="raw" /> $raw_check
</form>
FORM;
        $formatter->send_footer('', $params);
        return;
    }

    $shorturl1 = htmlspecialchars(shorten_url($name1));
    $shorturl2 = htmlspecialchars(shorten_url($name2));
    $title = "Duplicate Detector: $shorturl1 v $shorturl2";

    $button_diff = !empty($params['btn_diff']) ? true : false;

    if ($button_diff) {
        $diff = get_diff($filecontents1, $filecontents2, $params);

        if (!empty($params['raw'])) {
            header("Content-Type: text/plain");
            echo $diff;
            return;
        }
        $params['.title'] = "Diff: $shorturl1 v $shorturl2";
        $formatter->send_header('', $params);
        $formatter->send_title('', '', $params);

        if (isset($diff[0])) {
            echo "<div id='wikiDiffPreview'>\n";
            echo $formatter->processor_repl('diff', $diff, $params);
            echo "</div>\n";
        }

        $formatter->send_footer('', $params);
        return;
    }

    $minwords = $params['minwords'];
    if (!$minwords || $minwords < 2) { $minwords = 2; }
    $minchars = $params['minchars'];
    if (!$minchars) { $minchars = 13; }
    $removequotations = $params['removequotations'];
    $removenumbers = $params['removenumbers'];

    $params['.title'] = $title;
    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);

    print('<p><b>Warning</b>: Duplication Detector may in some cases give no results or incomplete results. This does not necessarily indicate copying has not occurred. Manually examine the source document to verify.</p>');

    print("<h2>Comparing documents for duplicated text:</h2>");
    print('<ul>');
    if (preg_match('/^https?:\/\//', $name1)) {
        print('<li><a href="' . htmlspecialchars($name1) . '">' . htmlspecialchars($name1) . '</a></li>');
    } else {
        print('<li>' . htmlspecialchars($name1) . '</li>');
    }
    if (preg_match('/^https?:\/\//', $name2)) {
        print('<li><a href="' . htmlspecialchars($name2) . '">' . htmlspecialchars($name2) . '</a></li>');
    } else {
        print('<li>' . htmlspecialchars($name2) . '</li>');
    }
    print('</ul>');

    echo "<p>","\n";
    $terms1 = get_terms($name1, $source1, $filecontents1,
        $removenumbers, $removequotations, $charset);
    $terms2 = get_terms($name2, $source2, $filecontents2,
        $removenumbers, $removequotations, $charset);
    // print("terms1: " . join(',', $terms1) . "\n");
    // print("terms2: " . join(',', $terms2) . "\n");

    $terms1_posts = compute_posts($terms1, $minwords);

    $matches1 = compute_matches($terms1, $terms2, $terms1_posts, $minwords);
    echo "<h3>Total match candidates found: ", count($matches1), "</h3>","\n";
    echo " (before eliminating redundant matches)</p>","\n";
    echo "</p>","\n";

    usort($matches1, 'cmp_by_length_desc');
    $already_matched_phrases = (array)null;
    $num_matches = 0;
    $context_words = 30;
    $min_context_words = 6;
    $max_context_words = 20;
    echo "<h2>Matched phrases:</h2>","\n";

    foreach ($matches1 as $value) {
        list($pos1, $pos2, $length, $phrase) = $value;
        $skip = 0;
        foreach ($already_matched_phrases as $already_phrase) {
            if (strpos($already_phrase, $phrase) !== false) {
                $skip = 1;
                break;
            }
        }
        $characters = mb_strlen($phrase, $charset);
        if (!$skip && $characters >= $minchars) {
            if ($length > $context_words - $min_context_words) {
                print("<p><ins class='diff-added'>$phrase</ins>" . " ($length words, $characters characters)</p>");
            } else {
                $context_len = ($context_words - $length)/2;
                if ($context_len * 2 >= $max_context_words) {
                    $context_len = $max_context_words/2;
                }
                $phraseprefix1 = join(' ', array_slice($terms1, max($pos1 - $context_len, 0), $pos1 - max($pos1 - $context_len, 0)));
                $phrasesuffix1 = join(' ', array_slice($terms1, $pos1 + $length, min(count($terms1) - ($pos1 + $length), $context_len)));
                $phraseprefix2 = join(' ', array_slice($terms2, max($pos2 - $context_len, 0), $pos2 - max($pos2 - $context_len, 0)));
                $phrasesuffix2 = join(' ', array_slice($terms2, $pos2 + $length, min(count($terms2) - ($pos2 + $length), $context_len)));
                print("<p>$phraseprefix1 <ins class='diff-added'>$phrase</ins> $phrasesuffix1<br/>");
                print("$phraseprefix2 <ins class='diff-added'>$phrase</ins> $phrasesuffix2<br/>");
                print("($length words, $characters characters)</p>");
            }
            $num_matches++;
        }
        $already_matched_phrases[] = $phrase;
    }

    echo "<h3>Matching phrases found: ", $num_matches, "<h3>\n";

    $time_delta = microtime_float() - $time_start;

    echo '<p>', 'Elapsed time:', $time_delta, '</p>';
    $formatter->send_footer('', $params);

    return;
}

function get_diff($old, $new, $params = array()) {
    include_once(dirname(__FILE__)."/../lib/difflib.php");

    $olines = explode("\n", $old);
    $tmp = array_pop($olines);
    if ($tmp != '') $olines[] = $tmp;

    $nlines = explode("\n", $new);
    $tmp = array_pop($nlines);
    if ($tmp != '') $nlines[] = $tmp;

    $diff = new Diff($olines, $nlines);
    $unified = new UnifiedDiffFormatter;
    //$unified->trailing_cr = "&nbsp;\n"; // hack to see inserted empty lines
    $unified->trailing_cr = "\n"; // hack to see inserted empty lines
    return $unified->format($diff);
}

function shorten_url($url) {
    $url = preg_replace('/^http:\/\//', '', $url);
    if (strlen($url) > 16) {
        $url = substr_replace($url, '...', 8, strlen($url) - 16);
    }
    return $url;
}

function cmp_by_length_desc($a, $b) {
    list($pos1a, $pos2a, $lengtha, $phrasea) = $a;
    list($pos1b, $pos2b, $lengthb, $phraseb) = $b;
    if ($lengtha == $lengthb) {
        $charsa = strlen($phrasea);
        $charsb = strlen($phraseb);
        if ($charsa == $charsb) {
            return 0;
        } else if ($charsa < $charsb) {
            return 1;
        } else if ($charsa > $charsb) {
            return -1;
        }
    } else if ($lengtha < $lengthb) {
        return 1;
    } else if ($lengtha > $lengthb) {
        return -1;
    }
}

function get_terms($name, $source, $page, $removenumbers, $removequotations, $charset = 'UTF-8') {
    //$page = convert_pdf($page);
    print("$source document from " . htmlspecialchars($name) . " (" . mb_strlen($page, $charset) . " characters");
    $page_text = strip_html($page, $removenumbers, $removequotations, $charset);
    // print ("page_text: $page_text\n");
    $result = preg_split('/\s+/', $page_text);
    print(", " . count($result) . " words)<br/>");
    return $result;
}

function convert_pdf($page) {
    // NOT USED
    $tempfilenamepdf = tempnam(sys_get_temp_dir(), 'dupdet');
    $tempfilenametxt = tempnam(sys_get_temp_dir(), 'dupdet');
    //print("Debugging temp file names: $tempfilenamepdf, $tempfilenametxt\n");
    $tempfile = fopen($tempfilenamepdf, "w");
    fwrite($tempfile, $page);
    fclose($tempfile);
    $output = `pdftotext $tempfilenamepdf $tempfilenametxt 2>&1`;
    if (!preg_match('/May not be a PDF file/', $output) && file_exists($tempfilenametxt) && filesize($tempfilenametxt) > 0) {
        $page = read_file_contents($tempfilenametxt);
        print("Converted PDF (" . filesize($tempfilenamepdf) . " bytes) to text file (" . filesize($tempfilenametxt) . " bytes)<br/>\n");
    }
    unlink($tempfilenamepdf);
    unlink($tempfilenametxt);
    return $page;
}

function wget($url) {
    $ch = curl_init();

    // set user-agent for some cases
    if (preg_match('/^http:\/\/webcache\.googleusercontent\.com/', $url)) {
        // Use user agent "" so Google will let us access their cache
        curl_setopt($ch, CURLOPT_USERAGENT, '');
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, 'Moniwiki Copycat detector based on Duplication Detector by dcoetzee');
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_NOBODY, 1);

    $mr = 5;
    $newurl = $url;
    do {
	curl_setopt($ch, CURLOPT_URL, $newurl);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $code = 0;
        } else {
	    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    if ($code == 301 || $code == 302) {
		$result = curl_exec($ch);
		preg_match('/Location:(.*?)\n/', $result, $matches);
		$newurl = trim(array_pop($matches));
	    } else {
                curl_setopt($ch, CURLOPT_NOBODY, 0);
                curl_setopt($ch, CURLOPT_HEADER, 0);
		$result = curl_exec($ch);
		$code = 0;
	    }
        }
    } while ($code && --$mr); 

    if ($result === false || strlen($result) < 300) {
        // Try again with fake user agent
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $result = curl_exec($ch);
    }
    if ($result === false) {
        print("<p>Could not load URL " . htmlspecialchars($url) . ": " . curl_error($ch) . "</p>");
        $result = '';
    }
    if (strlen($result) < 300) {
        print("<p>Warning: URL " . htmlspecialchars($url) . " gave short result:\n" . htmlspecialchars($result) . "</p>");
    }
    curl_close($ch);
    return $result;
}

function compute_posts($terms, $n) {
    $result = (array)null;
    for ($i = 0; $i <= count($terms) - $n + 1; $i++) {
        $result[ join(' ', array_slice($terms, $i, $n)) ][] = $i;
    }
    return $result;
}

function compute_matches($terms1, $terms2, $terms1_posts, $n) {
    $result = (array)null;
    for ($i = 0; $i <= count($terms2) - $n; $i++) {
        $phrase = join(' ', array_slice($terms2, $i, $n));
        if (array_key_exists($phrase, $terms1_posts)) {
	    foreach ($terms1_posts[$phrase] as $position) {
		for ($length = 0; ; $length++) {
		    if ($i + $length >= count($terms2) ||
			$position + $length >= count($terms1) ||
			strcmp($terms1[$position + $length], $terms2[$i + $length]) != 0) {
			break;
		    }
		}
		$result[] = array($position, $i, $length, join(' ', array_slice($terms1, $position, $length)));
	    }
        }
    }
    return $result;
}

function strip_html($page, $removenumbers, $removequotations, $charset = 'UTF-8') {
    // Not for sanitizing - just for extracting textual content
    $result = $page;

/*    if (!preg_match('/charset=UTF-8/iu', $result)) {
        $result = utf8_encode($result);
    } else {
        print(" (UTF8)");
    }
*/

    $result = preg_replace('/<!--.*-->/suU', ' ', $result);
    $result = preg_replace('/<script.*<\/script>/isuU', ' ', $result);
    $result = preg_replace('/<style.*<\/style>/isuU', ' ', $result);
    $result = preg_replace('/<sup[^>]*class="reference"[^>]*>.*?<\/sup>/isu', ' ', $result);

    $result = preg_replace('/<[^>]*>/u', ' ', $result);
    $result = html_entity_decode($result, ENT_QUOTES, $charset);

    $doublequotespecialchars =
            "\xC2\xAB" .     // left guillemet
            "\xC2\xBB" .     // right guillemet
            "\xE2\x80\x9C" . // left side double quote
            "\xE2\x80\x9D" . // right side double quote
            "";
    $result = preg_replace("/[" . $doublequotespecialchars . "]/u", '"', $result);

    $singlequotespecialchars =
            "\xE2\x80\xB9" . // left single angle quote
            "\xE2\x80\xBA" . // right single angle quote
            "\xE2\x80\x98" . // left side single quote
            "\xE2\x80\x99" . // right side single quote
            "";
    $result = preg_replace("/[" . $singlequotespecialchars . "]/u", '\'', $result);

    $specialchars =
            "\xC2\xAD" .     // soft hyphen
            "\xE2\x80\x90" . // hyphen
            "\xE2\x80\x91" . // non-breaking hyphen
            "\xE2\x80\x92" . // figure dash
            "\xE2\x80\x93" . // en-dash
            "\xE2\x80\x94" . // em-dash
            "\xE2\x80\x9A" . // single low quote
            "\xE2\x80\x9B" . // single high reversed quote
            "\xE2\x80\x9E" . // double low quote
            "\xE2\x80\x9F" . // double high reversed quote
            "\xE2\x80\xA2" . // bullet
            "\xE2\x80\xA6" . // ellipsis
            "\xE2\x86\x91" . // upwards arrow
            "";
    $result = preg_replace("/[" . $specialchars . "]/u", ' ', $result);
    $result = preg_replace('/[\\\\\\/\[\]\.,;:&#(){}*|?!=_\-\^%+`]/u', ' ', $result);
    // English/French rule: Get rid of ' except for words ending in 's
    // or beginning with l' or d' or c'
    $result = preg_replace('/(?<![DdLlCc])\'(?!s\s+)/u', ' ', $result);
    if ($removequotations) {
        $result = preg_replace('/"[^"]*"/u', ' ', $result);
    } else {
        $result = preg_replace('/"/u', ' ', $result);
    }
    //print("Debugging: $result\n");

    if ($removenumbers) {
        $result = preg_replace('/[0-9]+/u', ' ', $result);
    }
    $result = mb_strtolower($result, $charset);
    $result = preg_replace('/[\r\n\t]/u', ' ', $result);
    $result = preg_replace('/\s+/u', ' ', $result);

    return $result;
}

function read_file_contents($filename) {
    $tempfile = fopen($filename, "r");
    $contents = fread($tempfile, filesize($filename));
    fclose($tempfile);
    return $contents;
}

/* Get current time as a floating-point number of seconds since some reference
   point. */
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

// vim:et:sts=4:sw=4:
