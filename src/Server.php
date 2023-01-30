<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/1
 * Time: 14:16
 */

namespace Socket\Ms;

use Socket\Ms\Event\Epoll;
use Socket\Ms\Event\Event;
use Socket\Ms\Event\Select;

class Server {

    public $_address;
    public $_socket;
    public static $_connections = [];
    public $_events = [];

    public $_protocol = null;

    static public $_clientNum = 0;
    static public $_recvNum = 0;
    static public $_msgNum = 0;

    public $_starttime;

    public static $_startFile;
    public static $_pidFile;
    public static $_logFile;
    public $_settings = [];
    public $_pidMap = [];

    public $_protocols = [
        "stream" => "Socket\Ms\Protocols\Stream",
        "text" => "Socket\Ms\Protocols\Text",
        "websocket" => "",
        "http" => "",
        "mqtt" => ""
    ];

    static public $_eventLoop;


    public function on($eventName, callable $eventCall) {
        $this->_events[$eventName] = $eventCall;
    }

    public function __construct($address) {
        list($protocol, $ip, $port) = explode(":", $address);
        if (isset($this->_protocols[$protocol])) {
            $this->_protocol = new $this->_protocols[$protocol]();
        }
        $this->_starttime = time();
        $this->_address = "tcp:$ip:$port";
        if (DIRECTORY_SEPARATOR == '/') {
            static::$_eventLoop = new Epoll();
        } else {
            static::$_eventLoop = new Select();
        }
    }

    public function settings($settings) {
        $this->_settings = $settings;
    }

    public function onClientJoin() {
        self::$_clientNum++;
    }

    public function onRecv() {
        self::$_recvNum++;
    }

    public function onMsg() {
        self::$_msgNum++;
    }

    public function statistics() {
        $nowTime = time();
        $diffTime = $nowTime - $this->_starttime;
        $this->_starttime = $nowTime;
        if ($diffTime >= 1) {
            fprintf(STDOUT,"time:<%s>--socket<%d>--<clientNum:%d>--<recvNum:%d>--<msgNum:%d>\r\n",
                $diffTime, (int)$this->_socket, static::$_clientNum, static::$_recvNum, static::$_msgNum);
            static::$_recvNum = 0;
            static::$_msgNum = 0;
        }
    }

    public function listen() {
        $flags = STREAM_SERVER_LISTEN|STREAM_SERVER_BIND;
        $option['socket']['backlog'] = 102400;   //ulimit -a
        $context = stream_context_create($option);    //setsocketopt
        $this->_socket = stream_socket_server($this->_address, $error_code, $error_message, $flags, $context);
        stream_set_blocking($this->_socket, 0);
        if (!is_resource($this->_socket)) {
            fprintf(STDOUT, "socket create fail:%s\n", $error_message);
            exit(0);
        }
        fprintf(STDOUT,"listen on:%s\n", $this->_address);
    }

    public function signalHandler($sigNum) {
        $masterPid = file_get_contents(self::$_pidFile);
        switch ($sigNum) {
            case SIGINT:
            case SIGTERM:
            case SIGQUIT:
                if ($masterPid == posix_getpid()) {
                    foreach ($this->_pidMap as $pid) {
                        posix_kill($pid, $sigNum);
                    }
                } else {
                    //子进程收到中断信号
                    static::$_eventLoop->del($this->_socket, Event::READ);
                    set_error_handler(function (){});
                    fclose($this->_socket);
                    restore_error_handler();
                    $this->_socket = null;
                    foreach (static::$_connections as $connection) {
                        $connection->close();
                    }
                    static::$_connections = [];
                    static::$_eventLoop->clearTimer();
                    static::$_eventLoop->clearSignalEvents();
                    if (static::$_eventLoop->exitLoop()) {
                        fprintf(STDOUT, "<pid:%d> exit ok\r\n", posix_getpid());
                    }
                }
            break;
        }
    }

    public function start() {
        $this->init();
        global $argv;
        switch ($argv[1]) {
            case 'start':
                if (is_file(self::$_pidFile)) {
                    $masterPid = file_get_contents(self::$_pidFile);
                } else {
                    $masterPid = 0;
                }
                $masterProcessAlive = $masterPid && posix_kill($masterPid, 0) && $masterPid != posix_getpid();
                if ($masterProcessAlive) {
                    exit('server already running');
                }
                $this->forkWorker();
                $this->saveMasterPid();
                $this->installSignalHandler();
                $this->masterWorker();
                break;
            case 'stop':
                $masterPid = file_get_contents(self::$_pidFile);
                if ($masterPid && posix_kill($masterPid, 0)) {
                    posix_kill($masterPid, SIGINT);
                    echo "发送SIGINT中断信号了\r\n";
                    $timeout = 5;
                    $stopTime = time();
                    while (1) {
                        $masterPidAlive = $masterPid && posix_kill($masterPid, 0) && $masterPid != posix_getpid();
                        if ($masterPidAlive) {
                            if (time() - $stopTime > $timeout) {
                                echo "server stop failure\r\n";
                                break;
                            }
                            sleep(1);
                            continue;
                        }
                        echo "server stop successfully\r\n";
                        exit(0);
                    }
                } else {
                    exit("server not running\r\n");
                }
                break;
            default:
                $text = "php ".pathinfo(self::$_startFile)['filename'].".php - [start|stop]\r\n";
                exit($text);
        }
    }

    public function masterWorker() {
        while (1) {
            pcntl_signal_dispatch();
            $pid = pcntl_wait($status);
            pcntl_signal_dispatch();
            if ($pid > 0) {
                unset($this->_pidMap[$pid]);
            }
            if (empty($this->_pidMap)) {
                break;
            }
        }
        fprintf(STDOUT, "master process <pid:%d>exit ok\r\n", posix_getpid());
        exit(0);
    }

    public function saveMasterPid() {
        $masterPid = getmypid();
        file_put_contents(self::$_pidFile, $masterPid);
    }

    public function installSignalHandler() {
        pcntl_signal(SIGINT, [$this, 'signalHandler'], false);
        pcntl_signal(SIGQUIT, [$this, 'signalHandler'], false);
        pcntl_signal(SIGTERM, [$this, 'signalHandler'], false);
    }

    public function init() {
        $trace = debug_backtrace();
        $startFile = array_pop($trace)['file'];
        self::$_startFile = $startFile;
        self::$_pidFile = pathinfo($startFile)['filename'].'.pid';
        self::$_logFile = pathinfo($startFile)['filename'].'.log';
        if (!file_exists(self::$_logFile)) {
            touch(self::$_logFile);
            chown(self::$_logFile, posix_getuid());
        }
    }

    public function forkWorker() {
        $this->listen();
        $workerNum = 1;
        if (isset($this->_settings['workerNum']) && $this->_settings['workerNum']) {
            $workerNum = $this->_settings['workerNum'];
        }
        for ($i = 0; $i < $workerNum; $i++) {
            $pid = pcntl_fork();
            if (0 == $pid) {
                $this->worker();
            } else {
                $this->_pidMap[$pid] = $pid;
            }
        }
    }

    public function worker() {
        pcntl_signal(SIGINT, SIG_IGN, false);
        pcntl_signal(SIGTERM, SIG_IGN, false);
        pcntl_signal(SIGQUIT, SIG_IGN, false);

        static::$_eventLoop->add(SIGINT, Event::EVENT_SIGNAL, [$this, "signalHandler"]);
        static::$_eventLoop->add(SIGQUIT, Event::EVENT_SIGNAL, [$this, "signalHandler"]);
        static::$_eventLoop->add(SIGTERM, Event::EVENT_SIGNAL, [$this, "signalHandler"]);

        self::$_eventLoop->add($this->_socket, Event::READ, [$this, "accept"]);
//        self::$_eventLoop->add(1, Event::EVENT_TIMER, [$this, "checkHeartTime"]);
//        $timer_id1 = self::$_eventLoop->add(2, Event::EVENT_TIMER, function ($timer_id, $args) {
//            echo "定时时间到了1 start\r\n";
//            echo microtime(true)."\r\n";
//            echo "定时时间到了1 end\r\n";
//        }, ['name' => 'sxg']);
        $this->eventLoop();

        fprintf(STDOUT, "<workerPid:%d>exit success\r\n", posix_getpid());
        exit(0);
    }

    public function checkHeartTime() {
        echo "执行心跳检测了\r\n";
        foreach (self::$_connections as $idx => $fd) {
            /**@var TcpConnections $fd */
            if ($fd->checkHeartTime()) {
                $fd->close();
            }
        }
    }

    public function eventLoop() {
        static::$_eventLoop->loop();
    }

//    public function loop() {
//        $readFds[] = $this->_socket;
//        while (1) {
//            $reads = $readFds;
//            $writes = [];
//            $exps = [];
//
//            $this->statistics();
////            $this->checkHeartTime();
//
//            if (!empty(self::$_connections)) {
//                foreach (self::$_connections as $idx => $connection) {
//                    $socket_fd = $connection->_socketFd;
//                    if (is_resource($socket_fd)) {
//                        $reads[] = $socket_fd;
////                        $writes[] = $socket_fd;
//                    }
//                }
//            }
//            set_error_handler(function (){});
//            //此函数的第四个参数设置为null则为阻塞状态 当有客户端连接或者收发消息时 会解除阻塞 内核会修改 &$read &$write
//            $ret = stream_select($reads, $writes, $exps, 0, 100);
//            restore_error_handler();
//            if ($ret === false) {
//                break;
//            }
//            if ($reads) {
//                foreach ($reads as $fd) {
//                    if ($fd == $this->_socket) {
//                        $this->accept();
//                    } else {
//                        /**@var TcpConnections $connection */
//                        if (isset(self::$_connections[(int)$fd])) {
//                            $connection = self::$_connections[(int)$fd];
//                            if ($connection->isConnected()) {
//                                $connection->recvSocket();
//                            }
//                        }
//                    }
//                }
//            }
//
//            if ($writes) {
//                foreach ($writes as $fd) {
//                    if (isset(self::$_connections[(int)$fd])) {
//                        /**@var TcpConnections $connection*/
//                        $connection = self::$_connections[(int)$fd];
//                        if ($connection->isConnected()) {
//                            $connection->writeSocket();
//                        }
//                    }
//                }
//            }
//        }
//    }

    public function eventCallBak($eventName, $args = []) {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }

    public function removeClient($socket_fd) {
        if (isset(self::$_connections[(int)$socket_fd])) {
            unset(self::$_connections[(int)$socket_fd]);
            self::$_clientNum--;
        }
    }

    public function accept() {
        $connId = stream_socket_accept($this->_socket, -1, $peer_name);
        if (is_resource($connId)) {
            $connection = new TcpConnections($connId, $peer_name, $this);
            $this->onClientJoin();
            self::$_connections[(int)($connId)] = $connection;
            $this->eventCallBak("connect", [$connection]);
        }
    }
}

//proc/$pid/net/tcp   16进制数 前面的ip两位两位地从后往前算
//  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
//   0: 0100007F:2328 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 37810 1 ffff993cba8587c0 100 0 0 10 0
//   1: 00000000:18EB 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 37977 1 ffff993cba858f80 100 0 0 10 0
//   2: 00000000:0050 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 37992 1 ffff993cba859740 100 0 0 10 0
//   3: 00000000:0016 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 37771 1 ffff993cba858000 100 0 0 10 0
//   4: 0100007F:251D 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 39745 1 ffff993cba85be00 100 0 0 10 0
//   5: 64BCA8C0:0016 01BCA8C0:CB41 01 00000024:00000000 01:00000018 00000000     0        0 39625 4 ffff993cba85b640 24 4 21 10 -1
//   6: 64BCA8C0:0016 01BCA8C0:CB2C 01 00000000:00000000 02:000AA7A8 00000000     0        0 39500 2 ffff993cba85ae80 23 4 20 10 -1
//   7: 64BCA8C0:0016 01BCA8C0:E25A 01 00000000:00000000 02:0007E7A8 00000000     0        0 38526 2 ffff993cba85a6c0 22 4 31 10 -1
//   8: 64BCA8C0:0016 01BCA8C0:E03A 01 00000000:00000000 02:00061ADB 00000000     0        0 38298 2 ffff993cba859f00 24 4 20 10 -1
