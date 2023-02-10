<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/7/30
 * Time: 12:48
 */

$socket_fd = socket_create(AF_INET, SOCK_STREAM, 0);
fprintf(STDOUT, $socket_fd."\n");

socket_bind($socket_fd, "0.0.0.0", 9501);
socket_listen($socket_fd);

$connId = socket_accept($socket_fd);
if ($connId) {
    fprintf(STDOUT, "connId:%s\n", $connId);
    socket_write($connId, "hello\n", 6);
} else {
    $error_code = socket_last_error($socket_fd);
    fprintf(STDOUT, "connect fail errno:%s\n", socket_strerror($error_code));
}
socket_close($socket_fd);

//netstat -luntp
//Active Internet connections (only servers)
//Proto Recv-Q Send-Q Local Address           Foreign Address         State       PID/Program name
//tcp        0      0 0.0.0.0:22              0.0.0.0:*               LISTEN      6734/sshd
//tcp        0      0 0.0.0.0:9501            0.0.0.0:*               LISTEN      21351/php