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

    public $unix_socket;
    public $unix_file;

    public function __construct($unix_socket, $unix_file) {
        $this->unix_socket = $unix_socket;
        $this->unix_file = $unix_file;
    }



}