<?php
/**
 * HTTP Client
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Goetz <cpuidle@gmx.de>
 */

define('HTTP_NL',"\r\n");

/**
 * This class implements a basic HTTP client
 *
 * It supports POST and GET, Proxy usage, basic authentication,
 * handles cookies and referers. It is based upon the httpclient
 * function from the VideoDB project.
 *
 * @link   http://www.splitbrain.org/go/videodb
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @author Andreas Gohr <andi@splitbrain.org>
 */
class HTTPClient {
    //set these if you like
    var $agent;         // User agent
    var $http;          // HTTP version defaults to 1.0
    var $timeout;       // read timeout (seconds)
    var $cookies;
    var $referer;
    var $max_redirect;
    var $max_buffer_size; // store body as a temp file conditionally
    var $max_bodysize;  // abort if the response body is bigger than this
    var $header_regexp; // if set this RE must match against the headers, else abort
    var $headers;
    var $debug;

    // don't set these, read on error
    var $error;
    var $redirect_count;

    // read these after a successful request
    var $status;
    var $resp_body;
    var $resp_body_file; // store body as a temp file
    var $resp_headers;

    // set these to do basic authentication
    var $user;
    var $pass;

    // set these if you need to use a proxy
    var $proxy_host;
    var $proxy_port;
    var $proxy_user;
    var $proxy_pass;
    var $proxy_ssl; //boolean set to true if your proxy needs SSL

    /**
     * Constructor.
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function HTTPClient(){
        $this->agent        = 'Mozilla/4.0 (compatible; HTTP Client; '.PHP_OS.')';
        $this->timeout      = 15;
        $this->cookies      = array();
        $this->referer      = '';
        $this->max_redirect = 3;
        $this->redirect_count = 0;
        $this->status       = 0;
        $this->headers      = array();
        $this->http         = '1.0';
        $this->debug        = false;
        $this->max_buffer_size = 0;
        $this->max_bodysize = 0;
        $this->header_regexp= '';
        $this->nobody       = false;
        if(extension_loaded('zlib')) $this->headers['Accept-encoding'] = 'gzip';
        $this->headers['Accept'] = 'text/xml,application/xml,application/xhtml+xml,'.
                                   'text/html,text/plain,image/png,image/jpeg,image/gif,*/*';
        $this->headers['Accept-Language'] = 'en-us';
        $this->vartmp_dir = '/tmp';
    }


    /**
     * Simple function to do a GET request
     *
     * Returns the wanted page or false on an error;
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function get($url){
        if(!$this->sendRequest($url)) return false;
        if($this->status != 200) return false;
        return $this->resp_body;
    }

    /**
     * Simple function to do a POST request
     *
     * Returns the resulting page or false on an error;
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function post($url,$data){
        if(!$this->sendRequest($url,$data,'POST')) return false;
        if($this->status != 200) return false;
        return $this->resp_body;
    }

    /**
     * Do an HTTP request
     *
     * @author Andreas Goetz <cpuidle@gmx.de>
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function sendRequest($url,$data=array(),$method='GET'){
        $this->error = '';
        $this->status = 0;

        // parse URL into bits
        $uri = parse_url($url);
        $server = $uri['host'];
        $path   = $uri['path'];
        if(empty($path)) $path = '/';
        if(!empty($uri['query'])) $path .= '?'.$uri['query'];
        if(!empty($uri['port'])) $port = $uri['port'];
        if(isset($uri['user'][0])) $this->user = $uri['user'];
        if(isset($uri['pass'][0])) $this->pass = $uri['pass'];

        // proxy setup
        if($this->proxy_host){
            $request_url = $url;
            $server      = $this->proxy_host;
            $port        = $this->proxy_port;
            if (empty($port)) $port = 8080;
        }else{
            $request_url = $path;
            $server      = $server;
            if (empty($port)) $port = ($uri['scheme'] == 'https') ? 443 : 80;
        }

        // add SSL stream prefix if needed - needs SSL support in PHP
        if($port == 443 || $this->proxy_ssl) $server = 'ssl://'.$server;

        // prepare headers
        $headers               = $this->headers;
        $headers['Host']       = $uri['host'];
        if(!empty($uri['port'])) $headers['Host'].= ':'.$uri['port'];
        $headers['User-Agent'] = $this->agent;
        $headers['Referer']    = $this->referer;
        $headers['Connection'] = 'Close';
        $post = '';
        if(in_array($method, array('POST', 'PUT'))){
            if (is_array($data))
                $post = $this->_postEncode($data);
            else
                $post = $data;
            $headers['Content-Type']   = 'application/x-www-form-urlencoded';
            $headers['Content-Length'] = strlen($post);
        }
        if($this->user) {
            $headers['Authorization'] = 'BASIC '.base64_encode($this->user.':'.$this->pass);
        }
        if($this->proxy_user) {
            $headers['Proxy-Authorization'] = 'BASIC '.base64_encode($this->proxy_user.':'.$this->proxy_pass);
        }

        // stop time
        $start = time();

        // open socket
        $socket = @fsockopen($server,$port,$errno, $errstr, $this->timeout);
        if (!$socket){
            $this->status = -100 - $errno;
            $this->error = "Could not connect to $server:$port\n$errstr ($errno)";
            return false;
        }
        //set non blocking
        socket_set_blocking($socket,0);
        //stream_set_blocking($socket,0);

        // build request
        $request  = "$method $request_url HTTP/".$this->http.HTTP_NL;
        $request .= $this->_buildHeaders($headers);
        $request .= $this->_getCookies();
        $request .= HTTP_NL;
        $request .= $post;

        $this->_debug('request',$request);

        // send request
        $towrite = strlen($request);
        $written = 0;
        while($written < $towrite){
            // check timeout
            $time_used = time() - $start;
            if($time_used > $this->timeout) {
                $this->status = -100;
                $this->error = sprintf('Timeout while sending request (%.3fs)',$time_used);
                fclose($socket);
                return false;
            }

            // wait for stream ready or timeout (1sec)
            // select parameters
            $r = null;
            $w = array($socket);
            $e = null;
            if(@stream_select($r,$w,$e,1) === false){
                usleep(1000);
                continue;
            }

            // write to stream
            $ret = fwrite($socket, substr($request,$written,4096));
            if($ret === false) {
                $this->status = -100;
                $this->error = 'Failed writing to socket';
                fclose($socket);
                return false;
            }
            $written += $ret;
        }
        //fputs($socket, $request);

        // read headers from socket
        $r_headers = '';
        do{
            if(time()-$start > $this->timeout){
                $this->status = -100;
                $this->error = 'Timeout while reading headers';
                fclose($socket);
                return false;
            }
            if(feof($socket)){
                $this->error = 'Premature End of File (socket)';
                fclose($socket);
                return false;
            }

            // select parameters
            $r = array($socket);
            $w = null;
            $e = null;
            // wait for stream ready or timeout (1sec)
            if (@stream_select($r,$w,$e,1) === false) {
                usleep(1000);
                continue;
            }

            $r_headers .= fgets($socket,1024);
        }while(!preg_match('/\r?\n\r?\n$/',$r_headers));

        $this->_debug('response headers',$r_headers);

        // check if expected body size exceeds allowance
        if($this->max_bodysize && preg_match('/\r?\nContent-Length:\s*(\d+)\r?\n/i',$r_headers,$match)){
            if($match[1] > $this->max_bodysize){
                $this->error = 'Reported content length exceeds allowed response size';
                fclose($socket);
                return false;
            }
        }

        // get Status
        if (!preg_match('/^HTTP\/(\d\.\d)\s*(\d+).*?\n/', $r_headers, $m)) {
            $this->error = 'Server returned bad answer';
            fclose($socket);
            return false;
        }
        $this->status = $m[2];

        // handle headers and cookies
        $this->resp_headers = $this->_parseHeaders($r_headers);
        if(isset($this->resp_headers['set-cookie'])){
            foreach ((array) $this->resp_headers['set-cookie'] as $c){
                $cs=explode(';',$c);
                foreach ($cs as $c) {
                    list($key, $value) = explode('=', $c, 2);
                    $this->cookies[trim($key)] = $value;
                }
            }
        }

        $this->_debug('Object headers',$this->resp_headers);

        // check server status code to follow redirect
        if($this->status == 301 || $this->status == 302 ){
            if (empty($this->resp_headers['location'])){
                $this->error = 'Redirect but no Location Header found';
                fclose($socket);
                return false;
            }elseif($this->redirect_count == $this->max_redirect){
                $this->error = 'Maximum number of redirects exceeded';
                fclose($socket);
                return false;
            }else{
                $this->redirect_count++;
                $this->referer = $url;
                if (!preg_match('/^http/i', $this->resp_headers['location'])){
                    $this->resp_headers['location'] = $uri['scheme'].'://'.$uri['host'].
                                                      $this->resp_headers['location'];
                }
                // perform redirected request, always via GET (required by RFC)
                fclose($socket);
                return $this->sendRequest($this->resp_headers['location'],array(),'GET');
            }
        }

        // check if headers are as expected
        if($this->header_regexp && !preg_match($this->header_regexp,$r_headers)){
            $this->error = 'The received headers did not match the given regexp';
            fclose($socket);
            return false;
        }

        //read body (with chunked encoding if needed)
        $r_body    = '';
        $tmp_fp = null;
        $tmp_file = null;
        if (!empty($this->nobody) and preg_match('/transfer\-(en)?coding:\s*chunked\r\n/i',$r_headers)) {
            do {
                unset($chunk_size);
                do {
                    if(feof($socket)){
                        $this->error = 'Premature End of File (socket)';
                        fclose($socket);
                        return false;
                    }
                    if(time()-$start > $this->timeout){
                        $this->status = -100;
                        $this->error = 'Timeout while reading chunk';
                        fclose($socket);
                        return false;
                    }
                    $byte = fread($socket,1);
                    $chunk_size .= $byte;
                } while (preg_match('/[a-zA-Z0-9]/',$byte)); // read chunksize including \r

                $byte = fread($socket,1);     // readtrailing \n
                $chunk_size = hexdec($chunk_size);
                $this_chunk = fread($socket,$chunk_size);
                $r_body    .= $this_chunk;
                if ($chunk_size) $byte = fread($socket,2); // read trailing \r\n

                if (!empty($this->max_buffer_size) && $r_body > $this->max_buffer_size) {
                    if (empty($tmp_file)) {
                        $tmp_file = tempnam($this->vartmp_dir, 'HTTP_TMP');
                        $tmp_fp = fopen($tmp_file, 'w');
                        if (!is_resource($tmp_fp)) {
                            $this->status = -100;
                            $this->error = 'can not open temp file';
                            fclose($socket);
                            return false;
                        }
                    }
                    fwrite($tmp_fp, $r_body);
                    $r_body = '';
                } else
                if($this->max_bodysize && strlen($r_body) > $this->max_bodysize){
                    $this->error = 'Allowed response size exceeded';
                    fclose($socket);
                    return false;
                }
            } while ($chunk_size);
        } else if (empty($this->nobody)) {
            // read entire socket
            while (!feof($socket)) {
                if(time()-$start > $this->timeout){
                    $this->status = -100;
                    $this->error = 'Timeout while reading response';
                    fclose($socket);
                    return false;
                }
                $r_body .= fread($socket,4096);
                if (!empty($this->max_buffer_size) && $r_body > $this->max_buffer_size) {
                    if (empty($tmp_file)) {
                        $tmp_file = tempnam($this->vartmp_dir, 'HTTP_TMP');
                        $tmp_fp = fopen($tmp_file, 'w');
                        if (!is_resource($tmp_fp)) {
                            $this->status = -100;
                            $this->error = 'can not open temp file';
                            fclose($socket);
                            return false;
                        }
                    }
                    fwrite($tmp_fp, $r_body);
                    $r_body = '';
                } else
                if($this->max_bodysize && strlen($r_body) > $this->max_bodysize){
                    $this->error = 'Allowed response size exceeded';
                    fclose($socket);
                    return false;
                }
            }
        }

        // close socket
        $status = socket_get_status($socket);
        fclose($socket);

        if (is_resource($tmp_fp)) {
            if (isset($r_body[0]))
                fwrite($tmp_fp, $r_body);
            fclose($tmp_fp);
            $r_body = '';
        }

        // decode gzip if needed
        if(isset($this->resp_headers['content-encoding']) &&
           $this->resp_headers['content-encoding'] == 'gzip' &&
           strlen($r_body) > 10 && substr($r_body,0,3)=="\x1f\x8b\x08"){
            $this->resp_body = @gzinflate(substr($r_body, 10));
            if($this->resp_body === false){
                $this->error = 'Failed to decompress gzip encoded content';
                $this->resp_body = $r_body;
            }
        }else{
            if (!empty($tmp_file)) {
                $this->resp_body = $r_body;
                $this->resp_body_file =
                    $tmp_file;
            } else {
                $this->resp_body = $r_body;
            }
        }

        $this->_debug('response body',$this->resp_body);
        $this->redirect_count = 0;
        return true;
    }

    /**
     * print debug info
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _debug($info,$var){
        if(!$this->debug) return;
        print '<b>'.$info.'</b><br />';
        ob_start();
        print_r($var);
        $content = htmlspecialchars(ob_get_contents());
        ob_end_clean();
        print '<pre>'.$content.'</pre>';
    }

    /**
     * convert given header string to Header array
     *
     * All Keys are lowercased.
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _parseHeaders($string){
        $headers = array();
        $lines = explode("\n",$string);
        foreach($lines as $line){
            @list($key,$val) = explode(':',$line,2);
            $key = strtolower(trim($key));
            $val = trim($val);
            if(empty($val)) continue;
            if(isset($headers[$key])){
                if(is_array($headers[$key])){
                    $headers[$key][] = $val;
                }else{
                    $headers[$key] = array($headers[$key],$val);
                }
            }else{
                $headers[$key] = $val;
            }
        }
        return $headers;
    }

    /**
     * convert given header array to header string
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _buildHeaders($headers){
        $string = '';
        foreach($headers as $key => $value){
            if(empty($value)) continue;
            $string .= $key.': '.$value.HTTP_NL;
        }
        return $string;
    }

    /**
     * get cookies as http header string
     *
     * @author Andreas Goetz <cpuidle@gmx.de>
     */
    function _getCookies(){
        $headers = '';
        foreach ($this->cookies as $key => $val){
            if ($headers) $headers .= '; ';
            $headers .= $key.'='.$val;
        }

        if (!empty($headers)) $headers = "Cookie: $headers".HTTP_NL;
        return $headers;
    }

    /**
     * Encode data for posting
     *
     * @todo handle mixed encoding for file upoads
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _postEncode($data){
        $url = '';
        foreach($data as $key => $val){
            if($url) $url .= '&';
            $url .= $key.'='.urlencode($val);
        }
        return $url;
    }
}

//Setup VIM: ex: et ts=4 :
