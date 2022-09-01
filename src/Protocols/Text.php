<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/31
 * Time: 18:04
 */

namespace Socket\Ms\Protocols;

class Text implements Protocols {

    public function Len($data)
    {
        // TODO: Implement Len() method.
        if (strlen($data)) {
            return strpos($data, "\n");
        }
        return false;
    }

    public function encode($data = '')
    {
        // TODO: Implement encode() method.
        $data .= "\n";
        return [strlen($data), $data];
    }

    public function decode($data = '')
    {
        // TODO: Implement decode() method.
        return rtrim($data, "\n");
    }

    public function msgLen($data = '')
    {
        // TODO: Implement msgLen() method.
        return strpos($data, "\n") + 1;
    }
}