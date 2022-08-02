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
    public $_connId;

    public function __construct($socketFd, $clientIp, $connId) {
        $this->_socketFd = $socketFd;
        $this->_clientIp = $clientIp;
        $this->_connId = $connId;
    }
}