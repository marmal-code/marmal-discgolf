<?php
declare(strict_types=1);
// Počasí z Open-Meteo (zdarma, bez klíče). Výsledek se jen PŘEDVYPLNÍ do formuláře – lze ho upravit.

const WMO = [0 => 'jasno', 1 => 'skoro jasno', 2 => 'polojasno', 3 => 'zataženo', 45 => 'mlha', 48 => 'mlha', 51 => 'mrholení', 53 => 'mrholení', 55 => 'mrholení',
    56 => 'mrznoucí mrholení', 57 => 'mrznoucí mrholení', 61 => 'slabý déšť', 63 => 'déšť', 65 => 'silný déšť', 66 => 'mrznoucí déšť', 67 => 'mrznoucí déšť',
    71 => 'slabé sněžení', 73 => 'sněžení', 75 => 'silné sněžení', 77 => 'sněhová zrna', 80 => 'přeháňky', 81 => 'přeháňky', 82 => 'silné přeháňky',
    85 => 'sněhové přeháňky', 86 => 'sněhové přeháňky', 95 => 'bouřka', 96 => 'bouřka s kroupami', 99 => 'bouřka s kroupami'];

function http_json(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'discgolf-app']);
    $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($r === false || $code !== 200) return null;
    $j = json_decode((string)$r, true); return is_array($j) ? $j : null;
}

/** Souřadnice místa podle názvu (město/hřiště). */
function geocode(string $name): ?array {
    $j = http_json('https://geocoding-api.open-meteo.com/v1/search?count=1&language=cs&name=' . rawurlencode($name));
    $r = $j['results'][0] ?? null;
    return $r ? ['lat' => (float)$r['latitude'], 'lon' => (float)$r['longitude'], 'name' => (string)($r['name'] ?? $name)] : null;
}

/** Čistá funkce (testovatelná): z hodinových dat vybere hodiny $from–$to a spočítá souhrn. */
function weather_summarize(array $hourly, int $from = 9, int $to = 16): ?array {
    $t = $w = $g = $p = $c = [];
    foreach (($hourly['time'] ?? []) as $i => $iso) {
        $h = (int)substr((string)$iso, 11, 2); if ($h < $from || $h > $to) continue;
        if (isset($hourly['temperature_2m'][$i])) $t[] = (float)$hourly['temperature_2m'][$i];
        if (isset($hourly['wind_speed_10m'][$i])) $w[] = (float)$hourly['wind_speed_10m'][$i];
        if (isset($hourly['wind_gusts_10m'][$i])) $g[] = (float)$hourly['wind_gusts_10m'][$i];
        if (isset($hourly['precipitation'][$i])) $p[] = (float)$hourly['precipitation'][$i];
        if (isset($hourly['weather_code'][$i])) $c[] = (int)$hourly['weather_code'][$i];
    }
    if (!$t) return null;
    $code = $c ? max($c) : 0; $label = WMO[$code] ?? null;
    if ($label === null) { foreach (array_reverse(array_keys(WMO)) as $k) if ($k <= $code) { $label = WMO[$k]; break; } }
    return ['temp' => round(array_sum($t) / count($t), 1), 'wind' => $w ? round(array_sum($w) / count($w), 1) : null, 'gust' => $g ? round(max($g), 1) : null,
        'rain' => $p ? round(array_sum($p), 1) : null, 'cond' => $label ?? '', 'hours' => count($t)];
}

/** Stáhne počasí pro den (YYYY-MM-DD) a místo. Vrací souhrn nebo null. */
function weather_for(float $lat, float $lon, string $date, int $from = 9, int $to = 16): ?array {
    $days = (int)floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    $base = ($days >= -80 && $days <= 15) ? 'https://api.open-meteo.com/v1/forecast' : 'https://archive-api.open-meteo.com/v1/archive';
    if ($days > 15) return null;                       // předpověď dál než 16 dní dopředu není
    $j = http_json($base . '?latitude=' . $lat . '&longitude=' . $lon . '&start_date=' . $date . '&end_date=' . $date
        . '&hourly=temperature_2m,precipitation,wind_speed_10m,wind_gusts_10m,weather_code&wind_speed_unit=ms&timezone=Europe%2FPrague');
    $s = $j ? weather_summarize($j['hourly'] ?? [], $from, $to) : null;
    if ($s) $s['forecast'] = $days >= 0;
    return $s;
}
