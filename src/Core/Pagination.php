<?php
namespace Pendasi\Rest\Helpers;
class Pagination{
    public static function offset($page,$limit){
        return ($page -1) * $limit;
    }
}