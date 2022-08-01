<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/7/30
 * Time: 21:47
 */
#执行以下命令

//stream_socket_server 相当于socket_create socket_bind socket_listen
$socket_fd  = stream_socket_server("tcp://127.0.0.1:9501");
$connId = stream_socket_accept($socket_fd, -1, $ip);
//socket_write($connId, "hello world", 1024);  这里用socket_write 和 socket_send是不可以的
//PHP Warning:  socket_write(): supplied resource is not a valid Socket resource
// in /home/work/study/project/internetMessage/socket/stream1.php on line 12
fwrite($connId, "hello world", 1024);

