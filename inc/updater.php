<?php
declare(strict_types=1);

function gh_request(string $url, string $accept = 'application/vnd.github+json', ?string $saveTo = null): array {
    $cfg = config()['github'] ?? [];
    $token = (string)($cfg['token'] ?? '');
    if ($token === '' || $token === 'DOPLNIT') throw new RuntimeException('V config.php chybí GitHub token (sekce github → token).');
    $ch = curl_init($url);
    $fh = $saveTo ? fopen($saveTo, 'wb') : null;
    $opt = [CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: ' . $accept, 'User-Agent: discgolf-updater', 'X-GitHub-Api-Version: 2022-11-28']];
    if ($fh) $opt[CURLOPT_FILE] = $fh; else $opt[CURLOPT_RETURNTRANSFER] = true;
    curl_setopt_array($ch, $opt);
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
    curl_close($ch); if ($fh) fclose($fh);
    if ($body === false) throw new RuntimeException('Spojení s GitHubem selhalo: ' . $err);
    if ($code >= 400) throw new RuntimeException("GitHub vrátil chybu $code" . ($code === 404 ? ' (špatný repozitář nebo token bez přístupu)' : ($code === 401 ? ' (token je neplatný)' : '')));
    return [$code, is_string($body) ? $body : ''];
}

function remote_sha(): string {
    $c = config()['github']; [, $b] = gh_request("https://api.github.com/repos/{$c['repo']}/commits/" . rawurlencode($c['branch']), 'application/vnd.github.sha');
    return trim($b);
}

function apply_zip(string $zip): array {
    $log = [];
    $z = new ZipArchive();
    if ($z->open($zip) !== true) throw new RuntimeException('Stažený balík nejde otevřít.');
    $root = null; $n = 0; $skip = ['config.php', '.gitignore'];
    for ($i = 0; $i < $z->numFiles; $i++) {
        $name = $z->getNameIndex($i);
        if ($root === null) $root = explode('/', $name)[0] . '/';
        if (!str_starts_with($name, $root)) continue;
        $rel = substr($name, strlen($root));
        if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/')) continue;
        if (in_array($rel, $skip, true) || str_starts_with($rel, 'uploads/') || str_starts_with($rel, '.git')) continue;
        $dest = ROOT . '/' . $rel;
        if (str_ends_with($name, '/')) { if (!is_dir($dest)) mkdir($dest, 0755, true); continue; }
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
        $data = $z->getFromIndex($i);
        if ($data === false) throw new RuntimeException("Nelze přečíst $rel");
        $tmp = $dest . '.new'; file_put_contents($tmp, $data); rename($tmp, $dest); $n++;
    }
    $z->close();
    $r = $root ? rtrim($root, '/') : '';
    $sha = $r !== '' && str_contains($r, '-') ? substr($r, strrpos($r, '-') + 1) : '';
    $log[] = "Nahráno souborů: $n";
    if ($sha !== '') { file_put_contents(APP_VERSION_FILE, $sha); $log[] = 'Verze: ' . $sha; }
    if (function_exists('opcache_reset')) @opcache_reset();
    $ran = migrate();
    $log[] = $ran ? 'Databáze aktualizována: ' . implode(', ', $ran) : 'Databáze je aktuální.';
    set_setting('last_update', now());
    return ['ok' => true, 'log' => $log];
}

function run_update(): array {
    $c = config()['github']; $zip = tempnam(sys_get_temp_dir(), 'dgz');
    try {
        gh_request("https://api.github.com/repos/{$c['repo']}/zipball/" . rawurlencode($c['branch']), 'application/vnd.github+json', $zip);
        $res = apply_zip($zip);
        array_unshift($res['log'], 'Staženo z ' . $c['repo'] . ' (' . $c['branch'] . ').');
        return $res;
    } finally { @unlink($zip); }
}
