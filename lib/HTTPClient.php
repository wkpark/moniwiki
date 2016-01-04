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
 * @author Tobias Sarnowski <sarnowski@new-thoughts.org>
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

    var $keep_alive = false; // keep alive rocks

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

    // list of kept alive connections
    var $connections = array();

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
        $this->start = time();

        // already connected?
        $connectionId = $this->_uniqueConnectionId($server,$port);
        $this->_debug('connection pool', $this->connections);
        if (isset($this->connections[$connectionId])) {
            $this->_debug('reusing connection', $connectionId);
            $socket = $this->connections[$connectionId];
        } else {
            $this->_debug('opening connection', $connectionId);
            // open socket

            if ($uri['scheme'] == 'https' and $this->proxy_host) {
                $context = stream_context_create(array(
                        'ssl' => array(
                            'SNI_server_name'=>$uri['host'],
                            'SNI_enable'=>'true',
                            // (enabled since PHP 5.6)
                            'peer_name'=>$uri['host'],
                        )
                    ));
                $socket = stream_socket_client('tcp://'.$server.':'.$port, $errno, $errstr,
                    $this->timeout, STREAM_CLIENT_CONNECT, $context);
            } else {
                $socket = @fsockopen($server,$port,$errno, $errstr, $this->timeout);
            }
            if (!$socket){
                $this->status = -100 - $errno;
                $this->error = "Could not connect to $server:$port\n$errstr ($errno)";
                return false;
            }
            // try establish a CONNECT tunnel for SSL
            if($this->_ssltunnel($socket, $request_url)){
                // no keep alive for tunnels
                $this->keep_alive = false;
                // tunnel is authed already
                if(isset($headers['Proxy-Authentication'])) unset($headers['Proxy-Authentication']);
            }

            // keep alive?
            if ($this->keep_alive) {
                $this->connections[$connectionId] = $socket;
            }
        }
        if ($this->keep_alive && !$this->proxy_host) {
            // RFC 2068, section 19.7.1: A client MUST NOT send the Keep-Alive
            // connection token to a proxy server. We still do keep the connection the
            // proxy alive (well except for CONNECT tunnels)
            $headers['Connection'] = 'Keep-Alive';
        } else {
            $headers['Connection'] = 'Close';
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

        $ret = $this->_sendData($socket, $request, 'request');
        if ($ret === false)
            return false;
        //fputs($socket, $request);

        // read headers from socket
        $r_headers = '';
        do{
            $r_line = $this->_readLine($socket, 'headers');
            if ($r_line === false)
                return false;
            $r_headers .= $r_line;
        }while($r_line != "\r\n" && $r_line != "\n");

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
                    if (($p = strpos($c, '=')) === false)
                        continue;
                    list($key, $value) = explode('=', $c, 2);
                    $this->cookies[trim($key)] = $value;
                }
            }
        }

        $this->_debug('Object headers',$this->resp_headers);

        // check server status code to follow redirect
        if($this->status == 301 || $this->status == 302 ){
            // close the connection because we don't handle content retrieval here
            // that's the easiest way to clean up the connection
            fclose($socket);
            unset($this->connections[$connectionId]);

            if (empty($this->resp_headers['location'])){
                $this->error = 'Redirect but no Location Header found';
                return false;
            }elseif($this->redirect_count == $this->max_redirect){
                $this->error = 'Maximum number of redirects exceeded';
                return false;
            }else{
                $this->redirect_count++;
                $this->referer = $url;
                if (!preg_match('/^http/i', $this->resp_headers['location'])){
                    $this->resp_headers['location'] = $uri['scheme'].'://'.$uri['host'].
                                                      $this->resp_headers['location'];
                }
                // perform redirected request, always via GET (required by RFC)
                return $this->sendRequest($this->resp_headers['location'],array(),'GET');
            }
        }

        // check if headers are as expected
        if($this->header_regexp && !preg_match($this->header_regexp,$r_headers)){
            $this->error = 'The received headers did not match the given regexp';
            fclose($socket);
            return false;
        }

        $nobody = isset($http->resp_headers['content-length']) && $this->nobody;

        //read body (with chunked encoding if needed)
        $r_body    = '';
        $tmp_fp = null;
        $tmp_file = null;
        $length = 0;
        if (!$nobody && preg_match('/transfer\-(en)?coding:\s*chunked\r\n/i',$r_headers)) {
            do {
                $chunk_size = '';
                do {
                    if(feof($socket)){
                        $this->error = 'Premature End of File (socket)';
                        fclose($socket);
                        return false;
                    }
                    if(time()-$this->start > $this->timeout){
                        $this->status = -100;
                        $this->error = 'Timeout while reading chunk';
                        fclose($socket);
                        return false;
                    }
                    $byte = fread($socket,1);
                    $chunk_size .= $byte;
                } while (preg_match('/^[a-zA-Z0-9]?$/',$byte)); // read chunksize including \r

                $byte = fread($socket,1);     // readtrailing \n
                $chunk_size = hexdec($chunk_size);
                if ($chunk_size > 0) {
                    $length+= $chunk_size;
                    $read_size = $chunk_size;
                    while ($read_size > 0) {
                        $this_chunk = fread($socket,$read_size);
                        $r_body    .= $this_chunk;
                        $read_size -= strlen($this_chunk);
                    }
                    $byte = fread($socket,2); // read trailing \r\n
                }

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
        } else if (!$nobody){
            // read entire socket
            while (!feof($socket)) {
                if(time()-$this->start > $this->timeout){
                    $this->status = -100;
                    $this->error = 'Timeout while reading response';
                    fclose($socket);
                    return false;
                }
                $tmp = fread($socket,4096);
                $length+= strlen($tmp);
                $r_body .= $tmp;
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

        if (!$this->keep_alive ||
                (isset($this->resp_headers['connection']) && $this->resp_headers['connection'] == 'Close')) {
            // close socket
            $status = socket_get_status($socket);
            fclose($socket);
            unset($this->connections[$connectionId]);
        }

        if (is_resource($tmp_fp)) {
            if (isset($r_body[0]))
                fwrite($tmp_fp, $r_body);
            fclose($tmp_fp);
            $r_body = '';
        }

        if (!isset($this->resp_headers['content-length'])) {
            $this->resp_headers['content-length'] = $length;
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
     * Safely write data to a socket
     *
     * @param  handle $socket     An open socket handle
     * @param  string $data       The data to write
     * @param  string $message    Description of what is being read
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    function _sendData($socket, $data, $message) {
        // send request
        $towrite = strlen($data);
        $written = 0;
        while($written < $towrite){
            // check timeout
            $time_used = time() - $this->start;
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
            $ret = fwrite($socket, substr($data,$written,4096));
            if($ret === false) {
                $this->status = -100;
                $this->error = 'Failed writing to socket while sending '.$message;
                fclose($socket);
                return false;
            }
            $written += $ret;
        }
        return true;
    }

    /**
     * Safely read a \n-terminated line from a socket
     *
     * Always returns a complete line, including the terminating \n.
     *
     * @param  handle $socket     An open socket handle in non-blocking mode
     * @param  string $message    Description of what is being read
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    function _readLine($socket, $message) {
        $r_data = '';
        do {
            $time_used = time()-$this->start;
            if($time_used > $this->timeout){
                $this->status = -100;
                $this->error = sprintf('Timeout while reading %s (%.3fs)', $message, $time_used);
                fclose($socket);
                return false;
            }
            if(feof($socket)){
                $this->error = sprintf('Premature End of File (socket) while reading %s', $message);
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
            $r_data .= fgets($socket, 1024);
        } while (!preg_match('/\n$/',$r_data));
        return $r_data;
    }

    /**
     * Tries to establish a CONNECT tunnel via Proxy
     *
     * Protocol, Servername and Port will be stripped from the request URL when a successful CONNECT happened
     *
     * @param ressource &$socket
     * @param string &$requesturl
     * @return bool true if a tunnel was established
     */
    function _ssltunnel(&$socket, &$requesturl){
        if(!$this->proxy_host) return false;
        $requestinfo = parse_url($requesturl);
        if($requestinfo['scheme'] != 'https') return false;
        if(empty($requestinfo['port'])) $requestinfo['port'] = 443;

        // build request
        $request  = "CONNECT {$requestinfo['host']}:{$requestinfo['port']} HTTP/".$this->http.HTTP_NL;
        $request .= "Host: {$requestinfo['host']}:{$requestinfo['port']}".HTTP_NL;
        $request .= "User-Agent: ".$this->agent.HTTP_NL;
        if($this->proxy_user) {
            $request .= 'Proxy-Authorization Basic '.base64_encode($this->proxy_user.':'.$this->proxy_pass).HTTP_NL;
        }
        $request .= HTTP_NL;

        $this->_debug('SSL Tunnel CONNECT',$request);
        $this->_sendData($socket, $request, 'SSL Tunnel CONNECT');

        // read headers from socket
        $r_headers = '';
        do{
            $r_line = $this->_readLine($socket, 'headers');
            if ($r_line === false)
                return false;
            $r_headers .= $r_line;
        }while($r_line != "\r\n" && $r_line != "\n");

        $this->_debug('SSL Tunnel Response',$r_headers);
        if(preg_match('/^HTTP\/1\.[01] 200/i',$r_headers)){
            // Try a TLS connection first
            if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->_debug('STREAM_CRYPTO_METHOD_TLS_CLIENT', '');
                $requesturl = $requestinfo['path'];
                if ($requestinfo['query'])
                    $requesturl .= '?'.$requestinfo['query'];
                return true;
            }
            // Fall back to SSLv3
            if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_SSLv3_CLIENT)) {
                $this->_debug('STREAM_CRYPTO_METHOD_SSLv3_CLIENT', '');
                $requesturl = $requestinfo['path'];
                if ($requestinfo['query'])
                    $requesturl .= '?'.$requestinfo['query'];
                return true;
            }
        }
        return false;
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

    /**
     * Generates a unique identifier for a connection.
     *
     * @return string unique identifier
     */
    function _uniqueConnectionId($server, $port) {
        return "$server:$port";
    }
}

//Setup VIM: ex: et ts=4 :
