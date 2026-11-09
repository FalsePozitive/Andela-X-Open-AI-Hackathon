<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
date_default_timezone_set('UTC');
$configFile = getenv('PASSPORT_CONFIG') ?: dirname(__DIR__) . '/config/local.php';
if (!is_file($configFile)) {
    throw new RuntimeException('Create config/local.php from config/example.php before starting.');
}
$config = require $configFile;
if (!is_array($config) || !filter_var($config['base_url'] ?? '', FILTER_VALIDATE_URL)) {
    throw new RuntimeException('Invalid application configuration.');
}
return $config;
