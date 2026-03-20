<?php
namespace Pendasi\Rest\Middleware;

use Pendasi\Rest\Core\HttpException;

class RateLimitMiddleware implements MiddlewareInterface {

    public function handle(): void {

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = $ip ?: 'unknown';
        $key = sha1($ip);
        $file = rtrim(sys_get_temp_dir(), "/\\") . DIRECTORY_SEPARATOR . "rate_$key";

        $data = ["count" => 0, "time" => time()];

        // Lock fichier pour éviter les races en charge
        $fh = @fopen($file, 'c+');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                $raw = stream_get_contents($fh);
                $decoded = $raw ? json_decode($raw, true) : null;
                if (is_array($decoded) && isset($decoded['count'], $decoded['time'])) {
                    $data = $decoded;
                }

                // Réinitialiser si fenêtre dépassée
                if (time() - (int)$data['time'] > 60) {
                    $data = ["count" => 0, "time" => time()];
                }

                $data['count']++;

                if ((int)$data['count'] > 100) {
                    flock($fh, LOCK_UN);
                    fclose($fh);
                    throw new HttpException(429, [
                        "success" => false,
                        "message" => "Too many requests"
                    ]);
                }

                // Persist
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, json_encode($data));
                fflush($fh);
                flock($fh, LOCK_UN);
                fclose($fh);
                return;
            }

            fclose($fh);
        }

        // Fallback sans lock
        if (time() - (int)$data['time'] > 60) {
            $data = ["count" => 0, "time" => time()];
        }
        $data['count']++;

        if ((int)$data['count'] > 100) {
            throw new HttpException(429, [
                "success" => false,
                "message" => "Too many requests"
            ]);
        }

        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}