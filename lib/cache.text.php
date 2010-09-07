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
	var $revision = '0.5';

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

		if (empty($cache_info)) {
			$cache_info = $this->_fetch_serial('.info');

			if (empty($cache_info)) {
				$cache_info = $this->_save_cache_info();
			}
		}

		$this->initCacheInfo($cache_info);
	}

	function initCacheInfo($cache_info = false)
	{
		isset($cache_info['ttl']) ? $this->ttl = $cache_info['ttl'] : true;
		isset($cache_info['depth']) ? $this->depth = $cache_info['depth'] : true;
		isset($cache_info['ext']) ? $this->ext = $cache_info['ext'] : true;
		isset($cache_info['handler']) ? $this->handler = $cache_info['handler'] : true;
		isset($cache_info['hash']) ? $this->hash = $cache_info['hash'] : true;

		// FIXME. How can I direct access with a cache key name ?
		if (!function_exists($this->hash))
			$this->hash = 'md5';

		!empty($this->ext) ? $this->_ext = '.' . $this->ext : $this->_ext  = '';
	}

	function updateCacheInfo($ttl = 0, $depth = 0, $ext = 0, $handler = '', $hash = 'md5')
	{
		$info = $this->cache_dir . '/.info';
		if (file_exists($info)) {
			$infos = $this->_fetch_serial('.info');

			$infos['ttl'] = ($ttl) ? $ttl : $this->ttl;
			$infos['depth'] = ($depth) ? $depth : $this->depth;
			$infos['ext'] = ($ext) ? $ext : $this->ext;
			$infos['handler'] = ($handler) ? $handler : $this->handler;
			$infos['hash'] = ($hash) ? $hash : $this->hash;
		}
		return $this->_save_cache_info($infos);
	}

	function getCacheInfo()
	{
		$infos = $this->_fetch_serial('.info');
		return $infos;
	}

	function _save_cache_info($cache_info = false)
	{
		if (!$cache_info) {
			// set default cache_info
			$cache_info = array();
			$cache_info['ttl'] = $this->ttl;
			$cache_info['depth'] = $this->depth;
			!empty($cache_info['ext']) ? $cache_info['ext'] = $cache_info['ext'] : $this->ext;
			$cache_info['handler'] = $this->handler;
			$cache_info['hash'] = $this->hash;
			$this->_update('.info', $cache_info);
			//$this->_update('.info', $cache_info, 0, array('type'=>'php'));
		}
		return $cache_info;
	}


	function getKey($id, $hash = true)
	{
		if (!empty($this->hash) and $hash) {
			$func = $this->hash;
			$key = $func($id);
		} else {
			$key = $id;
		}

		if (!empty($this->depth)) {
			$prefix = substr($key, 0, $this->depth);
			return $prefix . '/' . $key . $this->_ext;
		}
		return $key . $this->_ext;
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
				if ($ttl_or_mtime and ($ttl_or_mtime <= $this->mtime($key)))
					return false;
			}
		}

		$this->updateCacheInfo();
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
		$dir = dirname($this->cache_dir . '/' . $key);
		if (!is_dir($dir)) {
			$om = umask(~0777);
			mkdir($dir, 0777, true);
			umask($om);
		}
		$fp = fopen($this->cache_dir . '/' .$key, 'a+b');
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
		return @file_exists($this->cache_dir . '/' . $key);
	}

	function _fetch_serial($key) {
		$fname = $this->cache_dir . '/'. $key;

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
		$fname = $this->cache_dir . '/'. $key;

		$val = @include $fname;
		if ($val === false) return false;
		return $val['val'];
	}

	function _fetch($key, $mtime = 0, $params = array()) {
		$type = isset($params['type']) ? $params['type'] : $this->ext;

		$fname = $this->cache_dir . '/'. $key;

		$fp = fopen($fname, 'r');
		if (!is_resource($fp)) return false;

		$size = filesize($fname);
		if ($size == 0) return false;
		$header = fgets($fp, 256);
		$len = 0;

		// Is it serialized ?
		if (isset($header[1]) and $header[1] == ':' and in_array($header[0], array('a','O'))) {
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
		return filemtime($this->cache_dir . '/' . $key);
	}

	function mtime($id = null) {
		if (empty($id))
			return filemtime($this->cache_dir . '/.info');

		$key = $this->getKey($id);
		if ($this->_exists($key))
			return $this->_mtime($key);
		return 0;
	}

	function remove($id) {
		$key = $this->getKey($id);
		if ($this->_exists($key)) {
			unlink($this->cache_dir.'/'.$key);
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
	function _caches(&$files, $dir = '')
	{
		$top = $this->cache_dir;
		$prefix = '';
		$_dir = $top;
		if (!empty($dir)) {
			$prefix = $dir .'/';
			$_dir = $top.'/'.$dir;
		}

		$dh = opendir($_dir);
		if (!$dh) return; // slightly ignore

		while (($file = readdir($dh)) !== false) {
			if ($file[0] == '.')
                		continue;
			if (is_dir($_dir . '/'. $file)) {
				$this->_caches($files, $file);
				continue;
			}

			$files[] = $prefix . $file;
		}
		closedir($dh);
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
