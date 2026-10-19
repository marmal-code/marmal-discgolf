<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/training.php';
migrate_if_needed();
require_login();

const PLAN_EXAMPLE = "# Plán na zimu\n## Základ | 3. 11. 2026 | 31. 12. 2026\nSíla a základ techniky.\n- denně | Ranní putting | 20 min | 5 min zahřátí, 10 min tlakové hry\n- út, pá | Posilovna | 60 min | nohy, core, záda\n- so | Trénink venku | 90 min | disk a rutina před hodem\n## Ostření | 1. 3. 2027 | 31. 3. 2027\n- po-pá | Ranní putting | 15 min\n- so | Tréninkový turnajový den | 120 min | kola včetně warm-upu";

$preview = null; $errors = []; $text = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = (string)($_POST['a'] ?? '');
    $str = fn(string $k, int $n) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n);
    if ($a === 'import_preview') {
        $text = (string)($_POST['text'] ?? ''); $r = tr_parse_plan($text);
        if (isset($r['errors'])) $errors = $r['errors']; else { $preview = $r; $_SESSION['tr_import'] = $r; }
    } elseif ($a === 'import_save' && !empty($_SESSION['tr_import'])) {
        tr_save_plan($_SESSION['tr_import'], ($_POST['mode'] ?? 'replace') !== 'add'); unset($_SESSION['tr_import']);
        flash('Plán nahrán a nastaven jako aktivní.'); redirect('trenink-plan.php');
    } else {
        if ($a === 'plan_activate') { db()->exec('UPDATE training_plans SET active = 0'); db()->prepare('UPDATE training_plans SET active = 1 WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]); flash('Plán aktivován.'); }
        if ($a === 'plan_del') { db()->prepare('DELETE FROM training_plans WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]); flash('Plán smazán (záznamy odtrénovaného zůstávají).'); }
        if ($a === 'plan_new' && $str('name', 150) !== '') { db()->exec('UPDATE training_plans SET active = 0'); db()->prepare('INSERT INTO training_plans (name, active, created) VALUES (?,1,?)')->execute([$str('name', 150), now()]); flash('Plán založen.'); }
        if ($a === 'phase_add' && $str('name', 150) !== '') {
            $dt = fn($k) => tr_date((string)($_POST[$k] ?? '')); $pl = (int)($_POST['plan'] ?? 0);
            db()->prepare('INSERT INTO training_phases (plan_id, name, start_date, end_date, descr, sort) VALUES (?,?,?,?,?,?)')->execute([$pl, $str('name', 150), $dt('start'), $dt('end'), $str('descr', 2000) ?: null, (int)db()->query('SELECT COALESCE(MAX(sort),0)+1 FROM training_phases WHERE plan_id = ' . $pl)->fetchColumn()]);
        }
        if ($a === 'phase_del') db()->prepare('DELETE FROM training_phases WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        if ($a === 'item_add' && $str('title', 200) !== '') {
            $days = implode(',', array_values(array_filter(array_map('intval', (array)($_POST['days'] ?? [])), fn($d) => $d >= 1 && $d <= 7)));
            $kind = isset(TR_KINDS[(string)($_POST['kind'] ?? '')]) ? (string)$_POST['kind'] : tr_guess_kind($str('title', 200)); $pid = (int)($_POST['phase'] ?? 0);
            if ($days !== '') db()->prepare('INSERT INTO training_items (phase_id, days, title, minutes, kind, details, sort) VALUES (?,?,?,?,?,?,?)')->execute([$pid, $days, $str('title', 200), ($_POST['minutes'] ?? '') === '' ? null : (int)$_POST['minutes'], $kind, $str('details', 1000) ?: null, (int)db()->query('SELECT COALESCE(MAX(sort),0)+1 FROM training_items WHERE phase_id = ' . $pid)->fetchColumn()]);
            else flash('Vyber aspoň jeden den.', 'err');
        }
        if ($a === 'item_del') db()->prepare('DELETE FROM training_items WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        redirect('trenink-plan.php');
    }
}

$plan = tr_plan();
$plans = db()->query('SELECT * FROM training_plans ORDER BY active DESC, id DESC')->fetchAll();
$phases = $plan ? db()->query('SELECT * FROM training_phases WHERE plan_id = ' . (int)$plan['id'] . ' ORDER BY COALESCE(start_date, "9999-12-31"), sort, id')->fetchAll() : [];
$items = []; foreach ($phases as $p) { $q = db()->prepare('SELECT * FROM training_items WHERE phase_id = ? ORDER BY sort, id'); $q->execute([$p['id']]); $items[$p['id']] = $q->fetchAll(); }
page_start('Tréninkový plán', 'trenink.php');
?>
<div class="ph">Tréninkový plán <small><a href="trenink.php">← Trénink</a></small></div>

<?php if ($preview): ?>
<section class="card" id="prev">
  <h2 style="margin-top:0">Náhled: <?= h($preview['name']) ?></h2>
  <?php foreach ($preview['phases'] as $f): ?>
    <h3 style="margin:12px 0 4px"><?= h($f['name']) ?> <span class="muted" style="font-weight:500"><?= $f['start'] ? tr_cz_date($f['start']) : '' ?><?= $f['end'] ? ' – ' . tr_cz_date($f['end']) : '' ?></span></h3>
    <?php if ($f['descr']) echo '<p class="sub" style="margin:0 0 6px">' . nl2br(h($f['descr'])) . '</p>'; ?>
    <?php foreach ($f['items'] as $x) echo '<div class="trrow k-' . h($x['kind']) . '"><div class="trmain"><div class="trt"><span class="kd"></span><b>' . h($x['title']) . '</b> <span class="muted">' . h(tr_days_label($x['days'])) . ($x['minutes'] ? ' · ' . (int)$x['minutes'] . ' min' : '') . '</span></div>' . ($x['details'] ? '<div class="sub">' . h($x['details']) . '</div>' : '') . '</div></div>'; ?>
  <?php endforeach; ?>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px"><?= csrf_field() ?><input type="hidden" name="a" value="import_save">
    <button class="btn" name="mode" value="replace">Uložit a nahradit aktivní plán</button><button class="btn ghost" name="mode" value="add">Uložit jako další plán</button></form>
  <p class="sub" style="margin-bottom:0">Dosavadní záznamy odtrénovaného zůstanou. Starý plán se schová do archivu.</p>
</section>
<?php endif; ?>

<?php if ($plan): ?>
<section class="card">
  <h2 style="margin-top:0"><?= h($plan['name']) ?> <span class="pill g">aktivní</span></h2>
  <?php if ($plan['note']) echo '<p class="muted" style="margin-top:0">' . h($plan['note']) . '</p>'; ?>
  <?php foreach ($phases as $f): $now = date('Y-m-d'); $cur = (!$f['start_date'] || $f['start_date'] <= $now) && (!$f['end_date'] || $f['end_date'] >= $now); ?>
    <div class="phase<?= $cur ? ' cur' : '' ?>">
      <div style="display:flex;justify-content:space-between;gap:8px;align-items:baseline;flex-wrap:wrap"><h3 style="margin:0"><?= h($f['name']) ?><?= $cur ? ' <span class="pill g">právě teď</span>' : '' ?></h3>
        <span class="muted"><?= $f['start_date'] ? tr_cz_date($f['start_date']) : '' ?><?= $f['end_date'] ? ' – ' . tr_cz_date($f['end_date']) . ' ' . date('Y', strtotime($f['end_date'])) : '' ?></span></div>
      <?php if ($f['descr']) echo '<p class="sub" style="margin:4px 0 6px">' . nl2br(h($f['descr'])) . '</p>'; ?>
      <?php foreach ($items[$f['id']] as $x): ?>
        <div class="trrow k-<?= h($x['kind']) ?>"><div class="trmain"><div class="trt"><span class="kd"></span><b><?= h($x['title']) ?></b> <span class="muted"><?= h(tr_days_label($x['days'])) ?><?= $x['minutes'] ? ' · ' . (int)$x['minutes'] . ' min' : '' ?></span></div><?php if ($x['details']) echo '<div class="sub">' . h($x['details']) . '</div>'; ?></div>
          <div class="trctl"><form method="post" onsubmit="return confirm('Smazat položku?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn sm danger" name="a" value="item_del" aria-label="Smazat">×</button></form></div></div>
      <?php endforeach; ?>
      <details class="skipbox" style="margin-top:8px"><summary class="btn sm ghost">+ Položka do fáze</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="item_add"><input type="hidden" name="phase" value="<?= (int)$f['id'] ?>">
          <input type="text" name="title" maxlength="200" placeholder="Název (např. Ranní putting)" required>
          <div class="chips"><?php foreach (TR_DAYS as $n => $l) echo '<label class="chip" style="cursor:pointer"><input type="checkbox" name="days[]" value="' . $n . '"> ' . $l . '</label>'; ?></div>
          <div class="row2"><input type="number" name="minutes" min="0" placeholder="minut"><select name="kind"><?php foreach (TR_KINDS as $k => $l) echo '<option value="' . $k . '">' . h($l) . '</option>'; ?></select></div>
          <input type="text" name="details" maxlength="1000" placeholder="Popis (nepovinné)"><button class="btn sm">Přidat</button></form></details>
      <form method="post" onsubmit="return confirm('Smazat celou fázi včetně položek?')" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn sm danger" name="a" value="phase_del">Smazat fázi</button></form>
    </div>
  <?php endforeach; ?>
  <details class="skipbox" style="margin-top:12px"><summary class="btn sm ghost">+ Nová fáze</summary>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="phase_add"><input type="hidden" name="plan" value="<?= (int)$plan['id'] ?>">
      <input type="text" name="name" maxlength="150" placeholder="Název fáze" required>
      <div class="row2"><input type="text" name="start" placeholder="od (3. 11. 2026)"><input type="text" name="end" placeholder="do (31. 12. 2026)"></div>
      <textarea name="descr" rows="2" placeholder="Popis (nepovinné)"></textarea><button class="btn sm">Přidat fázi</button></form></details>
</section>
<?php endif; ?>

<section class="card">
  <h2 style="margin-top:0">Nahrát celý plán</h2>
  <p class="muted" style="margin-top:0">Vlož plán jako text v tomto tvaru. Fáze začíná <code>##</code>, položka <code>-</code>. Hotový plán z Excelu, PDF nebo dokumentu ti rád převede Claude do tohoto tvaru – stačí ho vložit.</p>
  <?php foreach ($errors as $e) echo '<div class="flash err">' . h($e) . '</div>'; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="import_preview">
    <div class="field"><textarea name="text" id="plantext" rows="12" placeholder="# Název plánu&#10;## Fáze | od | do&#10;- dny | název | minuty | popis" style="font-family:ui-monospace,Consolas,monospace;font-size:13px"><?= h($text) ?></textarea></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn">Zobrazit náhled</button><button class="btn ghost" type="button" id="plan-example" data-example="<?= h(PLAN_EXAMPLE) ?>">Vložit ukázku</button></div>
  </form>
  <p class="sub" style="margin-bottom:0">Dny: <code>denně</code>, <code>po-pá</code>, <code>út, pá</code>. Datum: <code>3. 11. 2026</code> nebo <code>2026-11-03</code>. Minuty a popis jsou nepovinné.</p>
</section>

<?php if (count($plans) > 1 || ($plans && !$plan)): ?>
<section class="card"><h2 style="margin-top:0">Archiv plánů</h2>
  <?php foreach ($plans as $p) if (!$p['active']) echo '<form method="post" style="display:flex;gap:8px;align-items:center;margin:0 0 6px">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$p['id'] . '"><span style="flex:1">' . h($p['name']) . ' <span class="muted">' . h(tr_cz_date(substr($p['created'], 0, 10))) . '</span></span><button class="btn sm ghost" name="a" value="plan_activate">Aktivovat</button><button class="btn sm danger" name="a" value="plan_del" onclick="return confirm(\'Smazat plán?\')">×</button></form>'; ?>
</section>
<?php endif; ?>
<script>document.getElementById('plan-example')?.addEventListener('click',function(){document.getElementById('plantext').value=this.dataset.example;});</script>
<?php page_end('trenink.php');
