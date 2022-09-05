<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/1
 * Time: 14:16
 */

namespace Socket\Ms;

use Socket\Ms\Event\Epoll;
use Socket\Ms\Event\Event;

class Server {
    public $_address;
    public $_socket;
    public static $_connections = [];
    public $_events = [];

    public $_protocol = null;

    static public $_clientNum = 0;
    static public $_recvNum = 0;
    static public $_msgNum = 0;

    public $_starttime;

    public $_protocols = [
        "stream" => "Socket\Ms\Protocols\Stream",
        "text" => "Socket\Ms\Protocols\Text",
        "websocket" => "",
        "http" => "",
        "mqtt" => ""
    ];

    static public $_eventLoop;


    public function on($eventName, callable $eventCall) {
        $this->_events[$eventName] = $eventCall;
    }

    public function __construct($address) {
        list($protocol, $ip, $port) = explode(":", $address);
        if (isset($this->_protocols[$protocol])) {
            $this->_protocol = new $this->_protocols[$protocol]();
        }
        $this->_starttime = time();
        $this->_address = "tcp:$ip:$port";
        static::$_eventLoop = new Epoll();
    }

    public function onClientJoin() {
        self::$_clientNum++;
    }

    public function onRecv() {
        self::$_recvNum++;
    }

    public function onMsg() {
        self::$_msgNum++;
    }

    public function statistics() {
        $nowTime = time();
        $diffTime = $nowTime - $this->_starttime;
        $this->_starttime = $nowTime;
        if ($diffTime >= 1) {
            fprintf(STDOUT,"time:<%s>--socket<%d>--<clientNum:%d>--<recvNum:%d>--<msgNum:%d>\r\n",
                $diffTime, (int)$this->_socket, static::$_clientNum, static::$_recvNum, static::$_msgNum);
            static::$_recvNum = 0;
            static::$_msgNum = 0;
        }
    }

    public function listen() {
        $flags = STREAM_SERVER_LISTEN|STREAM_SERVER_BIND;
        $option['socket']['backlog'] = 102400;   //ulimit -a
        $context = stream_context_create($option);    //setsocketopt
        $this->_socket = stream_socket_server($this->_address, $error_code, $error_message, $flags, $context);
        stream_set_blocking($this->_socket, 0);
        if (!is_resource($this->_socket)) {
            fprintf(STDOUT, "socket create fail:%s\n", $error_message);
            exit(0);
        }
        fprintf(STDOUT,"listen on:%s\n", $this->_address);
    }

    public function start() {
        $this->listen();
        self::$_eventLoop->add($this->_socket, Event::READ, [$this, "accept"]);
        $this->eventLoop();
    }

    public function checkHeartTime() {
        foreach (self::$_connections as $idx => $fd) {
            /**@var TcpConnections $fd */
            if ($fd->checkHeartTime()) {
                $fd->close();
            }
        }
    }

    public function eventLoop() {
        echo "loop result:".static::$_eventLoop->loop();
    }

//    public function loop() {
//        $readFds[] = $this->_socket;
//        while (1) {
//            $reads = $readFds;
//            $writes = [];
//            $exps = [];
//
//            $this->statistics();
////            $this->checkHeartTime();
//
//            if (!empty(self::$_connections)) {
//                foreach (self::$_connections as $idx => $connection) {
//                    $socket_fd = $connection->_socketFd;
//                    if (is_resource($socket_fd)) {
//                        $reads[] = $socket_fd;
////                        $writes[] = $socket_fd;
//                    }
//                }
//            }
//            set_error_handler(function (){});
//            //此函数的第四个参数设置为null则为阻塞状态 当有客户端连接或者收发消息时 会解除阻塞 内核会修改 &$read &$write
//            $ret = stream_select($reads, $writes, $exps, 0, 100);
//            restore_error_handler();
//            if ($ret === false) {
//                break;
//            }
//            if ($reads) {
//                foreach ($reads as $fd) {
//                    if ($fd == $this->_socket) {
//                        $this->accept();
//                    } else {
//                        /**@var TcpConnections $connection */
//                        if (isset(self::$_connections[(int)$fd])) {
//                            $connection = self::$_connections[(int)$fd];
//                            if ($connection->isConnected()) {
//                                $connection->recvSocket();
//                            }
//                        }
//                    }
//                }
//            }
//
//            if ($writes) {
//                foreach ($writes as $fd) {
//                    if (isset(self::$_connections[(int)$fd])) {
//                        /**@var TcpConnections $connection*/
//                        $connection = self::$_connections[(int)$fd];
//                        if ($connection->isConnected()) {
//                            $connection->writeSocket();
//                        }
//                    }
//                }
//            }
//        }
//    }

    public function eventCallBak($eventName, $args = []) {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }

    public function removeClient($socket_fd) {
        if (isset(self::$_connections[(int)$socket_fd])) {
            unset(self::$_connections[(int)$socket_fd]);
            self::$_clientNum--;
        }
    }

    public function accept() {
        $connId = stream_socket_accept($this->_socket, -1, $peer_name);
        if (is_resource($connId)) {
            $connection = new TcpConnections($connId, $peer_name, $this);
            $this->onClientJoin();
            self::$_connections[(int)($connId)] = $connection;
            $this->eventCallBak("connect", [$connection]);
        }
    }
}

//proc/$pid/net/tcp   16进制数 前面的ip两位两位地从后往前算
//  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
//   0: 0100007F:2328 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 37810 1 ffff993cba8587c0 100 0 0 10 0
//   1: 00000000:18EB 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 37977 1 ffff993cba858f80 100 0 0 10 0
//   2: 00000000:0050 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 37992 1 ffff993cba859740 100 0 0 10 0
//   3: 00000000:0016 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 37771 1 ffff993cba858000 100 0 0 10 0
//   4: 0100007F:251D 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 39745 1 ffff993cba85be00 100 0 0 10 0
//   5: 64BCA8C0:0016 01BCA8C0:CB41 01 00000024:00000000 01:00000018 00000000     0        0 39625 4 ffff993cba85b640 24 4 21 10 -1
//   6: 64BCA8C0:0016 01BCA8C0:CB2C 01 00000000:00000000 02:000AA7A8 00000000     0        0 39500 2 ffff993cba85ae80 23 4 20 10 -1
//   7: 64BCA8C0:0016 01BCA8C0:E25A 01 00000000:00000000 02:0007E7A8 00000000     0        0 38526 2 ffff993cba85a6c0 22 4 31 10 -1
//   8: 64BCA8C0:0016 01BCA8C0:E03A 01 00000000:00000000 02:00061ADB 00000000     0        0 38298 2 ffff993cba859f00 24 4 20 10 -1
