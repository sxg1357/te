<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/1
 * Time: 14:24
 */

require_once "vendor/autoload.php";


class ms {
    private $_server;
    public function __construct() {
        $this->_server = new Socket\Ms\Server("tcp://127.0.0.1:9501");
        $this->_server->on("connect", [$this, 'onConnect']);
        $this->_server->on("receive", [$this, 'onReceive']);
        $this->_server->start();
    }

    public function onConnect(Socket\Ms\Server $Server, Socket\Ms\TcpConnections $TcpConnections) {
        fprintf(STDOUT, "有客户端连接了\n");
    }

    public function onReceive(Socket\Ms\Server $Server, $msg, Socket\Ms\TcpConnections $connections) {
        fprintf(STDOUT, "有客户发送数据:%s\n", $msg);
        $connections->writeSocket($connections->_socketFd, $msg);
    }
}

$ms = new ms();

