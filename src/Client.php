<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/7
 * Time: 9:53
 */
namespace Socket\Ms;

class Client {

    public $_socket;
    public $_events;

    public $_readBufferSize = 1024;
    public $_recvLen = 0;           //当前连接目前接受到的字节数
    public $_recvBufferFull = 0;    //当前连接是否超出接收缓冲区
    public $_recvBuffer = '';

    public function __construct($address) {
        $this->_socket = stream_socket_client($address, $error_code, $error_message);
        if (is_resource($this->_socket)) {
            $this->eventCallBak("connect");
        } else {
            $this->eventCallBak("error", [$error_code, $error_message]);
            exit(0);
        }
    }

    public function on($eventName, callable $eventCall) {
        $this->_events[$eventName] = $eventCall;
    }

    public function eventCallBak($eventName, $args = []) {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }

    public function eventLoop() {
        while (1) {
            $readFds[] = $this->_socket;
            $writeFds[] = $this->_socket;
            $expFds[] = $this->_socket;

            $ret = stream_select($readFds, $writeFds, $expFds, NULL, NULL);
            if ($ret === false) {
                break;
            }
            if ($readFds) {
                $this->recvSocket();
            }
        }
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

        }

    }
}