<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023/3/29
 * Time: 14:49
 */

namespace Socket\Ms\Protocols;

class Http implements Protocols {

    public $_headerLen = 0;
    public $_bodyLen = 0;

    public function Len($data)
    {
        // TODO: Implement Len() method.
        $len = strpos($data, "\r\n\r\n");
        if ($len !== false) {
            $this->_headerLen = $len + 4;
            if (preg_match("/\r\nContent-Length: ?(\d+)/i", $data, $matches)) {
               $this->_bodyLen = $matches[1];
            }
            $totalLen = $this->_bodyLen + $this->_headerLen;
            if (strlen($data) >= $totalLen) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
    POST /index.html?name=sxg&age=25 HTTP/1.1
    User-Agent: PostmanRuntime/7.31.3
    Accept: 1
    Postman-Token: 29cbb0fd-c895-4ceb-978c-9dc5a11db982
    Host: 127.0.0.1:9501
    Accept-Encoding: gzip, deflate, br
    Connection: keep-alive
    Content-Type: application/x-www-form-urlencoded
    Content-Length: 15
     */
    public function parseHeader($header) {
        $_REQUEST = $_GET = [];
        $arr = explode("\r\n", $header);
        $startLine = $arr[0];
        list($method, $uri, $schema) = explode(" ", $startLine);
        $_REQUEST['method'] = $method;
        $uri = parse_url($uri);
        $_REQUEST['uri'] = $uri['path'];
        parse_str($uri['query'], $_GET);
        $_REQUEST['schema'] = $schema;
        unset($arr[0]);
        foreach ($arr as $kv) {
            list($k, $v) = explode(': ', $kv);
            $k = str_replace('-', '_', $k);
            $k = strtolower($k);
            $_REQUEST[$k] = $v;
        }
        print_r($_GET);
        echo "------------------------";
        print_r($_REQUEST);
    }

    public function encode($data = '')
    {
        // TODO: Implement encode() method.
    }

    public function decode($data = '')
    {
        // TODO: Implement decode() method.
        $header = substr($data, 0, $this->_headerLen - 4);
        $this->parseHeader($header);
        $body = substr($data, $this->_headerLen);
    }

    public function msgLen($data = '')
    {
        // TODO: Implement msgLen() method.
        return $this->_headerLen + $this->_bodyLen;
    }
}