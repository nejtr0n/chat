<?php
/**
 * Created by PhpStorm.
 * User: a6y
 * Date: 22.05.16
 * Time: 21:00
 */

namespace Chat;


abstract class Socket
{
    // method originally found in phpws project:
    final protected function readBuffer($resource)
    {
        $buffer = '';
        $buffsize = 8192;
        $metadata['unread_bytes'] = 0;
        do {
            if(feof($resource)) {
                return false;
            }
            $result = fread($resource, $buffsize);
            if($result === false || feof($resource)) {
                return false;
            }
            $buffer .= $result;
            $metadata = stream_get_meta_data($resource);
            $buffsize = ($metadata['unread_bytes'] > $buffsize) ? $buffsize : $metadata['unread_bytes'];
        } while($metadata['unread_bytes'] > 0);

        return $buffer;
    }

    // method originally found in phpws project:
    final protected function writeBuffer($resource, $string)
    {
        $stringLength = strlen($string);
        for($written = 0; $written < $stringLength; $written += $fwrite) {
            $fwrite = fwrite($resource, substr($string, $written));
            if($fwrite === false || $fwrite === 0) {
                return false;
            }
        }
        return $written;
    }
}