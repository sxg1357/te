<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/1
 * Time: 14:24
 */

require_once "vendor/autoload.php";

$server = new Socket\Ms\Server("tcp://127.0.0.1:9501");

$server->on("connect", function (\Socket\Ms\Server $Server, \Socket\Ms\TcpConnections $TcpConnections) {
    fprintf(STDOUT, "有客户端连接了\n");
});
$server->on("receive", function ($msg, \Socket\Ms\TcpConnections $connections) {
    fprintf(STDOUT, "有客户发送数据:%s\n", $msg);
    $connections->writeSocket($connections->_socketFd, $msg);
});

$server->listen();
$server->eventLoop();

