<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/2
 * Time: 16:38
 */

namespace Socket\Ms;

use Socket\Ms\Event\Event;

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
        stream_set_read_buffer($this->_socketFd, 0);

        Server::$_eventLoop->add($this->_socketFd, Event::READ, [$this, "recvSocket"]);
        //客户端上来之后，不要马上添加这个写事件，否则它会一直触发可写事件，导致执行大量的发送操作
        //会消耗cpu的资源
        //Server::$_eventLoop->add($this->_socketFd, Event::WRITE, [$this, "writeSocket"]);
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
                $this->eventCallBack($message);
//                $server->eventCallBak("receive", [$message, $this]);
                $this->resetHeartTime();
            }
        } else {
//            $server->eventCallBak("receive", [$this->_recvBuffer, $this]);
            $this->eventCallBack($this->_recvBuffer);
            $this->_recvBufferFull = 0;
            $this->_recvLen = 0;
            $this->_recvBuffer = '';
            $server->onMsg();
            $this->resetHeartTime();
        }
    }

    public function eventCallBack($message) {
        switch ($this->_server->_usingProtocol) {
            case 'stream':
            case 'text':
            case 'tcp':
                $this->_server->eventCallBak("receive", [$message, $this]);
                break;
            case 'http':
                $request = $this->createRequest();
                $response = new Response($this);
                if ($request->_request['method'] == 'OPTIONS') {
                    $response->sendAllowOrigin();
                }
                break;
        }
    }

    public function createRequest() : Request {
        $request = new Request();
        $request->_get = $_GET;
        $request->_post = $_POST;
        $request->_files = $_FILES;
        $request->_request = $_REQUEST;
        return $request;
    }

    public function close() {
        //epoll_ctl(3, EPOLL_CTL_DEL, 7, 0x7ffd0f3f32b0) = 0
        Server::$_eventLoop->del($this->_socketFd, Event::READ);
        Server::$_eventLoop->del($this->_socketFd, Event::WRITE);
        if (is_resource($this->_socketFd)) {
            fclose($this->_socketFd);
        }
        /**@var Server $server*/
        $server = $this->_server;
        $server->eventCallBak("close", [$this]);
        $server->removeClient($this->_socketFd);
        $this->_status = self::STATUS_CLOSE;
        $this->_socketFd = null;
        $this->_sendLen = 0;
        $this->_sendBuffer = '';
        $this->_sendBufferFull = 0;
        $this->_sendBufferSize = 0;

        $this->_recvBufferFull = 0;
        $this->_recvBuffer = '';
        $this->_recvBufferSize = 0;
        $this->_recvLen = 0;
    }

    public function checkHeartTime() {
        $nowTime = time();
        if ($nowTime - $this->_heartTime >= self::HEART_BEAT) {
            $this->_server->echoLog("心跳时间已超出:%d\n", $nowTime - $this->_heartTime);
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
        if (!is_resource($this->_socketFd) || feof($this->_socketFd)) {
            $this->close();
            return false;
        }
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
        set_error_handler(function () {});
        $writeLen = fwrite($this->_socketFd, $this->_sendBuffer, $this->_sendLen);
        restore_error_handler();
        if ($writeLen == $this->_sendLen) {
            $this->_sendBuffer = '';
            $this->_sendLen = 0;
            $this->_sendBufferFull = 0;
            return true;
        } else if ($this->_sendLen > 0) {
            $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
            $this->_sendLen -= $writeLen;
            Server::$_eventLoop->add($this->_socketFd, Event::WRITE, [$this, "writeSocket"]);
        } else {
            if (!is_resource($this->_socketFd || feof($this->_socketFd))) {
                $this->close();
            }
        }

    }

    public function writeSocket() {
        if ($this->needWrite()) {
            set_error_handler(function () {});
            $writeLen = fwrite($this->_socketFd, $this->_sendBuffer, $this->_sendLen);
            restore_error_handler();
            if ($writeLen == $this->_sendLen) {
                $this->_sendBuffer = '';
                $this->_sendLen = 0;
                $this->_sendBufferFull = 0;
                Server::$_eventLoop->del($this->_socketFd, Event::WRITE);
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