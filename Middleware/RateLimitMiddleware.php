<?php
namespace Pendasi\Rest\Middleware;
class RateLimitMiddleware {

    public function handle() {

        $ip = $_SERVER['REMOTE_ADDR'];
        $file = sys_get_temp_dir()."/rate_$ip";

        $data = file_exists($file)
            ? json_decode(file_get_contents($file), true)
            : ["count"=>0,"time"=>time()];

        if (time() - $data['time'] > 60) {
            $data = ["count"=>0,"time"=>time()];
        }

        $data['count']++;

        if ($data['count'] > 100) {
            http_response_code(429);
            exit(json_encode(["success"=>false,"message"=>"Too many requests"]));
        }

        file_put_contents($file, json_encode($data));
    }
}