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


    public function __construct(array $workers, array $config) {
        $this->config = $config;
        $this->workers = $workers;
        $this->server = stream_socket_server("tcp://{$this->config['host']}:{$this->config['port']}", $errorNumber, $errorString);

        if (!$this->server) {
            die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
        }
    }

    public function start() {
        fwrite(STDOUT,"Ready for chat...\r\n");
        $connects = array();
        while (true)
        {
            $read = $connects;
            $read[] = $this->server;
            $write = $except = null;
            if (false === stream_select($read, $write, $except, null)) {//ожидаем сокеты доступные для чтения (без таймаута)
                break;
            }
            if ($read) {
                // Пришли данные от клиента
                if (in_array($this->server, $read)) {//есть новое соединение
                    if ($conn = stream_socket_accept($this->server, -1))
                    {
                        //var_dump(stream_get_meta_data($conn));
                        $message = fread($conn, 1024);
                        //var_dump(stream_get_meta_data($conn));
                        //var_dump(fread($conn, 1024));
                        echo 'I have received that : '.$message;
                        //fputs ($conn, "OK\n");
                        //fclose ($conn);
                    }
                }
            }
        }
    }
}