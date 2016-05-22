<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 19.05.16
 * Time: 12:27
 */

namespace Chat;


class WebsocketHandler extends WebsocketWorker
{

    public function __construct($master)
    {
        parent::__construct($master);

    }


}