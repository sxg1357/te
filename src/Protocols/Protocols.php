<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/7
 * Time: 10:21
 */

namespace Socket\Ms\Protocols;

interface Protocols {
    public function Len($data);
    public function encode($data = '');
    public function decode($data = '');
    public function msgLen($data = '');
}