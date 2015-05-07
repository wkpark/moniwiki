<?php
/**
 * Cache.Text.php - Flat file Cache class
 *
 * @author	Won-Kyu Park <wkpark@kldp.org>
 * @version	$Id$
 * @license	GPLv2
 * @revision	$Revision$
 * @description	save a serialized object/array or a raw string(html/php etc.)
 *
 */

class Cache_Text {
	/**
	 * the cache name
	 *
	 * @var		string	$name
	 */
	var $name = __CLASS__;

	/**
	 * the cache revision
	 *
	 * @var		string	$revision
	 */
	var $revision = '0.6';

	/**
	 * default cache arena name
	 *
	 * @var		string	$arena
	 */
	var $arena = 'default';

	/**
	 * time to live (default 1 day)
	 *
	 * @var		string	$ttl
	 */
	var $ttl = 86400 /* 60 * 60 * 24 */;

	/**
	 * cache depth for scalability
	 *
	 * @var		intger	$depth
	 */
	var $depth = 2;

	/**
	 * optional cache file extension
	 *
	 * @var		string	$ext
	 */
	var $ext = '';

	/**
	 * cache file handler for a raw string (html/php etc.)
	 *
	 * @var		string	$handler
	 */
	var $handler = '';

	/**
	 * cache file path
	 *
	 * @var		string	$cache_path
	 */
	var $cache_path = '/var/tmp/_cache';

	/**
	 * make a uniq id with a given hash function
	 *
	 * @var		string	$hash
	 */
	var $hash = 'md5';

	function Cache_Text($arena = 'default', $cache_info = false)
	{
		global $Config;

		$cache_path = (!empty($cache_info['dir'])) ? $cache_info['dir'] : $Config['cache_dir'];

		$this->arena = $arena;
		$this->cache_path = $cache_path;
		$this->cache_dir = $cache_path . '/' . $arena;

		// $cache_info init param is used only once for the first time
		if ($this->_exists($this->arena.'/.info')) {
			$cache_info = $this->_fetch_serial($this->arena.'/.info');
		} else {
			// validate cache_info
			$cache_info = $this->_validate_cache_info($cache_info);
			// init cache dirs
			$this->_prepare_cache_dirs($this->cache_dir, $cache_info['depth']);

			$this->_update($this->arena.'/.info', $cache_info);
		}

		$this->_initCacheInfo($cache_info);
	}

	function _initCacheInfo($cache_info = false)
	{
		$fields = array('ttl', 'depth', 'ext', 'handler', 'hash');
		foreach ($fields as $f) {
			if (isset($cache_info[$f])) $this->$f = $cache_info[$f];
		}

		!empty($this->ext) ? $this->_ext = '.' . $this->ext : $this->_ext  = '';
	}

	/**
	 * Change cacheinfo
	 *
	 */
	function _setCacheInfo($ttl = null, $depth = null, $ext = null, $handler = null, $hash = null)
	{
		$fields = array('ttl', 'depth', 'ext', 'handler', 'hash');

		$cache_info = array();
		foreach ($fields as $f) {
			if ($$f !== null) $cache_info[$f] = $$f;
			else $cache_info[$f] = $this->$f;
		}
		// validate cache_info
		$cache_info = $this->_validate_cache_info($cache_info);
		return $this->_update($this->arena.'/.info', $cache_info);
	}

	function getCacheInfo()
	{
		return $this->_fetch_serial($this->arena.'/.info');
	}

	function _validate_cache_info($cache_info = false)
	{
		$fields = array('ttl', 'depth', 'ext', 'handler', 'hash');
		if (!is_array($cache_info)) {
			// set the default cache_info
			$cache_info = array();
			foreach ($fields as $f) $cache_info[$f] = $this->$f;
		} else {
			$validated_cache_info = array();
			foreach ($fields as $f) {
				isset($cache_info[$f]) ? $validated_cache_info[$f] = $cache_info[$f]:
					$validated_cache_info[$f] = $this->$f;
			}
			$cache_info = $validated_cache_info;

			// validate cache infos
			// FIXME. How can I direct access with a cache key name ?
			if (isset($cache_info['hash'][0]) and
					!in_array($cache_info['hash'], array('md5', 'sha1')) and !function_exists($cache_info['hash']))
				$cache_info['hash'] = 'md5';

		}
		$cache_info['ext'] = ltrim($cache_info['ext'], '.'); // fix ext: .html => html
		return $cache_info;
	}

	/**
	 * prepare cache dirs
	 */
	function _prepare_cache_dirs($top_dir, $depth = 2, $mkdir = true, $mode = 0777) {
		if ($mkdir) {
			$om = umask(~0777);
			if (!is_dir($top_dir)) mkdir($top_dir, $mode);
		}

		$prefix = array('');
		for ($j = 0; $j < $depth; $j++) {
			$dirs = array();
			$rdirs = array();
			foreach ($prefix as $pre) {
				$d = '';
				for ($i = 0; $j > 0 and $i < $j; $i++)
					$d.= substr($pre, 0, $i + 1) . '/';

				for ($i = 0; $i < 16; $i++) {
					$dir = $pre.dechex($i);
					if ($mkdir) mkdir($top_dir.'/'.$d.$dir, $mode);

					$dirs[] = $dir;
					$rdirs[] = $d.$dir;
				}
			}
			$prefix = $dirs;
		}
		if ($mkdir) umask($om);

		return $rdirs;
	}

	function getKey($id, $hash = true)
	{
		if (!empty($this->hash) and $hash) {
			$func = $this->hash;
			$key = $hashkey = $func($id);
		} else {
			$key = $id;
			$hashkey = ($this->depth > 0) ? md5($id) : '';
		}

		$prefix = '';
		for ($i = 0; $i < $this->depth; $i++) {
			$prefix.= substr($hashkey, 0, $i + 1) . '/';
		}
		return $this->arena.'/'.$prefix . $key . $this->_ext;
	}

	function getUniq($id)
	{
		if ($this->hash) {
			$func = $this->hash;
			return $func($id);
		}
		return $id;
	}

	function update($id, $val, $ttl_or_mtime = 0, $params = null)
	{
		$id = (string)$id;
		if (!isset($id[0])) return false;

		$ttl = 0;
		$key = $this->getKey($id);

		if ($ttl_or_mtime) {
			if ($ttl_or_mtime < 31536000 /* 60*60*24*365 (1 year) */ ) {
				// is it ttl(time to live) ?
				$ttl = $ttl_or_mtime;
			} else {
				// or mtime.
				if ($ttl_or_mtime <= $this->mtime($key))
					// already updated
					return false;
			}
		}

		// update the mtime of the cache info file.
		@touch($this->cache_dir . '/.info');
		return $this->_update($key, $val, $ttl, $params);
	}

	function _update($key, $val, $ttl = 0, $params = null)
	{
		$type = isset($params['type']) ? $params['type'] : $this->ext;
		$save = $val;
		if (is_array($val) or is_object($val)) {
			$vals = array();
			!empty($params['deps']) ? $vals['deps'] = $params['deps'] : false;
			// php var_export() or serialize()
			if ($type == 'php') {
				$header = '';
				($ttl) ? $vals['ttl'] = $ttl : null;
				$header = $this->header($type, $vals);
				$save = $header."\n".'<'.'?php return '.var_export($val, true).';';
			} else {
				$vals['ttl'] = $ttl;
				$vals['mtime'] = time();
				$vals['val'] = $val;
				$save = serialize($vals);
			}
		} else if (!empty($type)) {
			$vals = array();
			($ttl) ? $vals['ttl'] = $ttl : null;
			!empty($params['deps']) ? $vals['deps'] = $params['deps'] : null;
			$header = $this->header($type, $vals);

			$save = $header."\n".$val;
		}
		return $this->_save($key, $save);
	}

	function _save($key, $val)
	{
		$fp = fopen($this->cache_path . '/' .$key, 'a+b');
		if (is_resource($fp)) {
			flock($fp, LOCK_EX);
			ftruncate($fp, 0);
			fwrite($fp, $val);
			flock($fp, LOCK_UN);
			fclose($fp);

			return true;
		}
		return false;
	}


	function fetch($id, $mtime = 0, $params = array()) {
		$key = $this->getKey($id);
		if ($this->_exists($key)) {
			if (empty($mtime))
				return $this->_fetch($key, 0, $params);
			else if ($this->_mtime($key) > $mtime)
				return $this->_fetch($key, $mtime, $params);
		}
		return false;
	}

	function exists($id) {
		$key = $this->getKey($id);
		return $this->_exists($key);
	}

	function _exists($key) {
		return @file_exists($this->cache_path . '/' . $key);
	}

	function _fetch_serial($key) {
		$fname = $this->cache_path . '/'. $key;

		$fp = @fopen($fname, 'r');
		if (!is_resource($fp)) return false;

		$size = filesize($fname);
		if ($size == 0) return false;
		$ret = fread($fp, $size);
		fclose($fp);
		$val = unserialize($ret);
		return $val['val'];
	}

	function _fetch_php($key) {
		$fname = $this->cache_path . '/'. $key;

		$val = @include $fname;
		if ($val === false) return false;
		return $val['val'];
	}

	function _fetch($key, $mtime = 0, $params = array()) {
		$type = isset($params['type']) ? $params['type'] : $this->ext;

		$fname = $this->cache_path . '/'. $key;

		if (!empty($params['nosanitycheck']) and !empty($params['print'])) {
			return readfile($fname);
		}

		$fp = fopen($fname, 'r');
		if (!is_resource($fp)) return false;

		$size = filesize($fname);
		if ($size == 0) return false;
		$header = fgets($fp, 256);
		$len = 0;

		// Is it serialized ?
		if (empty($type) and isset($header[1]) and $header[1] == ':' and in_array($header[0], array('a','O'))) {
			$len = strlen($header);
			$ret = $header;
			if ($size > $len) $ret.= fread($fp, $size - $len);
			fclose($fp);
			$val = unserialize($ret);
		} else {
			if (empty($type)) $type = 'raw';

			$val = $this->sanity($header, $type, $fp); // sanity check.
			$ret = $header;
			if (is_bool($val)) {
				if ($val === false)
					return false; // fail
				$val = array();
				$val['mtime'] = filemtime($fname);
			}
		}

		// get cache mtime
		$cmtime = $val['mtime'];

		// check mtime
		if ($mtime and $cmtime < $mtime)
			return false;

		// check ttl
		if (isset($params['ttl'])) {
			if (empty($val['ttl']) or $params['ttl'] < $val['ttl'])
				$val['ttl'] = $params['ttl'];
		}

		if (!empty($val['ttl']) and $cmtime + $val['ttl'] < time()) {
			return false;
		}

		// check mtime of dependencies
		if (!empty($val['deps'])) {
			$deps = &$val['deps'];
			if (!is_array($deps)) {
				$deps = array($deps);
			}
			$dmtime = $mtime ? $mtime : $cmtime;

			foreach ($deps as $dep) {
				if (file_exists($dep) and $dmtime < filemtime($dep)) {
					return false;
				}
			}
		}

		// flat files. html, php, etc.
		if (is_resource($fp) and $size > $len) {
			// include ?
			if (!empty($params['include'])) {
				fclose($fp);
				return include $fname;
			}

			// print contents
			if (!$len and !empty($params['print'])) {
				fclose($fp);
				return readfile($fname);
			}

			// read remaining contents
			$ret.= fread($fp, $size - $len);
			//$ret = fread($fp, $size - $len);
			fclose($fp);

			// print contents ?
			if (!empty($params['print']))
				return print $ret;

			// or return
			return $ret;
		}
		return $val['val'];
	}

	function _mtime($key) {
		return filemtime($this->cache_path . '/' . $key);
	}

	function mtime($id = null) {
		if (!$id === null or !isset($id[0]))
			return filemtime($this->cache_dir . '/.info');

		$key = $this->getKey($id);
		if ($this->_exists($key))
			return $this->_mtime($key);
		return 0;
	}

	function remove($id) {
		$key = $this->getKey($id);
		if ($this->_exists($key)) {
			unlink($this->cache_path.'/'.$key);
			return true;
		}
		return false;
	}

	/**
	 * make cache header
	 */
	function header($type, $vals = false)
	{
		$timestamp = time();
		switch($type) {
		case 'html':
			$bra = '<!-- // ' . $this->name . ' ' . $this->revision . ' ' . $timestamp."\n";
			$ket = "\n" . '-->';
			break;
		case 'php':
			$bra = '<'.'?php /* ' . $this->name . ' ' . $this->revision . ' ' . $timestamp."\n";
			$ket = "\n" . '*/?'.'>';
			break;
		default: // raw
			$bra = '';
			$ket = '';
			return '';
		}

		if (!empty($vals))
			$out = base64_encode(serialize($vals))."\n";
		else
			$out = '';

		return $bra.$timestamp.' '.$out.$ket;
	}

	/**
	 * parse cache header
	 */
	function sanity($header, $type, $fp, $params = false)
	{
		if ($type == 'raw') return true; // XXX binary or raw caches

		$ret = fgets($fp, 1024);
		while (!feof($fp) and strrpos($ret, "\n") === false)
			$ret.= fgets($fp, 1024);

		$vals = array();
		list($timestamp, $val) = explode(' ', $ret);
		if (trim($val)) {
			$vals = unserialize(base64_decode($val));
		}
		$vals['mtime'] = $timestamp;
		return $vals;
	}

	/**
	 * get all cache files
	 *
	 */
	function _caches(&$files, $params = array())
	{
		$top = $this->cache_dir;
		$dirs = $this->_prepare_cache_dirs($this->cache_dir, $this->depth, false);

		foreach ($dirs as $dir) {
			$dh = opendir($top.'/'.$dir);
			$prefix = $this->arena.'/'.$dir .'/';
			if (!is_resource($dh)) continue; // slightly ignore

			while (($file = readdir($dh)) !== false) {
				if ($file[0] == '.')
					continue;

				if (isset($params['prefix']))
					$files[] = $prefix . $file;
				else
					$files[] = $file;
			}
			closedir($dh);
		}
	}

	/**
	 * count cache files
	 *
	 */
	function count()
	{
		$top = $this->cache_dir;
		$dirs = $this->_prepare_cache_dirs($this->cache_dir, $this->depth, false);

		$count = 0;
		foreach ($dirs as $dir) {
			$dh = opendir($top.'/'.$dir);
			$prefix = $this->arena.'/'.$dir .'/';
			if (!is_resource($dh)) continue; // can't open. silently ignore

			while (($file = readdir($dh)) !== false) {
				if ($file[0] == '.')
					continue;
				$count++;
			}
		}
		return $count;
	}
}

	/**
	// TODO support handler
	function header($type, $vals = false)
	{
		if (empty($this->handler))
			return '';

		if (!is_object($this->handler)) {
			$class = $this->handler;
			$this->handler = new $class;
		}
		return $this->handler->header($this, $type, $vals);
	}

	function sanity($header, $type, $fp = false, $params = false)
	{
		if (empty($this->handler))
			return true;

		if (!is_object($this->handler)) {
			$class = $this->handler;
			$this->handler = new $class;
		}
		return $this->handler->sanity($this, $header, $params);
	}
	*/

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
