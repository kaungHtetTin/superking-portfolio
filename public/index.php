<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store'); // CSRF tokens and inquiry data must not be cached.

$router = new \Mini\Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch();
