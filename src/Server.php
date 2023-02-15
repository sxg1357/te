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
use Opis\Closure\SerializableClosure;

class Server {

    public $_address;
    public $_socket;
    public static $_connections = [];
    public static $_udpConnections = [];
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

    public static $_status;
    const STATUS_START = 1;
    const STATUS_RUNNING = 2;
    const STATUS_SHUTDOWN = 3;

    public $unix_socket;

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
            $this->echoLog("time:<%s>--socket<%d>--<clientNum:%d>--<recvNum:%d>--<msgNum:%d>",
                $diffTime, (int)$this->_socket, static::$_clientNum, static::$_recvNum, static::$_msgNum);
            static::$_recvNum = 0;
            static::$_msgNum = 0;
        }
    }

    public function listen() {
        $flags = STREAM_SERVER_LISTEN|STREAM_SERVER_BIND;
        $option['socket']['backlog'] = 102400;   //ulimit -a
        $option['socket']['so_reuseport'] = 1;
        $context = stream_context_create($option);    //setsocketopt
        $this->_socket = stream_socket_server($this->_address, $error_code, $error_message, $flags, $context);
        stream_set_blocking($this->_socket, 0);
        if (!is_resource($this->_socket)) {
            $this->echoLog("socket create fail:%s", $error_message);
            exit(0);
        }
        $this->echoLog("listen on:%s", $this->_address);
    }

    public function signalHandler($sigNum) {
        $this->echoLog("<pid:%d>recv signo:%d", posix_getpid(), $sigNum);
        $masterPid = file_get_contents(self::$_pidFile);
        switch ($sigNum) {
            case SIGINT:
            case SIGTERM:
            case SIGQUIT:
                if ($masterPid == posix_getpid()) {
                    foreach ($this->_pidMap as $pid) {
                        posix_kill($pid, $sigNum);
                    }
                    self::$_status = self::STATUS_SHUTDOWN;
                } else {
                    //子进程收到中断信号
                    static::$_eventLoop->del($this->_socket, Event::READ);
                    set_error_handler(function (){});
                    fclose($this->_socket);
                    $this->_socket = null;
                    restore_error_handler();
                    foreach (static::$_connections as $connection) {
                        $connection->close();
                    }
                    static::$_status = self::STATUS_SHUTDOWN;
                    static::$_connections = [];
                    static::$_eventLoop->clearTimer();
                    static::$_eventLoop->clearSignalEvents();
                    if (static::$_eventLoop->exitLoop()) {
                        $this->echoLog("<pid:%d> exit ok", posix_getpid());
                    }
                }
            break;
        }
    }

    public function taskSignalHandler() {
        //子进程收到中断信号
        static::$_eventLoop->del($this->unix_socket, Event::READ);
        set_error_handler(function (){});
        fclose($this->unix_socket);
        $this->unix_socket = null;
        restore_error_handler();
        static::$_eventLoop->clearTimer();
        static::$_eventLoop->clearSignalEvents();
        if (static::$_eventLoop->exitLoop()) {
            $this->echoLog("<pid:%d> task process exit ok", posix_getpid());
        }
    }

    public function start() {
        self::$_status = self::STATUS_START;
        $this->init();
        global $argv;
        set_error_handler(function (){});
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
                $this->eventCallBak("masterStart", [$this]);
                cli_set_process_title("te/master");
                if ($this->checkSetting('daemon')) {
                    $this->daemon();
                    $this->resetFd();
                }
                $this->saveMasterPid();
                $this->installSignalHandler();
                $this->forkWorker();
                $this->forkTask();
                self::$_status = self::STATUS_RUNNING;
                $this->masterWorker();
                break;
            case 'stop':
                $masterPid = file_get_contents(self::$_pidFile);
                if ($masterPid && posix_kill($masterPid, 0)) {
                    posix_kill($masterPid, SIGINT);
                    $this->echoLog("发送SIGINT中断信号了");
                    $timeout = 5;
                    $stopTime = time();
                    while (1) {
                        $masterPidAlive = $masterPid && posix_kill($masterPid, 0) && $masterPid != posix_getpid();
                        if ($masterPidAlive) {
                            if (time() - $stopTime > $timeout) {
                                $this->echoLog("server stop failure");
                                break;
                            }
                            sleep(1);
                            continue;
                        }
                        $this->echoLog("server stop successfully");
                        exit(0);
                    }
                } else {
                    exit("server not running");
                }
                break;
            default:
                $text = "php ".pathinfo(self::$_startFile)['filename'].".php - [start|stop]";
                exit($text);
        }
        restore_error_handler();
    }

    public function daemon() {
        umask(000);
        $pid = pcntl_fork();
        if ($pid > 0) {
            exit(0);
        }
        if (-1 == posix_setsid()) {
            exit("sid set failed");
        }
        $pid = pcntl_fork();
        if ($pid > 0) {
            exit(0);
        }
    }

    public function resetFd() {
//        fclose(STDIN);
//        fclose(STDOUT);
//        fclose(STDERR);
//        fopen("/dev/null", 'a');
//        fopen("/dev/null", 'a');
//        fopen("/dev/null", 'a');
    }

    public function checkSetting($option): bool
    {
        if (isset($this->_settings[$option]) && $this->_settings[$option]) {
            return true;
        }
        return false;
    }

    public function reloadWorker() {
        $this->echoLog("重启进程");
        $pid = pcntl_fork();
        if (0 == $pid) {
            $this->worker();
        } else {
            $this->_pidMap[$pid] = $pid;
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
            if (self::$_status != self::STATUS_SHUTDOWN) {
                $this->reloadWorker();
            }
            if (empty($this->_pidMap)) {
                break;
            }
        }
        $this->eventCallBak("masterShutdown", [$this]);
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
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $this->echoLog("<file:%s>---<line:%s>---<info:%s>", $errfile, $errline, $errstr);
        });
    }

    public function forkWorker() {
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

    public function forkTask() {
        $taskNum = 1;
        if (isset($this->_settings['taskNum'])) {
            $taskNum = $this->_settings['taskNum'];
        }
        for ($i = 0; $i < $taskNum; $i++) {
            $pid = pcntl_fork();
            if (0 == $pid) {
                $this->tasker($i + 1);
            } else {
                $this->_pidMap[$pid] = $pid;
            }
        }
    }

    public function worker() {
        if (self::$_status == self::STATUS_RUNNING) {
            //说明是重新启动的worker进程
            $this->eventCallBak("workerReload", [$this]);
        } else {
            self::$_status = self::STATUS_RUNNING;
        }
        $this->listen();
        //注意这里,从初始化方法调位至这里,创建不同的epoll对象,否则会出错的
        if (DIRECTORY_SEPARATOR == '/') {
            static::$_eventLoop = new Epoll();
        } else {
            static::$_eventLoop = new Select();
        }
        cli_set_process_title("te/worker");

        pcntl_signal(SIGINT, SIG_IGN, false);
        pcntl_signal(SIGTERM, SIG_IGN, false);
        pcntl_signal(SIGQUIT, SIG_IGN, false);

        static::$_eventLoop->add(SIGINT, Event::EVENT_SIGNAL, [$this, "signalHandler"]);
        static::$_eventLoop->add(SIGQUIT, Event::EVENT_SIGNAL, [$this, "signalHandler"]);
        static::$_eventLoop->add(SIGTERM, Event::EVENT_SIGNAL, [$this, "signalHandler"]);

        self::$_eventLoop->add($this->_socket, Event::READ, [$this, "accept"]);
//        self::$_eventLoop->add(1, Event::EVENT_TIMER, [$this, "checkHeartTime"]);
        $this->eventCallBak("workerStart", [$this]);
        $this->eventLoop();
        $this->eventCallBak("workerStop", [$this]);
        exit(0);
    }

    public function tasker($i) {
        self::$_status = self::STATUS_RUNNING;
        $this->echoLog("task process <pid:%d> start", posix_getpid());
        cli_set_process_title("te/task");
        $unix_server_socket_file = $this->_settings['unix_server_socket_file'].$i;
        if (file_exists($unix_server_socket_file)) {
            unlink($unix_server_socket_file);
        }
        $this->unix_socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($this->unix_socket, $unix_server_socket_file);
        $stream = socket_export_stream($this->unix_socket);
        stream_set_blocking($stream, 0);

        if (DIRECTORY_SEPARATOR == "/" ) {
            static::$_eventLoop = new Epoll();
        } else {
            static::$_eventLoop = new Select();
        }

        pcntl_signal(SIGINT, SIG_IGN, false);
        pcntl_signal(SIGTERM, SIG_IGN, false);
        pcntl_signal(SIGQUIT, SIG_IGN, false);

        static::$_eventLoop->add(SIGINT, Event::EVENT_SIGNAL, [$this, "taskSignalHandler"]);
        static::$_eventLoop->add(SIGQUIT, Event::EVENT_SIGNAL, [$this, "taskSignalHandler"]);
        static::$_eventLoop->add(SIGTERM, Event::EVENT_SIGNAL, [$this, "taskSignalHandler"]);
        self::$_eventLoop->add($stream, Event::READ, [$this, "acceptUdpClient"]);
        $this->eventLoop();
        $this->echoLog("task process <pid:%d> exit", posix_getpid());
        exit(0);
    }

    public function task($func) {
        $taskNum = $this->_settings['taskNum'] ?? 1;
        $unix_client_file = $this->_settings['unix_client_socket_file'];
        $unix_server_file = $this->_settings['unix_server_socket_file'].mt_rand(1, $taskNum);
        if (file_exists($unix_client_file)) {
            unlink($unix_client_file);
        }
        $wrapper = serialize(new SerializableClosure($func));
        $len = strlen($wrapper);
        $data = pack('N', $len + 4).$wrapper;

        $unix_socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($unix_socket, $unix_client_file);
        socket_sendto($unix_socket, $data, $len + 4, 0, $unix_server_file);
        socket_close($unix_socket);
    }

    public function checkHeartTime() {
        $this->echoLog("执行心跳检测了");
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

    public function acceptUdpClient() {
        set_error_handler(function (){});
        $len = socket_recvfrom($this->unix_socket, $wrapper, 65535, 0, $unixClientFile);
        restore_error_handler();
        if ($len > 0) {
            $this->echoLog("task process recv %d bytes", $len);
            $udpConnection = new UdpConnection($this->unix_socket, $len, $wrapper, $unixClientFile);
        }
    }

    public function echoLog($format, ...$data) {
        if ($this->checkSetting('daemon')) {
            $info = sprintf($format, ...$data);
            $msg = "[pid:".posix_getpid()."]-[".date("Y-m-d H:i:s", time())."]"."-[info:".$info."]\r\n";
            file_put_contents(self::$_logFile, $msg, FILE_APPEND);
        } else {
            $format .= PHP_EOL;
            fprintf(STDOUT, $format, ...$data);
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
