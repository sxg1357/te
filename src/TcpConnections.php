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

    public $_readBufferSize = 1024;
    public $_recvBufferSize = 1024 * 100;     //接收缓冲区字节数大小
    public $_recvLen = 0;           //当前连接目前接受到的字节数
    public $_recvBufferFull = 0;    //当前连接是否超出接收缓冲区


    public function __construct($socketFd, $clientIp, $server) {
        $this->_socketFd = $socketFd;
        $this->_clientIp = $clientIp;
        $this->_server = $server;
    }

    public function recvSocket() {
        $data = fread($this->_socketFd, $this->_readBufferSize);
        if ($data === '' || $data === false) {
            if (feof($this->_socketFd) || !is_resource($this->_socketFd)) {
                $this->close();
            }
        }
        if ($data) {
            /** @var Server $server */
            $server = $this->_server;
            $server->eventCallBak("receive", [$data, $this]);
        }
    }

    public function close() {
        if (is_resource($this->_socketFd)) {
            fclose($this->_socketFd);
        }
        /**@var Server $server*/
        $server = $this->_server;
        $server->eventCallBak("close", [$this]);
    }

    public function writeSocket($fd, $data) {
        $len = strlen($data);
        fwrite($fd, $data, $len);
        fprintf(STDOUT, "server write %s bytes\n", $len);
    }
}