<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/7
 * Time: 10:21
 */

namespace Socket\Ms\Protocols;

class Stream implements Protocols {

    /**
     * @param $data
     * @return bool
     */
    public function Len($data)
    {
        // TODO: Implement Len() method.
        if (strlen($data) < 4) {
            return false;
        }
        $tmp = unpack("NtotalLen", $data);
        if (strlen($data) < $tmp['totalLen']) {
            return false;
        }
        return true;
    }

    public function encode($data = '')
    {
        // TODO: Implement encode() method.
        $totalLen = strlen($data) + 6;
        $bin = pack("Nn", $totalLen, '1') . $data;
        return [$totalLen, $bin];
    }

    /**
     * @param string $data
     * @return string
     */
    public function decode($data = '')
    {
        // TODO: Implement decode() method.
        return substr($data, 6);
    }


    /**
     * @param string $data
     * @return mixed
     */
    public function msgLen($data = '')
    {
        // TODO: Implement msgLen() method.
        $tmp = unpack("Nlength", $data);
        return $tmp['length'];
    }
}