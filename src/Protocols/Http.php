<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023/3/29
 * Time: 14:49
 */

namespace Socket\Ms\Protocols;

class Http implements Protocols {

    public $_headerLen = 0;
    public $_bodyLen = 0;

    public function Len($data)
    {
        // TODO: Implement Len() method.
        $len = strpos($data, "\r\n\r\n");
        if ($len !== false) {
            $this->_headerLen = $len + 4;
            if (preg_match("/\r\nContent-Length:?(\d+)/i", $data, $matches)) {
               $this->_bodyLen = $matches[1];
               print_r($matches[1]);
            }
            $totalLen = $this->_bodyLen + $this->_headerLen;
            if (strlen($data) >= $totalLen) {
                return true;
            }
            return false;
        }
        return false;
    }

    public function encode($data = '')
    {
        // TODO: Implement encode() method.
    }

    public function decode($data = '')
    {
        // TODO: Implement decode() method.

    }

    public function msgLen($data = '')
    {
        // TODO: Implement msgLen() method.
        return $this->_headerLen + $this->_bodyLen;
    }
}