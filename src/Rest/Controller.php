<?php
namespace Pendasi\Rest\Rest;

class Controller {
    protected function json(array $data){
        header("Content-Type: application/json");
        echo json_encode($data);
        exit;
    }
}