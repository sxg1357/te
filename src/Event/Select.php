<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/9/6
 * Time: 11:24
 */

namespace Socket\Ms\Event;

class Select implements Event
{
    public $_eventBase;
    public $_allEvents = [];
    public $_signalEvents = [];

    public $_readFds = [];
    public $_writeFds = [];
    public $_expFds = [];

    public $_timers = [];
    public static $_timerId = 1;
    public $_timeout = 100000000;   //1秒=1000毫秒 1毫秒=1000微妙 100秒 微妙级别的定时

    public $_run = true;

    public function __construct()
    {

    }

    public function signalHandler($sigNum) {
        $callback = $this->_signalEvents[$sigNum];
        if (is_callable($callback[0])) {
            call_user_func_array($callback[0], [$sigNum]);
        }
    }

    public function timerCallBack() {
//        echo "执行timerCallBack函数了\r\n";
        //$params = [$func, $runTime, $flag, $timer_id, $fd, $args];
        foreach ($this->_timers as $key => $timer) {
            $func = $timer[0];
            $runTime = $timer[1];
            $flag = $timer[2];
            $timer_id = $timer[3];
            $fd = $timer[4];
            $args = $timer[5];
            if (microtime(true) >= $runTime) {
                if ($flag == self::EVENT_TIMER_ONCE) {
                    unset($this->_timers[$timer_id]);
                } else {
                    $runTime = microtime(true) + $fd;
                    $this->_timers[$key][1] = $runTime;
                }
                call_user_func_array($func, [$timer_id, $args]);
            }
        }
     }

    public function add($fd, $flag, $func, $args = [])
    {
        // TODO: Implement add() method.
        $fdKey = (int)$fd;
        switch ($flag) {
            case self::READ:
                $this->_readFds[$fdKey] = $fd;
                $this->_allEvents[$fdKey][self::READ] = [$func, [$fd, $flag, $args]];
                break;
            case self::WRITE:
                $this->_writeFds[$fdKey] = $fd;
                $this->_allEvents[$fdKey][self::WRITE] = [$func, [$fd, $flag, $args]];
                break;
            case self::EVENT_TIMER:
            case self::EVENT_TIMER_ONCE:
                $timer_id = self::$_timerId;
                $runTime = microtime(true) + $fd;
                $params = [$func, $runTime, $flag, $timer_id, $fd, $args];

                $this->_timers[$timer_id] = $params;
                $selectTime = $fd * 1000000;
                if ($this->_timeout >= $selectTime) {
                    $this->_timeout = $selectTime;
                }
                ++self::$_timerId;
                return $timer_id;
            case self::EVENT_SIGNAL:
                $params = [$func, $args];
                $this->_signalEvents[$fd] = $params;
                if (pcntl_signal($fd, [$this, 'signalHandler'], false)) {
                    fprintf(STDOUT, "pid %d add signal %d event successfully\r\n", posix_getpid(), $fd);
                }
                return true;
        }
    }

    public function del($fd, $flag)
    {
        // TODO: Implement del() method.
        $fdKey = (int)$fd;
        switch ($flag) {
            case self::READ:
                unset($this->_readFds[$fdKey]);
                unset($this->_allEvents[$fdKey][self::READ]);
                if (empty($this->_allEvents[$fdKey])) {
                    unset($this->_allEvents[$fdKey]);
                }
                break;
            case self::WRITE:
                unset($this->_writeFds[$fdKey]);
                unset($this->_allEvents[$fdKey][self::WRITE]);
                if (empty($this->_allEvents[$fdKey])) {
                    unset($this->_allEvents[$fdKey]);
                }
                break;
            case self::EVENT_TIMER:
            case self::EVENT_TIMER_ONCE:
                if (isset($this->_timers[$fd])) {
                    unset($this->_timers[$fd]);
                }
                break;
        }
    }

    public function loop()
    {
        // TODO: Implement loop() method.
        while ($this->_run) {
            $reads = $this->_readFds;
            $writes = $this->_writeFds;
            $exps = $this->_expFds;

            set_error_handler(function (){});
            //此函数的第四个参数设置为null则为阻塞状态 当有客户端连接或者收发消息时 会解除阻塞 内核会修改 &$read &$write
            $ret = stream_select($reads, $writes, $exps, 0, $this->_timeout);
            restore_error_handler();

            if (!empty($this->_timers)) {
                $this->timerCallBack();
            }

            if ($ret === false) {
                break;
            }

            if ($reads) {
                foreach ($reads as $fd) {
                    $fdKey = (int)$fd;
                    if (isset($this->_allEvents[$fdKey][self::READ])) {
                        $callback = $this->_allEvents[$fdKey][self::READ];
                        call_user_func_array($callback[0], $callback[1]);
                    }
                }
            }
            if ($writes) {
                foreach ($writes as $fd) {
                    $fdKey = (int)$fd;
                    if (isset($this->_allEvents[$fdKey][self::WRITE])) {
                        $callback = $this->_allEvents[$fdKey][self::WRITE];
                        call_user_func_array($callback[0], $callback[1]);
                    }
                }
            }
        }
    }

    public function loop1()
    {
        $reads = $this->_readFds;
        $writes = $this->_writeFds;
        $exps = $this->_expFds;

        set_error_handler(function (){});
        //此函数的第四个参数设置为null则为阻塞状态 当有客户端连接或者收发消息时 会解除阻塞 内核会修改 &$read &$write
        $ret = stream_select($reads, $writes, $exps, 0, 0);
        restore_error_handler();

        if ($ret === false) {
            return false;
        }

        if ($reads) {
            foreach ($reads as $fd) {
                $fdKey = (int)$fd;
                if (isset($this->_allEvents[$fdKey][self::READ])) {
                    $callback = $this->_allEvents[$fdKey][self::READ];
                    call_user_func_array($callback[0], $callback[1]);
                }
            }
        }
        if ($writes) {
            foreach ($writes as $fd) {
                $fdKey = (int)$fd;
                if (isset($this->_allEvents[$fdKey][self::WRITE])) {
                    $callback = $this->_allEvents[$fdKey][self::WRITE];
                    call_user_func_array($callback[0], $callback[1]);
                }
            }
        }
        return true;
    }

    public function clearTimer()
    {
        // TODO: Implement clearTimer() method.
        $this->_timers = [];
    }

    public function clearSignalEvents()
    {
        // TODO: Implement clearSignalEvents() method.
        $this->_signalEvents = [];
    }

    public function exitLoop()
    {
        // TODO: Implement exitLoop() method.
        $this->_run = false;
        $this->_signalEvents = [];
        $this->_timers = [];
        $this->_allEvents = [];
        $this->_writeFds = [];
        $this->_readFds = [];
        $this->_expFds = [];
        return true;
    }
}
