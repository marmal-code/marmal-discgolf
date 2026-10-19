<?php
declare(strict_types=1);
// Import výsledků z PDGA Live (veřejné API pdga.com). Dohledá hráče podle PDGA čísla (případně jména).
require_once __DIR__ . '/weather.php';      // http_json()
require_once __DIR__ . '/events.php';

const PDGA_API = 'https://www.pdga.com/apps/tournament/live-api/';

/** ID turnaje z odkazu (…/live/event/90000, ?eventId=90000, TournID=…) nebo holé číslo. */
function pdga_tourn_id(string $in): ?int {
    $in = trim($in);
    if (preg_match('/^\d{3,8}$/', $in)) return (int)$in;
    if (preg_match('/(?:eventId|TournID|tournId|tournamentId|event_id)=(\d{3,8})/i', $in, $m)) return (int)$m[1];
    if (preg_match('#/(?:live/event|tour/event|event|tournament)/(\d{3,8})#i', $in, $m)) return (int)$m[1];
    if (preg_match('/(\d{4,8})(?!.*\d{4})/', $in, $m)) return (int)$m[1];
    return null;
}

function pdga_ascii(string $s): string { $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s; return strtolower(preg_replace('/[^a-z]+/i', '', $t)); }

/** Najde seznam hráčů (data.scores): položky s klíči ResultID/PDGANum. Hřiště (layouts) mají také „Name“, proto se Name nepoužívá. */
function pdga_players(mixed $n): array {
    if (!is_array($n)) return [];
    if (is_array($n['scores'] ?? null) && array_is_list($n['scores'])) return $n['scores'];
    if ($n && array_is_list($n) && is_array($n[0] ?? null) && (array_key_exists('PDGANum', $n[0]) || array_key_exists('ResultID', $n[0]))) return $n;
    foreach ($n as $v) { $r = pdga_players($v); if ($r) return $r; }
    return [];
}

function pdga_event(int $tid): ?array { $j = http_json(PDGA_API . 'live_results_fetch_event.php?TournID=' . $tid); return is_array($j['data'] ?? null) ? $j['data'] : null; }
function pdga_round(int $tid, string $div, int $round): array {
    $j = http_json(PDGA_API . 'live_results_fetch_round.php?TournID=' . $tid . '&Division=' . rawurlencode($div) . '&Round=' . $round);
    return $j ? pdga_players($j['data'] ?? $j) : [];
}
function pdga_is_me(array $p, int $num, string $name): bool {
    if ($num > 0 && (int)preg_replace('/\D+/', '', (string)($p['PDGANum'] ?? '')) === $num) return true;
    return $num <= 0 && $name !== '' && pdga_ascii((string)($p['Name'] ?? '')) === pdga_ascii($name);
}

/**
 * Dohledá hráče v turnaji. Vrací ['tid','event','division','rows'=>[kolo=>řádek]] nebo vyhodí RuntimeException se srozumitelnou hláškou.
 */
function pdga_lookup(int $tid, int $num, string $name, string $prefDiv = 'MP40'): array {
    $ev = pdga_event($tid);
    if (!$ev) throw new RuntimeException('Turnaj ' . $tid . ' jsem na PDGA Live nenašel (nebo se nepodařilo spojení). Zkontroluj odkaz.');
    $divs = array_values(array_filter((array)($ev['Divisions'] ?? []), fn($d) => (int)($d['Players'] ?? 1) > 0));
    usort($divs, fn($a, $b) => (($b['Division'] ?? '') === $prefDiv) <=> (($a['Division'] ?? '') === $prefDiv));
    $maxRound = max(1, (int)($ev['HighestCompletedRound'] ?? 0), (int)($ev['LatestRound'] ?? 0));
    $maxRound = min($maxRound, 12);
    $found = null; $reqs = 0;
    foreach ($divs as $d) {
        $code = (string)$d['Division']; if ($code === '') continue;
        $maxR = max(1, (int)($d['LatestRound'] ?? $maxRound));
        for ($r = 1; $r <= min($maxR, $maxRound) && !$found; $r++) {          // hráč nemusí mít odehrané první kolo → zkusíme postupně
            if ($reqs++ > 30) break 2;
            foreach (pdga_round($tid, $code, $r) as $p) if (pdga_is_me($p, $num, $name)) { $found = [$code, $r, $p]; break; }
        }
        if ($found) break;
    }
    if (!$found) throw new RuntimeException('V turnaji „' . ($ev['Name'] ?? $tid) . '“ jsem nenašel hráče ' . ($num ? 'PDGA #' . $num : $name) . '. Hraješ v něm? Zkus doplnit PDGA číslo nebo jméno v Nastavení.');
    [$code, $firstSeen, $_] = $found; $rows = [];
    for ($r = 1; $r <= $maxRound; $r++) {
        foreach (pdga_round($tid, $code, $r) as $p) if (pdga_is_me($p, $num, $name)) { if ((int)($p['RoundScore'] ?? 0) > 0 || !empty($p['HoleScores'])) $rows[$r] = $p; break; }
    }
    if (!$rows) throw new RuntimeException('Hráč v turnaji je, ale zatím nemá žádné odehrané kolo.');
    return ['tid' => $tid, 'event' => $ev, 'division' => $code, 'rows' => $rows];
}

/** Z nalezeného výsledku vyrobí hodnoty pro tabulky events / rounds. */
function pdga_map(array $f): array {
    $ev = $f['event']; $rows = $f['rows']; $last = end($rows);
    $lay = (array)(($ev['Layouts'][0] ?? []));
    $city = trim(explode(',', (string)($ev['Location'] ?? ''))[0]);
    $tied = !empty($last['Tied']); $pl = (int)($last['RunningPlace'] ?? 0);
    $rounds = [];
    foreach ($rows as $n => $p) {
        $throws = (int)$p['RoundScore']; $par = (int)($p['Par'] ?? 0);
        $pars = array_filter(array_map('intval', explode(',', (string)($p['Pars'] ?? ''))), fn($x) => $x > 0);
        if (!$par && $pars) $par = array_sum($pars);
        $date = (string)($ev['RoundsList'][(string)$n]['Date'] ?? '') ?: null;
        $rounds[] = ['idx' => $n - 1, 'throws' => $throws, 'par' => $par, 'score' => isset($p['RoundtoPar']) && $p['RoundtoPar'] !== '' ? (int)$p['RoundtoPar'] : $throws - $par,
            'rating' => !empty($p['RoundRating']) ? (int)$p['RoundRating'] : null, 'rdate' => $date ? substr($date, 0, 10) : null,
            'hole_scores' => !empty($p['HoleScores']) ? implode(',', (array)$p['HoleScores']) : null, 'hole_pars' => $pars ? implode(',', $pars) : null];
    }
    return [
        'pdga_id' => $f['tid'], 'player' => (string)($last['Name'] ?? ''), 'division' => $f['division'],
        'event' => ['name' => (string)($ev['Name'] ?? 'Turnaj'), 'short' => mb_substr((string)($ev['SimpleName'] ?? $ev['Name'] ?? 'Turnaj'), 0, 40),
            'date' => substr((string)($ev['StartDate'] ?? ''), 0, 10) ?: null, 'end_date' => substr((string)($ev['EndDate'] ?? ''), 0, 10) ?: null,
            'venue' => (string)($lay['CourseName'] ?? '') ?: null, 'city' => $city ?: null, 'tier' => (string)($ev['FormattedTier'] ?? ''),
            'holes' => (int)($lay['Holes'] ?? 18) ?: 18, 'dist' => isset($lay['Length']) ? (int)$lay['Length'] : null, 'unit' => str_starts_with(strtolower((string)($lay['Units'] ?? '')), 'm') ? 'm' : 'ft',
            'place' => $pl ? ($tied ? 'T' . $pl : $pl . '.') : null, 'pdga_rating' => !empty($last['Rating']) ? (int)$last['Rating'] : null],
        'rounds' => $rounds,
    ];
}

/** Uloží namapovaný import. $eventId = existující turnaj (aktualizace), jinak se založí nový. Vrací id turnaje. */
function pdga_save(array $m, ?int $eventId): int {
    $e = $m['event'];
    if (!$eventId) { $q = db()->prepare('SELECT id FROM events WHERE pdga_id = ?'); $q->execute([$m['pdga_id']]); $eventId = (int)$q->fetchColumn() ?: null; }
    if ($eventId) {
        db()->prepare('UPDATE events SET status = "done", pdga = 1, pdga_id = ?, division = COALESCE(division, ?), place = ?, pdga_rating = COALESCE(?, pdga_rating), holes = ?, dist = COALESCE(?, dist), unit = ?,
            date = COALESCE(date, ?), end_date = COALESCE(end_date, ?), venue = COALESCE(venue, ?), city = COALESCE(city, ?) WHERE id = ?')
            ->execute([$m['pdga_id'], $m['division'], $e['place'], $e['pdga_rating'], $e['holes'], $e['dist'], $e['unit'], $e['date'], $e['end_date'], $e['venue'], $e['city'], $eventId]);
    } else {
        $year = $e['date'] ? (int)substr($e['date'], 0, 4) : (int)date('Y');
        db()->prepare('INSERT INTO events (year, slug, date, end_date, name, short, series, division, place, pdga_rating, holes, dist, unit, pdga, pdga_id, venue, city, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,"done")')
            ->execute([$year, event_unique_slug($year, $e['short']), $e['date'], $e['end_date'], $e['name'], $e['short'], null, $m['division'], $e['place'], $e['pdga_rating'], $e['holes'], $e['dist'], $e['unit'], $m['pdga_id'], $e['venue'], $e['city']]);
        $eventId = (int)db()->lastInsertId(); event_default_todos($eventId);
    }
    $multi = $e['date'] && $e['end_date'] && $e['end_date'] > $e['date'];
    $st = db()->prepare('INSERT INTO rounds (event_id, idx, score, throws, par, rating, rdate, day, hole_scores, hole_pars) VALUES (?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE score = VALUES(score), throws = VALUES(throws), par = VALUES(par), rating = VALUES(rating), rdate = VALUES(rdate), day = VALUES(day), hole_scores = VALUES(hole_scores), hole_pars = VALUES(hole_pars)');
    foreach ($m['rounds'] as $r) {
        $day = ($multi && $r['rdate']) ? ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'][(int)date('w', strtotime($r['rdate']))] : null;
        $st->execute([$eventId, $r['idx'], $r['score'], $r['throws'], $r['par'], $r['rating'], $r['rdate'], $day, $r['hole_scores'], $r['hole_pars']]);
    }
    event_recalc($eventId);
    return $eventId;
}
