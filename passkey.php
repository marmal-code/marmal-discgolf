<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
migrate_if_needed();
start_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function bin(string $b64): string { return (string)base64_decode($b64, true); }

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$a = $_GET['a'] ?? '';
$hdr = $_SERVER['HTTP_X_CSRF'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', (string)$hdr)) out(['error' => 'csrf'], 419);

try {
    $wa = webauthn();
    // registrace nového klíče – jen přihlášený (nebo právě dokončující instalaci)
    if ($a === 'reg_args' || $a === 'reg_verify') {
        $u = current_user(); if (!$u) out(['error' => 'auth'], 401);
        if ($a === 'reg_args') {
            $ex = db()->query('SELECT cred_id FROM passkeys WHERE user_id = ' . (int)$u['id'])->fetchAll(PDO::FETCH_COLUMN);
            $args = $wa->getCreateArgs((string)$u['id'], $u['username'], $u['username'], 120, 'preferred', 'required', null, array_map('bin', $ex));
            $_SESSION['pk_challenge'] = base64_encode($wa->getChallenge()->getBinaryString());
            out(['args' => $args]);
        }
        $d = $wa->processCreate(bin($in['clientDataJSON'] ?? ''), bin($in['attestationObject'] ?? ''), bin($_SESSION['pk_challenge'] ?? ''), false, true, false);
        unset($_SESSION['pk_challenge']);
        db()->prepare('INSERT INTO passkeys (user_id, cred_id, public_key, sign_count, name, created) VALUES (?,?,?,?,?,?)')
            ->execute([$u['id'], base64_encode($d->credentialId), $d->credentialPublicKey, (int)($d->signatureCounter ?? 0), mb_substr(trim((string)($in['name'] ?? 'Zařízení')), 0, 80) ?: 'Zařízení', now()]);
        out(['ok' => true]);
    }
    // přihlášení otiskem
    if ($a === 'login_args' || $a === 'unlock_args') {
        if (throttled()) out(['error' => 'throttled'], 429);
        $ids = db()->query('SELECT cred_id FROM passkeys')->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) out(['error' => 'none'], 404);
        $args = $wa->getGetArgs(array_map('bin', $ids), 120, true, true, true, true, true, true);
        $_SESSION['pk_challenge'] = base64_encode($wa->getChallenge()->getBinaryString());
        out(['args' => $args]);
    }
    if ($a === 'login_verify') {
        if (throttled()) out(['error' => 'throttled'], 429);
        $st = db()->prepare('SELECT * FROM passkeys WHERE cred_id = ?'); $st->execute([base64_encode(bin($in['id'] ?? ''))]);
        $k = $st->fetch();
        if (!$k) { failed_attempt(); out(['error' => 'unknown'], 403); }
        $wa->processGet(bin($in['clientDataJSON'] ?? ''), bin($in['authenticatorData'] ?? ''), bin($in['signature'] ?? ''), $k['public_key'], bin($_SESSION['pk_challenge'] ?? ''), (int)$k['sign_count'], false, true);
        db()->prepare('UPDATE passkeys SET sign_count = ?, last_used = ? WHERE id = ?')->execute([max((int)$wa->getSignatureCounter(), (int)$k['sign_count']), now(), $k['id']]);
        login_user((int)$k['user_id']);
        out(['ok' => true]);
    }
    // odemčení při spuštění aplikace (už přihlášený): ověří otisk, nepřihlašuje znovu
    if ($a === 'unlock_verify') {
        $u = current_user(); if (!$u) out(['error' => 'auth'], 401);
        if (throttled()) out(['error' => 'throttled'], 429);
        $st = db()->prepare('SELECT * FROM passkeys WHERE cred_id = ? AND user_id = ?'); $st->execute([base64_encode(bin($in['id'] ?? '')), $u['id']]);
        $k = $st->fetch(); if (!$k) { failed_attempt(); out(['error' => 'unknown'], 403); }
        $wa->processGet(bin($in['clientDataJSON'] ?? ''), bin($in['authenticatorData'] ?? ''), bin($in['signature'] ?? ''), $k['public_key'], bin($_SESSION['pk_challenge'] ?? ''), (int)$k['sign_count'], true, true);
        db()->prepare('UPDATE passkeys SET sign_count = ?, last_used = ? WHERE id = ?')->execute([max((int)$wa->getSignatureCounter(), (int)$k['sign_count']), now(), $k['id']]);
        $_SESSION['unlocked_at'] = time();
        out(['ok' => true]);
    }
    out(['error' => 'bad action'], 400);
} catch (Throwable $e) {
    if ($a === 'login_verify' || $a === 'unlock_verify') failed_attempt();
    out(['error' => 'webauthn', 'message' => $e->getMessage()], 400);
}
