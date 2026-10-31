<?php
// SimpleRCS text history adapter for MoniWiki.
// Copyright 2026 Won-Kyu Park.
// Licensed under the Apache License, Version 2.0; see SimpleRCS.LICENSE.

require_once(dirname(__FILE__).'/version.RCS.php');
require_once(dirname(__FILE__).'/difflib.php');

class Version_SimpleRCS extends Version_RCS {
  private $store;

  function __construct($conf) {
    parent::__construct($conf);
    $this->store = new MoniSimpleRCS(!empty($conf->simplercs_verify_hashes));
  }

  private function archive($filename) {
    return dirname($filename).'/RCS/'.basename($filename).',srcs';
  }

  private function load($filename) {
    return $this->store->load($this->archive($filename));
  }

  function canSave($pagename, $body='') {
    if (strpos($body, "\0") !== false || !preg_match('//u', $body)) return false;
    $filename = $this->_filename($pagename);
    $archive = $this->archive($filename);
    $legacy = dirname($filename).'/RCS/'.basename($filename).',v';
    if (file_exists($legacy) && !file_exists($archive)) return false;
    if (!$this->store->load($archive, true)) return false;
    return !$this->store->verifyHashes() || $this->store->head() === '' || $this->store->revision('1.0') !== false;
  }

  function co($pagename, $rev, $opt=array()) {
    $filename = $this->_filename($pagename);
    if (!$this->load($filename)) return '';
    $body = $this->store->revision($rev);
    if ($body === false) return '';
    if (!empty($opt['stdout'])) {
      if (file_put_contents($filename, $body, LOCK_EX) === false) return '';
      return '';
    }
    return $body;
  }

  function ci($pagename, $log, $force=false) {
    return $this->_ci($this->_filename($pagename), $log, $force);
  }

  function _ci($filename, $log, $force=false) {
    $dir = dirname($filename).'/RCS';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) return -1;
    $lock = @fopen($filename.'.srcs.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
      if ($lock) fclose($lock);
      return -1;
    }
    $archive = $this->archive($filename);
    $legacy = $dir.'/'.basename($filename).',v';
    $ok = !(file_exists($legacy) && !file_exists($archive));
    if ($ok) $ok = $this->store->load($archive, true);
    $body = $ok ? @file_get_contents($filename) : false;
    if ($body !== false) {
      $fields = explode(';;', $log, 3);
      $author = isset($fields[1]) && $fields[1] !== '' ? $fields[1] : 'Anonymous';
      $ok = $this->store->save($archive, $body, $author, $log);
    } else $ok = false;
    flock($lock, LOCK_UN);
    fclose($lock);
    return $ok ? 0 : -1;
  }

  function rlog($pagename, $rev='', $opt='', $params=array()) {
    if (!empty($params['archive']) || !$this->load($this->_filename($pagename))) return '';
    return $this->store->rlog($rev, $opt);
  }

  function get_rev($pagename, $mtime='', $last=0) {
    if (!$this->load($this->_filename($pagename))) return '';
    return $this->store->getRev($mtime, $last);
  }

  function diff($pagename, $rev='', $rev2='', $params=array()) {
    if (!empty($params['archive'])) return '';
    $filename = $this->_filename($pagename);
    if (!$this->load($filename)) return '';
    $old = $this->store->revision($rev !== '' ? $rev : $this->store->head());
    $new = $rev2 !== '' ? $this->store->revision($rev2) : @file_get_contents($filename);
    if ($old === false || $new === false) return '';
    return $this->store->diff($new, $old, 'udiff');
  }

  function is_broken($pagename) {
    $file = $this->archive($this->_filename($pagename));
    return file_exists($file) && (!$this->store->load($file) || $this->store->revision('1.0') === false);
  }

  function rename($pagename, $new, $params=array()) {
    $old = $this->archive($this->_filename($pagename));
    $newfile = $this->_filename($new);
    $target = $this->archive($newfile);
    $legacy = dirname($newfile).'/RCS/'.basename($newfile).',v';
    if (!is_file($old) || file_exists($target) || file_exists($legacy) || !@rename($old, $target)) return -1;
    return $this->ci($new, isset($params['log']) ? $params['log'] : '', true);
  }

  function delete($pagename) {
    $file = $this->archive($this->_filename($pagename));
    if (!is_file($file)) return false;
    for ($i=1; file_exists($file.'.'.$i); $i++) {}
    return @rename($file, $file.'.'.$i);
  }

  function attics($pagename) { return false; }
  function export($pagename, $limit=0) { return ''; }
  function import($pagename, $rcsfile) { return false; }
  function merge($old, $add, $params=array()) { return false; }
}

class MoniSimpleRCS {
  private $blocks = array();
  private $version = 2;
  private $verifyHashes = false;

  function __construct($verifyHashes=false) {
    $this->verifyHashes = (bool)$verifyHashes;
  }
  private function lines($text) {
    return $text === '' ? array() : preg_split('/(?<=\n)/', $text, -1, PREG_SPLIT_NO_EMPTY);
  }

  private function field($data, &$pos) {
    $len = strlen($data);
    while ($pos < $len && ctype_space($data[$pos])) $pos++;
    if ($pos === $len) return null;
    if (!preg_match('/\G([a-z_]+)\s+@/A', $data, $m, 0, $pos)) return false;
    $key = $m[1];
    $pos += strlen($m[0]);
    $value = '';
    while (($at = strpos($data, '@', $pos)) !== false) {
      $value .= substr($data, $pos, $at - $pos);
      $pos = $at + 1;
      if ($pos < $len && $data[$pos] === '@') { $value .= '@'; $pos++; continue; }
      while ($pos < $len && ctype_space($data[$pos])) $pos++;
      if ($pos >= $len || $data[$pos++] !== ';') return false;
      return array($key, $value);
    }
    return false;
  }

  function load($path, $allowNew=false) {
    $this->blocks = array();
    $this->version = 2;
    if (!file_exists($path)) return $allowNew;
    $data = @file_get_contents($path);
    if ($data === false || $data === '') return false;
    $pos = 0;
    if (strncmp($data, '# SimpleRCS', 11) === 0) {
      $end = strpos($data, "\n");
      if ($end === false || !preg_match('/^# SimpleRCS v2\.0;\s*hash_algo=sha256;\s*encoding=utf-8;\s*$/i', substr($data, 0, $end))) return false;
      $pos = $end + 1;
    } else $this->version = 1;
    $block = null;
    while (($field = $this->field($data, $pos)) !== null) {
      if ($field === false) return false;
      list($key, $value) = $field;
      if ($key === 'ver') {
        if ($block !== null) $this->blocks[] = $block;
        $block = array('ver'=>$value);
      } else {
        if ($block === null || isset($block[$key]) || !in_array($key, array('date','author','log','text','delta','hash','prev_hash'), true)) return false;
        $block[$key] = $value;
      }
    }
    if ($block !== null) $this->blocks[] = $block;
    if (!$this->blocks) return false;
    $prev = '';
    foreach ($this->blocks as $i=>$b) {
      if (!isset($b['date'], $b['author'], $b['log']) || $b['ver'] !== '1.'.$i) return false;
      if (isset($b['text']) === isset($b['delta'])) return false;
      if ($i === count($this->blocks)-1 && !isset($b['text'])) return false;
      if ($this->version === 1 && !isset($b['text'])) return false;
      if ($this->version === 2) {
        if (empty($b['hash']) || !preg_match('/\A[0-9a-f]{64}\z/', $b['hash'])) return false;
        if (($b['prev_hash'] ?? '') !== $prev) return false;
        $prev = $b['hash'];
      }
    }
    return true;
  }

  function head() {
    return $this->blocks ? $this->blocks[count($this->blocks)-1]['ver'] : '';
  }

  function verifyHashes() { return $this->verifyHashes; }

  private function hashBlock($b, $body) {
    return hash('sha256', $b['ver'].'|'.$b['date'].'|'.$b['author'].'|'.$b['log'].'|'.$body.'|'.($b['prev_hash'] ?? ''));
  }

  function revision($rev) {
    if (!preg_match('/\A1\.(0|[1-9][0-9]*)\z/', (string)$rev, $m)) return false;
    $target = (int)$m[1];
    $last = count($this->blocks)-1;
    if ($target > $last) return false;
    $body = $this->blocks[$last]['text'];
    for ($i=$last; $i >= $target; $i--) {
      $b = $this->blocks[$i];
      if ($i !== $last) {
        if ($this->version === 2 && isset($b['text'])) $body = $b['text'];
        else {
          $delta = $this->version === 1 ? $b['text'] : $b['delta'];
          $body = $this->applyDelta($body, $delta);
          if ($body === false) return false;
        }
      }
      if ($this->verifyHashes && $this->version === 2 && !hash_equals($b['hash'], $this->hashBlock($b, $body))) return false;
    }
    return $body;
  }

  private function applyDelta($body, $delta) {
    $lines = $this->lines($body);
    $commands = $this->lines($delta);
    $offset = 0;
    for ($i=0, $count=count($commands); $i<$count;) {
      if (!preg_match('/\A([ad])([0-9]+) ([0-9]+)\n?\z/', $commands[$i++], $m)) return false;
      $at = (int)$m[2]; $n = (int)$m[3];
      if ($n < 1 || $n > $count + count($lines)) return false;
      if ($m[1] === 'd') {
        $start = $at + $offset - 1;
        if ($start < 0 || $start + $n > count($lines)) return false;
        array_splice($lines, $start, $n); $offset -= $n;
      } else {
        $start = $at + $offset;
        if ($start < 0 || $start > count($lines) || $i + $n > $count) return false;
        array_splice($lines, $start, 0, array_slice($commands, $i, $n));
        $i += $n; $offset += $n;
      }
    }
    return implode('', $lines);
  }

  function diff($new, $old, $type='') {
    $diff = new Diff($this->lines($old), $this->lines($new));
    $formatter = $type === 'udiff' ? new UnifiedDiffFormatter() : new DeltaDiffFormatter();
    $formatter->trailing_cr = '';
    return $formatter->format($diff);
  }

  function save($path, $body, $author, $log) {
    if (strpos($body, "\0") !== false || !preg_match('//u', $body)) return false;
    if ($body !== '' && substr($body, -1) !== "\n") $body .= "\n";
    $last = count($this->blocks)-1;
    if ($last >= 0) {
      if ($this->verifyHashes && $this->revision('1.0') === false) return false;
      $old = $this->revision($this->head());
      if ($old === false) return false;
      if ($this->version === 1) $this->blocks[$last]['text'] = $this->diff($old, $body);
      else {
        $this->blocks[$last]['delta'] = $this->diff($old, $body);
        unset($this->blocks[$last]['text']);
      }
    }
    $b = array('ver'=>'1.'.($last+1), 'date'=>gmdate('Y-m-d\TH:i:s'), 'author'=>$author, 'log'=>$log, 'text'=>$body);
    if ($this->version === 2) {
      if ($last >= 0) $b['prev_hash'] = $this->blocks[$last]['hash'];
      $b['hash'] = $this->hashBlock($b, $body);
    }
    $this->blocks[] = $b;
    $out = $this->version === 2 ? "# SimpleRCS v2.0; hash_algo=sha256; encoding=utf-8;\n" : '';
    foreach ($this->blocks as $item) {
      foreach (array('ver','date','author','log','text','delta','prev_hash','hash') as $key) {
        if (isset($item[$key])) $out .= $key.' @'.str_replace('@', '@@', $item[$key])."@;\n";
      }
      $out .= "\n";
    }
    $tmp = @tempnam(dirname($path), '.srcs-');
    if (!$tmp) return false;
    $mode = file_exists($path) ? (fileperms($path) & 0777) : (0666 & ~umask());
    $fp = @fopen($tmp, 'wb');
    $ok = is_resource($fp);
    if ($ok) {
      $offset = 0; $length = strlen($out);
      while ($offset < $length) {
        $written = fwrite($fp, substr($out, $offset, 65536));
        if ($written === false || $written === 0) { $ok = false; break; }
        $offset += $written;
      }
      if ($ok) $ok = fflush($fp) && (!function_exists('fsync') || fsync($fp));
      if (!fclose($fp)) $ok = false;
    }
    if ($ok) $ok = @chmod($tmp, $mode) && @rename($tmp, $path);
    if (!$ok) @unlink($tmp);
    return $ok;
  }

  function getRev($mtime='', $last=0) {
    $n = count($this->blocks);
    if (!$n) return '';
    if ($last) return $this->head();
    if ($mtime !== '') {
      for ($i=$n-1; $i>=0; $i--) {
        $time = strtotime($this->blocks[$i]['date'].' UTC');
        if ($time !== false && $time <= $mtime) return $this->blocks[$i]['ver'];
      }
      return '';
    }
    return $n > 1 ? $this->blocks[$n-2]['ver'] : '';
  }

  function rlog($rev='', $option='') {
    $count = count($this->blocks); $selected = array();
    if ($rev !== '') $ranges = array($rev);
    else if ($option === '-r') $ranges = array($this->head());
    else if (strncmp($option, '-r', 2) === 0) $ranges = explode(';', str_replace('\\;', ';', substr($option, 2)));
    else $ranges = array('1.0:'.$this->head());
    foreach ($ranges as $range) {
      if (!preg_match('/\A1\.(\d+)(?::1\.(\d+))?\z/', $range, $m)) return '';
      $from = (int)$m[1]; $to = isset($m[2]) ? (int)$m[2] : $from;
      for ($i=max(0,$from); $i<=min($count-1,$to); $i++) $selected[$i] = true;
    }
    krsort($selected, SORT_NUMERIC);
    $out = 'total revisions: '.$count.'; selected revisions: '.count($selected)."\n";
    foreach ($selected as $i=>$unused) {
      $b = $this->blocks[$i]; $time = strtotime($b['date'].' UTC');
      if ($time === false) return '';
      $log = htmlspecialchars(str_replace(array("\r", "\n"), ' ', $b['log']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $author = htmlspecialchars(str_replace(array("\r", "\n", ';'), ' ', $b['author']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $change = '';
      if ($i > 0) {
        $previous = $this->blocks[$i-1];
        $delta = $this->version === 1 ? $previous['text'] : ($previous['delta'] ?? '');
        if ($delta !== '') {
          $lines = $this->lines($delta); $added = 0; $removed = 0;
          for ($j=0, $n=count($lines); $j<$n; $j++) {
            if (!preg_match('/\A([ad])[0-9]+ ([0-9]+)\n?\z/', $lines[$j], $m)) break;
            if ($m[1] === 'd') $added += (int)$m[2];
            else { $removed += (int)$m[2]; $j += (int)$m[2]; }
          }
          $change = '  lines: +'.$added.' -'.$removed;
        }
      }
      $out .= "----------------------------\nrevision ".$b['ver']."\ndate: ".gmdate('Y/m/d H:i:s', $time).';  author: '.$author.';  state: Exp;'.$change."\n".$log."\n";
    }
    return $out.str_repeat('=', 71)."\n";
  }
}
