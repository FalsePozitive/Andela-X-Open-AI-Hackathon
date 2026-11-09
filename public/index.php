<?php
declare(strict_types=1);
try {
    $config = require dirname(__DIR__) . '/src/bootstrap.php';
    $app = new Passport\Web(new Passport\Registry(Passport\Database::connect($config), $config), $config);
    $app->run();
} catch (Throwable $e) {
    error_log('Passport startup: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Okwethu is being prepared. Please check back shortly.';
}
