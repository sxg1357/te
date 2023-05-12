<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023-05-07
 * Time: 19:26
 */

namespace Socket\Ms\Protocols;

use phpseclib3\Math\BigInteger\Engines\PHP;

class WebSocket implements Protocols {

    public $_http;
    public $webSocketHandShakeStatus;
    const WEBSOCKET_START_STATUS = 11;
    const WEBSOCKET_RUNNING_STATUS = 12;
    const WEBSOCKET_CLOSE_STATUS = 13;

    public $fin;
    public $payload_len;
    public $opcode;
    public $mask;
    public $maskKey;
    const  OPCODE_FRAME = 0x01;
    const  OPCODE_BINARY = 0x02;
    const  OPCODE_CLOSED = 0x08;
    const  OPCODE_PING = 0x09;
    const  OPCODE_PONG = 0x0a;

    public $headerLen;
    public $dataLen;



    public function __construct() {
        $this->_http = new Http();
        $this->webSocketHandShakeStatus = self::WEBSOCKET_START_STATUS;
    }

    /**
     * +-+-+-+-+-------+-+-------------+-------------------------------+
     * |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
     * |I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
     * |N|V|V|V|       |S|             |   (if payload len==126/127)   |
     * | |1|2|3|       |K|             |                               |
     * +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
     * |     Extended payload length continued, if payload len == 127  |
     * + - - - - - - - - - - - - - - - +-------------------------------+
     * |                               |Masking-key, if MASK set to 1  |
     * +-------------------------------+-------------------------------+
     * | Masking-key (continued)       |          Payload Data         |
     * +-------------------------------- - - - - - - - - - - - - - - - +
     * :                     Payload Data continued ...                :
     * + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
     * |                     Payload Data continued ...                |
     * +---------------------------------------------------------------+
     * @param $data
     * @return bool
     */
    public function Len($data): bool {
        // TODO: Implement Len() method.
        if ($this->webSocketHandShakeStatus == self::WEBSOCKET_START_STATUS) {
            return $this->_http->Len($data);
        } else if ($this->webSocketHandShakeStatus == self::WEBSOCKET_RUNNING_STATUS) {
            //走到这里说明websocket已经建立了连接  需要按照以上格式进行解析
            if (strlen($data) < 2) {
                return false;
            }
            $firstByte = ord($data[0]);
            $this->headerLen = 2;
            //拿到fin  将第一个字节转为十进制和0b10000000进行&运算拿到第一位
            $this->fin = ($firstByte & 0x80) == 0x80 ? 1 : 0;
            echo "fin:$this->fin\r\n";
            $this->opcode = $firstByte & 0x0F;
            echo "opcode:$this->opcode\r\n";
            if ($this->opcode == self::OPCODE_CLOSED) {
                //客户端连接关闭时会走这里
                //注意：由于上次连接关闭导致状态为关闭，再次握手时状态会出问题，所以每个连接要有一个单独的协议类的状态信息
                $this->webSocketHandShakeStatus = self::WEBSOCKET_CLOSE_STATUS;
                //这里返回true关闭连接
                return true;
            }

            $secondByte = ord($data[1]);
            $this->mask = ($secondByte & 0x80) == 0x80 ? 1 : 0;
            echo "mask:$this->mask\r\n";
            if ($this->mask == 0) {
                $this->webSocketHandShakeStatus = self::WEBSOCKET_CLOSE_STATUS;
                return false;
            }

            $this->payload_len = $secondByte & 0x7F;
            if ($this->payload_len == 126) {
                //后续 2 个字节代表一个 16 位的无符号整数，该无符号整数的值为数据的长度
                $len = 0;
                $len |= ord($data[2]) << 8;
                $len |= ord($data[3]) << 0;
                $this->dataLen = $len;
                $this->headerLen += 2;
            } else if ($this->payload_len == 127) {
                //后续 8 个字节代表一个 64 位的无符号整数（最高位为 0），该无符号整数的值为数据的长度
                $len = 0;
                $len |= ord($data[2]) << 56;
                $len |= ord($data[3]) << 48;
                $len |= ord($data[4]) << 40;
                $len |= ord($data[5]) << 32;
                $len |= ord($data[6]) << 24;
                $len |= ord($data[7]) << 16;
                $len |= ord($data[8]) << 8;
                $len |= ord($data[9]) << 0;
                $this->dataLen = $len;
                $this->headerLen += 8;
            } else {
                $this->dataLen = $this->payload_len;
            }
            echo "payload_len:$this->payload_len\r\n";
            //加上mask-key的长度
            $this->headerLen += 4;

            $this->maskKey[0] = $data[$this->headerLen - 4];
            $this->maskKey[1] = $data[$this->headerLen - 3];
            $this->maskKey[2] = $data[$this->headerLen - 2];
            $this->maskKey[3] = $data[$this->headerLen - 1];
            if (strlen($data) < $this->headerLen + $this->dataLen) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    //处理握手数据
    public function encode($data = '') {
        // TODO: Implement encode() method.
        if ($this->webSocketHandShakeStatus == self::WEBSOCKET_START_STATUS) {
            $handshakeData = $this->handshake();
            if ($handshakeData) {
                $this->webSocketHandShakeStatus = self::WEBSOCKET_RUNNING_STATUS;
                return $this->_http->encode($handshakeData);
            } else {
                $this->webSocketHandShakeStatus = self::WEBSOCKET_CLOSE_STATUS;
                return $this->_http->encode($this->response400());
            }
        } else {
            $dataLen = strlen($data);
            $firstByte = 0x80 | self::OPCODE_FRAME;
            $secondByte = 0x00 | $dataLen;
            $headerLen = 2;
            if ($dataLen <= 125) {
                $frame = chr($firstByte).chr($secondByte).$data;
            } else if ($dataLen <= 65536) {
                $secondByte = 126;
                $len1 = $dataLen >> 8 & 0xFF;
                $len2 = $dataLen >> 0 & 0xFF;
                $frame = chr($firstByte).chr($secondByte).chr($len1).chr($len2).$data;
                $headerLen += 2;
            } else {
                $secondByte = 127;
                $len1 = $dataLen >> 56 & 0xFF;
                $len2 = $dataLen >> 48 & 0xFF;
                $len3 = $dataLen >> 40 & 0xFF;
                $len4 = $dataLen >> 32 & 0xFF;
                $len5 = $dataLen >> 24 & 0xFF;
                $len6 = $dataLen >> 16 & 0xFF;
                $len7 = $dataLen >>  8 & 0xFF;
                $len8 = $dataLen >>  0 & 0xFF;
                $frame = chr($firstByte).chr($secondByte)
                        .chr($len1).chr($len2)
                        .chr($len3).chr($len4)
                        .chr($len5).chr($len6)
                        .chr($len7).chr($len8)
                        .$data;
                $headerLen += 8;
            }
            return [$headerLen + $dataLen, $frame];
        }
    }


    public function decode($data = '') {
        // TODO: Implement decode() method.
        if ($this->webSocketHandShakeStatus == self::WEBSOCKET_START_STATUS) {
            $this->_http->decode($data);
        } else {
            $data = substr($data, $this->headerLen);
            //解码 和掩码进行异或运算
            for ($i = 0; $i < $this->dataLen; $i++) {
                $data[$i] = $data[$i] ^ $this->maskKey[$i & 0b00000011];
            }
            return $data;
        }
    }

    public function msgLen($data = '') {
        // TODO: Implement msgLen() method.
        if ($this->webSocketHandShakeStatus == self::WEBSOCKET_START_STATUS) {
            return $this->_http->msgLen($data);
        } else {
            return $this->headerLen + $this->dataLen;
        }
    }


    public function response400($data=''): string {
        $len   = strlen($data);
        $text  = sprintf("HTTP/1.1 %d %s\r\n", 200, 'OK');
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
     *返回数据格式
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
            $handshakeResponse  = "HTTP/1.1 101 Switching Protocols\r\n";
            $handshakeResponse .= sprintf("Upgrade: %s\r\n", "websocket");
            $handshakeResponse .= sprintf("Connection: %s\r\n", "Upgrade");
            $handshakeResponse .= sprintf("Sec-WebSocket-Accept: %s\r\n\r\n", $acceptKey);
            return $handshakeResponse;
        } else {
            return false;
        }
    }
}