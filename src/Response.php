<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023/4/12
 * Time: 18:24
 */

namespace Socket\Ms;

class Response {

    public $_connection;

    private $_statusReason = [
        '200' => 'OK',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway',
    ];

    public $_status_code = 200;

    public $_header = [];

    public function __construct(TcpConnections $connection) {
        $this->_connection = $connection;
    }

    public function setHeader($key, $value) {
        $this->_header[$key] = $value;
    }


    public function write($data) {
        $len = strlen($data);
        $text = sprintf("HTTP/1.1 %s %s\r\n", $this->_status_code, $this->_statusReason[$this->_status_code]);
        $text .= sprintf("Date: %s\r\n", date("Y-m-d H:i:s"));
        $text .= sprintf("Server: %s\r\n", "sxg");
        $text .= sprintf("OS: %s\r\n", PHP_OS);
        $text .= sprintf("Content-Language: %s\r\n", "zh-CN,zh;q=0.9");
        $text .= sprintf("Connection: %s\r\n", $_REQUEST['connection']);
        $text .= sprintf("Access-Control-Allow-Origin: *\r\n");
        foreach ($this->_header as $key => $value) {
            $text .= sprintf("%s: %s\r\n", $key, $value);
        }

        $text .= sprintf("Content-Length: %d\r\n", $len);
        if (!isset($this->_header['Content-Type'])) {
            $text .= sprintf("Content-Type: %s\r\n", "text/html;charset=utf-8");
        }
        $text .= "\r\n";
        $text .= $data;
        $this->_connection->send($text);
        if ($_REQUEST['connection'] != 'keep-alive') {
            $this->_connection->close();
        }
    }



    public function sendAllowOrigin() {
        $text = "HTTP/1.1 200 OK\r\n";
        $text .= "Content-Length: 0\r\n";
        $text .= "Connection: keep-alive\r\n";
        $text .= "Access-Control-Allow-Origin: *\r\n";
        $text .= "Access-Control-Allow-Method:POST,GET\r\n";
        $text .= "Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept\r\n\r\n";
        $this->_connection->send($text);
    }
}