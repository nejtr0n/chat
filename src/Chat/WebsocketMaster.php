<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 19.05.16
 * Time: 12:27
 */

namespace Chat;


class WebsocketMaster
{
    private $config = array();
    private $server = NULL;
    protected $workers = array();
    protected $clients = array();


    public function __construct(array $workers, $config) {
        $this->config = $config;
        $this->clients = $this->workers = $workers;
        $this->server = stream_socket_server("tcp://{$this->config['host']}:{$this->config['port']}", $errorNumber, $errorString);

        if (!$this->server) {
            die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
        }
    }

    public function start() {
        fwrite(STDOUT,"Ready for chat...\r\n");
        while (true)
        {
            $read = $this->clients;
            if (false === stream_select($read, $b, $c, null)) {//ожидаем сокеты доступные для чтения (без таймаута)
                break;
            }
            if ($read) {
                var_dump($read);
            }



        }
    }
}