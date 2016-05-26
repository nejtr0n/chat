<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 25.05.16
 * Time: 17:13
 */
require_once 'vendor/autoload.php';

$chat = new Chat\WebsocketServer();
$chat->start(array (
    'host' => '127.0.0.1',
    'port' => 8081,
    'limit' => 5,
    'origin' => '', // Origin to check in request
    'ssl'  => false,
));