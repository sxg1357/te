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

    public function __construct(TcpConnections $connection) {
        $this->_connection = $connection;
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