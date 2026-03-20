<?php
namespace Pendasi\Rest\Middleware;

interface MiddlewareInterface {
    public function handle(): void;
}