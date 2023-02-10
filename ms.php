<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/1
 * Time: 14:24
 */

require_once "vendor/autoload.php";

ini_set("memory_limit", "2048M");

class ms {

    private $_server;

    public function __construct() {
        $this->_server = new Socket\Ms\Server("tcp://0.0.0.0:9501");
        $this->_server->on("connect", [$this, "onConnect"]); 
        $this->_server->on("receive", [$this, "onReceive"]);
        $this->_server->on("close", [$this, "onClose"]);
        $this->_server->settings([
            'workerNum' => 2,
            'taskNum' => 2,
            'unix_server_socket_file' => '/home/sxg/te/sock/unix_sock_server.sock',
            'unix_client_socket_file' => '/home/sxg/te/sock/unix_sock_client.sock'
        ]);
        $this->_server->on("masterStart", [$this, "masterStart"]);
        $this->_server->on("masterShutdown", [$this, "masterShutdown"]);
        $this->_server->on("workerStart", [$this, "workerStart"]);
        $this->_server->on("workerStop", [$this, "workerStop"]);
        $this->_server->on("workerReload", [$this, "workerReload"]);
        $this->_server->on("task", [$this, "task"]);
        $this->_server->start();
    }

    public function onConnect(Socket\Ms\Server $Server, Socket\Ms\TcpConnections $TcpConnections) {
        fprintf(STDOUT, "有客户端连接了\n");
    }

    public function onReceive(Socket\Ms\Server $Server, $msg, Socket\Ms\TcpConnections $connection) {
        fprintf(STDOUT, "有客户发送数据:%s\n", $msg);
        echo time().PHP_EOL;
        $Server->task(function ($result) {
            sleep($result);
            echo "异步任务执行了\r\n";
            echo time().PHP_EOL;
        });
        $connection->send("i am a server ".time());
    }

    public function onClose(Socket\Ms\Server $Server, Socket\Ms\TcpConnections $connection) {
        fprintf(STDOUT, "有客户端连接关闭了\n");
        $Server->removeClient($connection->_socketFd);
    }

    public function masterStart(Socket\Ms\Server $Server) {
        fprintf(STDOUT, "master server <pid:%d> start working\r\n", posix_getpid());
    }

    public function masterShutdown(Socket\Ms\Server $Server) {
        fprintf(STDOUT, "master server <pid:%d> shutdown\r\n", posix_getpid());
    }

    public function workerStart(Socket\Ms\Server $Server) {
        fprintf(STDOUT, "worker <pid:%d> start working\r\n", posix_getpid());
    }

    public function workerStop(Socket\Ms\Server $Server) {
        fprintf(STDOUT, "worker <pid:%d> stop\r\n", posix_getpid());
    }

    public function workerReload(Socket\Ms\Server $Server) {
        fprintf(STDOUT, "worker <pid:%d> reload\r\n", posix_getpid());
    }

    public function task(Socket\Ms\Server $Server, $msg) {
        fprintf(STDOUT, "task process <pid:%d> recv msg:%s\r\n", posix_getpid(), $msg);
    }
}

$ms = new ms();

