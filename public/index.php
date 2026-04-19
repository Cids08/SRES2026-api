<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Maintenance mode
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Autoload
require __DIR__.'/../vendor/autoload.php';

// Bootstrap app (Laravel 11 style)
$app = require_once __DIR__.'/../bootstrap/app.php';

// Handle request
$response = $app->handle(Request::capture());

$response->send();