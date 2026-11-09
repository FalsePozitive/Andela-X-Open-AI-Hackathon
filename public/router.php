<?php
declare(strict_types=1);
// Development router: serve only the two explicitly public compiled assets.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array($path, ['/assets/app.css', '/assets/app.js', '/assets/okwethu-mark.svg', '/assets/okwethu-logo.svg', '/assets/okwethu-logo.png'], true)) {
    return false;
}
require __DIR__ . '/index.php';
