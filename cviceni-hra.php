<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/drills.php';
migrate_if_needed();
require_login();

$key = (string)($_GET['k'] ?? $_POST['drill'] ?? '');
if (!isset(DRILLS[$key])) redirect('cviceni.php');
$D = DRILLS[$key];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = (string)($_POST['a'] ?? '');
    if ($a === 'save') {
        $sum = json_decode((string)($_POST['summary'] ?? ''), true); $ev = json_decode((string)($_POST['events'] ?? ''), true);
        if (!is_array($sum) || !is_array($ev) || !$ev) { flash('Nebylo co uložit.', 'err'); redirect("cviceni-hra.php?k=$key"); }
        $var = array_key_exists((string)($_POST['variant'] ?? ''), $D['variants']) ? (string)$_POST['variant'] : 'normal';
        $score = (float)($_POST['score'] ?? 0);
        [, $record] = drill_save($key, $var, $score, (int)($_POST['minutes'] ?? 1), $sum, $ev);
        flash('Uloženo: ' . drill_num($score) . ' ' . $D['unit'] . ($record ? ' – nový osobní rekord! 🏆' : ''));
        redirect("cviceni-hra.php?k=$key&v=$var");
    }
    if ($a === 'del') {
        require_once __DIR__ . '/inc/health.php';
        $q = db()->prepare('SELECT * FROM drill_sessions WHERE id = ?'); $q->execute([(int)($_POST['id'] ?? 0)]); $r = $q->fetch();
        if ($r) {
            try { health_delete_drill($r); } catch (Throwable $e) {}
            db()->prepare('DELETE FROM training_log WHERE src = "cviceni" AND tdate = ? AND note LIKE ?')->execute([$r['d'], 'Cvičení: ' . $D['name'] . '%']);
            db()->prepare('DELETE FROM drill_sessions WHERE id = ?')->execute([(int)$r['id']]);
        }
        redirect("cviceni-hra.php?k=$key");
    }
}

$var = array_key_exists((string)($_GET['v'] ?? ''), $D['variants']) ? (string)$_GET['v'] : 'normal';
$sessions = drill_list($key, 40, $var);
$sum = array_map(function ($r) { $j = json_decode((string)$r['summary'], true); return is_array($j) ? $j : []; }, $sessions);
$q = db()->prepare('SELECT variant, COUNT(*) n FROM drill_sessions WHERE drill = ? GROUP BY variant'); $q->execute([$key]); $played = $q->fetchAll(PDO::FETCH_KEY_PAIR);

function avg_of(array $rows, callable $f, int $n = 0): ?float {
    if ($n) $rows = array_slice($rows, 0, $n);
    $v = array_values(array_filter(array_map($f, $rows), fn($x) => $x !== null));
    return $v ? array_sum($v) / count($v) : null;
}
$fmt = fn($v, $d = 0, $suf = '') => $v === null ? '–' : drill_num((float)$v, $d) . $suf;
$best = drill_best($key, $var);
$stats = [['Nejlepší', $fmt($best, 1, ' ' . $D['unit'])], ['Průměr posledních 5', $fmt(avg_of($sessions, fn($r) => (float)$r['score'], 5), 1, ' ' . $D['unit'])], ['Odehráno', count($sessions) . '×']];
try { switch ($key) {
    case 'zlata_zona':
        $stats[] = ['Do zóny (průměr)', $fmt(avg_of($sum, fn($s) => $s['zonePct'] ?? null), 0, ' %')];
        $stats[] = ['Zóna BH / FH', $fmt(avg_of($sum, fn($s) => $s['bhZone'] ?? null), 0, ' %') . ' / ' . $fmt(avg_of($sum, fn($s) => $s['fhZone'] ?? null), 0, ' %')];
        $stats[] = ['OB (průměr)', $fmt(avg_of($sum, fn($s) => $s['obPct'] ?? null), 0, ' %')];
        foreach ([30, 40, 50] as $d) $stats[] = ['Body z ' . $d . ' m (průměr za hru)', $fmt(avg_of($sum, fn($s) => $s['dist'][$d] ?? null), 1)];
        break;
    case 'must_make':
        $stats[] = ['Přihoz do C1', $fmt(avg_of($sum, fn($s) => $s['c1AppPct'] ?? null), 0, ' %')];
        $stats[] = ['Putt v C1 po přihozu', $fmt(avg_of($sum, fn($s) => $s['c1PuttPct'] ?? null), 0, ' %')];
        $stats[] = ['Přihoz mimo C2', $fmt(avg_of($sum, fn($s) => $s['outPct'] ?? null), 0, ' %')];
        break;
    case 'dvacetjedna':
        $stats[] = ['Resetů (průměr)', $fmt(avg_of($sum, fn($s) => $s['resets'] ?? null), 1)];
        $stats[] = ['Úspěšnost puttů', $fmt(avg_of($sum, fn($s) => $s['pct'] ?? null), 0, ' %')];
        foreach ([5, 7, 9] as $d) $stats[] = ['Z ' . $d . ' m', $fmt(avg_of($sum, fn($s) => ($s['by'][$d] ?? 0) ?: null), 0, ' %')];
        break;
    case 'tlakovy_hrnec':
        $stats[] = ['Návratů zpět (průměr)', $fmt(avg_of($sum, fn($s) => $s['backs'] ?? null), 1)];
        $stats[] = ['Úspěšnost puttů', $fmt(avg_of($sum, fn($s) => $s['pct'] ?? null), 0, ' %')];
        $miss = [5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0];
        foreach ($sum as $s) foreach (($s['miss'] ?? []) as $d => $m) $miss[(int)$d] = ($miss[(int)$d] ?? 0) + $m;
        arsort($miss); $stats[] = ['Nejvíc chybuješ na', array_sum($miss) ? key($miss) . ' m (' . current($miss) . '× minul)' : '–'];
        break;
    case 'risk':
        $stats[] = ['Hry bez missu', array_sum(array_map(fn($s) => (int)($s['clean'] ?? 0), $sum)) . '×'];
        foreach ([5, 6, 7] as $d) { $n = array_sum(array_map(fn($s) => (int)($s['ch'][$d] ?? 0), $sum)); $stats[] = ['Volba ' . $d . ' m', $n . '× · úspěšnost ' . $fmt(avg_of($sum, fn($s) => ($s['ch'][$d] ?? 0) ? ($s['pct'][$d] ?? null) : null), 0, ' %')]; }
        break;
} } catch (Throwable $e) { /* statistiky jsou doplněk, hra musí fungovat vždy */ }
page_start($D['name'], 'cviceni.php');
?>
<div class="ph"><?= $D['icon'] ?> <?= h($D['name']) ?> <small><a href="cviceni.php">← Cvičení</a></small></div>

<section class="card drill" id="drill" data-drill="<?= h($key) ?>">
  <div id="drill-start">
    <p class="sub" style="margin-top:0"><?= h($D['kind']) ?> · asi <?= (int)$D['minutes'] ?> min</p>
    <?php if ($D['variants']): ?>
      <div class="field"><label for="variant">Varianta</label><select id="variant"><?php foreach ($D['variants'] as $vk => $vl): ?><option value="<?= h($vk) ?>" <?= $vk === $var ? 'selected' : '' ?>><?= h($vl) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <button type="button" class="btn block" id="d-go">▶ Začít</button>
    <p class="sub" style="margin-bottom:0">Rozehraná hra se ukládá průběžně v telefonu – když zavřeš stránku, pokračuješ tam, kde jsi skončil.</p>
  </div>
  <div id="drill-play" hidden>
    <div class="resume" id="d-resume" hidden>Rozehraná hra (začátek <span id="d-resume-t"></span>) – pokračuješ tam, kde jsi skončil. <button type="button" class="btn sm ghost" id="d-restart">Začít znovu</button></div>
    <div class="dstatus"><div class="dbig"><b id="d-big">0</b><span id="d-biglabel"></span></div>
      <div class="dinfo"><div id="d-line"></div><div id="d-sub" class="sub"></div><div id="d-extra" class="sub"></div></div></div>
    <div id="d-buttons" class="dbtns"></div>
    <div class="dlog sub" id="d-log"></div>
    <div class="dctl"><button type="button" class="btn sm ghost" id="d-undo">↶ Zpět</button><button type="button" class="btn sm danger" id="d-cancel">Zahodit</button><button type="button" class="btn sm" id="d-finish">💾 Uložit výsledek</button></div>
    <p class="sub" id="d-hint"></p>
  </div>
  <form method="post" id="d-save" hidden><?= csrf_field() ?><input type="hidden" name="a" value="save"><input type="hidden" name="drill" value="<?= h($key) ?>">
    <input type="hidden" name="variant"><input type="hidden" name="events"><input type="hidden" name="summary"><input type="hidden" name="score"><input type="hidden" name="minutes"></form>
</section>
<script src="<?= h(asset('assets/drills.js')) ?>"></script>
<?php ob_start(); try { ?>
<section class="card"><h3 style="margin:0 0 6px">Statistiky <small class="muted" style="font-weight:500"><?= $D['better'] === 'up' ? '↑ víc je lépe' : '↓ méně je lépe' ?></small></h3>
  <?php if ($D['variants'] && count($played) > 0): ?><div class="pills" style="margin-bottom:8px"><?php foreach ($D['variants'] as $vk => $vl): if (!isset($played[$vk]) && $vk !== $var) continue; ?>
    <a class="pill<?= $vk === $var ? ' g' : '' ?>" href="cviceni-hra.php?k=<?= h($key) ?>&v=<?= h($vk) ?>" style="text-decoration:none;color:inherit"><?= h(explode(' (', $vl)[0]) ?> (<?= (int)($played[$vk] ?? 0) ?>)</a><?php endforeach; ?></div><?php endif; ?>
  <div class="sub" style="margin-bottom:4px"><?= h($D['metric']) ?><?= $D['variants'] ? ' · ' . h($D['variants'][$var]) : '' ?></div>
  <?= line_chart(drill_points($key, 30, $var), $D['unit'], 150) ?>
  <div class="statlist"><?php foreach ($stats as [$l, $v]): ?><div><span><?= h($l) ?></span><b><?= $v ?></b></div><?php endforeach; ?></div>
</section>

<section class="card"><h3 style="margin:0 0 6px">Historie <small class="muted" style="font-weight:500"><?= count($sessions) ? count($sessions) . '×' : '' ?></small></h3>
  <?php if (!$sessions) echo '<p class="sub" style="margin:0">Zatím žádná odehraná hra.</p>'; ?>
  <?php foreach (array_slice($sessions, 0, 20) as $i => $r): $s = $sum[$i]; ?>
    <form method="post" class="histrow" onsubmit="return confirm('Smazat tento záznam?')"><?= csrf_field() ?><input type="hidden" name="a" value="del"><input type="hidden" name="drill" value="<?= h($key) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <span class="grow"><b><?= h(drill_num((float)$r['score'])) ?> <?= h($D['unit']) ?></b> <small class="muted"><?= h(date('j. n. Y', strtotime($r['d']))) ?><?= $r['variant'] !== 'normal' && isset($D['variants'][$r['variant']]) ? ' · ' . h(explode(' (', $D['variants'][$r['variant']])[0]) : '' ?> · <?= (int)$r['minutes'] ?> min<?= $r['src'] === 'zdravi' ? ' · ze Zdraví' : (!$r['sent'] && health_conf_ok() ? ' · čeká na odeslání' : '') ?></small><?= $s ? '<br><small class="muted">' . h(drill_detail($key, $s)) . '</small>' : '' ?></span>
      <button class="btn sm danger" aria-label="Smazat">×</button></form>
  <?php endforeach; ?>
</section>

<section class="card"><h3 style="margin:0 0 6px">📖 Popis cvičení</h3>
  <details<?= !$sessions ? ' open' : '' ?>><summary class="btn sm ghost">Zobrazit pravidla</summary>
    <p><?= h($D['about']) ?></p>
    <h4>Co budeš potřebovat</h4><ul class="sub"><?php foreach ($D['need'] as $x) echo '<li>' . h($x) . '</li>'; ?></ul>
    <h4>Příprava</h4><p class="sub"><?= h($D['setup']) ?></p>
    <h4>Jak se hraje</h4><ol class="sub"><?php foreach ($D['rules'] as $x) echo '<li>' . h($x) . '</li>'; ?></ol>
    <h4>Bodování</h4><ul class="sub"><?php foreach ($D['scoring'] as $x) echo '<li>' . h($x) . '</li>'; ?></ul>
    <h4>Na co se soustředit</h4><ul class="sub"><?php foreach ($D['tips'] as $x) echo '<li>' . h($x) . '</li>'; ?></ul>
    <h4>Jak měřit pokrok</h4><ul class="sub"><?php foreach ($D['progress'] as $x) echo '<li>' . h($x) . '</li>'; ?></ul>
    <p class="sub">Podle tréninkových materiálů Discgolf klubu Kladno (Horníci Kladno).</p></details>
</section>
<?php ob_end_flush(); } catch (Throwable $e) { ob_end_clean(); echo '<section class="card"><p class="sub" style="margin:0">Statistiky a historii se teď nepodařilo zobrazit.</p></section>'; } ?>
<?php page_end('cviceni.php');
