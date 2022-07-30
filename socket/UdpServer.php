<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/7/30
 * Time: 16:26
 */

//udp 是数据报服务 传输数据的长度是固定的 不可靠的 也就是说发送端写数据 服务端没有及时接收数据
//或者数据缓冲区不够 容易造成数据的丢失

//tcp是字节流的服务 传输是可靠的 有序的 数据是没有边界的
///home/work/study/project/internetMessage/socket
$socket_fd = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_bind($socket_fd, "0.0.0.0", "9502");
$connections = [];

while (1) {
    socket_recvfrom($socket_fd, $data, 1024, 0, $address, $port);
    $connections[$port] = $address;

    fprintf(STDOUT, "recv from client:%s,address:%s,port:%s", $data, $address, $port);
    foreach ($connections as $port => $add) {
        socket_sendto($socket_fd, $data, 1024, 0, $address, $port);
    }

    if (strncasecmp($data, "quit", 4) == 0) {
        break;
    }
}
socket_close($socket_fd);