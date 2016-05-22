<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 19.05.16
 * Time: 11:53
 */

namespace Chat;

/**
 * Class WebsocketServer
 * @package Chat
 */
class WebsocketServer
{
    private $workers = 2;

    public function __construct($workers = 2)
    {
        $this->workers = $workers;
    }

    public function start($config = array())
    {
        fwrite(STDOUT,"Chat server started...\r\n");
        list($pid, $master, $workers) = $this->spawnWorkers(); //создаём дочерние процессы
        if ($pid) {//мастер
            $WebsocketMaster = new WebsocketMaster($config); //мастер будет пересылать сообщения между воркерами
            $WebsocketMaster->start($workers);
        } else {//воркер
            $WebsocketHandler = new WebsocketHandler($master);
            $WebsocketHandler->start();
        }
    }

    protected function spawnWorkers() {
        $master = null;
        $workers = array();
        $i = 0;
        while ($i < $this->workers) {
            $i++;
            //создаём парные сокеты, через них будут связываться мастер и воркер
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork();//создаём форк
            if ($pid == -1) {
                die("Error: pcntl_fork\r\n");
            } elseif ($pid) { //мастер
                fclose($pair[0]);
                $workers[$pid] = $pair[1];//один из пары будет в мастере
            } else { //воркер
                fclose($pair[1]);
                $master = $pair[0];//второй в воркере
                break;
            }
        }

        return array($pid, $master, $workers);
    }
}