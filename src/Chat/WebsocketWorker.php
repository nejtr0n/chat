<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 19.05.16
 * Time: 17:51
 */

namespace Chat;


abstract class WebsocketWorker
{
    protected $pid;

    public function __construct($master) {
        $this->master = $master;
        $this->pid = posix_getpid();
    }

    public function start()
    {
        while (true)
        {
            ;
        }
    }

}