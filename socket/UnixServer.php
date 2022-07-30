<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/7/30
 * Time: 20:20
 */

$server_unix = "unix_server";

if (posix_access($server_unix, POSIX_F_OK)) {
    unlink($server_unix);
}

$socket_fd = socket_create(AF_UNIX, SOCK_DGRAM, 0);
socket_bind($socket_fd, $server_unix);

while (1) {
    socket_recvfrom($socket_fd, $data, 1024, 0, $clientUnix);
    if ($data) {
        fprintf(STDOUT, "recv data:%s from client file:%s\n", $data, $clientUnix);
        socket_sendto($socket_fd, $data, 1024, 0, $clientUnix);
    }
    if (strncasecmp($data, "quit", 4) == 0) {
        break;
    }
}
unlink($server_unix);