<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/9/2
 * Time: 16:14
 */

namespace Socket\Ms\Event;

class Epoll implements Event
{
    public $_eventBase;
    public $_allEvents;
    public $_signalEvents;

    public function __construct() {
        $this->_eventBase = new \EventBase();
    }

    public function add($fd, $flag, $func, $args = [])
    {
        // TODO: Implement add() method.
        switch ($flag) {
            case self::EVENT_READ:
                $event = new \Event($this->_eventBase, $fd, \Event::READ|\Event::PERSIST, $func, $args);
                if (!$event || !$event->add()) {
                    fprintf(STDOUT, "事件添加失败\n");
                    print_r(error_get_last());
                    return false;
                }
                $this->_allEvents[(int)$fd][Event::EVENT_READ] = $event;
                break;
            case self::EVENT_WRITE:
                $event = new \Event($this->_eventBase, $fd, \Event::WRITE|\Event::PERSIST, $func, $args);
                if (!$event || !$event->add()) {
                    fprintf(STDOUT, "事件添加失败\n");
                    print_r(error_get_last());
                    return false;
                }
                $this->_allEvents[(int)$fd][Event::EVENT_WRITE] = $event;
                break;
        }
    }

    public function del($fd, $flag)
    {
        // TODO: Implement del() method.
    }

    public function loop()
    {
        // TODO: Implement loop() method.
        echo "执行事件循环了\r\n";
        return $this->_eventBase->loop();
    }

    public function clearTimer()
    {
        // TODO: Implement clearTimer() method.
    }

    public function clearSignalEvents()
    {
        // TODO: Implement clearSignalEvents() method.
    }
}