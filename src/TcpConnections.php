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
    public $_recvBufferSize = 1024 * 1000 * 10;     //当前连接接收缓冲区字节数大小
    public $_recvLen = 0;           //当前连接目前接受到的字节数
    public $_recvBufferFull = 0;    //当前连接是否超出接收缓冲区
    public $_recvBuffer = '';

    public $_sendLen = 0;
    public $_sendBuffer = '';
    public $_sendBufferSize = 1024 * 1000;
    public $_sendBufferFull = 0;

    public $_heartTime = 0;
    const HEART_BEAT = 5;

    const STATUS_CLOSE = 10;
    const STATUS_CONNECTED = 11;
    public $_status;


    public function __construct($socketFd, $clientIp, $server) {
        $this->_socketFd = $socketFd;
        $this->_clientIp = $clientIp;
        $this->_server = $server;
        $this->_heartTime = time();
        $this->_status = self::STATUS_CONNECTED;
        stream_set_blocking($this->_socketFd, 0);
        stream_set_write_buffer($this->_socketFd, 0);
        stream_set_blocking($this->_socketFd, 0);
    }

    public function isConnected() : bool
    {
        if ($this->_status == self::STATUS_CONNECTED && is_resource($this->_socketFd))
            return true;
        else
            return false;
    }

    public function recvSocket() {
        if ($this->_recvLen < $this->_recvBufferSize) {
            $data = fread($this->_socketFd, $this->_readBufferSize);
            if ($data === '' || $data === false) {
                if (feof($this->_socketFd) || !is_resource($this->_socketFd)) {
                    $this->close();
                }
            } else {
                //把接收到的数据放在接收缓冲区
                $this->_recvBuffer .= $data;
                $this->_recvLen += strlen($data);
                $this->_server->onRecv();
            }
        } else {
            $this->_recvBufferFull++;
        }
        if ($this->_recvLen > 0) {
            $this->handleMessage();
        }
    }

    public function handleMessage() {
        /**@var server $server*/
        $server = $this->_server;

        if (is_object($server->_protocol) && $server->_protocol != null) {
            while ($server->_protocol->Len($this->_recvBuffer)) {
                $msgLen = $server->_protocol->msgLen($this->_recvBuffer);
                //截取一条消息
                $oneMsg = substr($this->_recvBuffer, 0, $msgLen);
                $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);
                $this->_recvLen -= $msgLen;
                $server->onMsg();
                $message = $server->_protocol->decode($oneMsg);
                $server->eventCallBak("receive", [$message, $this]);
                $this->resetHeartTime();
            }
        } else {
            $server->eventCallBak("receive", [$this->_recvBuffer, $this]);
            $this->_recvBufferFull = 0;
            $this->_recvLen = 0;
            $this->_recvBuffer = '';
            $server->onMsg();
            $this->resetHeartTime();
        }
    }

    public function close() {
        if (is_resource($this->_socketFd)) {
            fclose($this->_socketFd);
        }
        /**@var Server $server*/
        $server = $this->_server;
        $server->eventCallBak("close", [$this]);
        $server->removeClient($this->_socketFd);
        $this->_status = self::STATUS_CLOSE;
        $this->_socketFd = null;
    }

    public function checkHeartTime() {
        $nowTime = time();
        if ($nowTime - $this->_heartTime >= self::HEART_BEAT) {
            fprintf(STDOUT, "心跳时间已超出:%d\n", $nowTime - $this->_heartTime);
            return true;
        }
        return false;
    }

    public function resetHeartTime() {
        $this->_heartTime = time();
    }

    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    public function send($data)
    {
        $len = strlen($data);
        $server = $this->_server;
        if ($this->_sendLen + $len < $this->_sendBufferSize) {
            if (is_object($server->_protocol) && $server->_protocol != null) {
                $bin = $this->_server->_protocol->encode($data);
                $this->_sendBuffer .= $bin[1];
                $this->_sendLen += $bin[0];
            } else {
                $this->_sendBuffer .= $data;
                $this->_sendLen += $len;
            }

            if ($this->_sendLen >= $this->_sendBufferSize) {
                $this->_sendBufferFull++;
            }
        }
    }

    public function writeSocket()
    {
        if ($this->needWrite()) {
            $writeLen = fwrite($this->_socketFd, $this->_sendBuffer, $this->_sendLen);
            if ($writeLen == $this->_sendLen) {
                $this->_sendBuffer = '';
                $this->_sendLen = 0;
                return true;
            }
            else if ($writeLen > 0) {
                $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
                $this->_sendLen -= $writeLen;
            } else {
                $this->close();
            }
        }
    }
}