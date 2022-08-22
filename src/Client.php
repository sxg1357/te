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

    public $_readBufferSize = 1024;
    public $_recvBufferSize = 1024 * 100;
    public $_recvLen = 0;           //当前连接目前接收到的字节数
    public $_recvBufferFull = 0;    //当前连接是否超出接收缓冲区
    public $_recvBuffer = '';

    public $_protocol;
    public $_address;


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

    public function eventLoop() {
        if (is_resource($this->_socket)) {
            $readFds[] = $this->_socket;
            $writeFds[] = $this->_socket;
            $expFds[] = $this->_socket;
        }
        set_error_handler(function (){});
        $ret = stream_select($readFds, $writeFds, $expFds, NULL, NULL);
        restore_error_handler();
        if ($ret <= 0 || $ret === false) {
            return false;
        }
        if ($readFds) {
            $this->recvSocket();
        }
        return true;
    }

    public function close() {
        fclose($this->_socket);
        $this->eventCallBak("close");
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

    public function writeSocket($data) {
        $bin =$this->_protocol->encode($data);
        $writeLen = fwrite($this->_socket, $bin[1], $bin[0]);
        fprintf(STDOUT, "client write %s bytes\n", $writeLen);
    }

    public function handleMessage() {
        while ($this->_protocol->Len($this->_recvBuffer)) {
            $msgLen = $this->_protocol->msgLen($this->_recvBuffer);
            //截取一条消息
            $oneMsg = mb_substr($this->_recvBuffer, 0, $msgLen);
            $this->_recvBuffer = mb_substr($this->_recvBuffer, $msgLen);
            $this->_recvLen -= $msgLen;

            $message = $this->_protocol->decode($oneMsg);
            $this->eventCallBak("receive", [$message]);
        }
    }
}