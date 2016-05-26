<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 19.05.16
 * Time: 17:51
 */

namespace Chat;
use MongoClient;

abstract class WebsocketWorker extends Socket
{
    protected $pid;
    protected $master = NULL;
    protected $mongodb = NULL;

    public function __construct($master) {
        $this->master = $master;
        $this->pid = posix_getpid();
        $this->mongodb = new MongoClient("mongodb://chat:chat@localhost:27017/chat");
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
                $mess = json_decode($this->decode($this->readBuffer($this->master))["payload"]);
                var_dump($mess);
                //var_dump(json_decode($mess)->mess);
            }
        }
    }

    // https://github.com/nekudo/php-websocket/blob/master/server/lib/WebSocket/Connection.php
    private function encode($payload, $type = 'text', $masked = true)
    {
        $frameHead = array();
        $frame = '';
        $payloadLength = strlen($payload);

        switch($type)
        {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for($i = 0; $i < 8; $i++) {
                $frameHead[$i+2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0 (close connection if frame too big)
            if($frameHead[2] > 127) {
                $this->close(1004);
                return false;
            }
        }
        elseif($payloadLength > 125)
        {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        }
        else
        {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }
        // convert frame-head to string:
        foreach(array_keys($frameHead) as $i)
        {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if($masked === true)
        {
            // generate a random mask:
            $mask = array();
            for($i = 0; $i < 4; $i++)
            {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);
        // append payload to frame:
        $framePayload = array();
        for($i = 0; $i < $payloadLength; $i++)
        {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }
        return $frame;
    }

    private function decode($data)
    {
        $mask = $payloadLength = $unmaskedPayload = '';
        $decodedData = array();

        // estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = ($secondByteBinary[0] == '1') ? true : false;
        $payloadLength = ord($data[1]) & 127;

        // close connection if unmasked frame is received:
        if($isMasked === false) {
            //$this->close(1002);
        }
        $opcodes = array(
            1 => 'text', // text frame:
            2 => 'binary', // binary frame:
            8 => 'close', // connection close frame:
            9 => 'ping', // ping frame:
            10 => 'pong', // pong frame:
        );
        if (!array_key_exists($opcode, $opcodes)) {
            $this->close(1003);
        }
        $decodedData['type'] = $opcodes[$opcode];

        if($payloadLength === 126) {
            $mask = substr($data, 4, 4);
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } elseif($payloadLength === 127) {
            $mask = substr($data, 10, 4);
            $payloadOffset = 14;
            $tmp = '';
            for($i = 0; $i < 8; $i++) {
                $tmp .= sprintf('%08b', ord($data[$i+2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
            unset($tmp);
        } else{
            $mask = substr($data, 2, 4);
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        /**
         * We have to check for large frames here. socket_recv cuts at 1024 bytes
         * so if websocket-frame is > 1024 bytes we have to wait until whole
         * data is transferd.
         */
        if(strlen($data) < $dataLength) {
            return false;
        }

        if($isMasked === true) {
            for($i = $payloadOffset; $i < $dataLength; $i++) {
                $j = $i - $payloadOffset;
                if(isset($data[$i])) {
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
                }
            }
            $decodedData['payload'] = $unmaskedPayload;
        } else {
            $payloadOffset = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset);
        }

        return $decodedData;
    }

}