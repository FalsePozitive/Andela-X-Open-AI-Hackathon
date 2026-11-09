<?php
declare(strict_types=1);
// Creates configuration only for the isolated loopback development database.
// Never included in the production release.
require dirname(__DIR__) . '/vendor/autoload.php';
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
$config = require $root . '/config/example.php';
$config['environment'] = 'development';
$config['base_url'] = 'http://127.0.0.1:8087';
$config['dsn'] = 'mysql:host=127.0.0.1;port=3317;dbname=story_passport_demo;charset=utf8mb4';
$config['db_user'] = 'root';
$config['db_password'] = '';
$config['storage_path'] = $root . '/storage';
$config['allow_demo_reset'] = true;
$password = bin2hex(random_bytes(12));
$config['steward_password_hash'] = password_hash($password,PASSWORD_DEFAULT);
if (!is_dir($root . '/.tools')) {mkdir($root . '/.tools',0700,true);}
if (!is_file($root . '/config/local.php')) {
    file_put_contents($root . '/config/local.php',"<?php\nreturn " . var_export($config,true) . ";\n");
    file_put_contents($root . '/.tools/preview-login.txt',"steward\n" . $password . "\n");
}
$config['dsn'] = 'mysql:host=127.0.0.1;port=3317;dbname=story_passport_test;charset=utf8mb4';
$config['storage_path'] = $root . '/tests/.runtime/storage';
$config['steward_password_hash'] = password_hash('Test-only-password-1907',PASSWORD_DEFAULT);
if (!is_dir($config['storage_path'])) {mkdir($config['storage_path'],0700,true);}
file_put_contents($root . '/config/test.php',"<?php\nreturn " . var_export($config,true) . ";\n");
Passport\Database::migrate(Passport\Database::connect($config));
echo "Local configurations prepared. Random preview login is in ignored .tools/preview-login.txt.\n";
