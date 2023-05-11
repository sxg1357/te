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

    public function Len($data) {
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
     * http报文结构
     * POST /nginx.html?name=sxg&age=25 HTTP/1.1\r\n
     * User-Agent: PostmanRuntime/7.31.3\r\n
     * Accept: 1\r\n
     * Postman-Token: 29cbb0fd-c895-4ceb-978c-9dc5a11db982\r\n
     * Host: 127.0.0.1:9501\r\n
     * Accept-Encoding: gzip, deflate, br\r\n
     * Connection: keep-alive\r\n
     * Content-Type: application/x-www-form-urlencoded\r\n
     * Content-Length: 15\r\n
     * \r\n
     * name=sxg&age=25
     * @param $header
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
    }

    public function parserBody($body) {
        $content_type = $_REQUEST['content_type'];
        $boundary = '';
        if (preg_match("/boundary=?(\S+)/i", $content_type, $matches)) {
            $boundary = '--'.$matches[1];
            $content_type = 'multipart/form-data';
        }
        switch ($content_type) {
            case 'multipart/form-data':
                $this->parseFormData($body, $boundary);
                break;
            case 'application/x-www-form-urlencoded':
                parse_str($body, $_POST);
                break;
            case 'application/json':
                $_POST = json_decode($body, true);
                break;
        }
    }

    public function parseFormData($data, $boundary) {
        $data = substr($data, 0 , -4);
        $formData = explode($boundary, $data);
        $_FILES = [];
        $key = 0;
        foreach ($formData as $field) {
            if ($field) {
                $kv = explode("\r\n\r\n", $field, 2);
                $val = trim($kv[1], "\r\n");
                if (preg_match('/name="(.*)"; filename="(.*)"/', $kv[0], $matches)) {
                    $_FILES[$key]['name'] = $matches[1];
                    $_FILES[$key]['file_name'] = $matches[2];
                    $_FILES[$key]['file_size'] = strlen($val);
                    $file_type = explode("\r\n", $kv[0], 3);
                    $file_type = explode(": ", $file_type[2]);
                    $_FILES[$key]['file_type'] = $file_type[1];
                    file_put_contents('www/'.$matches[2], $val);
                    ++$key;
                } else if (preg_match('/name="(.*)"/', $kv[0], $matches)) {
                    $_POST[$matches[1]] = $val;
                }
            }
        }
    }

    public function encode($data = '')
    {
        // TODO: Implement encode() method.
        return [strlen($data), $data];
    }

    public function decode($data = '')
    {
        // TODO: Implement decode() method.
        $header = substr($data, 0, $this->_headerLen - 4);
        $this->parseHeader($header);
        $body = substr($data, $this->_headerLen);
        if ($body) {
            $this->parserBody($body);
        }
        return $body;
    }

    public function msgLen($data = '')
    {
        // TODO: Implement msgLen() method.
        return $this->_headerLen + $this->_bodyLen;
    }
}