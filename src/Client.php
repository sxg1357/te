<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/7
 * Time: 9:53
 */
namespace Socket\Ms;

use Socket\Ms\Event\Epoll;
use Socket\Ms\Event\Event;
use Socket\Ms\Event\Select;
use Socket\Ms\Protocols\Http;
use Socket\Ms\Protocols\Stream;
use Socket\Ms\Protocols\WebSocketClient;

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

    public $_sendNum = 0;
    public $_sendMsgNum = 0;

    const STATUS_CLOSE = 10;
    const STATUS_CONNECTED = 11;
    public $_status;

    public static $_eventLoop;

    public $_protocols = [
        "stream" => "Socket\Ms\Protocols\Stream",
        "text" => "Socket\Ms\Protocols\Text",
        "http" => "Socket\Ms\Protocols\Http",
        "ws" => "Socket\Ms\Protocols\WebSocketClient",
        "mqtt" => ""
    ];

    public $localAddr;

    public $usingProtocol;

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
        list($protocol, $ip, $port) = explode(":", $address);
        if (isset($this->_protocols[$protocol])) {
            $this->usingProtocol = $protocol;
            $this->_protocol = new $this->_protocols[$protocol]();
        } else {
            $this->usingProtocol = "tcp";
        }
        $this->localAddr = "tcp:".$ip.":".$port;
        if (DIRECTORY_SEPARATOR == "/") {
            self::$_eventLoop = new Epoll();
        } else {
            self::$_eventLoop = new Select();
        }
    }

    public function start() {
        $this->_socket = stream_socket_client($this->localAddr, $error_code, $error_message);
        if (is_resource($this->_socket)) {
            stream_set_blocking($this->_socket, 0);
            stream_set_write_buffer($this->_socket, 0);
            stream_set_read_buffer($this->_socket, 0);
            if ($this->usingProtocol == "ws") {
                $this->handleEvent();
            } else {
                $this->eventCallBak("connect", []);
            }
            $this->_status = self::STATUS_CONNECTED;
            self::$_eventLoop->add($this->_socket, Event::READ, [$this, "recvSocket"]);
            self::$_eventLoop->add(5, Event::EVENT_TIMER, [$this, "sendPing"]);
        } else {
            $this->eventCallBak("error", [$error_code, $error_message]);
            exit(0);
        }
    }

    public function handleEvent($message = "") {
        switch ($this->usingProtocol) {
            case "tcp":
            case "text":
            case "stream":
                $this->eventCallBak("receive", [$message]);
                break;
            case "ws":
                if ($this->_protocol->webSocketHandShakeStatus == WebSocketClient::WEBSOCKET_START_STATUS) {
                    if (!$this->send()) {
                        $this->close();
                    }
                } else if ($this->_protocol->webSocketHandShakeStatus == WebSocketClient::WEBSOCKET_PREPARE_STATUS) {
                    if ($this->_protocol->verifyWebSocketKey()) {
                        $this->eventCallBak("open");
                    } else {
                        $this->close();
                    }
                } else if ($this->_protocol->webSocketHandShakeStatus == WebSocketClient::WEBSOCKET_RUNNING_STATUS) {
                    if ($message) {
                        $this->eventCallBak("message", [$message]);
                    }
                } else {
                    $this->close();
                }
                break;
        }
    }

    public function loop()
    {
        return self::$_eventLoop->loop();
    }

    public function close() {
        if (is_resource($this->_socket)) {
            fclose($this->_socket);
        }
        $this->eventCallBak("close");
        $this->_status = self::STATUS_CLOSE;
        $this->_socket = null;
    }

    public function isConnected() : bool
    {
        if ($this->_status == self::STATUS_CONNECTED && is_resource($this->_socket))
            return true;
        else
            return false;
    }

    public function send($data = "") {
        if (!is_resource($this->_socket) || feof($this->_socket)) {
            $this->close();
            return false;
        }
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

        $writeLen = fwrite($this->_socket, $this->_sendBuffer, $this->_sendLen);
        if ($writeLen == $this->_sendLen) {
            $this->_sendLen = 0;
            $this->_sendBuffer = '';
            $this->_sendBufferFull = 0;
            $this->onSendWrite();
            return true;
        } else if ($writeLen > 0) {
            $this->_sendLen -= $writeLen;
            $this->_sendBufferFull = substr($this->_sendBuffer, $writeLen);
            static::$_eventLoop->add($this->_socket, Event::WRITE, [$this, "writeSocket"]);
        } else {
            $this->close();
        }
    }

    public function recvSocket() {
        if ($this->isConnected()) {
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
    }

    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    public function writeSocket()
    {
        if ($this->needWrite() && $this->isConnected()) {
            if (is_resource($this->_socket)) {
                $writeLen = fwrite($this->_socket, $this->_sendBuffer, $this->_sendLen);
                $this->onSendWrite();
                if ($writeLen == $this->_sendLen) {
                    $this->_sendBuffer = '';
                    $this->_sendLen = 0;
                    static::$_eventLoop->del($this->_socket, Event::WRITE);
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
            $this->handleEvent($message);
//            $this->eventCallBak("receive", [$message]);
        }
    }

    public function sendPing($timer_id, $args) {
        if ($this->_protocol->webSocketHandShakeStatus == WebSocketClient::WEBSOCKET_RUNNING_STATUS) {
            $ping = $this->_protocol->ping();
            if (is_resource($this->_socket)) {
                fwrite($this->_socket, $ping);
            }
        }
    }
}