<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/9/2
 * Time: 15:21
 */

$sockets = stream_socket_pair(AF_UNIX, STREAM_SOCK_DGRAM, 0);
stream_set_blocking($sockets[0], 0);
stream_set_blocking($sockets[1], 0);

$pid = pcntl_fork();

if (0 == $pid) {
    $eventBase = new \EventBase();
    $event = new \Event($eventBase, $sockets[1], \Event::WRITE|\Event::PERSIST, function ($fd, $what, $args) {
        echo fwrite($fd, "China");
        echo "\r\n";
    }, ['a' => 'b']);
    $event->add();
    $events[] = $event;
    $eventBase->dispatch();
} else {
    $eventBase = new \EventBase();
    $event = new \Event($eventBase, $sockets[0], \Event::READ|\Event::PERSIST, function ($fd, $what, $args) {
        echo fread($fd, 128);
        echo "\r\n";
    }, ['a' => 'b']);
    $event->add();
    $events[] = $event;
    $eventBase->dispatch();
}
