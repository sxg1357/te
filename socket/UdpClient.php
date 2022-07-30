<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/7/30
 * Time: 16:56
 */

$socket_fd = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

$pid = pcntl_fork();
if (0 == $pid) {
    while (1) {
        socket_recvfrom($socket_fd, $buf, 1024, 0, $address, $port);
        if ($buf) {
            fprintf(STDOUT, "recv:%s from server address:%s,port:%s\n", $buf, $address, $port);
        }
        if (strncasecmp($buf, "quit", 4) == 0) {
            break;
        }
    }
    exit(0);

}
while (1) {
    $data = fgets(STDIN, 1024);
    if ($data) {
        socket_sendto($socket_fd, $data, 1024, 0, "127.0.0.1", "9502");
    }
    if (strncasecmp($data, "quit", 4) == 0) {
        break;
    }
}
$pid = pcntl_wait($status);
if ($pid) {
    fprintf(STDOUT, "pid %d exit\n", $pid);
}
socket_close($socket_fd);