<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 19.05.16
 * Time: 17:51
 */

namespace Chat;


abstract class WebsocketWorker extends Socket
{
    protected $pid;

    public function __construct($master) {
        $this->master = $master;
        $this->pid = posix_getpid();
    }

    public function start()
    {
        while (true) {
            $read = array ($this->master);
            $write = $except = null;
            if (false === stream_select($read, $write, $except, null)) {//ожидаем сокеты доступные для чтения (без таймаута)
                break;
            }
            if ($read) {
                //echo $this->readBuffer($this->master);
            }
        }
    }

}