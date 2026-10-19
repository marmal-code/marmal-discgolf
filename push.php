<?php
declare(strict_types=1);
// Správa push zařízení (jen přihlášený, CSRF přes hlavičku X-CSRF).
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/push.php';
migrate_if_needed();
require_login();
header('Content-Type: application/json; charset=utf-8');
function out(array $a, int $c = 200): never { http_response_code($c); echo json_encode($a); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') { out(['key' => vapid_keys()[1]]); }
csrf_check();
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$a = (string)($in['a'] ?? '');
if ($a === 'subscribe') {
    $ep = (string)($in['endpoint'] ?? '');
    if (!preg_match('#^https://#', $ep) || strlen($ep) > 600) out(['ok' => false, 'error' => 'Neplatný endpoint.'], 400);
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 80) ?: 'Zařízení';
    db()->prepare('INSERT INTO push_subs (user_id, endpoint, endpoint_hash, name, created) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE name = VALUES(name)')
        ->execute([(int)require_login()['id'], $ep, sha1($ep), $name, now()]);
    out(['ok' => true]);
}
if ($a === 'unsubscribe') { db()->prepare('DELETE FROM push_subs WHERE endpoint_hash = ?')->execute([sha1((string)($in['endpoint'] ?? ''))]); out(['ok' => true]); }
if ($a === 'remove') { db()->prepare('DELETE FROM push_subs WHERE id = ?')->execute([(int)($in['id'] ?? 0)]); out(['ok' => true]); }
if ($a === 'test') { $n = push_all(); out(['ok' => $n > 0, 'sent' => $n, 'error' => $n ? null : 'Nepodařilo se doručit na žádné zařízení.']); }
out(['ok' => false, 'error' => 'Neznámá akce.'], 400);
