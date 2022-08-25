<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/7
 * Time: 9:53
 */
namespace Socket\Ms;

use Socket\Ms\Protocols\Stream;

class Client {

    public $_socket;
    public $_events = [];

    public $_readBufferSize = 102400;
    public $_recvBufferSize = 1024 * 100;
    public $_recvLen = 0;           //当前连接目前接收到的字节数
    public $_recvBufferFull = 0;    //当前连接是否超出接收缓冲区
    public $_recvBuffer = '';

    public $_sendLen = 0;
    public $_sendBuffer = '';
    public $_sendBufferSize = 1024 * 1000;
    public $_sendBufferFull = 0;

    public $_protocol;
    public $_address;

    public $_sendNum = 0;
    public $_sendMsgNum = 0;


    public function onSendWrite()
    {
        ++$this->_sendNum;
    }

    public function onSendMsg()
    {
        ++$this->_sendMsgNum;
    }

    public function on($eventName, callable $eventCall) {
        $this->_events[$eventName] = $eventCall;
    }

    public function eventCallBak($eventName, $args = []) {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }

    public function __construct($address) {
        $this->_address = $address;
        $this->_protocol = new Stream();
    }

    public function start() {
        $this->_socket = stream_socket_client($this->_address, $error_code, $error_message);
        if (is_resource($this->_socket)) {
            $this->eventCallBak("connect");
        } else {
            $this->eventCallBak("error", [$error_code, $error_message]);
            exit(0);
        }
    }

    public function eventLoop()
    {
        if (is_resource($this->_socket)) {
            $readFds = [$this->_socket];
            $writeFds = [$this->_socket];
            $expFds = [$this->_socket];

            set_error_handler(function () {});
            $ret = stream_select($readFds, $writeFds, $expFds, NULL, NULL);
            restore_error_handler();
            if ($ret <= 0 || $ret === false) {
                return false;
            }

            if ($readFds) {
                $this->recvSocket();
            }

            if ($writeFds) {
                $this->writeSocket();
            }
            return true;
        } else {
            return false;
        }
    }

    public function close() {
        if (is_resource($this->_socket)) {
            fclose($this->_socket);
        }
        $this->eventCallBak("close");
    }

    public function send($data) {
        $len = strlen($data);
        if ($this->_sendLen + $len < $this->_sendBufferSize) {
            $bin = $this->_protocol->encode($data);
            $this->_sendBuffer .= $bin[1];
            $this->_sendLen += $bin[0];
            if ($this->_sendLen >= $this->_sendBufferSize) {
                $this->_sendBufferFull++;
            }
            $this->onSendMsg();
        }
    }

    public function recvSocket() {
        $data = fread($this->_socket, $this->_readBufferSize);
        if ($data === '' || $data === false) {
            if (feof($this->_socket) || !is_resource($this->_socket)) {
                $this->close();
            }
        } else {
            //把接收到的数据放在接收缓冲区
            $this->_recvBuffer .= $data;
            $this->_recvLen += strlen($data);
        }
        if ($this->_recvLen > 0) {
            $this->handleMessage();
        }
    }

    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    public function writeSocket()
    {
        if ($this->needWrite()) {
            if (is_resource($this->_socket)) {
                $writeLen = fwrite($this->_socket, $this->_sendBuffer, $this->_sendLen);
                $this->onSendWrite();
                if ($writeLen == $this->_sendLen) {
                    $this->_sendBuffer = '';
                    $this->_sendLen = 0;
                    return true;
                } else if ($writeLen > 0) {
                    $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
                    $this->_sendLen -= $writeLen;
                } else {
                    $this->close();
                }
            }
        }
    }

    public function handleMessage() {
        while ($this->_protocol->Len($this->_recvBuffer)) {
            $msgLen = $this->_protocol->msgLen($this->_recvBuffer);
            //截取一条消息
            $oneMsg = substr($this->_recvBuffer, 0, $msgLen);
            $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);
            $this->_recvLen -= $msgLen;

            $message = $this->_protocol->decode($oneMsg);
            $this->eventCallBak("receive", [$message]);
        }
    }
}