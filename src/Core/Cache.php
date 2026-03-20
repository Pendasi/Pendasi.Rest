<?php
namespace Pendasi\Rest\Core;

class Cache {

    public static function remember($key, callable $callback, $ttl = 60) {

        $file = sys_get_temp_dir()."/cache_$key";

        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            return json_decode(file_get_contents($file), true);
        }

        $data = $callback();

        file_put_contents($file, json_encode($data));

        return $data;
    }
}