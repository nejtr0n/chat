<?php

/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 20.05.16
 * Time: 11:44
 */
class ChatTest extends PHPUnit_Framework_TestCase
{
    protected function send_message($ipServer,$portServer,$message)
    {
        $fp = stream_socket_client("tcp://$ipServer:$portServer", $errno, $errstr);
        if (!$fp) {
            echo "Error : $errno - $errstr<br />\n";
        } else {
            fwrite($fp,"$message\n");
            $response =  fread($fp, 1024);
            fclose($fp);
            return $response;
        }
    }

    /**
     * TestChat constructor.
     * @param $host
     * @param $port
     * @param $text
     * @dataProvider providerChat
     */
    public function testChat($host, $port, $text)
    {
        var_dump($this->send_message($host, $port, $text));
    }

    public function providerChat()
    {
        return array(
            array("127.0.0.1", "8080", "Test"),
        );
    }
}
