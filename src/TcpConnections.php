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
    public $_server;

    public function __construct($socketFd, $clientIp, $server) {
        $this->_socketFd = $socketFd;
        $this->_clientIp = $clientIp;
        $this->_server = $server;
    }

    public function recvSocket() {
        $data = fread($this->_socketFd, 1024);
        if ($data === '' || $data === false) {
            if (feof($this->_socketFd) || !is_resource($this->_socketFd)) {
                fclose($this->_socketFd);
                /**@var Server $server*/
                $server = $this->_server;
                $server->eventCallBak("close", [$this]);
            }
        }
        if ($data) {
            /** @var Server $server */
            $server = $this->_server;
            $server->eventCallBak("receive", [$data, $this]);
        }
    }

    public function writeSocket($fd, $data) {
        $len = strlen($data);
        fwrite($fd, $data, $len);
        fprintf(STDOUT, "server write %s bytes\n", $len);
    }
}