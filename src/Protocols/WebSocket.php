<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023-05-07
 * Time: 19:26
 */

namespace Socket\Ms\Protocols;

/**
+-+-+-+-+-------+-+-------------+-------------------------------+
|F|R|R|R| opcode|M| Payload len |    Extended payload length    |
|I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
|N|V|V|V|       |S|             |   (if payload len==126/127)   |
| |1|2|3|       |K|             |                               |
+-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
|     Extended payload length continued, if payload len == 127  |
+ - - - - - - - - - - - - - - - +-------------------------------+
|                               |Masking-key, if MASK set to 1  |
+-------------------------------+-------------------------------+
| Masking-key (continued)       |          Payload Data         |
+-------------------------------- - - - - - - - - - - - - - - - +
:                     Payload Data continued ...                :
+ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
|                     Payload Data continued ...                |
+---------------------------------------------------------------+

 **/

class WebSocket implements Protocols {

    public $_http;
    public $_websocket_handshake_status;
    const WEBSOCKET_START_STATUS = 0;
    const WEBSOCKET_RUNNING_STATUS = 1;
    const WEBSOCKET_CLOSE_STATUS = 2;


    public function __construct() {
        $this->_http = new Http();
        $this->_websocket_handshake_status = self::WEBSOCKET_START_STATUS;
    }

    public function Len($data)
    {
        // TODO: Implement Len() method.
        if ($this->_websocket_handshake_status == self::WEBSOCKET_START_STATUS) {
            return $this->_http->Len($data);
        }
    }

    //处理握手数据
    public function encode($data = '') {
        // TODO: Implement encode() method.
        if ($this->_websocket_handshake_status == self::WEBSOCKET_START_STATUS) {
            $handshakeData = $this->handshake();
            if ($handshakeData) {
                $this->_websocket_handshake_status = self::WEBSOCKET_RUNNING_STATUS;
                return $this->_http->encode($handshakeData);
            } else {
                $this->_websocket_handshake_status = self::WEBSOCKET_CLOSE_STATUS;
                return $this->_http->encode($this->response400());
            }
        }
    }

    //这里直接处理握手数据
    public function decode($data = '')
    {
        // TODO: Implement decode() method.
        if ($this->_websocket_handshake_status == self::WEBSOCKET_START_STATUS) {
            $this->_http->decode($data);
        }
    }

    public function msgLen($data = '') {
        // TODO: Implement msgLen() method.
        if ($this->_websocket_handshake_status == self::WEBSOCKET_START_STATUS) {
            return $this->_http->msgLen($data);
        }
    }

    public function response400($data=''): string {
        $len = strlen($data);
        $text = sprintf("HTTP/1.1 %d %s\r\n", 200, 'OK');
        $text .= sprintf("Date: %s\r\n", date("Y-m-d H:i:s"));
        $text .= sprintf("OS: %s\r\n", PHP_OS);
        $text .= sprintf("Server: %s\r\n", "Sxg");
        $text .= sprintf("Content-Language: %s\r\n", "zh-CN,zh;q=0.9");
        $text .= sprintf("Connection: %s\r\n", "Close");//keep-alive close
        $text .= "Access-Control-Allow-Origin: *\r\n";
        $text .= sprintf("Content-Type: %s\r\n", "text/html;charset=utf-8");
        $text .= sprintf("Content-Length: %d\r\n", $len);
        $text .= "\r\n";
        $text .= $data;
        return $text;
    }

    /**
     *请求发起websocket连接时客户端发送的报文
     *GET / HTTP/1.1
     *Host: 127.0.0.1:9501
     *Connection: Upgrade
     *Pragma: no-cache
     *Cache-Control: no-cache
     *User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36
     *Upgrade: websocket
     *Origin: null
     *Sec-WebSocket-Version: 13
     *Accept-Encoding: gzip, deflate, br
     *Accept-Language: zh-CN,zh;q=0.9
     *Sec-WebSocket-Key: Mtb3KA//LTnx5Y4w7pHLZw==
     *Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits
     */

    /**
     * 返回数据格式
     *HTTP/1.1 101 Switching Protocols
     *Connection:Upgrade
     *Upgrade: websocket
     *Sec-WebSocket-Accept: Oy4NRAQ13jhfONC7bP8dTKb4PTU=*/
    public function handshake() {
        if (isset($_REQUEST['connection']) && $_REQUEST['connection'] == 'Upgrade'
            && isset($_REQUEST['upgrade']) && $_REQUEST['upgrade'] == 'websocket')
        {
            $key = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
            $acceptKey = base64_encode(sha1($_REQUEST['sec_websocket_key'].$key, true));
            $handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\n";
            $handshakeResponse .= sprintf("Upgrade: %s\r\n", "websocket");
            $handshakeResponse .= sprintf("Connection: %s\r\n", "Upgrade");
            $handshakeResponse .= sprintf("Sec-WebSocket-Accept: %s\r\n\r\n", $acceptKey);
            return $handshakeResponse;
        } else {
            return false;
        }
    }
}