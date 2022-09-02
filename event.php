<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/9/1
 * Time: 17:26
 */

$eventBase = new \EventBase();

$event = new \Event($eventBase, -1, Event::TIMEOUT|Event::PERSIST, function ($fd, $what, $arg) {
    echo "时间到了\n";
    echo "$fd-$what\n";
    var_dump($arg);
}, ['a' => 'b']);

$event->add(1);
$events[] = $event;
$eventBase->loop();


