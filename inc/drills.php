<?php
declare(strict_types=1);
// Disc golf cvičení (podle tréninkových materiálů Discgolf klubu Kladno). Zkopírováno z aplikace Moje Zdraví – definice jsou stejné, aby se výsledky daly posílat tam a zpět.
// metric: hlavní číslo do grafu, better: 'up' = víc je lépe, 'down' = méně je lépe

const DRILLS = [
'zlata_zona' => [
    'name' => 'Zlatá zóna', 'icon' => '🎯', 'kind' => 'Přihozy', 'minutes' => 35,
    'metric' => 'Body (max 90)', 'unit' => 'b.', 'better' => 'up',
    'variants' => [],
    'about' => 'Učí odhadovat vzdálenost a riskovat s rozumem. Přihozy rozhodují o skóre víc než si myslíš – cvičení ber jako situaci po nevydařeném drivu, kdy potřebuješ zachránit par. Učíš se vybírat správnou míru rizika a uvědomit si, kdy se vyplatí jít naplno a kdy zvolit jistotu.',
    'need' => ['1 koš', '5 disků na přihozy (ideálně puttery a midrange)', 'Kužely, vlaječky nebo markery na vyznačení zlaté zóny'],
    'setup' => 'Kolem koše vyznač 6 m širokou zónu – 3 m před košem a 3 m za košem. To je zlatá zóna, odkud vždy v klidu doputtuješ. Odměř tři odhodové vzdálenosti: 30, 40 a 50 m. Z každé hodíš 5 disků: 2× backhand, 2× forhand, 1× volba (hod, který ti nejvíc sedí).',
    'rules' => [
        '1. kolo – OB je ZA zlatou zónou. Nedohodit je v pohodě (SAFE), přehození je OB. Učí nepřehazovat.',
        '2. kolo – OB je PŘED zlatou zónou. Za zónou je SAFE. Učí dotahovat hody až ke koši a nebát se jít naplno.',
        '3. kolo – OB je vepředu i vzadu. Buď trefíš zlatou zónu, nebo dostaneš −2. Nejtěžší kolo – čistý odhad vzdálenosti a nervy ze železa.',
    ],
    'scoring' => ['Disk ve zlaté zóně = +2', 'Disk v SAFE zóně = +1 (jen když je odtud snadný douputt, jinak 0)', 'Disk v OB = −2', 'Max 10 bodů za vzdálenost, 30 za kolo, 90 za celé cvičení.'],
    'tips' => ['Před každým hodem si řekni nahlas, jaký disk volíš a proč.', 'Zapisuj výsledky a dělej cvičení celý měsíc – uvidíš, kam se posouváš.', 'Pokročilí: zúžit zónu na 4 m a přidat 60 m. Začátečníci: rozšířit na 8 m.'],
    'progress' => ['Celkové skóre (max 90)', 'Úspěšnost do zóny backhand vs. forhand', 'Podíl OB'],
],
'must_make' => [
    'name' => 'Must-Make', 'icon' => '🥏', 'kind' => 'Přihoz + putt', 'minutes' => 30,
    'metric' => 'Průměr bodů na kolo (max 15)', 'unit' => 'b./kolo', 'better' => 'up',
    'variants' => [],
    'about' => 'Trénuješ realistickou sekvenci: přihozy z různých vzdáleností a pozic s následným puttem, který „musíš“ dát. Učíš se přemýšlet o přihozu jako o přípravě na snadný putt. Každý putt se počítá, takže je tam mírný tlak.',
    'need' => ['Koš', '5 approach disků (puttery a midrange)', '5 putterů', 'Prostor kolem koše – ideálně aspoň 60 m jedním směrem'],
    'setup' => 'Vyber 5 pozic kolem koše ve vzdálenosti 35–60 m, každou z jiného úhlu a ideálně pro jiný typ hodu (BH, FH, roller, thumber…). Pozice si označ.',
    'rules' => [
        'Z každé pozice hoď 1 přihoz – cíl je dostat disk do C1 (do 10 m od koše).',
        'Odehraj všech 5 pozic, pak jdi k diskům a postupně všechny doputtuj. Putt se počítá jako „must-make“.',
        '5 pozic + 5 puttů = 1 kolo. Odehraj 3–5 kol, mezi koly můžeš změnit pozice nebo typ hodu.',
    ],
    'scoring' => ['Přihoz do C1 (do 10 m): +1', 'Přihoz do C2 (do 20 m): 0', 'Přihoz mimo C2: −1', 'Proměněný putt v C1: +2', 'Nedaný putt v C1: −2 (to je ten tlak)', 'Putt z C2 (dal i nedal): 0', 'Max 15 bodů za kolo.'],
    'tips' => ['Přihoz: cílem není trefit koš, ale přihodit si na snadný putt. Hraj „před“ koš a počítej se skluzem.', 'Sleduj stabilitu disku – rolluje, skipuje, sedne na zem?', 'Putt: kompletní rutina jako na turnaji – postav se, nadechni, vizualizuj, puttuj. Žádné rychloputtování.'],
    'progress' => ['Průměrné skóre za kolo', '% úspěšnost přihozů do C1', '% úspěšnost proměněných puttů v C1'],
],
'dvacetjedna' => [
    'name' => 'Jednadvacet „21“', 'icon' => '2️⃣', 'kind' => 'Putting pod tlakem', 'minutes' => 25,
    'metric' => 'Počet kol do 21 bodů', 'unit' => 'kol', 'better' => 'down',
    'variants' => ['normal' => 'Klasika (5 putterů)', 'hardcore' => 'Hardcore (od 17 b. jen z 9 m)', 'tri' => 'Se třemi puttery (do 18 b.)'],
    'about' => 'Cílený trénink puttování pod tlakem i na sériovou práci, kdy tě může čekat hodně puttů. Simuluje situace „tohle je na birdie / na vítězství / na záchranu paru“. Funguje na principu odměny a trestu – za chybu platíš, takže se mozek učí zacházet s tlakem místo toho, aby ho ignoroval.',
    'need' => ['Koš', '5 putterů', 'Označené pozice na 5, 7 a 9 m (vzdálenost můžeš upravit)'],
    'setup' => 'Začínáš s 0 body. Cíl: nasbírat 21 bodů.',
    'rules' => [
        'V každém kole hodíš všech 5 putterů z jedné vzdálenosti (začni na 5 m).',
        'Po každém kole (5 puttů) se posuň na další vzdálenost: 5 → 7 → 9 → 5 → 7 → 9 m… pořád dokola.',
        'Když skóre spadne na −5 nebo níž, začínáš celé cvičení od nuly.',
        'Hra končí dosažením 21 bodů.',
        'Hardcore: jakmile máš 17 bodů, přesuň se na nejvzdálenější pozici a 21 musíš dohrát odtud. Když spadneš pod 17, vracíš se na poslední pozici.',
        'Se třemi puttery: hraje se jen do 18 bodů a minutý putt je za −1. Vhodné, když nemáš tolik času.',
    ],
    'scoring' => ['Proměněný putt: +1', 'Minutý putt: −2 (se třemi puttery −1)', '−5 a méně = reset na 0'],
    'tips' => ['Sleduj vnitřní dialog – co si říkáš na −3 a co na +18? Trénuješ i mentální odolnost.', 'Každý putt s turnajovou rutinou. Když cítíš tlak, zpomal a přidej jeden nádech navíc.', 'Na prvních dvou pozicích se soustřeď na rytmus a techniku, na poslední na plnou turnajovou rutinu.'],
    'progress' => ['Počet kol (sad po 5 puttech) potřebných k 21', 'Počet resetů (spadnutí na −5)'],
],
'tlakovy_hrnec' => [
    'name' => 'Tlakový hrnec', 'icon' => '🔥', 'kind' => 'Putting C1', 'minutes' => 20,
    'metric' => 'Průměr pokusů na kolo', 'unit' => 'pokusů', 'better' => 'down',
    'variants' => ['normal' => 'Klasika (minutí = o pozici zpět)', 'hardcore' => 'Hardcore (minutí = na začátek)'],
    'about' => 'Čistě puttovací cvičení, které simuluje narůstající tlak. Musíš dát sérii puttů za sebou, a pokud mineš, vracíš se. Vytváří přesně ten pocit „tohle musím dát“, který znáš z turnaje. Zaměřeno na C1 (5–9 m).',
    'need' => ['Koš', '5 putterů', '5 označených pozic: 5, 6, 7, 8, 9 m (v přímce nebo v kruhu)'],
    'setup' => 'Začni na 5 m.',
    'rules' => [
        'Dej putt → posuň se o pozici dál (5 → 6 → 7 → 8 → 9 m).',
        'Pokud mineš na jakékoli pozici, vrať se o jednu pozici zpět.',
        'Kolo je dokončené, když dáš putt z poslední pozice (9 m).',
        'Odehraj 5 kol a sleduj počet pokusů na dokončení každého kola.',
        'Hardcore: když mineš, vracíš se úplně na začátek (5 m). Brutální, ale extrémně efektivní.',
    ],
    'scoring' => ['Počítá se počet pokusů na dokončení kola – méně je lépe.', 'Nejlepší možné kolo = 5 pokusů.'],
    'tips' => ['Všimni si, co se děje s tělem na 8 a 9 m po sérii úspěšných puttů. Zrychlíš? Ztuhneš? Přestaneš dýchat? Tohle chceš odhalit.', 'Drž stejnou rutinu na každém puttu – stejný nádech, stejný počet pohledů na koš, stejný release.'],
    'progress' => ['Počet pokusů na dokončení kola (méně = lépe)', 'Počet „resetů“ během cvičení', 'Na které vzdálenosti nejčastěji chybuješ pod tlakem'],
],
'risk' => [
    'name' => 'Risk je zisk', 'icon' => '🎲', 'kind' => 'Putting a rozhodování', 'minutes' => 15,
    'metric' => 'Skóre z 10 puttů (max 30)', 'unit' => 'b.', 'better' => 'up',
    'variants' => [],
    'about' => 'Trénink puttování, který tě učí pracovat s rizikem a odměnou. Před každým puttem se rozhodneš – jdeš na jistotu, nebo riskneš víc bodů? Jeden miss ti smaže všechno. Přesně jako na turnaji, kdy zvažuješ, jestli zaútočit na birdie, nebo hrát bezpečně na par.',
    'need' => ['Koš', '5 putterů', 'Markery na pozicích 5, 6 a 7 m (vzdálenost můžeš upravit)'],
    'setup' => 'Začínáš s 0 body. Jedna hra = 10 puttů.',
    'rules' => [
        'Před každým puttem si sám zvolíš vzdálenost, ze které budeš házet.',
        'Minutý putt z jakékoli vzdálenosti = všechny nasbírané body se mažou na 0.',
        'Po 10 puttech hra končí – tvé skóre je výsledek.',
    ],
    'scoring' => ['Proměněný putt z 5 m: +1', 'z 6 m: +2', 'ze 7 m: +3', 'Minutý putt: skóre na 0'],
    'tips' => ['Všimni si, jak se mění strategie s rostoucím skóre. Při 0 riskuješ snadno, při 8 najednou cítíš, že je co ztratit. Přesně tenhle moment je důvod, proč cvičení děláš.', 'Každý putt s kompletní turnajovou rutinou, i když házíš „jen“ z 5 m.', 'Hraj proti kamarádovi – tlak se znásobí.'],
    'progress' => ['Nejvyšší skóre z 10 puttů (max 30)', 'Průměrné skóre za 5 her', 'Počet her bez jediného missu'],
],
'osmdesatka' => [
    'name' => 'Osmdesátka', 'icon' => '8️⃣', 'kind' => 'Putting – série', 'minutes' => 25,
    'metric' => 'Body z 10 sérií (cíl 8)', 'unit' => 'b.', 'better' => 'up',
    'variants' => [],
    'about' => 'Trénink puttování zaměřený na konzistenci a mentální odolnost. Nejde o jeden výjimečný putt, ale o opakované trefování z vzdálenosti, která pro tebe není samozřejmá. Učíš se přijmout neúspěch a dohrát i ve chvíli, kdy už nemůžeš dosáhnout cíle.',
    'need' => ['Koš', '5 putterů', 'Zvolená vzdálenost (např. 8 m) – dosažitelná při plném soustředění, ale ne pohodlná'],
    'setup' => 'Zvol si vzdálenost, kterou zvládneš při plném soustředění. Po celou dobu cvičení zůstává stejná, mění se pouze úhel, ze kterého puttuješ. Na kladenském hřišti se hodí jamka č. 2 s různými pozicemi v přirozeném terénu.',
    'rules' => [
        'Z jednoho místa odehraj sérii 5 puttů.',
        'Pokud proměníš alespoň 4 z 5, získáš 1 bod. Při 3 a méně bod nezískáš.',
        'Přesuň se na jiné místo kolem koše – vzdálenost zůstává stejná.',
        'Odehraj celkem 10 sérií. Cíl: 8 bodů z 10 sérií.',
        'Dohraj každou sérii, i když už po druhém missu víš, že bod nezískáš. Dohraj celé cvičení, i když už nemůžeš dosáhnout 8 bodů.',
    ],
    'scoring' => ['Série se 4 nebo 5 proměněnými putty = 1 bod', 'Série s 0 až 3 proměněnými putty = 0 bodů', 'Maximum 10 bodů, cílová hodnota 8 bodů'],
    'tips' => ['Sleduj kvalitu puttů i v sériích, kde už bod nezískáš – zachovej stejnou rutinu jako v prvních, nezfláknout je.', 'Trénuješ návyky, ne pouhé výsledky.', 'Tlak roste – v pozdějších sériích je jeho vliv větší.', 'Pokud cvičení splníš na první pokus, příště zvol větší vzdálenost.'],
    'progress' => ['Počet bodů z 10 sérií (cíl 8)', 'Celkový počet proměněných puttů z 50 a celková úspěšnost', 'Při splnění na první pokus zvýšit vzdálenost'],
],
];

// ───────────── historie cvičení (tabulka drill_sessions) ─────────────
// Varianty mají jiná pravidla, proto se statistiky a rekordy počítají pro každou variantu zvlášť.
function drill_list(string $key, int $limit = 40, ?string $variant = null): array {
    $q = db()->prepare('SELECT * FROM drill_sessions WHERE drill = ?' . ($variant !== null ? ' AND variant = ?' : '') . ' ORDER BY d DESC, id DESC LIMIT ' . (int)$limit);
    $q->execute($variant !== null ? [$key, $variant] : [$key]); return $q->fetchAll();
}
function drill_points(string $key, int $limit = 30, string $variant = 'normal'): array {
    return array_map(fn($r) => [$r['d'], (float)$r['score']], array_reverse(drill_list($key, $limit, $variant)));
}
function drill_best(string $key, string $variant = 'normal'): ?float {
    $q = db()->prepare('SELECT ' . ((DRILLS[$key]['better'] ?? 'up') === 'up' ? 'MAX' : 'MIN') . '(score) FROM drill_sessions WHERE drill = ? AND variant = ?'); $q->execute([$key, $variant]);
    $v = $q->fetchColumn(); return $v === null || $v === false ? null : (float)$v;
}
function drill_num(float $v, int $dec = 1): string { return rtrim(rtrim(number_format($v, $dec, ',', ' '), '0'), ','); }

/** Uloží odehrané cvičení, odškrtne putting v plánu a zkusí poslat výsledek do Moje Zdraví. Vrací [id, nový rekord?]. */
function drill_save(string $key, string $variant, float $score, int $minutes, array $summary, array $events): array {
    require_once __DIR__ . '/training.php'; require_once __DIR__ . '/health.php';
    $prev = drill_best($key, $variant); $up = (DRILLS[$key]['better'] ?? 'up') === 'up';
    $d = date('Y-m-d');
    db()->prepare('INSERT INTO drill_sessions (drill, variant, d, minutes, score, summary, events, src, sent, created) VALUES (?,?,?,?,?,?,?,"dg",0,?)')
        ->execute([$key, $variant, $d, max(1, min(240, $minutes)), $score, json_encode($summary, JSON_UNESCAPED_UNICODE), json_encode($events), now()]);
    $id = (int)db()->lastInsertId();
    drill_mark_plan($id);
    try { health_push_drills(); } catch (Throwable $e) { /* pošle se při další synchronizaci */ }
    return [$id, $prev !== null && ($up ? $score > $prev : $score < $prev)];
}

/** Cvičení → první neodškrtnutá položka plánu typu „putting“ ten den (ruční záznam má přednost). */
function drill_mark_plan(int $sessionId): bool {
    require_once __DIR__ . '/training.php';
    $q = db()->prepare('SELECT * FROM drill_sessions WHERE id = ?'); $q->execute([$sessionId]); $s = $q->fetch(); if (!$s || !tr_plan()) return false;
    foreach (tr_day($s['d']) as $it) {
        if ($it['kind'] !== 'putting' || $it['status']) continue;
        $name = DRILLS[$s['drill']]['name'] ?? $s['drill'];
        db()->prepare('INSERT IGNORE INTO training_log (tdate, item_id, status, note, src, created) VALUES (?,?,"done",?,"cviceni",?)')
            ->execute([$s['d'], (int)$it['id'], mb_substr('Cvičení: ' . $name . ' ' . drill_num((float)$s['score']) . ' ' . (DRILLS[$s['drill']]['unit'] ?? ''), 0, 300), now()]);
        return true;
    }
    return false;
}

/** Souhrn podrobností jedné hry do jedné řádky. */
function drill_detail(string $key, array $s): string {
    switch ($key) {
        case 'zlata_zona': return 'kola ' . implode(' · ', $s['rounds'] ?? []) . ' · zóna ' . ($s['zonePct'] ?? 0) . ' % · OB ' . ($s['obPct'] ?? 0) . ' %';
        case 'must_make': return 'kola ' . implode(' · ', $s['rounds'] ?? []) . ' · C1 ' . ($s['c1AppPct'] ?? 0) . ' % · putt ' . ($s['c1PuttPct'] ?? 0) . ' %';
        case 'dvacetjedna': return ($s['resets'] ?? 0) . ' resetů · ' . ($s['putts'] ?? 0) . ' puttů · ' . ($s['pct'] ?? 0) . ' %';
        case 'tlakovy_hrnec': return 'kola ' . implode(' · ', $s['rounds'] ?? []) . ' pokusů · návratů ' . ($s['backs'] ?? 0);
        case 'osmdesatka': return 'série ' . implode(' · ', array_map(fn($m) => (int)$m . '/5', (array)($s['series'] ?? []))) . ' · úspěšnost ' . ($s['pct'] ?? 0) . ' %';
        case 'risk': return 'max ' . ($s['max'] ?? 0) . ' · missů ' . ($s['misses'] ?? 0);
    }
    return '';
}

/** Jednoduchý SVG graf vývoje (body = [[datum, hodnota], …]). */
function line_chart(array $pts, string $unit = '', int $H = 150): string {
    if (count($pts) < 2) return '<p class="sub">Graf se ukáže od druhého odehrání.</p>';
    $W = 320; $pl = 30; $pr = 8; $pt = 10; $pb = 20; $vals = array_column($pts, 1); $min = min($vals); $max = max($vals);
    if ($max - $min < 1) { $min -= 1; $max += 1; } $pad = ($max - $min) * 0.15; $min -= $pad; $max += $pad; $n = count($pts);
    $x = fn($i) => $pl + ($n > 1 ? $i / ($n - 1) : 0) * ($W - $pl - $pr);
    $y = fn($v) => $pt + ($max - $v) / ($max - $min) * ($H - $pt - $pb);
    $s = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="chart" role="img">';
    foreach ([$max - $pad, ($min + $max) / 2, $min + $pad] as $gv) $s .= '<line x1="' . $pl . '" x2="' . ($W - $pr) . '" y1="' . round($y($gv), 1) . '" y2="' . round($y($gv), 1) . '" class="grid"/><text x="' . ($pl - 5) . '" y="' . round($y($gv) + 3, 1) . '" class="axis" text-anchor="end">' . round($gv) . '</text>';
    $poly = []; foreach ($pts as $i => [$d, $v]) $poly[] = round($x($i), 1) . ',' . round($y($v), 1);
    $s .= '<polyline points="' . implode(' ', $poly) . '" class="line"/>';
    foreach ($pts as $i => [$d, $v]) $s .= '<g class="pt"><circle cx="' . round($x($i), 1) . '" cy="' . round($y($v), 1) . '" r="10" class="hit"/><circle cx="' . round($x($i), 1) . '" cy="' . round($y($v), 1) . '" r="3.5" class="dot"/><title>' . date('j. n. Y', strtotime($d)) . ': ' . drill_num((float)$v) . ' ' . $unit . '</title></g>';
    $s .= '<text x="' . $pl . '" y="' . ($H - 4) . '" class="axis">' . date('j. n.', strtotime($pts[0][0])) . '</text><text x="' . ($W - $pr) . '" y="' . ($H - 4) . '" class="axis" text-anchor="end">' . date('j. n.', strtotime(end($pts)[0])) . '</text>';
    return $s . '</svg>';
}
