<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/totp.php';

function user_count(): int { return (int)db()->query('SELECT COUNT(*) FROM users WHERE totp_confirmed = 1')->fetchColumn(); }

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['uid']) || empty($_SESSION['auth'])) return null;
    $st = db()->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([$_SESSION['uid']]);
    return $st->fetch() ?: null;
}
function require_login(): array {
    $u = current_user();
    if (!$u) redirect(user_count() === 0 ? 'setup.php' : 'login.php');
    return $u;
}
function login_user(int $uid): void {
    start_session(); session_regenerate_id(true);
    $_SESSION = ['uid' => $uid, 'auth' => true, 'csrf' => bin2hex(random_bytes(24))];
}
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '?'), 0, 64); }
function throttled(): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts > ?');
    $st->execute([client_ip(), date('Y-m-d H:i:s', time() - 900)]);
    return (int)$st->fetchColumn() >= 8;
}
function failed_attempt(): void {
    db()->prepare('INSERT INTO login_attempts (ip, ts) VALUES (?, ?)')->execute([client_ip(), now()]);
    db()->prepare('DELETE FROM login_attempts WHERE ts < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
}
function gen_recovery_codes(int $uid): array {
    $plain = []; $hashes = [];
    for ($i = 0; $i < 8; $i++) { $c = strtoupper(bin2hex(random_bytes(4))); $c = substr($c, 0, 4) . '-' . substr($c, 4); $plain[] = $c; $hashes[] = password_hash($c, PASSWORD_DEFAULT); }
    db()->prepare('UPDATE users SET recovery_codes = ? WHERE id = ?')->execute([json_encode($hashes), $uid]);
    return $plain;
}
function use_recovery_code(array $user, string $code): bool {
    $code = strtoupper(trim($code)); $hashes = json_decode((string)$user['recovery_codes'], true) ?: [];
    foreach ($hashes as $i => $h) if (password_verify($code, $h)) {
        unset($hashes[$i]);
        db()->prepare('UPDATE users SET recovery_codes = ? WHERE id = ?')->execute([json_encode(array_values($hashes)), $user['id']]);
        return true;
    }
    return false;
}
function webauthn(): \lbuchs\WebAuthn\WebAuthn {
    require_once ROOT . '/lib/WebAuthn/WebAuthn.php';
    foreach (['WebAuthnException', 'Binary/ByteBuffer', 'CBOR/CborDecoder', 'Attestation/AuthenticatorData', 'Attestation/AttestationObject',
              'Attestation/Format/FormatBase', 'Attestation/Format/None', 'Attestation/Format/Packed', 'Attestation/Format/Apple',
              'Attestation/Format/AndroidKey', 'Attestation/Format/AndroidSafetyNet', 'Attestation/Format/Tpm', 'Attestation/Format/U2f'] as $f)
        require_once ROOT . '/lib/WebAuthn/' . $f . '.php';
    $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return new \lbuchs\WebAuthn\WebAuthn('Discgolf', $host, ['none', 'packed', 'apple', 'android-key', 'android-safetynet', 'tpm', 'fido-u2f']);
}
