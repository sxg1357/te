<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/1
 * Time: 14:24
 */

require_once "vendor/autoload.php";

ini_set("memory_limit", "2048M");
use Socket\Ms\Response;

class ms {

    private $_server;

    public function __construct() {
        $this->_server = new Socket\Ms\Server("http://0.0.0.0:9501");
        $this->_server->on("connect", [$this, "onConnect"]);
        $this->_server->on("request", [$this, 'onRequest']);
//        $this->_server->on("receive", [$this, "onReceive"]);
//        $this->_server->on("close", [$this, "onClose"]);
        $this->_server->settings([
            'workerNum' => 1,
            'unix_server_socket_file' => '/home/sxg/te/sock/unix_sock_server.sock',
            'unix_client_socket_file' => '/home/sxg/te/sock/unix_sock_client.sock',
            'daemon' => false
        ]);
//        $this->_server->on("masterStart", [$this, "masterStart"]);
//        $this->_server->on("masterShutdown", [$this, "masterShutdown"]);
        $this->_server->on("workerStart", [$this, "workerStart"]);
//        $this->_server->on("workerStop", [$this, "workerStop"]);
//        $this->_server->on("workerReload", [$this, "workerReload"]);
//        $this->_server->on("task", [$this, "task"]);
        $this->_server->start();
    }

    public function onConnect(Socket\Ms\Server $server, Socket\Ms\TcpConnections $TcpConnections) {
        $server->echoLog("有客户端连接了");
    }

    public function onRequest(\Socket\Ms\Server $server, \Socket\Ms\Request $request, Response $response): bool
    {
        if (preg_match("/.jpg|.html|.png|.gif|.js|jpeg/", $_REQUEST['uri'])) {
            $response->sendFile('www/'.$_REQUEST['uri']);
            return true;
        }
//        $response->setHeader("Content-Type", "application/json");
//        $response->write(json_encode(['name' => 'sxg', 'age' => '25']));
        global $routes;
        global $dispatch;
        $dispatch->callAction($routes, $request, $response);
        return true;
    }

//    public function onReceive(Socket\Ms\Server $server, $msg, Socket\Ms\TcpConnections $connection) {
//        $server->echoLog("有客户发送数据:%s", $msg);
//        $server->echoLog(time());
//        if (DIRECTORY_SEPARATOR == '/') {
//            $server->task(function ($result) use ($server) {
//                sleep($result);
//                $server->echoLog( "异步任务执行了");
//                $server->echoLog(time());
//            });
//        }
//        $connection->send("i am a server ".time());
//    }

//    public function onClose(Socket\Ms\Server $server, Socket\Ms\TcpConnections $connection) {
//        $server->echoLog("有客户端连接关闭了");
//        $server->removeClient($connection->_socketFd);
//    }
//
//    public function masterStart(Socket\Ms\Server $server) {
//        $server->echoLog("master server <pid:%d> start working", posix_getpid());
//    }
//
//    public function masterShutdown(Socket\Ms\Server $server) {
//        $server->echoLog("master server <pid:%d> shutdown", posix_getpid());
//    }
//
    public function workerStart(Socket\Ms\Server $server) {
//        $server->echoLog("worker <pid:%d> start working", posix_getpid());
        global $routes;
        global $dispatch;
        $routes = require_once "app/routes/api.php";
        $dispatch = new \App\Controller\DispatchController();
    }
//
//    public function workerStop(Socket\Ms\Server $server) {
//        $server->echoLog("worker <pid:%d> stop", posix_getpid());
//    }
//
//    public function workerReload(Socket\Ms\Server $server) {
//        $server->echoLog("worker <pid:%d> reload", posix_getpid());
//    }
//
//    public function task(Socket\Ms\Server $server, $msg) {
//        $server->echoLog("task process <pid:%d> recv msg:%s", posix_getpid(), $msg);
//    }
}

$ms = new ms();

