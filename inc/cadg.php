<?php
declare(strict_types=1);
// Český discgolf (ČADG, ceskydiscgolf.cz / iDG): veřejné JSON API – profil hráče a live scoring (výsledky, hody po jamkách).
// Statistiky se počítají z hodů stejnou logikou jako web ČADG (ověřeno na jeho číslech: C1R, C2R, C1/C1X/C2 putting, scramble, OB, birdie).
require_once __DIR__ . '/weather.php';   // http_json()
require_once __DIR__ . '/pdga.php';
require_once __DIR__ . '/events.php';

const CADG_API = 'https://ceskydiscgolf.cz/api/';
const CADG_PENALTY = ['ob', 'hazard', 'missed_mando', 'lost_disc', 'other_penalty'];

function cadg_uuid(string $s): ?string { return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $s) ? strtolower($s) : null; }

/** Z odkazu live scoringu vyčte turnaj / divizi / kolo. Vrací null, když to není odkaz ČADG. */
function cadg_parse_url(string $in): ?array {
    $in = trim($in); if (!preg_match('~ceskydiscgolf\.cz~i', $in) && !preg_match('~tournament=~', $in)) return null;
    parse_str((string)parse_url($in, PHP_URL_QUERY), $q);
    $t = cadg_uuid((string)($q['tournament'] ?? '')); if (!$t && preg_match('~tournaments?/([0-9a-f-]{36})~i', $in, $m)) $t = cadg_uuid($m[1]);
    return $t ? ['t' => $t, 'd' => cadg_uuid((string)($q['division'] ?? '')), 'r' => cadg_uuid((string)($q['round'] ?? ''))] : null;
}

function cadg_get(string $path): mixed { return http_json(CADG_API . ltrim($path, '/')); }

/** Čistá funkce (testovatelná): statistiky jednoho kola z hodů. $throws[jamka] = seznam hodů, $scores[jamka] = skóre, $pars[jamka] = par. */
function cadg_round_stats(array $throws, array $scores, array $pars): ?array {
    $holes = array_filter($throws, fn($t) => is_array($t) && count($t) > 0);
    if (!$holes) return null;
    $n = 0; $fwH = 0; $c1rH = 0; $c2rH = 0; $c1a = 0; $c1m = 0; $c1xm = 0; $c2a = 0; $c2m = 0; $scN = 0; $scH = 0; $obHoles = 0; $dist = []; $maxD = 0.0;
    $mid = ['circle1_0_3' => 1.5, 'circle1_3_7' => 5.0, 'circle1_7_10' => 8.5, 'circle2_10_13' => 11.5, 'circle2_13_17' => 15.0, 'circle2_17_20' => 18.5];
    foreach ($holes as $h => $th) {
        $th = array_values($th); $n++; $par = (int)($pars[$h] ?? 3); $first = (string)($th[0]['outcome'] ?? '');
        if ($first === 'fairway') $fwH++;
        // C1R / C2R: hod v regulaci (par − 2) skončil v C1 (C2) nebo v koši
        $reg = $par - 2;
        if ($reg >= 1) {
            if (count($th) < $reg) { if (in_array('basket', array_column($th, 'outcome'), true)) { $c1rH++; $c2rH++; } }
            else { $o = (string)($th[$reg - 1]['outcome'] ?? ''); if ($o === 'circle1' || $o === 'basket') { $c1rH++; $c2rH++; } elseif ($o === 'circle2') $c2rH++; }
        }
        // putting: hod po dopadu do C1 / C2
        foreach ($th as $i => $x) {
            $prev = $th[$i - 1]['outcome'] ?? null;
            if ($prev === 'circle1') { $c1a++; if ($x['outcome'] === 'basket') $c1m++; }
            if ($prev === 'circle2') { $c2a++; if ($x['outcome'] === 'basket') $c2m++; }
            if (($x['outcome'] ?? '') === 'basket' && isset($mid[(string)($x['putt_distance'] ?? '')])) {
                $pd = (string)$x['putt_distance']; $dist[] = $mid[$pd]; $maxD = max($maxD, $mid[$pd]);
                if ($pd === 'circle1_3_7' || $pd === 'circle1_7_10') $c1xm++;
            }
        }
        $bad = in_array($first, ['off_fairway', ...CADG_PENALTY], true);
        if ($bad) { $scN++; if (isset($scores[$h]) && (int)$scores[$h] <= $par) $scH++; }
        if (array_intersect(array_column($th, 'outcome'), CADG_PENALTY)) $obHoles++;
    }
    $o = ['fw_h' => $fwH, 'fw_n' => $n];
    $o += ['c1r_h' => $c1rH, 'c1r_n' => $n, 'c2r_h' => $c2rH, 'c2r_n' => $n];
    if ($scN > 0) $o += ['sc_h' => $scH, 'sc_n' => $scN];
    $c1xN = $c1xm + ($c1a - $c1m);                 // web ČADG počítá všechny nedané putty z C1 do C1X (u neúspěchu není známa vzdálenost)
    if ($c1xN > 0) $o += ['c1x_h' => $c1xm, 'c1x_n' => $c1xN];
    if ($c2a > 0) $o += ['c2_h' => $c2m, 'c2_n' => $c2a];
    if ($dist) { $o['avg_putt'] = round(array_sum($dist) / count($dist), 1); $o['long_putt'] = floor($maxD); }
    $o['ob'] = $obHoles;
    return $o;
}

/** Najde mě v leaderboardu: podle ČADG id (z profilu), jinak podle jména. */
function cadg_is_me(array $p, int $uid, string $name): bool {
    if ($uid > 0 && (int)($p['user_id'] ?? 0) === $uid) return true;
    if ($uid > 0) foreach ((array)($p['members'] ?? []) as $m) if ((int)($m['user_id'] ?? $m['id'] ?? 0) === $uid) return true;
    return $uid <= 0 && $name !== '' && pdga_ascii((string)($p['player_name'] ?? '')) === pdga_ascii($name);
}

/** Stáhne turnaj + moje výsledky. Vrací stejně strukturované pole jako pdga_map() (+ cadg_id). Při problému vyhodí RuntimeException. */
function cadg_import(string $url): array {
    $u = cadg_parse_url($url); if (!$u) throw new RuntimeException('Z odkazu nevyčtu turnaj. Vlož odkaz z Live scoringu na ceskydiscgolf.cz (stránka „Výsledky“ s vybraným turnajem).');
    if (!$u['d']) throw new RuntimeException('V odkazu chybí divize. Otevři na ceskydiscgolf.cz Live scoring → Výsledky, vyber turnaj a svou divizi a zkopíruj adresu z prohlížeče.');
    $t = $u['t']; $div = $u['d'];
    $ev = cadg_get("tournaments/$t/"); if (!is_array($ev) || empty($ev['name'])) throw new RuntimeException('Turnaj se nepodařilo načíst z ceskydiscgolf.cz (adresa nebo dostupnost webu).');
    $lb = cadg_get("scoring/tournaments/$t/live-scoring/leaderboard/?division=$div");
    if (!is_array($lb) || !$lb) throw new RuntimeException('V téhle divizi nejsou zatím žádné výsledky.');
    $uid = (int)setting('cadg_user_id', '0'); $name = (string)setting('player_name', '');
    $me = null; foreach ($lb as $p) if (cadg_is_me($p, $uid, $name)) { $me = $p; break; }
    if (!$me) throw new RuntimeException('V téhle divizi jsem tě nenašel' . ($uid ? ' (ČADG id ' . $uid . ')' : ' – nastav si ČADG profil v Nastavení') . '. Zkopíruj odkaz z divize, ve které jsi hrál.');
    $act = cadg_get("scoring/tournaments/$t/live-scoring/round-activity/"); $rounds = [];
    foreach ((array)($act['rounds'] ?? []) as $r) $rounds[(int)$r['round_number']] = $r;
    $out = []; $tz = new DateTimeZone('Europe/Prague'); $dist = 0; $holesN = 0;
    foreach ((array)($me['hole_scores'] ?? []) as $rn => $hs) {
        $rn = (int)$rn; $hs = array_filter((array)$hs, fn($v) => $v !== null && (int)$v > 0); if (!$hs) continue;
        $rid = $rounds[$rn]['round_id'] ?? null; $pars = []; $len = 0;
        if ($rid) foreach ((array)cadg_get("scoring/tournaments/$t/live-scoring/rounds/$rid/holes/?division=$div") as $h) { $pars[(int)$h['hole_number']] = (int)$h['par']; $len += (int)($h['length'] ?? 0); }
        ksort($hs); $throws = array_sum(array_map('intval', $hs)); $sm = $me['round_summaries'][$rn] ?? null;
        $par = $pars ? array_sum(array_intersect_key($pars, $hs)) : ($sm ? $throws - (int)$sm['to_par'] : 0);
        $date = null; if (!empty($rounds[$rn]['last_activity_at'])) { try { $date = (new DateTime((string)$rounds[$rn]['last_activity_at']))->setTimezone($tz)->format('Y-m-d'); } catch (Throwable $e) {} }
        $stats = cadg_round_stats((array)($me['hole_throws'][$rn] ?? []), $hs, $pars);
        $out[] = ['idx' => $rn - 1, 'throws' => $throws, 'par' => $par, 'score' => $throws - $par, 'rating' => null, 'rdate' => $date ?: ($ev['start_date'] ?? null),
            'stats' => $stats, 'hole_scores' => implode(',', $hs), 'hole_pars' => $pars ? implode(',', array_intersect_key($pars, $hs)) : null];
        $dist = max($dist, $len); $holesN = max($holesN, count($pars) ?: count($hs));
    }
    if (!$out) throw new RuntimeException('Máš tam zapsané výsledky, ale zatím žádné odehrané kolo.');
    $tied = count(array_filter($lb, fn($p) => (int)($p['place'] ?? 0) === (int)$me['place'])) > 1; $pl = (int)($me['place'] ?? 0);
    $city = trim((string)($ev['city'] ?? '')); $lat = $lon = null;
    if (preg_match('/^\s*(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)\s*$/', $city, $m)) { $lat = (float)$m[1]; $lon = (float)$m[2]; $city = ''; }
    return [
        'pdga_id' => null, 'cadg_id' => $t, 'player' => (string)$me['player_name'], 'division' => (string)($me['division'] ?? ''),
        'event' => ['name' => (string)$ev['name'], 'short' => mb_substr((string)$ev['name'], 0, 40), 'date' => $ev['start_date'] ?? null, 'end_date' => $ev['end_date'] ?? null,
            'venue' => null, 'city' => $city ?: null, 'lat' => $lat, 'lon' => $lon, 'tier' => '', 'holes' => $holesN ?: 18, 'dist' => $dist ?: null, 'unit' => 'm',
            'place' => $pl ? ($tied ? 'T' . $pl : $pl . '.') : null, 'pdga_rating' => null, 'event_rating' => !empty($me['event_rating']) ? (int)$me['event_rating'] : null,
            'is_pdga' => !empty($ev['is_pdga'])],
        'rounds' => $out,
    ];
}

// ───────────── Profil hráče (rating, čísla, členství, rozhodčí) ─────────────

/** Najde moje ČADG id podle PDGA čísla / jména z Nastavení a uloží ho. */
function cadg_find_user(): int {
    $uid = (int)setting('cadg_user_id', '0'); if ($uid) return $uid;
    $name = trim((string)setting('player_name', '')); $num = (int)preg_replace('/\D+/', '', (string)setting('player_pdga', '0')); if ($name === '') return 0;
    $parts = explode(' ', $name); $last = trim((string)end($parts));
    $j = cadg_get('user/users/?page=1&page_size=50&search=' . rawurlencode($name)) ?: cadg_get('user/users/?page=1&page_size=50&search=' . rawurlencode($last));
    foreach ((array)($j['results'] ?? []) as $r) {
        if (($num && (int)($r['pdga_number'] ?? 0) === $num) || (!$num && pdga_ascii($r['first_name'] . $r['last_name']) === pdga_ascii($name))) { set_setting('cadg_user_id', (string)(int)$r['id']); return (int)$r['id']; }
    }
    return 0;
}

/** Stáhne profil a uloží (settings.cadg_profile JSON). Když se změnil PDGA rating, zapíše ho do rating_history. */
function cadg_profile_sync(): array {
    set_setting('cadg_last', now());
    $uid = cadg_find_user(); if (!$uid) { set_setting('cadg_err', 'Nenašel jsem tě na ceskydiscgolf.cz (zkontroluj jméno a PDGA číslo v profilu hráče).'); return ['ok' => false, 'msg' => (string)setting('cadg_err')]; }
    $j = cadg_get("user/users/$uid/");
    if (!is_array($j) || empty($j['id'])) { set_setting('cadg_err', 'ceskydiscgolf.cz neodpovědělo.'); return ['ok' => false, 'msg' => 'ceskydiscgolf.cz neodpovědělo.']; }
    set_setting('cadg_err', '');
    $club = $j['clubs'][0]['club']['name'] ?? null;
    $p = ['id' => (int)$j['id'], 'name' => trim($j['first_name'] . ' ' . $j['last_name']), 'cadg_no' => $j['old_id'] ?? null, 'club' => $club, 'city' => $j['city'] ?? null,
        'pdga_no' => $j['pdga_number'] ?? null, 'pdga_rating' => $j['pdga_rating'] ?? null, 'idg_rating' => $j['idg_rating'] ?? null,
        'pdga_active' => !empty($j['has_active_pdga_membership']), 'pdga_pro' => !empty($j['is_pdga_professional']), 'pdga_until' => $j['pdga_membership_end_date'] ?? null,
        'ref' => $j['pdga_official_status'] ?? null, 'ref_until' => $j['pdga_official_end_date'] ?? null,
        'cadg_until' => $j['membership_end_date'] ?? null, 'cadg_type' => $j['membership_type'] ?? null];
    set_setting('cadg_profile', json_encode($p, JSON_UNESCAPED_UNICODE));
    $changed = false;
    if ((int)$p['pdga_rating'] > 0) {
        $last = db()->query('SELECT rating FROM rating_history ORDER BY d DESC LIMIT 1')->fetchColumn();
        if ($last === false || (int)$last !== (int)$p['pdga_rating']) {
            db()->prepare('INSERT INTO rating_history (d, rating, rounds_used) VALUES (?,?,NULL) ON DUPLICATE KEY UPDATE rating = VALUES(rating)')->execute([date('Y-m-d'), (int)$p['pdga_rating']]); $changed = true;
        }
    }
    return ['ok' => true, 'msg' => 'Profil aktualizován' . ($changed ? ' (nový PDGA rating ' . (int)$p['pdga_rating'] . ')' : '') . '.', 'profile' => $p];
}

function cadg_profile(): ?array { $j = json_decode((string)setting('cadg_profile', ''), true); return is_array($j) ? $j : null; }

/** Jednou za 12 hodin tiše aktualizuje profil (chyba stránku nezpomalí: pokus se zapisuje vždy). */
function cadg_sync_maybe(): void {
    $last = (string)setting('cadg_last', ''); if ($last !== '' && strtotime($last) > time() - 12 * 3600) return;
    try { cadg_profile_sync(); } catch (Throwable $e) { /* ne */ }
}
