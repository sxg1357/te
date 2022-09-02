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
    const EVENT_READ = 10;
    const EVENT_WRITE = 11;
    const EVENT_SIGNAL = 12;
    const EVENT_TIMER = 13;
    const EV_TIMER_ONCE = 13;

    public function add($fd, $flag, $func, $args);
    public function del($fd, $flag);

    public function loop();
    public function clearTimer();
    public function clearSignalEvents();
}