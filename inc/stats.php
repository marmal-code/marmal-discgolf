<?php
declare(strict_types=1);

function pair($h, $n): ?array { return ($h === null || $n === null) ? null : [(int)$h, (int)$n]; }

/** Data pro stránku statistik ve stejném tvaru, jaký používá stats.js. */
function stats_data(): array {
    $pdo = db();
    $hist = $pdo->query('SELECT d, rating, rounds_used FROM rating_history ORDER BY d')->fetchAll();
    $rating = $hist ? (int)end($hist)['rating'] : 0;
    $out = [
        'player' => ['name' => setting('player_name', 'Martin Malý'), 'pdga' => (int)setting('player_pdga', '113434'), 'rating' => $rating, 'goal' => (int)setting('rating_goal', '980')],
        'ratingHistory' => array_map(fn($r) => [$r['d'], (int)$r['rating'], (int)$r['rounds_used']], $hist),
        'years' => [],
    ];
    $events = $pdo->query('SELECT * FROM events WHERE status = "done" ORDER BY year, date, id')->fetchAll();
    $rs = $pdo->prepare('SELECT * FROM rounds WHERE event_id = ? ORDER BY idx');
    foreach ($events as $i => $e) {
        $y = (string)$e['year'];
        $out['years'][$y] ??= ['insights' => [], 'events' => []];
        $rs->execute([$e['id']]); $rounds = [];
        foreach ($rs->fetchAll() as $r) {
            $rounds[] = [
                's' => (int)$r['score'], 't' => (int)$r['throws'], 'r' => $r['rating'] !== null ? (int)$r['rating'] : null, 'day' => $r['day'],
                'fw' => pair($r['fw_h'], $r['fw_n']), 'sc' => pair($r['sc_h'], $r['sc_n']), 'c1r' => pair($r['c1r_h'], $r['c1r_n']), 'c2r' => pair($r['c2r_h'], $r['c2r_n']),
                'c1x' => pair($r['c1x_h'], $r['c1x_n']), 'c2' => pair($r['c2_h'], $r['c2_n']),
                'ap' => $r['avg_putt'] !== null ? (float)$r['avg_putt'] : null, 'lp' => $r['long_putt'] !== null ? (float)$r['long_putt'] : null, 'ob' => $r['ob'] !== null ? (int)$r['ob'] : null,
            ];
        }
        $out['years'][$y]['events'][] = [
            'id' => $e['slug'], 'order' => $i + 1, 'name' => $e['name'], 'short' => $e['short'], 'series' => $e['series'], 'division' => $e['division'],
            'place' => $e['place'], 'total' => (int)$e['total'], 'eventRating' => $e['event_rating'] !== null ? (int)$e['event_rating'] : null,
            'pdgaRating' => $e['pdga_rating'] !== null ? (int)$e['pdga_rating'] : null, 'par' => (int)$e['par'], 'dist' => $e['dist'] !== null ? (int)$e['dist'] : null,
            'unit' => $e['unit'], 'holes' => (int)$e['holes'], 'pdga' => (bool)$e['pdga'], 'note' => $e['note'] ?? '', 'date' => $e['date'], 'venue' => $e['venue'],
            'cat' => $e['cat'] !== null ? (int)$e['cat'] : null, 'rounds' => $rounds,
        ];
    }
    $ys = array_keys($out['years']); $max = $ys ? max(array_map('intval', $ys)) : (int)date('Y');
    $out['years'][(string)($max + 1)] ??= ['insights' => [], 'events' => []];
    ksort($out['years']);
    return $out;
}
