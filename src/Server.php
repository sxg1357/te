<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/1
 * Time: 14:16
 */

namespace Socket\Ms;

class Server {
    public $_address;
    public $_socket;
    public static $_connections = [];
    public $_events = [];


    public function on($eventName, $eventCall) {
        $this->_events[$eventName] = $eventCall;
    }

    public function __construct($address) {
        $this->_address = $address;
    }

    public function listen() {
        $flags = STREAM_SERVER_LISTEN|STREAM_SERVER_BIND;
        $option['socket']['backlog'] = 10;
        $context = stream_context_create($option);    //setsocketopt
        $this->_socket = stream_socket_server($this->_address, $error_code, $error_message, $flags, $context);
        if (!is_resource($this->_socket)) {
            fprintf(STDOUT, "socket create fail:%s\n", $error_message);
            exit(0);
        }
    }

    public function eventLoop() {
        while (1) {
            $readFds[] = $this->_socket;
            $writeFds = [];
            $expFds = [];
            if (!empty(self::$_connections)) {
                foreach (self::$_connections as $idx => $connection) {
                    $readFds[] = $connection->_socketFd;
                    $writeFds[] = $connection->_socketFd;
                }
            }
            $ret = stream_select($readFds, $writeFds, $expFds, NULL);
            if ($ret === false) {
                break;
            }
            if ($readFds) {
                foreach ($readFds as $fd) {
                    if ($fd == $this->_socket) {
                        $this->accept();
                    } else {
                        /**@var TcpConnections $connection */
                        $connection = self::$_connections[(int)$fd];
                        $connection->recvSocket();
                    }
                }
            }
        }
    }

    public function eventCallBak($eventName, $args = []) {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName](...$args);
        }
    }

    public function accept() {
        $connId = stream_socket_accept($this->_socket, -1, $peer_name);
        if (is_resource($connId)) {
            $connection = new TcpConnections($connId, $peer_name, $this);
            self::$_connections[(int)($connId)] = $connection;
            fprintf(STDOUT, "connect success connId:%s\n", $connId);
            $this->eventCallBak("connect", [$this, $connection]);
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
