<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/7/30
 * Time: 20:29
 */

$server_unix = "unix_server";
$client_unix = "unix_client";

if (posix_access($client_unix, POSIX_F_OK)) {
    unlink($client_unix);
}

$socket_fd = socket_create(AF_UNIX, SOCK_DGRAM, 0);
socket_bind($socket_fd, $client_unix);

$pid = pcntl_fork();
if (0 == $pid) {
    while (1) {
        socket_recvfrom($socket_fd, $data, 1024, 0, $serverFile);
        if ($data) {
            fprintf(STDOUT, "recv data:%s from server\n", $data);
        }
        if (strncasecmp($data, "quit", 4) == 0) {
            break;
        }
    }
    exit(0);
}

while (1) {
    $data = fread(STDIN, 1024);
    if ($data) {
        socket_sendto($socket_fd, $data, 1024, 0, $server_unix);
    }
    if (strncasecmp($data, "quit", 4) == 0) {
        break;
    }
}

unlink($client_unix);
$pid = pcntl_wait($status);
if ($pid) {
    fprintf(STDOUT, "pid:%s exit\n", $pid);
}