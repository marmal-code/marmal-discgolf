<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/events.php';
migrate_if_needed();
require_login();

$eid = (int)($_GET['event'] ?? 0); $e = event_get($eid); if (!$e) redirect('turnaje.php');
$rid = (int)($_GET['id'] ?? 0); $r = null;
if ($rid) { $q = db()->prepare('SELECT * FROM rounds WHERE id = ? AND event_id = ?'); $q->execute([$rid, $eid]); $r = $q->fetch() ?: null; if (!$r) redirect("turnaj.php?id=$eid"); }
$back = "turnaj.php?id=$eid#rounds";

const PAIRS = ['fw' => 'Fairway (zásahy / pokusy)', 'sc' => 'Scramble', 'c1r' => 'C1 v regulaci', 'c2r' => 'C2 v regulaci', 'c1x' => 'C1X putty (do 10 m)', 'c2' => 'C2 putty (10–20 m)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['a'] ?? '') === 'delete' && $r) { db()->prepare('DELETE FROM rounds WHERE id = ?')->execute([$rid]); event_recalc($eid); flash('Kolo smazáno.'); redirect($back); }
    $n = fn(string $k) => (($_POST[$k] ?? '') === '' || !is_numeric($_POST[$k])) ? null : (int)$_POST[$k];
    $f = fn(string $k) => (($_POST[$k] ?? '') === '' || !is_numeric(str_replace(',', '.', (string)$_POST[$k]))) ? null : (float)str_replace(',', '.', (string)$_POST[$k]);
    $throws = $n('throws'); $par = $n('par'); $idx = max(1, (int)($_POST['idx'] ?? 1)) - 1;
    $rdate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['rdate'] ?? '')) ? (string)$_POST['rdate'] : null;
    if ($throws === null || $par === null || $throws < 1) { flash('Vyplň počet hodů a par kola.', 'err'); redirect($_SERVER['REQUEST_URI']); }
    $day = null;
    if ($rdate && $e['end_date'] && $e['date'] && $e['end_date'] > $e['date']) $day = ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'][(int)date('w', strtotime($rdate))];
    $v = ['idx' => $idx, 'score' => $throws - $par, 'throws' => $throws, 'par' => $par, 'rating' => $n('rating'), 'day' => $day, 'rdate' => $rdate];
    foreach (PAIRS as $k => $_) { $v[$k . '_h'] = $n($k . '_h'); $v[$k . '_n'] = $n($k . '_n'); }
    $v += ['avg_putt' => $f('avg_putt'), 'long_putt' => $f('long_putt'), 'ob' => $n('ob'),
        'w_temp' => $f('w_temp'), 'w_wind' => $f('w_wind'), 'w_gust' => $f('w_gust'), 'w_rain' => $f('w_rain'),
        'w_cond' => mb_substr(trim((string)($_POST['w_cond'] ?? '')), 0, 40) ?: null, 'w_src' => in_array($_POST['w_src'] ?? '', ['auto', 'manual'], true) ? $_POST['w_src'] : null,
        'note' => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 3000) ?: null];
    $dup = db()->prepare('SELECT COUNT(*) FROM rounds WHERE event_id = ? AND idx = ? AND id <> ?'); $dup->execute([$eid, $idx, $rid]);
    if ((int)$dup->fetchColumn()) { flash('Kolo číslo ' . ($idx + 1) . ' už u turnaje existuje.', 'err'); redirect($_SERVER['REQUEST_URI']); }
    if ($r) db()->prepare('UPDATE rounds SET ' . implode(', ', array_map(fn($k) => "$k=?", array_keys($v))) . ' WHERE id=?')->execute([...array_values($v), $rid]);
    else { $v['event_id'] = $eid; db()->prepare('INSERT INTO rounds (' . implode(',', array_keys($v)) . ') VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')')->execute(array_values($v)); }
    event_recalc($eid);
    if ($e['status'] === 'plan') { db()->prepare('UPDATE events SET status = "done" WHERE id = ?')->execute([$eid]); flash('Kolo uloženo. Turnaj je teď označený jako odehraný a je ve statistikách.'); }
    else flash('Kolo uloženo.');
    redirect($back);
}

$cnt = (int)db()->query('SELECT COUNT(*) FROM rounds WHERE event_id = ' . $eid)->fetchColumn();
$last = db()->query('SELECT par FROM rounds WHERE event_id = ' . $eid . ' AND par IS NOT NULL ORDER BY idx DESC LIMIT 1')->fetchColumn();
$x = $r ?: array_fill_keys(['throws', 'rating', 'avg_putt', 'long_putt', 'ob', 'w_temp', 'w_wind', 'w_gust', 'w_rain', 'w_cond', 'w_src', 'note'], '') + ['idx' => $cnt, 'rdate' => $e['date'], 'par' => $last ?: ($e['par'] ?: 60)] + array_fill_keys(array_merge(...array_map(fn($k) => [$k . '_h', $k . '_n'], array_keys(PAIRS))), '');
if ($r && $r['par'] === null) $x['par'] = (int)$r['throws'] - (int)$r['score'];
page_start('Kolo', 'turnaje.php');
?>
<div class="ph"><?= $r ? 'Kolo ' . ((int)$r['idx'] + 1) : 'Nové kolo' ?> <small><a href="<?= h($back) ?>">← <?= h($e['short']) ?></a></small></div>
<form method="post" class="card"><?= csrf_field() ?>
  <div class="row2"><div class="field"><label>Kolo č.</label><input type="number" name="idx" min="1" value="<?= (int)$x['idx'] + 1 ?>"></div>
    <div class="field"><label>Datum kola</label><input type="date" name="rdate" value="<?= h((string)$x['rdate']) ?>"></div></div>
  <div class="row2"><div class="field"><label>Hody celkem</label><input type="number" name="throws" id="throws" min="1" required value="<?= h((string)$x['throws']) ?>" inputmode="numeric"></div>
    <div class="field"><label>Par kola</label><input type="number" name="par" id="par" min="1" required value="<?= h((string)$x['par']) ?>" inputmode="numeric"></div>
    <div class="field"><label>Skóre</label><output id="score" class="kpi" style="padding:8px 12px;font:700 22px var(--font-d)">–</output></div></div>
  <div class="field"><label>Rating kola (z PDGA Live, nepovinné)</label><input type="number" name="rating" value="<?= h((string)$x['rating']) ?>" inputmode="numeric"></div>

  <fieldset id="wxbox" style="border:1px solid var(--line);border-radius:12px;padding:12px;margin:0 0 12px">
    <legend class="lbl" style="padding:0 6px">Počasí</legend>
    <div class="row2"><div class="field"><label>Od hodiny</label><input type="number" name="wfrom" min="0" max="23" value="9"></div><div class="field"><label>Do hodiny</label><input type="number" name="wto" min="0" max="23" value="16"></div></div>
    <button type="button" class="btn ghost sm" id="wxload" data-event="<?= $eid ?>">Načíst počasí podle data</button> <span class="muted" id="wxmsg"></span>
    <input type="hidden" name="w_src" value="<?= h((string)$x['w_src']) ?>">
    <div class="row2" style="margin-top:10px"><div class="field"><label>Teplota °C</label><input type="number" step="0.1" name="w_temp" value="<?= h((string)$x['w_temp']) ?>"></div>
      <div class="field"><label>Vítr m/s</label><input type="number" step="0.1" name="w_wind" value="<?= h((string)$x['w_wind']) ?>"></div>
      <div class="field"><label>Nárazy m/s</label><input type="number" step="0.1" name="w_gust" value="<?= h((string)$x['w_gust']) ?>"></div>
      <div class="field"><label>Srážky mm</label><input type="number" step="0.1" name="w_rain" value="<?= h((string)$x['w_rain']) ?>"></div></div>
    <div class="field" style="margin-bottom:0"><label>Podmínky</label><input type="text" name="w_cond" maxlength="40" value="<?= h((string)$x['w_cond']) ?>" placeholder="déšť, jasno, mokrá tráva…"></div>
  </fieldset>

  <details style="margin-bottom:12px"><summary style="cursor:pointer;font-weight:600">Statistiky kola z PDGA Live (nepovinné)</summary>
    <div style="margin-top:10px">
    <?php foreach (PAIRS as $k => $label): ?>
      <div class="field"><label><?= h($label) ?></label><div class="row2" style="grid-template-columns:1fr auto 1fr;align-items:center"><input type="number" min="0" name="<?= $k ?>_h" value="<?= h((string)$x[$k . '_h']) ?>" placeholder="zásahů" inputmode="numeric"><span>z</span><input type="number" min="0" name="<?= $k ?>_n" value="<?= h((string)$x[$k . '_n']) ?>" placeholder="pokusů" inputmode="numeric"></div></div>
    <?php endforeach; ?>
      <div class="row2"><div class="field"><label>Průměrný putt (m)</label><input type="text" name="avg_putt" value="<?= h((string)$x['avg_putt']) ?>" inputmode="decimal"></div>
        <div class="field"><label>Nejdelší putt (m)</label><input type="text" name="long_putt" value="<?= h((string)$x['long_putt']) ?>" inputmode="decimal"></div>
        <div class="field"><label>OB hody</label><input type="number" min="0" name="ob" value="<?= h((string)$x['ob']) ?>" inputmode="numeric"></div></div>
    </div></details>
  <div class="field"><label>Poznámka ke kolu</label><textarea name="note" rows="3" placeholder="Co fungovalo, co ne, které disky…"><?= h((string)$x['note']) ?></textarea></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn">Uložit kolo</button><a class="btn ghost" href="<?= h($back) ?>">Zrušit</a></div>
</form>
<?php if ($r): ?><form method="post" onsubmit="return confirm('Smazat toto kolo?')"><?= csrf_field() ?><button class="btn danger" name="a" value="delete">Smazat kolo</button></form><?php endif; ?>
<script>
(function(){var t=document.getElementById('throws'),p=document.getElementById('par'),o=document.getElementById('score');
function u(){var a=parseInt(t.value,10),b=parseInt(p.value,10);if(isNaN(a)||isNaN(b)){o.textContent='–';return;}var s=a-b;o.textContent=s>0?'+'+s:s<0?'−'+Math.abs(s):'E';}
t.addEventListener('input',u);p.addEventListener('input',u);u();})();
</script>
<script src="<?= h(asset('assets/weather.js')) ?>"></script>
<?php page_end('turnaje.php');
