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
        $this->_server = new Socket\Ms\Server("stream://127.0.0.1:9501");
        $this->_server->on("connect", [$this, "onConnect"]); 
        $this->_server->on("receive", [$this, "onReceive"]);
        $this->_server->on("close", [$this, "onClose"]);
        $this->_server->start();
    }

    public function onConnect(Socket\Ms\Server $Server, Socket\Ms\TcpConnections $TcpConnections) {
        fprintf(STDOUT, "有客户端连接了\n");
    }

    public function onReceive(Socket\Ms\Server $Server, $msg, Socket\Ms\TcpConnections $connection) {
//        fprintf(STDOUT, "有客户发送数据:%s\n", $msg);
//        $connection->send("i am a server");
    }

    public function onClose(Socket\Ms\Server $Server, Socket\Ms\TcpConnections $connection) {
        fprintf(STDOUT, "有客户端连接关闭了\n");
        $Server->onClientLeave($connection->_socketFd);
    }
}

$ms = new ms();

