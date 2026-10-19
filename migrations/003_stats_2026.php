<?php
declare(strict_types=1);
// Turnajové statistiky sezóny 2026 + historie PDGA ratingu (seed/stats.json).
return function (PDO $pdo): void {
    $d = json_decode((string)file_get_contents(ROOT . '/seed/stats.json'), true);
    $ev = $pdo->prepare('INSERT INTO events (year, slug, date, name, short, series, division, place, total, event_rating, pdga_rating, par, dist, unit, holes, pdga, venue, cat, note)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE name = VALUES(name)');
    $rd = $pdo->prepare('INSERT IGNORE INTO rounds (event_id, idx, score, throws, rating, day,
        fw_h, fw_n, sc_h, sc_n, c1r_h, c1r_n, c2r_h, c2r_n, c1x_h, c1x_n, c2_h, c2_n, avg_putt, long_putt, ob)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $pair = fn($x) => is_array($x) ? [$x[0], $x[1]] : [null, null];
    foreach ($d['years'] as $year => $y) {
        foreach ($y['events'] as $e) {
            $ev->execute([(int)$year, $e['id'], $e['date'] ?? null, $e['name'], $e['short'], $e['series'] ?? null, $e['division'] ?? null,
                $e['place'] ?? null, $e['total'], $e['eventRating'] ?? null, $e['pdgaRating'] ?? null, $e['par'], $e['dist'] ?? null,
                $e['unit'] ?? 'ft', $e['holes'] ?? 18, $e['pdga'] ? 1 : 0, $e['venue'] ?? null, $e['cat'] ?? null, $e['note'] ?? null]);
            $eid = (int)$pdo->query('SELECT id FROM events WHERE year = ' . (int)$year . ' AND slug = ' . $pdo->quote($e['id']))->fetchColumn();
            foreach ($e['rounds'] as $i => $r) {
                [$fh, $fn] = $pair($r['fw'] ?? null); [$sh, $sn] = $pair($r['sc'] ?? null);
                [$a, $b] = $pair($r['c1r'] ?? null); [$c, $dd] = $pair($r['c2r'] ?? null);
                [$x1, $x2] = $pair($r['c1x'] ?? null); [$p1, $p2] = $pair($r['c2'] ?? null);
                $rd->execute([$eid, $i, $r['s'], $r['t'], $r['r'] ?? null, $r['day'] ?? null,
                    $fh, $fn, $sh, $sn, $a, $b, $c, $dd, $x1, $x2, $p1, $p2, $r['ap'] ?? null, $r['lp'] ?? null, $r['ob'] ?? null]);
            }
        }
    }
    $h = $pdo->prepare('INSERT IGNORE INTO rating_history (d, rating, rounds_used) VALUES (?,?,?)');
    foreach ($d['ratingHistory'] as $x) $h->execute([$x[0], $x[1], $x[2]]);
};
