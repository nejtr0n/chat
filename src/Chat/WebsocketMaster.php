<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 19.05.16
 * Time: 12:27
 */

namespace Chat;


class WebsocketMaster extends Socket
{
    private $config = array (
        'host' => '127.0.0.1',
        'port' => 8080,
        'limit' => 5,
        'origin' => '', // Origin to check in request
        'ssl'  => false,
    );

    protected $workers = array();
    protected $context = null;

    private $server = NULL;
    private $ips = array();
    private $handshakes = array();



    protected $clients = array();


    public function __construct(array $config = array()) {
        if (!empty($config)) {
            $this->config = $config;
        }
        $this->context = stream_context_create();
        if($this->config['ssl'] == true) {
            $this->applySSLContext();
        }
        $this->server = stream_socket_server(
            ($this->config['ssl']) ? "tls://" : "tcp://" . "{$this->config['host']}:{$this->config['port']}",
            $errorNumber,
            $errorString,
            STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,
            $this->context
        );

        if (!$this->server) {
            die("Error: stream_socket_server: $errorString ($errorNumber)\r\n");
        }
    }
    private function applySSLContext()
    {
        $pem_file = './server.pem';
        $pem_passphrase = 'shinywss';

        // Generate PEM file
        if(!file_exists($pem_file)) {
            $dn = array(
                "countryName" => "RU",
                "stateOrProvinceName" => "none",
                "localityName" => "none",
                "organizationName" => "none",
                "organizationalUnitName" => "none",
                "commonName" => "foo.lh",
                "emailAddress" => "baz@foo.lh"
            );
            $privkey = openssl_pkey_new();
            $cert    = openssl_csr_new($dn, $privkey);
            $cert    = openssl_csr_sign($cert, null, $privkey, 365);
            $pem = array();
            openssl_x509_export($cert, $pem[0]);
            openssl_pkey_export($privkey, $pem[1], $pem_passphrase);
            $pem = implode($pem);
            file_put_contents($pem_file, $pem);
        }

        // apply ssl context:
        stream_context_set_option($this->context, 'ssl', 'local_cert', $pem_file);
        stream_context_set_option($this->context, 'ssl', 'passphrase', $pem_passphrase);
        stream_context_set_option($this->context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($this->context, 'ssl', 'verify_peer', false);
    }

    public function start(array $workers) {
        $this->workers = $workers;
        fwrite(STDOUT,"Ready for chat...\r\n");
        $connects = array();
        while (true)
        {
            $read = array_merge($this->workers, $connects);
            $read[] = $this->server;
            $write = $except = null;
            if (false === stream_select($read, $write, $except, null)) {//ожидаем сокеты доступные для чтения (без таймаута)
                break;
            }
            if ($read) {
                // Пришли данные от с сокета
                foreach ($read as $read_connection) {
                    //есть новое соединение
                    if ($read_connection == $this->server) {
                        unset($read[ array_search($this->server, $read)]);
                        if ($conn = stream_socket_accept($this->server, -1)) {
                            $connects[] = $conn;
                            $address = explode(':', stream_socket_get_name($conn, true));
                            if(!array_key_exists($address[0], $this->ips)) {
                                $this->ips[$address[0]] = 0;
                            }
                            if (!empty($this->ips[$address[0]]) && $this->ips[$address[0]] > $this->config['limit']) {//блокируем более пяти соединий с одного ip
                                fwrite(STDOUT,"Error: too many connections from IP: {$address[0]}\r\n");
                                fclose($conn);
                            } else {
                                $this->ips[$address[0]]++;
                                // Если не было рукопожатия, то выполняем его
                                if (FALSE === array_search(intval($conn) , $this->handshakes)) {
                                    $this->handshake($conn, $this->readBuffer($conn));
                                    $this->handshakes[] = intval($conn);
                                    continue;
                                }
                            }
                        }
                    }
                    // Пришли данные от уже подсоединённых клиентов
                    if (in_array($read_connection, $connects)) {
                        $this->sendToWorker($read_connection);
                    }
                }
            }
        }
    }

    private function sendToWorker($conn)
    {
        // Балансируем нагрузку по воркерам
        $worker = current($this->workers);
        $next = next($this->workers);
        if ($next === false) {
            reset($this->workers);
        }
        fwrite($worker, $this->readBuffer($conn));
    }
    private function handshake($resource, $data)
    {
        $lines = explode("\r\n",  $data);
        // check for valid http-header:
        if(!preg_match('/\AGET (\S+) HTTP\/1.1\z/', $lines[0], $matches)) {
            fwrite(STDOUT, 'Invalid request: ' . $lines[0]);
            $this->sendHttpResponse(400);
            stream_socket_shutdown($resource, STREAM_SHUT_RDWR);
            return false;
        }
        // generate headers array:
        $headers = array();
        foreach($lines as $line) {
            $line = chop($line);
            if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        // check for supported websocket version:
        if(!isset($headers['Sec-WebSocket-Version']) || $headers['Sec-WebSocket-Version'] < 6) {
            fwrite(STDOUT, 'Unsupported websocket version.');
            $this->sendHttpResponse(501);
            stream_socket_shutdown($resource, STREAM_SHUT_RDWR);
            return false;
        }
        // check origin:
        if (!empty($this->config['origin'])) {
            $origin = (isset($headers['Sec-WebSocket-Origin'])) ? $headers['Sec-WebSocket-Origin'] : false;
            $origin = (isset($headers['Origin'])) ? $headers['Origin'] : $origin;
            if ($origin === false) {
                fwrite(STDOUT, 'No origin provided.');
                $this->sendHttpResponse(401);
                stream_socket_shutdown($resource, STREAM_SHUT_RDWR);
                return false;
            }

            if (empty($origin)) {
                fwrite(STDOUT, 'Empty origin provided.');
                $this->sendHttpResponse(401);
                stream_socket_shutdown($resource, STREAM_SHUT_RDWR);
                return false;
            }

            if ($this->config['origin'] !== $origin) {
                fwrite(STDOUT, 'Invalid origin provided.');
                $this->sendHttpResponse(401);
                stream_socket_shutdown($resource, STREAM_SHUT_RDWR);
                return false;
            }
        }
        // do handyshake: (hybi-10)
        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response.= "Upgrade: websocket\r\n";
        $response.= "Connection: Upgrade\r\n";
        $response.= "Sec-WebSocket-Accept: " . $secAccept . "\r\n";
        if(isset($headers['Sec-WebSocket-Protocol']) && !empty($headers['Sec-WebSocket-Protocol']))
        {
            //$response.= "Sec-WebSocket-Protocol: " . substr($path, 1) . "\r\n";
        }
        $response.= "\r\n";
        if(false === ($this->writeBuffer($resource, $response))) {
            return false;
        }
        fwrite(STDOUT, "Handshake sent\r\n");
        return true;
    }

    protected function sendHttpResponse($httpStatusCode = 400)
    {
        $codes = array (
            400 => '400 Bad Request',
            401 => '401 Unauthorized',
            403 => '403 Forbidden',
            404 => '404 Not Found',
            501 => '501 Not Implemented',
        );
        if (!array_key_exists($httpStatusCode, $codes)) {
            return ;
        }
        $httpHeader = 'HTTP/1.1 ';
        $httpHeader .= $codes[$httpStatusCode];
        $httpHeader .= "\r\n";
        $this->server->writeBuffer($this->socket, $httpHeader);
    }
}