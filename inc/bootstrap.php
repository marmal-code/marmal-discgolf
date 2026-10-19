<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Prague');
const ROOT = __DIR__ . '/..';
const APP_VERSION_FILE = ROOT . '/VERSION';

function app_version(): string { return is_file(APP_VERSION_FILE) ? trim((string)file_get_contents(APP_VERSION_FILE)) : 'dev'; }

function config(): array {
    static $c = null;
    if ($c === null) {
        $f = ROOT . '/config.php';
        if (!is_file($f)) { http_response_code(500); exit('Chybí config.php – zkopíruj config.sample.php a vyplň přístup k databázi.'); }
        $c = require $f;
    }
    return $c;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $d = config()['db'];
        $pdo = new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function is_https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'; }

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'secure' => is_https(),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_name('dgsess');
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
    session_start();
}

function csrf(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . h(csrf()) . '">'; }
function csrf_check(): void {
    start_session();
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) { http_response_code(419); exit('Vypršela platnost formuláře, obnov stránku.'); }
}

function setting(string $k, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) { $cache = []; foreach (db()->query('SELECT skey, sval FROM settings') as $r) $cache[$r['skey']] = $r['sval']; }
    return $cache[$k] ?? $default;
}
function set_setting(string $k, string $v): void {
    db()->prepare('INSERT INTO settings (skey, sval) VALUES (?, ?) ON DUPLICATE KEY UPDATE sval = VALUES(sval)')->execute([$k, $v]);
}

function flash(?string $msg = null, string $type = 'ok') {
    start_session();
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function redirect(string $to): never { header('Location: ' . $to); exit; }

/** Spustí čekající migrace (migrations/NNN_nazev.sql nebo .php). Volá se při startu a po aktualizaci. */
function migrate(): array {
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) PRIMARY KEY, applied DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $done = $pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    $files = glob(ROOT . '/migrations/*.{sql,php}', GLOB_BRACE) ?: [];
    sort($files);
    $ran = [];
    foreach ($files as $f) {
        $name = basename($f);
        if (in_array($name, $done, true)) continue;
        $lock = $pdo->query("SELECT GET_LOCK('dg_migrate', 20)")->fetchColumn();
        if (!$lock) throw new RuntimeException('Migrace právě běží jinde.');
        try {
            if (str_ends_with($name, '.sql')) {
                $pdo->exec((string)file_get_contents($f));
            } else {
                $fn = require $f;
                $fn($pdo);
            }
            $pdo->prepare('INSERT INTO migrations (name, applied) VALUES (?, ?)')->execute([$name, now()]);
            $ran[] = $name;
        } finally { $pdo->query("SELECT RELEASE_LOCK('dg_migrate')"); }
    }
    return $ran;
}

function migrate_if_needed(): void {
    $files = glob(ROOT . '/migrations/*.{sql,php}', GLOB_BRACE) ?: [];
    try {
        $n = (int)db()->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
    } catch (Throwable $e) { $n = -1; }
    if ($n !== count($files)) migrate();
}
