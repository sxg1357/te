<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/9/2
 * Time: 16:10
 */

namespace Socket\Ms\Event;

interface Event
{
    const READ = 2;
    const WRITE = 4;
    const EVENT_SIGNAL = 8;
    const EVENT_TIMER = 12;
    const EVENT_TIMER_ONCE = 13;

    public function add($fd, $flag, $func, $args);
    public function del($fd, $flag);

    public function loop();
    public function clearTimer();
    public function clearSignalEvents();
}