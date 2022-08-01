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

    public function accept() {
        $connId = stream_socket_accept($this->_socket, -1);
        if (is_resource($connId)) {
            self::$_connections[(int)($connId)] = $connId;
            fprintf(STDOUT, "connect success connId:%s\n", $connId);
        }
    }
}
