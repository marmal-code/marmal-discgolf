<?php
declare(strict_types=1);

const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32_encode(string $bin): string {
    $bits = ''; foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = ''; foreach (str_split($bits, 5) as $chunk) $out .= B32[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}
function base32_decode(string $s): string {
    $s = strtoupper(preg_replace('/[^A-Z2-7]/', '', $s)); $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos(B32, $c)), 5, '0', STR_PAD_LEFT);
    $out = ''; foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
    return $out;
}
function totp_new_secret(): string { return base32_encode(random_bytes(20)); }
function totp_code(string $secret, ?int $t = null): string {
    $counter = intdiv($t ?? time(), 30);
    $hash = hash_hmac('sha1', pack('N2', 0, $counter), base32_decode($secret), true);
    $o = ord($hash[19]) & 0xf;
    $n = ((ord($hash[$o]) & 0x7f) << 24) | ((ord($hash[$o + 1]) & 0xff) << 16) | ((ord($hash[$o + 2]) & 0xff) << 8) | (ord($hash[$o + 3]) & 0xff);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
function totp_verify(string $secret, string $code): bool {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    foreach ([0, -30, 30] as $d) if (hash_equals(totp_code($secret, time() + $d), $code)) return true;
    return false;
}
function totp_uri(string $secret, string $user): string {
    return 'otpauth://totp/' . rawurlencode('Discgolf:' . $user) . '?secret=' . $secret . '&issuer=Discgolf&algorithm=SHA1&digits=6&period=30';
}
