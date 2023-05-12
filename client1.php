<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023/5/11
 * Time: 11:35
 */

require_once "vendor/autoload.php";

$client = new \Socket\Ms\Client("ws://127.0.0.1:9501/");

$client->on("open", function (\Socket\Ms\Client $client) {
    echo "客户端和服务器握手成功\r\n";
//    $client->send("hello");
});

$client->on("message", function (\Socket\Ms\Client $client, $data) {
    echo "recv from server $data\r\n";
    $client->send("hellox");
});

$client->on("close", function (\Socket\Ms\Client $client) {
    echo "连接关闭\r\n";
});

$client->start();
$client->loop();

