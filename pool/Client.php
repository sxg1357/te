<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/10/31
 * Time: 10:16
 */

namespace pool;

class Client {

    public $_socketFd;
    public $_unixFile = "unix_server";

    public function __construct() {
        $this->_socketFd = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!is_resource($this->_socketFd)) {
            fprintf(STDOUT, "socket create failed,strerrno=%s\r\n", socket_strerror(socket_last_error()));
        }

        if (!socket_connect($this->_socketFd, $this->_unixFile)) {
            fprintf(STDOUT, "connect failed\r\n");
        }
        fprintf(STDOUT, "connect ok\r\n");

        $data = fgets(STDIN);
        if ($data) {
            socket_write($this->_socketFd, $data, strlen($data));
            $recv = socket_read($this->_socketFd, 1024);
            fprintf(STDOUT, "recv data:%s\r\n", $recv);
        }
        socket_close($this->_socketFd);
    }
}

new client();