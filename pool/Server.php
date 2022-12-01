<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/10/31
 * Time: 10:16
 */

namespace pool;

class Process {
    public $_procId;
    public $_msqId;
}

class Server {

    public $_procNUm;
    public $_process = [];
    public $_socketFd;
    public static $_idx;
    public $_unixFile = "unix_server";
    public $_keyFile = "Server.php";
    public static $_grun = true;


    public function __construct($process_num) {
        pcntl_signal(SIGINT, [$this, "signalHandler"]);
        $this->_procNUm = $process_num;
        $this->forkWorker();
        $this->listen();

        $exitPids = [];
        for ($i = 0; $i < $this->_procNUm; $i++) {
            $pid = pcntl_wait($status);
            $exitPids[] = $pid;
            if (count($exitPids) == $this->_procNUm) {
                break;
            }
        }

        foreach ($this->_process as $process) {
            /**@var Process $process */
            msg_remove_queue($process->_msqId);
        }
        fprintf(STDOUT, "master process exit\r\n");
        unlink($this->_unixFile);
        exit(0);
    }

    public function signalHandler($signo) {
        fprintf(STDOUT, "pid=%d recv signal:%d\r\n", getmypid(), $signo);
        self::$_grun = false;
    }

    public function forkWorker() {
        $processObj = new Process();
        for ($i = 0; $i < $this->_procNUm; $i++) {
            $key = ftok($this->_keyFile, "$i");
            $msqId = msg_get_queue($key);

            $process = clone $processObj;
            $process->_msqId = $msqId;
            $this->_process[$i] = $process;
            self::$_idx = $i;

            $this->_process[$i]->_procId = pcntl_fork();
            if (0 == $this->_process[$i]->_procId) {
                $this->worker();
            } else if ($this->_process[$i]->_procId < 0) {
                fprintf(STDOUT, "child process create failed,strerrno=%s\r\n", posix_strerror(posix_get_last_error()));
                exit(0);
            } else {
                continue;
            }
        }
    }

    public function listen() {
        fprintf(STDOUT, "master process start pid=%d\r\n", getmypid());
        $this->_socketFd = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!is_resource($this->_socketFd)) {
            fprintf(STDOUT, "socket create failed,strerrno=%s\r\n", socket_strerror(socket_last_error()));
        }
        if (posix_access($this->_unixFile, POSIX_F_OK)) {
            unlink($this->_unixFile);
        }
        socket_bind($this->_socketFd, $this->_unixFile);
        socket_listen($this->_socketFd, 10);
        $this->eventLoop();
    }

    public function eventLoop() {
        $readFds = [$this->_socketFd];
        $writeFds = [];
        $expFds = [];

        while (self::$_grun) {
            pcntl_signal_dispatch();
            set_error_handler(function () {});
            $ret = socket_select($readFds, $writeFds, $expFds, NULL, NULL);
            restore_error_handler();

            if ($ret === false) {
                break;
            } else if ($ret == 0) {
                continue;
            } else {
                foreach ($readFds as $fd) {
                    if ($fd == $this->_socketFd) {
                        $this->accept();
                    }
                }
            }
        }
        socket_close($this->_socketFd);

        /**@var Process $process */
        foreach ($this->_process as $process) {
            msg_send($process->_msqId, 1, "quit");
        }
    }

    public function accept() {
        $connId = socket_accept($this->_socketFd);
        if (is_resource($connId)) {
            $data = socket_read($connId, 1024);
            if ($data) {
                socket_write($connId, "ok\r\n", 4);
                $this->selectWorker($data);
            }
            socket_close($connId);
        }
    }

    public function worker() {
        fprintf(STDOUT, "child process start pid=%d\r\n", getmypid());
        /**@var Process $process */
        $process = $this->_process[self::$_idx];
        while (self::$_grun) {
            if (msg_receive($process->_msqId, 1, $received_message_type, 1024, $message)) {
                fprintf(STDOUT, "child process pid=%d recv data:%s\r\n", getmypid(), $message);
            }
            if (strncasecmp($message, "quit", 4) == 0) {
                break;
            }
        }
        fprintf(STDOUT, "child process pid=%d exit\r\n", getmypid());
        exit(0);
    }

    public function selectWorker($data) {
        /**@var Process $process */
        $process = $this->_process[self::$_idx++ % $this->_procNUm];
        $msqId = $process->_msqId;
        if (msg_send($msqId, 1, $data)) {
            fprintf(STDOUT, "master process send data to child process pid=%d\r\n", $process->_procId);
        }
    }
}

new Server(2);