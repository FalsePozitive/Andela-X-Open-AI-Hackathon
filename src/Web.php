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
        return 'Demo · ' . $currency . ' ' . ($minor < 0 ? '-' : '') . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public function steward(): void
    {
        if (empty($this->registry->actor['id'])) {
            throw new Problem('UNAUTHORIZED', 'Please sign in to your steward account.', 401);
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
            unset($_SESSION['steward'],$_SESSION['accountId'],$_SESSION['authVersion']);
        }
        $_SESSION['lastActive'] = time();
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; media-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'];
        $api = str_starts_with($path, '/api/');
        try {
            if (!Database::run($this->registry->db,'SELECT version FROM migrations WHERE version=?',['002_community_tools.sql'])->fetchColumn()) {throw new Problem('UPGRADE_REQUIRED','This release needs its database update. Ask the site operator to import the community-tools upgrade SQL.',503);}
            $accounts=new Accounts($this->registry->db,$this->config);
            $this->registry->actor=$accounts->current()??[];
            $_SESSION['steward']=!empty($this->registry->actor['id']);
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
                if (!Rules::isPublic($story) || !$story['filePath']) {
                    throw new Problem('NOT_FOUND', 'Media not available.', 404);
                }
                $file = $story['mediaType'] === 'image' ? Watermark::image($story, $this->config) : Watermark::original($story, $this->config);
                $mime = $story['mediaType'] === 'image' ? 'image/png' : $story['mimeType'];
                $this->file($file, $mime, 'okwethu-' . $story['id'] . ($story['mediaType'] === 'image' ? '.png' : ''), isset($_GET['download']));
            }
            if ($path === '/') {
                $this->page('home', Catalog::search($this->registry->stories(),$_GET));
            } elseif ($path === '/about') {
                $this->page('about');
            } elseif ($path === '/faq') {
                $this->page('faq');
            } elseif ($path === '/login') {
                $this->page('login');
            } elseif (preg_match('~^/p/([A-Z2-9]{4}-[A-Z2-9]{4})$~', $path, $m)) {
                $raw=$this->registry->story($m[1]);
                $this->page('passport', ['story' => Rules::publicStory($raw),'credit'=>Rules::credit($raw,$this->config['base_url'])]);
            } elseif (preg_match('~^/r/([a-f0-9]{64})$~',$path,$m)) {
                $this->page('tracking',['request'=>(new RequestUpdates($this->registry->db,$this->config))->get($m[1]),'token'=>$m[1]]);
            } elseif (str_starts_with($path, '/dashboard') || $path === '/ledger') {
                $this->steward();
                if ($path === '/dashboard') {
                    $this->page('dashboard', Catalog::search($this->registry->stories(true),$_GET));
                } elseif ($path === '/dashboard/new') {
                    if (!$accounts->communities($this->registry->actor,true)) {throw new Problem('FORBIDDEN','Your role can review records, but cannot register stories.',403);}
                    $this->page('new');
                } elseif ($path === '/dashboard/requests') {
                    $this->page('requests', ['requests' => $this->registry->requests()]);
                } elseif ($path === '/dashboard/claims') {
                    $this->page('claims', ['claims' => $this->registry->claims()]);
                } elseif (preg_match('~^/dashboard/stories/([A-Z2-9]{4}-[A-Z2-9]{4})$~', $path, $m)) {
                    $this->page('manage', ['story' => $this->registry->privateStory($m[1]),'history'=>(new StoryChanges($this->registry))->history($m[1])]);
                } elseif ($path === '/dashboard/account') {
                    $this->page('account');
                } elseif ($path === '/dashboard/team') {
                    $this->page('team',$accounts->team($this->registry->actor));
                } elseif ($path === '/dashboard/notifications') {
                    $mail=new Mailbox($this->registry->db,$this->config);
                    $this->page('notifications',['jobs'=>$mail->pending(array_column($accounts->communities($this->registry->actor,true),'id')),'mailEnabled'=>$mail->enabled()]);
                } elseif (preg_match('~^/dashboard/stories/([^/]+)/history/([0-9]+)$~',$path,$m)) {
                    $this->page('revision',['snapshot'=>(new StoryChanges($this->registry))->version($m[1],(int)$m[2])]);
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
        if (!is_string($_POST['username']??null) || !is_string($_POST['password']??null)) {throw new Problem('LOGIN','The sign-in details were not accepted.',403);}
        $user=(new Accounts($this->registry->db,$this->config))->login($_POST['username'],$_POST['password']);
        $_SESSION['accountId']=$user['id'];$_SESSION['authVersion']=(int)$user['auth_version'];
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
        if ($method === 'GET' && preg_match('~^/api/stories/([^/]+)/(pdf|cover|download)$~', $path, $m)) {
            $story = $this->registry->story($m[1]);
            if ($m[2] === 'pdf') {
                $bytes = PassportPdf::render($story, $this->config);
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="okwethu-passport-' . $story['id'] . '.pdf"');
                header('Content-Length: ' . strlen($bytes));
                echo $bytes; exit;
            }
            if ($m[2] === 'cover') {
                $this->file(Watermark::cover($story, $this->config), 'image/png', 'okwethu-cover-' . $story['id'] . '.png', isset($_GET['download']));
            }
            if ($story['mediaType'] === 'image') {
                $this->file(Watermark::image($story, $this->config), 'image/png', 'okwethu-' . $story['id'] . '.png', true);
            }
            if ($story['mediaType'] === 'audio') {
                $this->file(Watermark::audioZip($story, $this->config), 'application/zip', 'okwethu-audio-' . $story['id'] . '.zip', true);
            }
            throw new Problem('NOT_FOUND', 'Use the passport’s video export button for a QR-marked video.', 404);
        }
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
        if ($method==='POST' && preg_match('~^/api/tracking/([a-f0-9]{64})$~',$path,$m)) {
            $updates=new RequestUpdates($this->registry->db,$this->config);
            $result=$updates->reply($m[1],$this->input());$updates->dispatch($updates->get($m[1])['id']);$this->json($result);
        }
        if ($method==='GET' && preg_match('~^/api/stories/([^/]+)/credit$~',$path,$m)) {
            $raw=$this->registry->story($m[1]);
            if (!Rules::isPublic($raw)) {throw new Problem('NOT_FOUND','Attribution is not public for this record.',404);}
            header('Content-Type: text/plain; charset=utf-8');header('Content-Disposition: attachment; filename="okwethu-credit-'.$raw['id'].'.txt"');
            echo Rules::credit($raw,$this->config['base_url']);exit;
        }
        $this->steward();
        $accounts=new Accounts($this->registry->db,$this->config);
        if ($method==='POST' && $path==='/api/account/password') {
            $accounts->changePassword($this->registry->actor,$this->input());$this->json(['saved'=>true]);
        }
        if ($method==='POST' && $path==='/api/team') {$this->json($accounts->manage($this->registry->actor,$this->input()));}
        if ($method==='POST' && preg_match('~^/api/notifications/([a-f0-9]{32})/retry$~',$path,$m)) {
            $job=Database::run($this->registry->db,'SELECT community_id FROM mail_outbox WHERE id=?',[$m[1]])->fetch();
            if (!$job) {throw new Problem('NOT_FOUND','Notification not found.',404);}
            $accounts->community($this->registry->actor,$job['community_id'],true);
            $mail=new Mailbox($this->registry->db,$this->config);
            if (!$mail->enabled()) {throw new Problem('EMAIL_DISABLED','Email delivery is disabled. Configure the mailbox first.',409);}
            if (!$mail->deliver($m[1])) {throw new Problem('EMAIL_FAILED','Delivery did not complete. Check the mailbox settings or retry limit.',409);}
            $this->json(['saved'=>true]);
        }
        if ($method==='POST' && preg_match('~^/api/requests/([^/]+)/link$~',$path,$m)) {
            $r=Database::run($this->registry->db,'SELECT story_id FROM permission_requests WHERE id=?',[$m[1]])->fetch();
            if (!$r) {throw new Problem('NOT_FOUND','Request not found.',404);}
            $this->registry->privateStory($r['story_id'],true);
            $this->json(['trackingUrl'=>(new RequestUpdates($this->registry->db,$this->config))->link($m[1])]);
        }
        if ($method==='PATCH' && preg_match('~^/api/stories/([^/]+)/edit$~',$path,$m)) {$this->json((new StoryChanges($this->registry))->update($m[1],$this->input()));}
        if ($method==='GET' && preg_match('~^/api/steward/stories/([^/]+)/history$~',$path,$m)) {$this->json((new StoryChanges($this->registry))->history($m[1]));}
        if ($method === 'POST'  && $path === '/api/stories') {
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
            $this->json($this->registry->privateStory($m[1]));
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

    private function file(string $path, string $mime, string $name, bool $download): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        if (session_status() === PHP_SESSION_ACTIVE) {session_write_close();}
        readfile($path); exit;
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
        $actor=$this->registry->actor??[];
        $signedIn = !empty($actor['id']);
        $communities=$signedIn ? (new Accounts($this->registry->db,$this->config))->communities($actor) : [];
        $adminCommunities=$signedIn ? (new Accounts($this->registry->db,$this->config))->adminCommunities($actor) : [];
        $canEdit=fn(string $id):bool=>$this->registry->canWriteStory($id);
        $writeCommunities=$signedIn ? (new Accounts($this->registry->db,$this->config))->communities($actor,true) : [];
        $canWrite=isset($data['story']) ? in_array($data['story']['communityId']??'',array_column($writeCommunities,'id'),true) : $writeCommunities!==[];
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . '/templates/layout.php';
    }
}
