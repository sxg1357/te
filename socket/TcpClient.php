<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/7/30
 * Time: 14:03
 */

$socket_fd = socket_create(AF_INET, SOCK_STREAM, 0);

if (socket_connect($socket_fd, "127.0.0.1", "9501")) {
    fprintf(STDOUT, "连接成功\n");
    socket_recv($socket_fd, $data, 1024, 0);
    if ($data) {
        fprintf(STDOUT, "recv data:%s from server\n", $data);
    }
}
socket_close($socket_fd);