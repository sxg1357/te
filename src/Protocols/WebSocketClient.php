<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023/5/11
 * Time: 11:35
 */

namespace Socket\Ms\Protocols;

class WebSocketClient implements Protocols {

    public $_http;
    public $webSocketHandShakeStatus;
    const WEBSOCKET_START_STATUS = 11;
    const WEBSOCKET_PREPARE_STATUS = 12;
    const WEBSOCKET_RUNNING_STATUS = 13;
    const WEBSOCKET_CLOSE_STATUS = 14;

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

    public $websocketKey;


    public function __construct() {
        $this->_http = new Http();
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
    public function Len($data) {
        // TODO: Implement Len() method.
        if ($this->webSocketHandShakeStatus == self::WEBSOCKET_PREPARE_STATUS) {
            return $this->_http->Len($data);
        } else {
            if (strlen($data) < 2) {
                return false;
            }
            $bin = unpack("CfirstByte/CsecondByte", $data);
            $this->headerLen = 2;
            $this->fin = $bin['firstByte'] & 0b10000000 == 0x80 ? 1 : 0;
            $this->opcode = $bin['firstByte'] & 0b00001111;
            if ($this->opcode == self::OPCODE_CLOSED) {
                $this->webSocketHandShakeStatus = self::WEBSOCKET_CLOSE_STATUS;
                return true;
            }
            $this->payload_len = $bin['secondByte'] & 0b01111111;
            if ($this->payload_len == 0b01111110) {
                $this->headerLen += 2;
                $bin = unpack("Cf/Cs/ndataLen", $data);
                $this->dataLen = $bin['dataLen'];
            } else if ($this->payload_len == 0b01111111) {
                $this->headerLen += 8;
                $bin = unpack("Cf/Cs/JdataLen", $data);
                $this->dataLen = $bin['dataLen'];
            } else {
                $this->dataLen = $this->payload_len;
            }
            if (strlen($data) < $this->headerLen + $this->dataLen) {
                return false;
            }
            $this->maskKey = 0;
            return true;
        }
    }

    public function encode($data = '') {
        // TODO: Implement encode() method.
        if ($this->webSocketHandShakeStatus == self::WEBSOCKET_START_STATUS) {
            $this->webSocketHandShakeStatus = self::WEBSOCKET_PREPARE_STATUS;
            return $this->handshake();
        } else {
            $maskKey = chr(0x00).chr(0x00).chr(0x00).chr(0x00);
            $dataLen = strlen($data);
            for ($i = 0; $i < $dataLen; $i++) {
                $data[$i] = $data[$i] ^ $maskKey[$i & 0b0011];
            }
            $headerLen = 2;
            if ($dataLen <= 125) {
                $frame = pack("CCN", 0b10000001, 0b01111111 & $dataLen, $maskKey).$data;
            } else if ($dataLen <= 65536) {
                $frame = pack("CCnN", 0b10000001, 0b01111110, $dataLen, $maskKey).$data;
                $headerLen += 2;
            } else {
                $frame = pack("CCJN", 0b10000001, 0b01111110, $dataLen, $maskKey).$data;
                $headerLen += 8;
            }
            $headerLen += 4;
            return [$dataLen + $headerLen, $frame];
        }
    }

    public function decode($data = '') {
        // TODO: Implement decode() method.
        if ($this->webSocketHandShakeStatus == self::WEBSOCKET_PREPARE_STATUS) {
            return $this->_http->decode($data);
        } else {
            return substr($data, $this->headerLen + $this->dataLen);
        }
    }

    public function msgLen($data = '') {
        // TODO: Implement msgLen() method.
        if ($this->webSocketHandShakeStatus == self::WEBSOCKET_PREPARE_STATUS) {
            return $this->_http->msgLen($data);
        } else {
            return $this->headerLen + $this->dataLen;
        }
    }

    public function handshake(): string {
        $this->websocketKey = base64_encode(md5(mt_rand(), true));
        $text  = sprintf("GET / HTTP/1.1\r\n");
        $text .= sprintf("Host: %s\r\n", "127.0.0.1:9501");
        $text .= sprintf("Connection: %s\r\n", "Upgrade");
        $text .= sprintf("Upgrade: %s\r\n", "websocket");
        $text .= sprintf("Sec-WebSocket-Version: %s\r\n", "13");
        $text .= sprintf("Sec-WebSocket-Key: %s\r\n", $this->websocketKey);
        $text .= "\r\n";
        return $text;
    }

    public function verifyWebSocketKey() : bool {
        if (isset($_REQUEST['sec_webSocket_accept']) && $_REQUEST['sec_webSocket_accept']) {
            if ($_REQUEST['sec_webSocket_accept'] == base64_encode(sha1($this->websocketKey."258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true))) {
                $this->webSocketHandShakeStatus = WebSocketClient::WEBSOCKET_RUNNING_STATUS;
                return true;
            }
        }
        return false;
    }
}