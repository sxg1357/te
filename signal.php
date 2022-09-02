<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/9/2
 * Time: 14:36
 */

$eventBase = new \EventBase();

$event = new \Event($eventBase, 2, \Event::SIGNAL, function ($fd, $what, $args) {
    echo "中断信号处理函数执行了\n";
    print_r($fd);
    print_r($what);
    print_r($args);
}, ['a' => 'b']);

$event->add();
$eventBase->dispatch();

