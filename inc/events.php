<?php
declare(strict_types=1);

const EVENT_TODO_DEFAULT = ['Zaregistrovat se / zaplatit startovné', 'Ubytování a doprava', 'Zkontrolovat počasí den předem', 'Vybrat disky do bagu', 'Voda, jídlo, převlečení', 'Rozcvička a tee time'];

function event_get(int $id): ?array { $q = db()->prepare('SELECT * FROM events WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null; }

function slugify(string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s; $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $s)); return trim(substr($s, 0, 30), '-') ?: 'turnaj';
}

/** Přepočítá celkové skóre a par turnaje ze zapsaných kol. */
function event_recalc(int $id): void {
    $r = db()->prepare('SELECT COUNT(*) n, COALESCE(SUM(score),0) s, COALESCE(SUM(throws - score),0) p FROM rounds WHERE event_id = ?'); $r->execute([$id]); $x = $r->fetch();
    if ((int)$x['n'] > 0) {
        $perRound = (int)round((int)$x['p'] / (int)$x['n']);
        db()->prepare('UPDATE events SET total = ?, par = ? WHERE id = ?')->execute([(int)$x['s'], $perRound, $id]);
    }
}

function event_default_todos(int $id): void {
    $i = db()->prepare('INSERT INTO event_todo (event_id, text, sort) VALUES (?,?,?)');
    foreach (EVENT_TODO_DEFAULT as $k => $t) $i->execute([$id, $t, $k]);
}

/** Disky do bagu na turnaj: výchozí = aktuálně V bagu. */
function event_prefill_bag(int $id): void {
    db()->prepare('DELETE FROM event_discs WHERE event_id = ?')->execute([$id]);
    db()->prepare('INSERT INTO event_discs (event_id, disc_id) SELECT ?, id FROM discs WHERE in_bag = 1 AND is_sold = 0 AND is_lost = 0')->execute([$id]);
}

function fmt_date(?string $d): string { return $d ? date('j. n. Y', strtotime($d)) : ''; }

function weather_line(array $r): string {
    if ($r['w_temp'] === null && $r['w_cond'] === null) return '';
    $p = [];
    if ($r['w_temp'] !== null) $p[] = rtrim(rtrim(number_format((float)$r['w_temp'], 1, ',', ''), '0'), ',') . ' °C';
    if ($r['w_wind'] !== null) $p[] = 'vítr ' . rtrim(rtrim(number_format((float)$r['w_wind'], 1, ',', ''), '0'), ',') . ($r['w_gust'] !== null ? '/' . rtrim(rtrim(number_format((float)$r['w_gust'], 1, ',', ''), '0'), ',') : '') . ' m/s';
    if ($r['w_rain'] !== null && (float)$r['w_rain'] > 0) $p[] = rtrim(rtrim(number_format((float)$r['w_rain'], 1, ',', ''), '0'), ',') . ' mm';
    if ($r['w_cond']) $p[] = $r['w_cond'];
    return implode(' · ', $p);
}

function event_unique_slug(int $year, string $base): string {
    $slug = $base = slugify($base); $n = 2;
    $chk = db()->prepare('SELECT COUNT(*) FROM events WHERE year = ? AND slug = ?');
    while (true) { $chk->execute([$year, $slug]); if (!(int)$chk->fetchColumn()) return $slug; $slug = $base . '-' . $n++; }
}
