<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/7/31
 * Time: 13:07
 */

$socket_fd = stream_socket_client("tcp://127.0.0.1:9501");

$data = fread($socket_fd, 1024);

echo $data;
