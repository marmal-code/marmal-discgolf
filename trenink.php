<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/training.php';
require_once __DIR__ . '/inc/health.php';
migrate_if_needed();
require_login();

$today = date('Y-m-d');
$week = tr_week_start(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['w'] ?? '')) ? (string)$_GET['w'] : $today);
$self = 'trenink.php?w=' . $week;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = (string)($_POST['a'] ?? ''); $date = (string)($_POST['date'] ?? '');
    $ret = ($_POST['ret'] ?? '') === 'index.php' ? 'index.php' : (preg_match('/^trenink\.php\?w=\d{4}-\d{2}-\d{2}$/', (string)($_POST['ret'] ?? '')) ? (string)$_POST['ret'] : $self);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= $today) {
        $item = (int)($_POST['item'] ?? 0);
        if ($a === 'done') tr_set($date, $item, 'done', null, null);
        elseif ($a === 'undo') tr_set($date, $item, 'none', null, null);
        elseif ($a === 'skip') tr_set($date, $item, 'skip', (string)($_POST['reason'] ?? ''), trim((string)($_POST['note'] ?? '')));
        elseif ($a === 'extra' && trim((string)($_POST['title'] ?? '')) !== '') {
            $kind = isset(TR_KINDS[(string)($_POST['kind'] ?? '')]) ? (string)$_POST['kind'] : 'other'; $min = ($_POST['minutes'] ?? '') === '' ? null : max(0, (int)$_POST['minutes']);
            db()->prepare('INSERT INTO training_log (tdate, item_id, title, kind, minutes, status, created) VALUES (?,NULL,?,?,?,"done",?)')->execute([$date, mb_substr(trim((string)$_POST['title']), 0, 200), $kind, $min, now()]);
        }
    }
    if ($a === 'extra_del') db()->prepare('DELETE FROM training_log WHERE id = ? AND item_id IS NULL')->execute([(int)($_POST['id'] ?? 0)]);
    redirect($ret);
}

if (health_conf()) health_sync_maybe();
$plan = tr_plan();
$end = date('Y-m-d', strtotime($week) + 6 * 86400);
$sum = tr_summary($week, $end);
$hq = db()->prepare('SELECT * FROM health_sessions WHERE d BETWEEN ? AND ? ORDER BY d, id'); $hq->execute([$week, $end]); $hs = [];
foreach ($hq->fetchAll() as $h) $hs[$h['d']][] = $h;
$dq = db()->prepare('SELECT * FROM drill_sessions WHERE d BETWEEN ? AND ? ORDER BY d, id'); $dq->execute([$week, $end]); $ds = [];
foreach ($dq->fetchAll() as $r) $ds[$r['d']][] = $r;
require_once __DIR__ . '/inc/drills.php';
page_start('Trénink', 'trenink.php');
?>
<div class="ph">Trénink <small><a href="trenink-plan.php">Plán<?= $plan ? ': ' . h($plan['name']) : '' ?> →</a> · <a href="priprava.php">Příprava a výsledky →</a></small></div>
<div class="nav2" style="margin-bottom:12px">
  <a class="btn ghost sm" href="trenink.php?w=<?= date('Y-m-d', strtotime($week) - 7 * 86400) ?>">← týden</a>
  <b style="align-self:center"><?= tr_cz_date($week) ?> – <?= tr_cz_date($end) ?><?= $week === tr_week_start($today) ? ' (tento týden)' : '' ?></b>
  <a class="btn ghost sm" href="trenink.php?w=<?= date('Y-m-d', strtotime($week) + 7 * 86400) ?>">týden →</a>
</div>
<?php if (!$plan): ?><div class="card empty"><b>Zatím nemáš aktivní plán</b><a class="btn" href="trenink-plan.php">Vytvořit nebo nahrát plán</a></div><?php endif; ?>

<div class="kpis">
  <div class="kpi hl"><b><?= $sum['done'] ?>/<?= $sum['planned'] ?></b><span>splněno</span></div>
  <div class="kpi"><b><?= $sum['skip'] ?></b><span>vynecháno</span></div>
  <div class="kpi"><b><?= $sum['none'] ?></b><span>bez záznamu</span></div>
  <div class="kpi"><b><?= $sum['extra'] ?></b><span>vlastních tréninků</span></div>
</div>
<?php if ($sum['skip']): ?><div class="pills" style="margin:10px 0"><?php foreach ($sum['reasons'] as $k => $n) if ($n) echo '<span class="pill b">' . h(TR_REASONS[$k]) . ': ' . $n . '×</span>'; ?></div><?php endif; ?>

<?php for ($i = 0; $i < 7; $i++):
    $d = date('Y-m-d', strtotime($week) + $i * 86400); $items = $plan ? tr_day($d) : []; $extra = tr_extra($d); $future = $d > $today; ?>
  <section class="card trday<?= $d === $today ? ' today' : '' ?><?= $future ? ' future' : '' ?>" id="d<?= $d ?>">
    <h3 style="margin:0 0 8px"><?= TR_DAYS_LONG[$i + 1] ?> <span class="muted" style="font-weight:500"><?= tr_cz_date($d) ?><?= $d === $today ? ' · dnes' : '' ?></span></h3>
    <?php if (!$items && !$extra) echo '<p class="muted" style="margin:0">Volno.</p>'; ?>
    <?php foreach ($items as $it): $st = $it['status']; $ret = h($self); ?>
      <div class="trrow k-<?= h($it['kind']) ?> st-<?= $st ?: ($d < $today ? 'miss' : 'open') ?>">
        <div class="trmain">
          <div class="trt"><span class="kd"></span><b><?= h($it['title']) ?></b><?= $it['minutes'] ? ' <span class="muted">' . (int)$it['minutes'] . ' min</span>' : '' ?></div>
          <?php if ($it['details']): ?><div class="sub"><?= h($it['details']) ?></div><?php endif; ?>
          <?php if ($st === 'done' && in_array(($it['log_src'] ?? ''), ['zdravi', 'cviceni'], true)): ?><div class="sub" style="color:var(--good,var(--accent))">✓ <?= h((string)$it['log_note']) ?></div><?php endif; ?>
          <?php if ($it['kind'] === 'putting' && !$st && !$future): ?><div class="sub"><a href="cviceni.php">🥏 Zahrát cvičení →</a></div><?php endif; ?>
          <?php if ($st === 'skip'): ?><div class="sub" style="color:var(--bad)">Vynecháno – <?= h(TR_REASONS[$it['reason'] ?: 'jine']) ?><?= $it['log_note'] ? ': ' . h($it['log_note']) : '' ?></div>
          <?php elseif (!$st && $d < $today): ?><div class="sub" style="color:var(--bad)">Bez záznamu – splnil jsi to?</div><?php endif; ?>
        </div>
        <?php if (!$future): ?>
        <div class="trctl">
          <?php if ($st): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="date" value="<?= $d ?>"><input type="hidden" name="item" value="<?= (int)$it['id'] ?>"><input type="hidden" name="ret" value="<?= $ret ?>"><button class="btn sm ghost" name="a" value="undo"><?= $st === 'done' ? '✓ Splněno' : 'Vrátit' ?></button></form>
          <?php else: ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="date" value="<?= $d ?>"><input type="hidden" name="item" value="<?= (int)$it['id'] ?>"><input type="hidden" name="ret" value="<?= $ret ?>"><button class="btn sm" name="a" value="done">Splněno</button></form>
            <details class="skipbox"><summary class="btn sm ghost">Nesplněno</summary>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="skip"><input type="hidden" name="date" value="<?= $d ?>"><input type="hidden" name="item" value="<?= (int)$it['id'] ?>"><input type="hidden" name="ret" value="<?= $ret ?>">
                <select name="reason"><?php foreach (TR_REASONS as $k => $l) echo '<option value="' . $k . '">' . h($l) . '</option>'; ?></select>
                <input type="text" name="note" maxlength="300" placeholder="Poznámka (nepovinné)"><button class="btn sm">Uložit</button></form></details>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php foreach ($ds[$d] ?? [] as $r): $DD = DRILLS[$r['drill']] ?? null; if (!$DD) continue; ?>
      <div class="sub" style="margin:4px 0">🥏 <a href="cviceni-hra.php?k=<?= h($r['drill']) ?>&v=<?= h($r['variant']) ?>" style="color:inherit"><b><?= h($DD['name']) ?></b></a> – <?= h(drill_num((float)$r['score'])) ?> <?= h($DD['unit']) ?> · <?= (int)$r['minutes'] ?> min</div>
    <?php endforeach; ?>
    <?php foreach ($hs[$d] ?? [] as $h): ?>
      <div class="sub" style="margin:4px 0">🔗 Moje Zdraví: <b><?= h($h['title']) ?></b><?= $h['variant'] ? ' (' . h($h['variant']) . ')' : '' ?><?= $h['score'] !== null ? ' – ' . h(rtrim(rtrim(number_format((float)$h['score'], 2, ',', ''), '0'), ',')) . ' ' . h((string)$h['unit']) : '' ?><?= $h['minutes'] ? ' · ' . (int)$h['minutes'] . ' min' : '' ?><?= $h['kind'] === 'gym' && $h['volume_kg'] ? ' · objem ' . number_format((int)$h['volume_kg'], 0, ',', ' ') . ' kg' : '' ?></div>
    <?php endforeach; ?>
    <?php foreach ($extra as $x): ?>
      <div class="trrow k-<?= h((string)$x['kind']) ?> st-done"><div class="trmain"><div class="trt"><span class="kd"></span><b><?= h((string)$x['title']) ?></b><?= $x['minutes'] ? ' <span class="muted">' . (int)$x['minutes'] . ' min</span>' : '' ?> <span class="pill g">vlastní</span></div></div>
        <div class="trctl"><form method="post"><?= csrf_field() ?><input type="hidden" name="ret" value="<?= h($self) ?>"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn sm danger" name="a" value="extra_del" aria-label="Smazat">×</button></form></div></div>
    <?php endforeach; ?>
    <?php if (!$future): ?>
    <details class="skipbox" style="margin-top:8px"><summary class="btn sm ghost">+ Vlastní trénink</summary>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="extra"><input type="hidden" name="date" value="<?= $d ?>"><input type="hidden" name="ret" value="<?= h($self) ?>">
        <input type="text" name="title" maxlength="200" placeholder="Co jsi trénoval" required>
        <select name="kind"><?php foreach (TR_KINDS as $k => $l) echo '<option value="' . $k . '">' . h($l) . '</option>'; ?></select>
        <input type="number" name="minutes" min="0" placeholder="minut"><button class="btn sm">Přidat</button></form></details>
    <?php endif; ?>
  </section>
<?php endfor; ?>

<section class="card">
  <h2 style="margin-top:0">Posledních 8 týdnů</h2>
  <?php for ($k = 7; $k >= 0; $k--):
      $ws = date('Y-m-d', strtotime(tr_week_start($today)) - $k * 7 * 86400); $we = date('Y-m-d', strtotime($ws) + 6 * 86400); $s = tr_summary($ws, $we);
      $pct = $s['planned'] ? (int)round($s['done'] / $s['planned'] * 100) : 0; ?>
    <a href="trenink.php?w=<?= $ws ?>" class="weekbar" style="color:inherit;text-decoration:none">
      <span class="muted"><?= tr_cz_date($ws) ?></span>
      <span class="bar"><i style="width:<?= $pct ?>%"></i></span>
      <span><b><?= $s['planned'] ? $pct . ' %' : '–' ?></b> <span class="muted"><?= $s['done'] ?>/<?= $s['planned'] ?></span><?php foreach ($s['reasons'] as $r => $n) if ($n) echo ' <span class="pill b">' . h(TR_REASONS[$r]) . ' ' . $n . '</span>'; ?><?= $s['none'] ? ' <span class="pill">bez záznamu ' . $s['none'] . '</span>' : '' ?></span>
    </a>
  <?php endfor; ?>
</section>
<?php page_end('trenink.php');
