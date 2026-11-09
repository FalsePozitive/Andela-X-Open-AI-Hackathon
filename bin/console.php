<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/vendor/autoload.php';
use Passport\Database;
use Passport\Demo;
use Passport\Registry;

$command = $argv[1] ?? 'help';
try {
    if ($command === 'password') {
        fwrite(STDERR, "Enter the new steward password (input is not printed by this tool; your terminal may echo): ");
        $password = trim((string) fgets(STDIN));
        if (strlen($password) < 12) {
            throw new RuntimeException('Use at least 12 characters.');
        }
        echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
        exit;
    }
    if ($command === 'lint') {
        $failed = false;
        foreach (['src', 'templates', 'bin', 'public', 'tests', 'config', 'resources'] as $folder) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/' . $folder, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (str_contains(str_replace('\\','/',$file->getPathname()),'/tests/.runtime/')) {continue;}
                if ($file->getExtension() === 'php') {
                    passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $code);
                    $failed = $failed || $code !== 0;
                }
            }
        }
        exit($failed ? 1 : 0);
    }
    if ($command === 'help') {
        echo "Commands: preflight, migrate, seed, reset-demo --confirm=RESET-DEMO, password, lint\n";
        exit;
    }
    $config = require dirname(__DIR__) . '/src/bootstrap.php';
    if ($command === 'preflight') {
        foreach (['pdo_mysql', 'fileinfo', 'gd', 'mbstring', 'session'] as $extension) {
            if (!extension_loaded($extension)) {
                throw new RuntimeException('Missing extension: ' . $extension);
            }
        }
        if (!function_exists('imagewebp') || !(gd_info()['WebP Support'] ?? false)) {
            throw new RuntimeException('GD must support WebP image re-encoding.');
        }
        if ($config['environment'] === 'production' && !str_starts_with($config['base_url'], 'https://')) {
            throw new RuntimeException('Production requires an HTTPS base URL.');
        }
        $public = realpath(dirname(__DIR__) . '/public');
        $storage = realpath($config['storage_path']);
        if (!$storage || !$public || str_starts_with(strtolower($storage), strtolower($public)) || !is_writable($storage)) {
            throw new RuntimeException('Storage must be writable and outside the public directory.');
        }
        if (!password_get_info($config['steward_password_hash'])['algo']) {
            throw new RuntimeException('Set a steward password hash.');
        }
        Database::connect($config)->query('SELECT 1');
        echo "Preflight passed: PHP extensions, configuration, private storage and database. Check Apache rewrites and HTTPS from a browser separately.\n";
        exit;
    }
    $db = Database::connect($config);
    if ($command === 'migrate') {
        Database::migrate($db);
        echo "Migrations applied.\n";
    } elseif ($command === 'seed') {
        echo json_encode(Demo::seed(new Registry($db, $config), $config), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    } elseif ($command === 'reset-demo') {
        if (!$config['allow_demo_reset'] || !in_array('--confirm=RESET-DEMO', $argv, true) || !preg_match('/dbname=[a-zA-Z0-9_]*_demo(?:;|$)/', $config['dsn'])) {
            throw new RuntimeException('Reset requires allow_demo_reset=true, a database name ending in _demo, and --confirm=RESET-DEMO.');
        }
        // Explicitly guarded destructive operation, restricted to this demo schema.
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['mail_outbox','request_events','request_tokens','story_revisions','ledger_entries','claims','permission_requests','consents','split_shares','licenses','contributors','stories','creators','login_guard'] as $table) {
            $db->exec('TRUNCATE TABLE ' . $table);
        }
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
        $db->exec('INSERT INTO login_guard(id) VALUES(1)');
        echo json_encode(Demo::seed(new Registry($db, $config), $config), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    } else {
        throw new RuntimeException('Unknown command.');
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
