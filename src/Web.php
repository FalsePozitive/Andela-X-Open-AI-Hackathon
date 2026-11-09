<?php
declare(strict_types=1);
namespace Passport;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

final class Web
{
    public function __construct(private readonly Registry $registry, private readonly array $config) {}

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function money(int $minor, string $currency): string
    {
        $absolute = abs($minor);
        return 'MOCK ' . $currency . ' ' . ($minor < 0 ? '-' : '') . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public function steward(): void
    {
        if (empty($_SESSION['steward'])) {
            throw new Problem('UNAUTHORIZED', 'Please sign in as the demo steward.', 401);
        }
    }

    public static function httpsRedirect(array $config, array $server): ?string
    {
        if ($config['environment'] !== 'production') {
            return null;
        }
        $secure = in_array(strtolower((string) ($server['HTTPS'] ?? '')), ['on', '1'], true)
            || (int) ($server['SERVER_PORT'] ?? 0) === 443;
        if ($secure) {
            return null;
        }
        $base = rtrim($config['base_url'], '/');
        if (!str_starts_with($base, 'https://')) {
            throw new \RuntimeException('Production requires an HTTPS base URL.');
        }
        $uri = $server['REQUEST_URI'] ?? '/';
        if (!is_string($uri) || !str_starts_with($uri, '/') || preg_match('/[\r\n]/', $uri)) {
            $uri = '/';
        }
        // Never trust Host or arbitrary forwarded headers for the redirect.
        return $base . $uri;
    }

    public function run(): void
    {
        $redirect = self::httpsRedirect($this->config, $_SERVER);
        if ($redirect !== null) {
            header('Cache-Control: no-store');
            header('Location: ' . $redirect, true, 308);
            return;
        }
        if ($this->config['environment'] === 'production') {
            header('Strict-Transport-Security: max-age=31536000');
        }
        session_name('passport_session');
        session_set_cookie_params(['httponly' => true, 'secure' => $this->config['environment'] === 'production', 'samesite' => 'Lax', 'path' => '/']);
        session_start();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        if (isset($_SESSION['lastActive']) && time() - $_SESSION['lastActive'] > 3600) {
            unset($_SESSION['steward']);
        }
        $_SESSION['lastActive'] = time();
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; media-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'];
        $api = str_starts_with($path, '/api/');
        try {
            if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 11 * 1024 * 1024) {
                throw new Problem('UPLOAD_TOO_LARGE', 'The request exceeds the upload limit.', 413);
            }
            if (in_array($method, ['POST', 'PATCH'], true)) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
                if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
                    throw new Problem('CSRF', 'Your form expired. Refresh the page and try again.', 403);
                }
            }
            if ($path === '/api/session' && $method === 'GET') {
                $this->json(['csrf' => $_SESSION['csrf'], 'steward' => !empty($_SESSION['steward'])]);
            }
            if ($path === '/login' && $method === 'POST') {
                $this->login();
                $this->redirect('/dashboard');
            }
            if ($path === '/logout' && $method === 'POST') {
                $_SESSION = [];
                session_regenerate_id(true);
                $this->redirect('/');
            }
            if ($api) {
                $this->api($method, $path);
                throw new Problem('NOT_FOUND', 'Endpoint not found.', 404);
            }
            if ($method !== 'GET') {
                throw new Problem('METHOD', 'This method is not supported.', 405);
            }
            if (preg_match('~^/media/([A-Z2-9]{4}-[A-Z2-9]{4})$~', $path, $m)) {
                $story = $this->registry->story($m[1]);
                if ($story['sensitivity'] === 'RESTRICTED' || !$story['filePath']) {
                    throw new Problem('NOT_FOUND', 'Media not available.', 404);
                }
                header('Content-Type: ' . $story['mimeType']);
                $file = $this->config['storage_path'] . '/uploads/' . $story['filePath'];
                if (!is_file($file)) {
                    throw new Problem('NOT_FOUND', 'Media not available.', 404);
                }
                header('Content-Length: ' . filesize($file));
                session_write_close();
                readfile($file);
                exit;
            }
            if ($path === '/') {
                $this->page('home', ['stories' => $this->registry->stories()]);
            } elseif ($path === '/login') {
                $this->page('login');
            } elseif (preg_match('~^/p/([A-Z2-9]{4}-[A-Z2-9]{4})$~', $path, $m)) {
                $this->page('passport', ['story' => Rules::publicStory($this->registry->story($m[1]))]);
            } elseif (str_starts_with($path, '/dashboard') || $path === '/ledger') {
                $this->steward();
                if ($path === '/dashboard') {
                    $this->page('dashboard', ['stories' => $this->registry->stories(true)]);
                } elseif ($path === '/dashboard/new') {
                    $this->page('new');
                } elseif ($path === '/dashboard/requests') {
                    $this->page('requests', ['requests' => $this->registry->requests()]);
                } elseif ($path === '/dashboard/claims') {
                    $this->page('claims', ['claims' => $this->registry->claims()]);
                } elseif (preg_match('~^/dashboard/stories/([A-Z2-9]{4}-[A-Z2-9]{4})$~', $path, $m)) {
                    $this->page('manage', ['story' => $this->registry->story($m[1])]);
                } elseif ($path === '/ledger') {
                    $this->page('ledger', ['ledger' => $this->registry->ledger(isset($_GET['storyId']) && is_string($_GET['storyId']) ? $_GET['storyId'] : null)]);
                } else {
                    throw new Problem('NOT_FOUND', 'Page not found.', 404);
                }
            } else {
                throw new Problem('NOT_FOUND', 'Page not found.', 404);
            }
        } catch (Problem $e) {
            http_response_code($e->status);
            if ($api) {
                $this->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'fields' => $e->fields]], $e->status);
            }
            if ($e->status === 401) {
                $this->redirect('/login');
            }
            $this->page('error', ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('Passport failure: ' . get_class($e) . ': ' . $e->getMessage());
            http_response_code(500);
            if ($api) {
                $this->json(['error' => ['code' => 'SERVER', 'message' => 'Something went wrong. Please try again.']], 500);
            }
            $this->page('error', ['message' => 'Something went wrong. Please try again.']);
        }
    }

    private function login(): void
    {
        $db = $this->registry->db;
        $db->beginTransaction();
        $guard = Database::run($db, 'SELECT *, blocked_until > UTC_TIMESTAMP() AS blocked FROM login_guard WHERE id=1 FOR UPDATE')->fetch();
        if ($guard && $guard['blocked']) {
            $db->rollBack();
            throw new Problem('RATE_LIMIT', 'Sign in is temporarily paused. Please try again in fifteen minutes.', 429);
        }
        $valid = is_string($_POST['password'] ?? null) && is_string($_POST['username'] ?? null)
            && $this->config['steward_password_hash'] !== ''
            && hash_equals($this->config['steward_name'], $_POST['username'])
            && password_verify($_POST['password'], $this->config['steward_password_hash']);
        if (!$valid) {
            Database::run($db, 'UPDATE login_guard SET failures=IF(blocked_until IS NOT NULL AND blocked_until <= UTC_TIMESTAMP(),1,failures+1),blocked_until=IF(failures>=5,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),NULL) WHERE id=1');
            $db->commit();
            throw new Problem('LOGIN', 'The sign-in details were not accepted.', 403);
        }
        Database::run($db, 'UPDATE login_guard SET failures=0,blocked_until=NULL WHERE id=1');
        $db->commit();
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['steward'] = true;
    }

    private function input(): array
    {
        $raw = $_POST['payload'] ?? file_get_contents('php://input');
        try {
            $data = json_decode(is_string($raw) ? $raw : '', true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new Problem('VALIDATION', 'Send a valid JSON object.');
        }
        if (!is_array($data) || array_is_list($data) && $data !== []) {
            throw new Problem('VALIDATION', 'Send a valid JSON object.');
        }
        return $data;
    }

    private function api(string $method, string $path): void
    {
        if ($method === 'GET' && preg_match('~^/api/stories/([^/]+)/qr$~', $path, $m)) {
            $this->registry->story($m[1]);
            $png = (new PngWriter())->write(QrCode::create(rtrim($this->config['base_url'], '/') . '/p/' . $m[1])->setSize(300)->setMargin(16));
            header('Content-Type: image/png');
            header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="passport-' . $m[1] . '.png"');
            echo $png->getString();
            exit;
        }
        if ($method === 'GET' && preg_match('~^/api/stories/([^/]+)$~', $path, $m)) {
            $this->json(Rules::publicStory($this->registry->story($m[1])));
        }
        if ($method === 'POST' && preg_match('~^/api/stories/([^/]+)/(requests|claims)$~', $path, $m)) {
            $data = $this->input();
            $this->json($m[2] === 'requests' ? $this->registry->request($m[1], $data) : $this->registry->claim($m[1], $data), 201);
        }
        $this->steward();
        if ($method === 'POST' && $path === '/api/stories') {
            $story = $this->registry->create($this->input(), $_FILES['media'] ?? null);
            $this->json(['id' => $story['id']], 201);
        }
        if ($method === 'GET' && $path === '/api/requests') {
            $this->json($this->registry->requests());
        }
        if ($method === 'GET' && $path === '/api/claims') {
            $this->json($this->registry->claims());
        }
        if ($method === 'GET' && $path === '/api/ledger') {
            $this->json($this->registry->ledger(isset($_GET['storyId']) && is_string($_GET['storyId']) ? $_GET['storyId'] : null));
        }
        if ($method === 'GET' && preg_match('~^/api/steward/stories/([^/]+)$~', $path, $m)) {
            $this->json($this->registry->story($m[1]));
        }
        if ($method === 'PATCH' && preg_match('~^/api/stories/([^/]+)/(consent|status)$~', $path, $m)) {
            if ($m[2] === 'consent') {
                $this->registry->consent($m[1], $this->input());
            } else {
                $this->registry->status($m[1], $this->input());
            }
            $this->json(['saved' => true]);
        }
        if ($method === 'PATCH' && preg_match('~^/api/requests/([^/]+)$~', $path, $m)) {
            $this->json($this->registry->decide($m[1], $this->input()));
        }
        if ($method === 'PATCH' && preg_match('~^/api/claims/([^/]+)$~', $path, $m)) {
            $this->registry->resolve($m[1], $this->input());
            $this->json(['saved' => true]);
        }
        if ($method === 'POST' && preg_match('~^/api/ledger/([^/]+)/adjustments$~', $path, $m)) {
            $this->json($this->registry->adjust($m[1], $this->input()), 201);
        }
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    private function page(string $view, array $data = []): void
    {
        $e = self::escape(...);
        $t = Copy::text(...);
        $money = self::money(...);
        $csrf = $_SESSION['csrf'];
        $signedIn = !empty($_SESSION['steward']);
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/templates/layout.php';
    }
}
