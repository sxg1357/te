<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/2
 * Time: 16:38
 */

namespace Socket\Ms;

class TcpConnections {
    public $_socketFd;
    public $_clientIp;

    public function __construct($socketFd, $clientIp) {
        $this->_socketFd = $socketFd;
        $this->_clientIp = $clientIp;
    }

    public function recvSocket() {
        $data = fread($this->_socketFd, 1024);
        //这里需要做一个校验 不然fwrite会处于阻塞状态
        if ($data) {
            fprintf(STDOUT, "recv data:%s from client\n", $data);
            fwrite($this->_socketFd, "hello world\n");
        }

    }
}