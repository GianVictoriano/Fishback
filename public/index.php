<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Tukuyin kung nasa maintenance mode ang application...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// I-register ang Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// I-bootstrap ang Laravel at i-handle ang request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
