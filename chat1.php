<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 19.05.16
 * Time: 11:51
 */
require_once 'vendor/autoload.php';

$chat = new Chat\WebsocketServer();
$chat->start();
