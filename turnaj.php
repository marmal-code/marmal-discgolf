<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/discs.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/health.php';
migrate_if_needed();
require_login();

$id = (int)($_GET['id'] ?? 0); $e = null;
if ($id) { $e = event_get($id); if (!$e) redirect('turnaje.php'); }
$self = $id ? "turnaj.php?id=$id" : 'turnaj.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = (string)($_POST['a'] ?? '');
    $str = fn(string $k, int $n) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n);
    $date = fn(string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST[$k] ?? '')) ? (string)$_POST[$k] : null;
    $int = fn(string $k) => ($_POST[$k] ?? '') === '' ? null : (int)$_POST[$k];
    if ($a === 'save') {
        $name = $str('name', 150); if ($name === '') { flash('Vyplň název turnaje.', 'err'); redirect($self); }
        $d = $date('date'); $year = $d ? (int)substr($d, 0, 4) : (int)date('Y');
        $f = ['name' => $name, 'short' => $str('short', 40) ?: mb_substr($name, 0, 40), 'date' => $d, 'end_date' => $date('end_date'), 'year' => $year, 'venue' => $str('venue', 150) ?: null, 'city' => $str('city', 100) ?: null,
            'series' => $str('series', 80) ?: null, 'division' => $str('division', 20) ?: null, 'pdga' => isset($_POST['pdga']) ? 1 : 0, 'holes' => max(1, (int)($_POST['holes'] ?? 18)),
            'status' => ($_POST['status'] ?? '') === 'done' ? 'done' : 'plan', 'place' => $str('place', 10) ?: null, 'event_rating' => $int('event_rating'), 'pdga_rating' => $int('pdga_rating'),
            'goal' => $str('goal', 200) ?: null, 'strategy' => $str('strategy', 5000) ?: null, 'note' => $str('note', 5000) ?: null];
        if ($e && ($e['city'] ?? '') !== ($f['city'] ?? '')) { $f['lat'] = null; $f['lon'] = null; }    // nové město → znovu vyhledat souřadnice
        if ($e) {
            db()->prepare('UPDATE events SET ' . implode(', ', array_map(fn($k) => "$k=?", array_keys($f))) . ' WHERE id=?')->execute([...array_values($f), $id]);
            flash('Uloženo.');
        } else {
            $base = slugify($f['short']); $slug = $base; $n = 2;
            $chk = db()->prepare('SELECT COUNT(*) FROM events WHERE year = ? AND slug = ?');
            while (true) { $chk->execute([$year, $slug]); if (!(int)$chk->fetchColumn()) break; $slug = $base . '-' . $n++; }
            $f['slug'] = $slug; $f['unit'] = 'm';
            db()->prepare('INSERT INTO events (' . implode(',', array_keys($f)) . ') VALUES (' . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
            $id = (int)db()->lastInsertId(); event_default_todos($id); event_prefill_bag($id);
            flash('Turnaj založen – bag předvyplněn z aktuálního bagu, uprav ho níže.');
        }
        redirect("turnaj.php?id=$id");
    }
    if (!$e) redirect('turnaje.php');
    if ($a === 'bag') {
        db()->prepare('DELETE FROM event_discs WHERE event_id = ?')->execute([$id]);
        $ins = db()->prepare('INSERT IGNORE INTO event_discs (event_id, disc_id) VALUES (?,?)');
        foreach (array_unique(array_map('intval', (array)($_POST['disc'] ?? []))) as $did) if ($did > 0) $ins->execute([$id, $did]);
        flash('Bag na turnaj uložen.');
    }
    if ($a === 'bag_prefill') { event_prefill_bag($id); flash('Bag nastaven podle aktuálního bagu.'); }
    if ($a === 'todo_add' && $str('text', 200) !== '') db()->prepare('INSERT INTO event_todo (event_id, text, sort) VALUES (?,?,?)')->execute([$id, $str('text', 200), (int)db()->query('SELECT COALESCE(MAX(sort),0)+1 FROM event_todo WHERE event_id = ' . $id)->fetchColumn()]);
    if ($a === 'todo_toggle') db()->prepare('UPDATE event_todo SET done = 1 - done WHERE id = ? AND event_id = ?')->execute([(int)($_POST['tid'] ?? 0), $id]);
    if ($a === 'todo_del') db()->prepare('DELETE FROM event_todo WHERE id = ? AND event_id = ?')->execute([(int)($_POST['tid'] ?? 0), $id]);
    if ($a === 'delete') { db()->prepare('DELETE FROM events WHERE id = ?')->execute([$id]); flash('Turnaj smazán.'); redirect('turnaje.php'); }
    redirect($self . ($a === 'bag' || $a === 'bag_prefill' ? '#bag' : ($a !== 'save' ? '#plan' : '')));
}

$x = $e ?: ['name' => '', 'short' => '', 'date' => '', 'end_date' => '', 'venue' => '', 'city' => '', 'series' => '', 'division' => 'MP40', 'pdga' => 1, 'holes' => 18, 'status' => 'plan', 'place' => '', 'event_rating' => '', 'pdga_rating' => '', 'goal' => '', 'strategy' => '', 'note' => '', 'total' => 0, 'par' => 0];
$rounds = $e ? db()->query('SELECT * FROM rounds WHERE event_id = ' . $id . ' ORDER BY idx')->fetchAll() : [];
$todos = $e ? db()->query('SELECT * FROM event_todo WHERE event_id = ' . $id . ' ORDER BY done, sort, id')->fetchAll() : [];
$sel = $e ? array_flip(array_map('intval', db()->query('SELECT disc_id FROM event_discs WHERE event_id = ' . $id)->fetchAll(PDO::FETCH_COLUMN))) : [];
$discs = db()->query('SELECT * FROM discs WHERE is_sold = 0 AND is_lost = 0 ORDER BY ' . DISC_ORDER)->fetchAll();
$selDiscs = array_filter($discs, fn($d) => isset($sel[(int)$d['id']]));
$cats = []; foreach ($selDiscs as $d) $cats[$d['category']] = ($cats[$d['category']] ?? 0) + 1;

page_start($e ? $e['name'] : 'Nový turnaj', 'turnaje.php');
?>
<div class="ph"><?= $e ? h($e['short']) : 'Nový turnaj' ?> <small><a href="turnaje.php">← Turnaje</a></small></div>

<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="a" value="save">
  <h2 style="margin-top:0">Údaje</h2>
  <div class="field"><label>Název turnaje</label><input type="text" name="name" required maxlength="150" value="<?= h($x['name']) ?>" placeholder="např. PDGL #5 – Brno"></div>
  <div class="row2"><div class="field"><label>Datum (první den)</label><input type="date" name="date" value="<?= h((string)$x['date']) ?>"></div>
    <div class="field"><label>Poslední den</label><input type="date" name="end_date" value="<?= h((string)$x['end_date']) ?>"></div></div>
  <div class="field"><label>Hřiště / místo</label><input type="text" name="venue" maxlength="150" value="<?= h((string)$x['venue']) ?>"></div>
  <div class="field"><label>Město pro počasí</label><input type="text" name="city" maxlength="100" value="<?= h((string)$x['city']) ?>" placeholder="např. Kozel, Brno… (podle něj se hledá počasí)"></div>
  <div class="row2"><div class="field"><label>Série</label><input type="text" name="series" value="<?= h((string)$x['series']) ?>" placeholder="PDGL, MOVit…"></div>
    <div class="field"><label>Divize</label><input type="text" name="division" value="<?= h((string)$x['division']) ?>"></div>
    <div class="field"><label>Jamek</label><input type="number" name="holes" min="1" value="<?= (int)$x['holes'] ?>"></div></div>
  <label class="flag" style="--c:var(--line);margin-bottom:12px"><input type="checkbox" name="pdga" value="1"<?= $x['pdga'] ? ' checked' : '' ?>> PDGA turnaj (počítá se do ratingu)</label>
  <div class="field"><label>Stav</label><select name="status"><option value="plan"<?= $x['status'] === 'plan' ? ' selected' : '' ?>>Plánovaný</option><option value="done"<?= $x['status'] === 'done' ? ' selected' : '' ?>>Odehraný (zobrazí se ve statistikách)</option></select></div>
  <div class="row2"><div class="field"><label>Umístění</label><input type="text" name="place" maxlength="10" value="<?= h((string)$x['place']) ?>" placeholder="1. / T3"></div>
    <div class="field"><label>Rating turnaje</label><input type="number" name="event_rating" value="<?= h((string)$x['event_rating']) ?>"></div>
    <div class="field"><label>Můj rating tehdy</label><input type="number" name="pdga_rating" value="<?= h((string)$x['pdga_rating']) ?>"></div></div>
  <div class="field"><label>Poznámka</label><textarea name="note"><?= h((string)$x['note']) ?></textarea></div>
  <h2>Plán</h2>
  <div class="field"><label>Cíl</label><input type="text" name="goal" maxlength="200" value="<?= h((string)$x['goal']) ?>" placeholder="např. TOP 3, nebo rating kola 980+"></div>
  <div class="field"><label>Strategie, hřiště, na co si dát pozor</label><textarea name="strategy" rows="4"><?= h((string)$x['strategy']) ?></textarea></div>
  <button class="btn"><?= $e ? 'Uložit' : 'Založit turnaj' ?></button>
</form>

<?php if ($e): ?>
<section class="card" id="weather">
  <h2 style="margin-top:0">Počasí</h2>
  <p class="muted" style="margin-top:0">Předpověď (do 16 dní dopředu) nebo skutečnost z Open-Meteo podle města výše. U kola se předvyplní a můžeš ji upravit.</p>
  <button class="btn ghost" type="button" id="wxbtn" data-event="<?= $id ?>" data-date="<?= h((string)$e['date']) ?>" data-end="<?= h((string)$e['end_date']) ?>" data-csrf="<?= h(csrf()) ?>">Zobrazit počasí na turnaj</button>
  <div id="wxout" class="muted" style="margin-top:10px"></div>
</section>

<?php if ($e && $e['date']): $future = $e['date'] > date('Y-m-d'); $pp = health_prep($future ? date('Y-m-d', strtotime('+1 day')) : (string)$e['date']); ?>
<section class="card" id="priprava">
  <h2 style="margin-top:0">Příprava <small class="muted" style="font-weight:500"><?= $future ? 'poslední 4 týdny' : '4 týdny před turnajem' ?></small></h2>
  <?php if ($pp['has'] || $pp['planned']): ?>
    <div class="pills"><span class="pill">Posilovna <?= $pp['gym'] ?>×</span><span class="pill">Putting cvičení <?= $pp['drill'] ?>×<?= $pp['drill_min'] ? ' · ' . $pp['drill_min'] . ' min' : '' ?></span><?= $pp['pct'] !== null ? '<span class="pill ' . ($pp['pct'] >= 70 ? 'g' : ($pp['pct'] < 40 ? 'b' : '')) . '">Plán splněn ' . $pp['pct'] . ' %</span>' : '' ?><?= $pp['skip'] ? '<span class="pill b">Vynecháno ' . $pp['skip'] . '×</span>' : '' ?></div>
  <?php else: ?><p class="sub" style="margin:0">Z tohoto období nemám data o tréninku.</p><?php endif; ?>
  <p class="sub" style="margin:8px 0 0"><a href="priprava.php">Příprava a výsledky ve všech turnajích →</a></p>
</section>

<?php endif; ?>
<section class="card" id="plan">
  <h2 style="margin-top:0">Co zařídit</h2>
  <?php foreach ($todos as $t): ?>
    <form method="post" style="display:flex;gap:8px;align-items:center;margin:0 0 6px"><?= csrf_field() ?><input type="hidden" name="tid" value="<?= (int)$t['id'] ?>">
      <button class="btn sm ghost" name="a" value="todo_toggle" style="min-width:44px" aria-label="Splněno"><?= $t['done'] ? '☑' : '☐' ?></button>
      <span style="flex:1<?= $t['done'] ? ';text-decoration:line-through;opacity:.6' : '' ?>"><?= h($t['text']) ?></span>
      <button class="btn sm danger" name="a" value="todo_del" aria-label="Smazat">×</button></form>
  <?php endforeach; ?>
  <form method="post" style="display:flex;gap:8px;margin-top:8px"><?= csrf_field() ?><input type="hidden" name="a" value="todo_add"><input type="text" name="text" maxlength="200" placeholder="Nový úkol…" style="flex:1"><button class="btn">Přidat</button></form>
</section>

<section class="card" id="bag">
  <h2 style="margin-top:0">Bag na turnaj <small class="muted" style="font-weight:500"><?= count($selDiscs) ?> disků</small></h2>
  <?php if ($cats): ?><div class="pills" style="margin-bottom:10px"><?php foreach ($cats as $c => $n) echo '<span class="pill">' . $n . '× ' . h(category_name($c)) . '</span>'; ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="bag">
    <div class="chips" style="gap:8px">
    <?php foreach ($discs as $d): $pt = disc_tags($d)[0] ?? null; ?>
      <label class="flag" style="--c:var(<?= $pt ? '--t-' . $pt : '--line' ?>);padding:6px 10px"><input type="checkbox" name="disc[]" value="<?= (int)$d['id'] ?>"<?= isset($sel[(int)$d['id']]) ? ' checked' : '' ?>><span><?= h($d['model']) ?> <small style="opacity:.7"><?= num((float)$d['speed']) ?>/<?= num((float)$d['glide']) ?>/<?= num((float)$d['turn']) ?>/<?= num((float)$d['fade']) ?></small></span></label>
    <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><button class="btn">Uložit bag na turnaj</button><button class="btn ghost" name="a" value="bag_prefill" formnovalidate>Nastavit podle mého bagu</button></div>
  </form>
</section>

<section class="card" id="rounds">
  <h2 style="margin-top:0">Kola</h2>
  <?php if (!$rounds) echo '<p class="muted">Zatím žádné kolo.</p>'; ?>
  <?php foreach ($rounds as $r): ?>
    <a href="kolo.php?event=<?= $id ?>&id=<?= (int)$r['id'] ?>" class="card ev" style="text-decoration:none;display:block;margin-bottom:8px">
      <div class="row"><strong>Kolo <?= (int)$r['idx'] + 1 ?><?= $r['rdate'] ? ' · ' . h(fmt_date($r['rdate'])) : '' ?></strong><span class="place"><?= $r['score'] > 0 ? '+' . $r['score'] : ($r['score'] < 0 ? '−' . abs((int)$r['score']) : 'E') ?></span></div>
      <div class="sub"><?= (int)$r['throws'] ?> hodů<?= $r['rating'] ? ' · rating ' . (int)$r['rating'] : '' ?><?= weather_line($r) ? ' · ' . h(weather_line($r)) : '' ?></div></a>
  <?php endforeach; ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" href="kolo.php?event=<?= $id ?>">+ Přidat kolo</a><a class="btn ghost" href="import.php?event=<?= $id ?>">Načíst z PDGA Live</a></div>
  <?php if ($rounds): ?><p class="sub" style="margin-bottom:0">Celkem <?= $e['total'] > 0 ? '+' . $e['total'] : ($e['total'] < 0 ? '−' . abs((int)$e['total']) : 'E') ?> po <?= count($rounds) ?> kolech. Statistiky se přepočítají samy.</p><?php endif; ?>
</section>

<form method="post" onsubmit="return confirm('Opravdu smazat celý turnaj včetně kol?')"><?= csrf_field() ?><button class="btn danger" name="a" value="delete">Smazat turnaj</button></form>
<script src="<?= h(asset('assets/weather.js')) ?>"></script>
<?php endif; ?>
<?php page_end('turnaje.php');
