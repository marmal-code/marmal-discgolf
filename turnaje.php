<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/events.php';
migrate_if_needed();
require_login();

$plan = db()->query('SELECT e.*, (SELECT COUNT(*) FROM event_discs d WHERE d.event_id = e.id) bag_n, (SELECT COUNT(*) FROM event_todo t WHERE t.event_id = e.id) todo_n, (SELECT COUNT(*) FROM event_todo t WHERE t.event_id = e.id AND t.done = 1) todo_done
    FROM events e WHERE status = "plan" ORDER BY COALESCE(date, "9999-12-31"), id')->fetchAll();
$done = db()->query('SELECT e.*, (SELECT COUNT(*) FROM rounds r WHERE r.event_id = e.id) rn FROM events e WHERE status = "done" ORDER BY COALESCE(date, "0000-00-00") DESC, id DESC')->fetchAll();

page_start('Turnaje', 'turnaje.php');
?>
<div class="ph">Turnaje <small><?= count($plan) ?> plánovaných · <?= count($done) ?> odehraných</small></div>
<div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" href="turnaj.php?new=1">+ Nový turnaj</a><a class="btn ghost" href="import.php">Import z PDGA Live</a></div>

<?php if ($plan): ?><h2 style="margin:18px 0 8px">Plán</h2>
<?php foreach ($plan as $e):
    $days = $e['date'] ? (int)floor((strtotime($e['date']) - strtotime(date('Y-m-d'))) / 86400) : null; ?>
  <a class="card ev" href="turnaj.php?id=<?= (int)$e['id'] ?>" style="text-decoration:none;display:block">
    <div class="row"><strong><?= h($e['name']) ?></strong><span class="place"><?= $days === null ? 'bez data' : ($days > 0 ? 'za ' . $days . ' d' : ($days === 0 ? 'dnes' : 'proběhlo')) ?></span></div>
    <div class="sub"><?= $e['date'] ? h(fmt_date($e['date'])) . ($e['end_date'] && $e['end_date'] !== $e['date'] ? '–' . h(fmt_date($e['end_date'])) : '') . ' · ' : '' ?><?= h((string)($e['venue'] ?: $e['city'] ?: '')) ?></div>
    <div class="pills" style="margin-top:6px"><span class="pill"><?= (int)$e['bag_n'] ?> disků v bagu</span><span class="pill"><?= (int)$e['todo_done'] ?>/<?= (int)$e['todo_n'] ?> úkolů</span><?php if ($e['goal']): ?><span class="pill g">cíl: <?= h($e['goal']) ?></span><?php endif; ?></div>
  </a>
<?php endforeach; endif; ?>

<h2 style="margin:18px 0 8px">Odehrané</h2>
<?php if (!$done) echo '<div class="card empty"><b>Zatím nic</b>Odehrané turnaje se ukážou tady a ve statistikách.</div>'; ?>
<?php foreach ($done as $e): ?>
  <a class="card ev" href="turnaj.php?id=<?= (int)$e['id'] ?>" style="text-decoration:none;display:block">
    <div class="row"><strong><?= h($e['name']) ?></strong><span class="place<?= $e['place'] === '1.' ? ' win' : '' ?>"><?= h((string)$e['place']) ?></span></div>
    <div class="sub"><?= h(fmt_date($e['date'])) ?> · <?= (int)$e['rn'] ?> kol · celkem <?= $e['total'] > 0 ? '+' . $e['total'] : ($e['total'] < 0 ? '−' . abs((int)$e['total']) : 'E') ?><?= $e['venue'] ? ' · ' . h($e['venue']) : '' ?></div>
  </a>
<?php endforeach; ?>
<?php page_end('turnaje.php');
