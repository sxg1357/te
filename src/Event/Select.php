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
    public $_allEvents;
    public $_signalEvents;

    public $_readFds = [];
    public $_writeFds = [];
    public $_expFds = [];

    public function __construct()
    {

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
        }
    }

    public function loop()
    {
        // TODO: Implement loop() method.
        while (1) {
            $reads = $this->_readFds;
            $writes = $this->_writeFds;
            $exps = $this->_expFds;

            set_error_handler(function (){});
            //此函数的第四个参数设置为null则为阻塞状态 当有客户端连接或者收发消息时 会解除阻塞 内核会修改 &$read &$write
            $ret = stream_select($reads, $writes, $exps, 0, 100);
            restore_error_handler();

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
        $ret = stream_select($reads, $writes, $exps, 0, 100);
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
    }

    public function clearSignalEvents()
    {
        // TODO: Implement clearSignalEvents() method.
    }
}
