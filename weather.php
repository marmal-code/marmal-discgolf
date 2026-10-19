<?php
declare(strict_types=1);
// JSON: počasí pro turnaj a den (jen přihlášený). Výsledek se nikam neukládá – jen předvyplní formulář.
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/weather.php';
migrate_if_needed();
require_login();
header('Content-Type: application/json; charset=utf-8');
function wout(array $a): never { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$e = event_get((int)($_GET['event'] ?? 0));
$date = (string)($_GET['date'] ?? '');
if (!$e) wout(['ok' => false, 'error' => 'Turnaj nenalezen.']);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) wout(['ok' => false, 'error' => 'Zadej datum.']);
if ($e['lat'] === null || $e['lon'] === null) {
    $q = trim((string)($e['city'] ?: $e['venue'] ?: ''));
    if ($q === '') wout(['ok' => false, 'error' => 'Vyplň u turnaje „Město pro počasí“.']);
    $g = geocode($q);
    if (!$g) wout(['ok' => false, 'error' => 'Místo „' . $q . '“ jsem nenašel, zkus jiné město.']);
    db()->prepare('UPDATE events SET lat = ?, lon = ? WHERE id = ?')->execute([$g['lat'], $g['lon'], $e['id']]);
    $e['lat'] = $g['lat']; $e['lon'] = $g['lon'];
}
$from = max(0, min(23, (int)($_GET['from'] ?? 9))); $to = max($from, min(23, (int)($_GET['to'] ?? 16)));
$w = weather_for((float)$e['lat'], (float)$e['lon'], $date, $from, $to);
if (!$w) wout(['ok' => false, 'error' => strtotime($date) > time() + 15 * 86400 ? 'Předpověď je k dispozici nejvýše 16 dní dopředu.' : 'Počasí se nepodařilo načíst. Vyplň ho ručně.']);
wout(['ok' => true] + $w);
