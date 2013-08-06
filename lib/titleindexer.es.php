<?php
/**
 * A ElasticSearch Indexer
 *
 * @since 2013/05/26
 * @author Won-Kyu Park <wkpark@kldp.org>
 * @license GPLv2
 */

require_once "lib/HTTPClient.php";
require_once "lib/JSON.php";

class TitleIndexer_ES {
    var $text_dir = '';

    function TitleIndexer_ES($name = 'elasticsearch')
    {
        global $Config;

        $this->text_dir = $Config['text_dir'];
        // make elastic search query string
        $host = !empty($Config['elasticsearch_host_url']) ? $Config['elasticsearch_host_url'] : 'http://localhost:9200';
        $this->host = rtrim($host, '/').'/';
        $this->index = !empty($Config['elasticsearch_index']) ? $Config['elasticsearch_index'] : 'moniwiki';
        $this->index_url = $this->host . $this->index;
        $this->type = 'text';
    }

    function mtime()
    {
        return @filemtime($this->pagelst);
    }

    /**
     * update selected page
     *
     * @access public
     */
    function update($pagename)
    {
        $ret = $this->addPage($pagename, true); // update
        if ($ret === false)
            $ret = $this->addPage($pagename);

        return $ret;
    }

    function init()
    {
        $http = new HTTPClient();

        $url = $this->index_url;
        $data = <<<EOF
{
  "settings":{
    "analysis": {
      "analyzer": {
        "title_analyzer": {
          "type":"custom",
          "tokenizer":"standard",
          "filter":["standard", "lowercase", "myngram" ]
        }
      },
      "filter":{
        "myngram":{
          "type":"edgeNGram",
          "min_gram":1,
          "max_gram":20
        }
      }
    }
  },
  "mappings": {
    "text": {
      "properties": {
        "body": {
         "type":"string"
        },
        "title": {
          "type":"multi_field",
          "fields":{
            "title": {
              "type":"string"
            },
            "titleindex": {
              "type":"string",
              "analyzer":"title_analyzer"
            }
          }
        }
      }
    }
  }
}
EOF;
        $ret = $http->sendRequest($url, $data, 'PUT');
        if ($http->status != 200) return false;
    }

    function init_module()
    {
        global $DBInfo;

        // check init() is needed
        $http = new HTTPClient();
        if (empty($this->json))
            $this->json = new Services_JSON;

        $url = $this->index_url.'/_settings';
        $ret = $http->sendRequest($url, '', 'GET');
        if ($http->status != 404) return false;

        // exclusive lock to prevent multiple init() calls
        $eslock = $DBInfo->vartmp_dir.'/es.lock';
        $lock = @fopen($eslock, 'x');
        if (is_resource($lock)) {
            if (flock($lock, LOCK_EX)) {
                $this->init();
                flock($lock, LOCK_UN);
            }
            fclose($lock);
            unlink($eslock);
        }
    }

    function getPagesByIds($ids)
    {
        $http = new HTTPClient();
        if (empty($this->json)) $this->json = new Services_JSON;

        $selected = array();
        $url = $this->index_url.'/text/_search';
        foreach((array)$ids as $id) {
            $data = '{ "fields" : "title", "from" : '.$id.', "size" : 1, "query": { "query_string" : {"query" : "*"} } }';
            if (!$http->sendRequest($url, $data, 'POST')) return false;
            if ($http->status != 200) return false;
            $ret = $http->resp_body;
            if ($ret !== false) {
                $json = $this->json->decode($ret);
                $selected[] = $json->hits->hits->_id;
            }
        }

        return $selected;
    }

    function pageCount()
    {
        $http = new HTTPClient();

        $ret = $http->get($this->index_url.'/text/_count');
        if ($ret !== false) {
            if (empty($this->json)) $this->json = new Services_JSON;
            $json = $this->json->decode($ret);
            return $json->count;
        }
        return false;
    }

    function sort()
    {
        // nop
    }

    function addPage($pagename, $update = false)
    {
        global $DBInfo;

        if (!isset($pagename[0])) return false;

        $extra = '';
        if ($update) $extra = '/_update';

        $page = $DBInfo->getPage($pagename);
        $body = $page->_get_raw_body();
        $mtime = $page->mtime();

        $http = new HTTPClient();

        $url = $this->index_url.'/text/'.urlencode($pagename).$extra;
        $time = gmdate("Y-m-d\TH:i:s", $mtime);
        $data = array('title'=>$pagename, 'body'=>$body, 'date'=>$time);
        $json = json_encode($data);

        if (!$http->sendRequest($url, $json, 'PUT')) return false;
        if ($http->status != 200) return false;
        if ($http->resp_body === false) return false;

        return true;
    }

    function deletePage($pagename)
    {
        if (!isset($pagename[0])) return false;

        $http = new HTTPClient();

        $url = $this->index_url.'/text/'.urlencode($pagename);
        $data = array();

        if (!$http->sendRequest($url, $data, 'DELETE')) return false;
        if ($http->status != 200) return false;
        if ($http->resp_body === false) return false;

        return true;
    }

    function renamePage($oldname, $newname)
    {
        $this->deletePage($oldname);
        $this->addPage($newname);
        return true;
    }

    function getLikePages($needle, $limit = 100, $params = array())
    {
        if (!isset($needle[0])) return false; // null needle

        $http = new HTTPClient();
        if (empty($this->json)) $this->json = new Services_JSON;

        $url = $this->index_url.'/text/_search';
        if (!isset($limit[0])) $limit = 1000;

        if ($needle[0] == '^') {
            $needle = substr($needle, 1);
            if ($needle == '') $needle = '.';
            $field = 'title.titleindex';
        } else {
            $field = 'title';
        }
        $title = json_encode(array($field=>strtolower($needle)));
        $data = '{ "fields" : [ "title" ], "from" : 0, "size" : '.$limit.', "query": { "regexp" : '.$title.' } }';
        if (!$http->sendRequest($url, $data, 'POST')) return false;
        if ($http->status != 200) return false;
        if ($http->resp_body === false) return false;

        $pages = array();
        $json = $this->json->decode($http->resp_body);
        foreach ($json->hits->hits as $hit)
            $pages[] = $hit->_id;

        return $pages;
    }

    function getPages($params) {
        global $DBInfo;

        $http = new HTTPClient();
        if (empty($this->json)) $this->json = new Services_JSON;

        $offset = 0;
        if (!empty($params['offset']) and
                is_numeric($params['offset']) and
                $params['offset'] > 0)
            $offset = $params['offset'];

        // set page_limit
        $pages_limit = isset($DBInfo->pages_limit) ?
                $DBInfo->pages_limit : 5000; // 5000 pages

        $total = $this->pageCount();
        $size = $pages_limit;
        if (!empty($params['all'])) $size = $total;

        $url = $this->index_url.'/text/_search';
        $data = '{ "fields" : "title", "from" : '.$offset.', "size" : '.$size.', "query": { "query_string" : {"query" : "*"} } }';
        if (!$http->sendRequest($url, $data, 'POST')) return false;
        if ($http->status != 200) return false;
        if ($http->resp_body === false) return false;

        $pages = array();
        $json = $this->json->decode($http->resp_body);
        foreach ($json->hits->hits as $hit)
            $pages[] = $hit->_id;

        $info['offset'] = $offset;
        $info['count'] = count($pages);
        if (isset($params['ret'])) $params['ret'] = $info;

        return $pages;
    }
}

// vim:et:sts=4:sw=4:
