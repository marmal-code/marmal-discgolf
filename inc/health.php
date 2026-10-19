<?php
declare(strict_types=1);
// Propojení s aplikací Moje Zdraví (zdravi.marmal.cz): stažení tréninků (jen čtení), automatické odškrtnutí plánu,
// příprava před turnajem. Odpověď endpointu api-export.php je v tabulce health_sessions.
require_once __DIR__ . '/weather.php';   // http_json()
require_once __DIR__ . '/training.php';

function health_conf(): ?array {
    $u = trim((string)setting('health_url', '')); $t = trim((string)setting('health_token', ''));
    return ($u !== '' && $t !== '') ? ['url' => $u, 'token' => $t] : null;
}

/** Stáhne tréninky za posledních $days dní, uloží je a odškrtne odpovídající položky plánu. */
function health_sync(int $days = 150): array {
    $c = health_conf(); if (!$c) return ['ok' => false, 'msg' => 'Propojení není nastavené (Nastavení → Moje Zdraví).'];
    set_setting('health_last', now());
    $to = date('Y-m-d'); $from = date('Y-m-d', strtotime("-$days day"));
    $j = http_json($c['url'] . (str_contains($c['url'], '?') ? '&' : '?') . 'token=' . rawurlencode($c['token']) . '&from=' . $from . '&to=' . $to);
    if (!$j || empty($j['ok'])) { set_setting('health_err', 'Moje Zdraví neodpovědělo (adresa nebo klíč?).'); return ['ok' => false, 'msg' => 'Moje Zdraví neodpovědělo – zkontroluj adresu a klíč.']; }
    set_setting('health_err', '');
    $up = db()->prepare('INSERT INTO health_sessions (src, kind, d, title, variant, minutes, score, metric, unit, better, feeling, knee, sets_done, volume_kg, synced)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE d = VALUES(d), title = VALUES(title), variant = VALUES(variant), minutes = VALUES(minutes), score = VALUES(score),
        metric = VALUES(metric), unit = VALUES(unit), better = VALUES(better), feeling = VALUES(feeling), knee = VALUES(knee), sets_done = VALUES(sets_done), volume_kg = VALUES(volume_kg), synced = VALUES(synced)');
    $n = 0; $now = now();
    foreach (($j['gym'] ?? []) as $g) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($g['d'] ?? ''))) continue;
        $up->execute(['g' . (int)$g['id'], 'gym', $g['d'], mb_substr((string)$g['title'], 0, 120), null, (int)$g['minutes'], null, null, null, null,
            $g['feeling'] ?? null, $g['knee'] ?? null, (int)($g['sets'] ?? 0), (int)($g['volume_kg'] ?? 0), $now]); $n++;
    }
    foreach (($j['drills'] ?? []) as $r) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($r['d'] ?? ''))) continue;
        $up->execute(['d' . (int)$r['id'], 'drill', $r['d'], mb_substr((string)$r['title'], 0, 120), mb_substr((string)($r['variant_name'] ?? ''), 0, 120) ?: null, (int)$r['minutes'], (float)$r['score'],
            mb_substr((string)($r['metric'] ?? ''), 0, 80), mb_substr((string)($r['unit'] ?? ''), 0, 12), ($r['better'] ?? 'up') === 'down' ? 'down' : 'up', null, null, null, null, $now]); $n++;
    }
    $marked = health_automark();
    return ['ok' => true, 'msg' => "Staženo $n tréninků" . ($marked ? ", automaticky splněno v plánu: $marked" : '') . '.', 'n' => $n, 'marked' => $marked];
}

/** Jednou za hodinu tiše synchronizuje (chyba stránku nezpomalí: pokus se zapisuje i při neúspěchu). */
function health_sync_maybe(): void {
    if (!health_conf()) return;
    $last = (string)setting('health_last', '');
    if ($last !== '' && strtotime($last) > time() - 3600) return;
    try { health_sync(); } catch (Throwable $e) { /* nechceme shodit stránku */ }
}

/** Posilovna → položka plánu typu „gym“, putting cvičení → „putting“. Jen když ten den ještě není záznam; ruční volba má přednost. */
function health_automark(): int {
    if (!tr_plan()) return 0;
    $marked = 0;
    $rows = db()->query('SELECT * FROM health_sessions WHERE marked = 0 AND d >= "' . date('Y-m-d', strtotime('-60 day')) . '" ORDER BY d, id')->fetchAll();
    $seen = [];
    foreach ($rows as $s) {
        $kind = $s['kind'] === 'gym' ? 'gym' : 'putting'; $key = $s['d'] . $kind;
        $label = $s['title'] . ($s['score'] !== null ? ' ' . rtrim(rtrim(number_format((float)$s['score'], 2, ',', ''), '0'), ',') . ' ' . $s['unit'] : '') . ($s['minutes'] ? ', ' . (int)$s['minutes'] . ' min' : '');
        foreach (tr_day($s['d']) as $it) {
            if ($it['kind'] !== $kind) continue;
            if ($it['status'] || isset($seen[$key])) { db()->prepare('UPDATE health_sessions SET marked = 1 WHERE id = ?')->execute([(int)$s['id']]); continue; }   // jedna položka na druh a den
            db()->prepare('INSERT IGNORE INTO training_log (tdate, item_id, status, note, src, created) VALUES (?,?,"done",?,"zdravi",?)')
                ->execute([$s['d'], (int)$it['id'], mb_substr('Z Moje Zdraví: ' . $label, 0, 300), now()]);
            db()->prepare('UPDATE health_sessions SET marked = 1 WHERE id = ?')->execute([(int)$s['id']]);
            $seen[$key] = true; $marked++;
        }
    }
    return $marked;
}

/** Příprava v $weeks týdnech před datem: počet tréninků z Moje Zdraví, plnění plánu, pocit. */
function health_prep(string $date, int $weeks = 4): array {
    $to = date('Y-m-d', strtotime($date . ' -1 day')); $from = date('Y-m-d', strtotime($date . ' -' . ($weeks * 7) . ' day'));
    $q = db()->prepare('SELECT kind, COUNT(*) n, COALESCE(SUM(minutes),0) min, AVG(feeling) feel, AVG(knee) knee FROM health_sessions WHERE d BETWEEN ? AND ? GROUP BY kind');
    $q->execute([$from, $to]); $by = []; foreach ($q->fetchAll() as $r) $by[$r['kind']] = $r;
    $has = (int)db()->query('SELECT COUNT(*) FROM health_sessions')->fetchColumn() > 0;
    $sum = tr_plan() ? tr_summary($from, $to) : ['planned' => 0, 'done' => 0, 'skip' => 0, 'none' => 0];
    $firstS = db()->query('SELECT MIN(d) FROM health_sessions')->fetchColumn();
    return ['from' => $from, 'to' => $to, 'has' => $has && $firstS && $firstS <= $to,
        'gym' => (int)($by['gym']['n'] ?? 0), 'drill' => (int)($by['drill']['n'] ?? 0), 'drill_min' => (int)($by['drill']['min'] ?? 0),
        'planned' => $sum['planned'], 'done' => $sum['done'], 'skip' => $sum['skip'],
        'pct' => $sum['planned'] ? (int)round($sum['done'] / $sum['planned'] * 100) : null];
}

/** Součet úspěšnosti putting z kol turnaje (C1X, C2): [c1x%, c2%] nebo null. */
function event_putting(int $eventId): array {
    $q = db()->prepare('SELECT SUM(c1x_h) a, SUM(c1x_n) b, SUM(c2_h) c, SUM(c2_n) d FROM rounds WHERE event_id = ?'); $q->execute([$eventId]); $r = $q->fetch();
    return [$r && $r['b'] > 0 ? round($r['a'] / $r['b'] * 100) : null, $r && $r['d'] > 0 ? round($r['c'] / $r['d'] * 100) : null];
}
