<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/7
 * Time: 9:56
 */

use Socket\Ms\Client;

require_once "vendor/autoload.php";

ini_set("memory_limit", "2048M");
$clientNum = $argv[1];
$startTime = time();
$clients = [];

for ($i = 0; $i < $clientNum; $i++) {

    $clients[] = $client = new Client("tcp://127.0.0.1:9501");

    $client->on("connect", function (Client $client) {
        fprintf(STDOUT, "socket<%d> connect success!\r\n", (int)$client->_socket);
    });

    $client->on("receive", function (Client $client, $msg) {
//        fprintf(STDOUT, "recv from server %s\n", $msg);
    });

    $client->on("error", function (Client $client, $error_code, $error_message) {
        fprintf(STDOUT, "error_codee:%s,error_message:%s\n", $error_code, $error_message);
    });

    $client->on("close", function (Client $client) {
        fprintf(STDOUT, "服务器断开我的连接了\n");
    });

    $client->start();
}

while (1) {
    $now = time();
    $diffTime = $now - $startTime;
    $startTime = $now;
    if ($diffTime >= 1) {
        $sendNum = 0;
        $sendMsgNum = 0;
        foreach ($clients as $client) {
            /**@var Client $client * */
            $sendNum += $client->_sendNum;
            $sendMsgNum += $client->_sendMsgNum;
        }
        fprintf(STDOUT, "time:<%s>--<clientNum:%d>--<sendNum:%d>--<msgNum:%d>\r\n",
            $diffTime, $clientNum, $sendNum, $sendMsgNum);

        foreach ($clients as $client) {
            $client->_sendNum = 0;
            $client->_sendMsgNum = 0;
        }
    }

    for ($i = 0; $i < $clientNum; $i++) {
        $client = $clients[$i];
        for ($j = 0; $j < 5; $j++) {
            $client->send("hello server,i'm client ".time());
        }

        if (!$client->eventLoop()) {
            break;
        }
    }

}


