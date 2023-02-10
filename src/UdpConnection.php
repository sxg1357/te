<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023/2/9
 * Time: 13:38
 */

namespace Socket\Ms;

use Socket\Ms\Event\Event;

class UdpConnection {

    public $_recvLen = 0;           //当前连接目前接受到的字节数
    public $_recvBuffer = '';

    public $unix_socket;
    public $client_unix_file;


    public function __construct($unix_socket, $len, $wrapper, $client_unix_file) {
        $this->unix_socket = $unix_socket;
        $this->_recvLen = $len;
        $this->_recvBuffer = $wrapper;
        $this->client_unix_file = $client_unix_file;
        $this->handleMessage();
    }

    public function handleMessage() {
        while ($this->_recvLen) {
            $bin = unpack('NLength', $this->_recvBuffer);
            $msgLen = $bin['Length'];
            $oneMsg = substr($this->_recvBuffer, 0, $msgLen);
            $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);
            $this->_recvLen -= $msgLen;

            if ($oneMsg) {
                $wrapper = unserialize(substr($oneMsg, 4));
                $closure = $wrapper->getClosure();
                $closure(3);
            }
        }
    }

}