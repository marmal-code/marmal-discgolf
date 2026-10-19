<?php
declare(strict_types=1);
// Web Push (VAPID, bez payloadu): server pošle prázdný „budíček“, service worker zobrazí notifikaci.

function b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64u_dec(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/')); }

/** Vrátí [privátní PEM, veřejný klíč base64url]; při prvním volání vygeneruje a uloží do settings. */
function vapid_keys(): array {
    $priv = setting('vapid_private', ''); $pub = setting('vapid_public', '');
    if ($priv !== '' && $pub !== '') return [$priv, $pub];
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$k) throw new RuntimeException('OpenSSL nedokáže vytvořit EC klíč.');
    openssl_pkey_export($k, $priv);
    $d = openssl_pkey_get_details($k)['ec'];
    $pub = b64u("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT));
    set_setting('vapid_private', $priv); set_setting('vapid_public', $pub);
    return [$priv, $pub];
}

/** DER podpis ECDSA → raw R||S (64 B) pro JWT ES256. */
function der_to_raw(string $der): string {
    $o = 3; $rl = ord($der[$o]); $r = substr($der, $o + 1, $rl); $o += 1 + $rl + 1; $sl = ord($der[$o]); $s = substr($der, $o + 1, $sl);
    return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
}

function vapid_jwt(string $audience, string $privPem, ?int $exp = null): string {
    $head = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = b64u(json_encode(['aud' => $audience, 'exp' => $exp ?? time() + 12 * 3600, 'sub' => 'mailto:' . (setting('notify_email', '') ?: 'info@marmal.cz')]));
    openssl_sign("$head.$claims", $sig, $privPem, OPENSSL_ALGO_SHA256);
    return "$head.$claims." . b64u(der_to_raw($sig));
}

/** Pošle budíček na jedno zařízení. Vrací HTTP kód (0 = chyba spojení). */
function push_one(string $endpoint): int {
    [$priv, $pub] = vapid_keys();
    $p = parse_url($endpoint); $aud = $p['scheme'] . '://' . $p['host'];
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Authorization: vapid t=' . vapid_jwt($aud, $priv) . ', k=' . $pub, 'TTL: 86400', 'Urgency: high', 'Content-Length: 0']]);
    curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $code;
}

/** Pošle upozornění na všechna uložená zařízení; neplatná (404/410) smaže. Vrací počet doručených. */
function push_all(): int {
    $ok = 0;
    foreach (db()->query('SELECT id, endpoint FROM push_subs')->fetchAll() as $s) {
        $c = push_one($s['endpoint']);
        if ($c >= 200 && $c < 300) { $ok++; db()->prepare('UPDATE push_subs SET last_ok = ? WHERE id = ?')->execute([now(), $s['id']]); }
        elseif ($c === 404 || $c === 410) db()->prepare('DELETE FROM push_subs WHERE id = ?')->execute([$s['id']]);
    }
    return $ok;
}
