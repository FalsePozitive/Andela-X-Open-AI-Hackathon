<?php
declare(strict_types=1);
return [
    'environment' => 'production',
    'base_url' => 'https://okwethu.wilmans.co.za',
    'dsn' => 'mysql:host=localhost;dbname=story_passport;charset=utf8mb4',
    'db_user' => 'story_app',
    'db_password' => '',
    'steward_name' => 'steward',
    // Generate with: php bin/console.php password
    'steward_password_hash' => '',
    'storage_path' => dirname(__DIR__) . '/storage',
    'allow_demo_reset' => false,
    // Enter the exact SMTP server from your Xneelo mailbox settings.
    // Keep disabled until credentials are entered; never commit local.php.
    'mail' => [
        'transport' => 'disabled', // 'smtp' to enable; 'spool' is local testing only
        'from' => 'steward@okwethu.wilmans.co.za',
        'host' => '',
        'port' => 465,
        'encryption' => 'smtps', // alternatively starttls on 587
        'username' => 'steward@okwethu.wilmans.co.za',
        'password' => '',
    ],
];
