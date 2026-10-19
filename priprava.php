<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/training.php';
require_once __DIR__ . '/inc/health.php';
require_once __DIR__ . '/inc/events.php';
migrate_if_needed();
require_login();
health_sync_maybe();

$today = date('Y-m-d');
$haveHealth = (int)db()->query('SELECT COUNT(*) FROM health_sessions')->fetchColumn() > 0;
$fnum = fn(float $v): string => rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',');

// Pokrok cvičení z Moje Zdraví (podle cvičení a varianty)
$drills = [];
foreach (db()->query('SELECT * FROM health_sessions WHERE kind = "drill" ORDER BY d, id')->fetchAll() as $r) $drills[$r['title'] . '|' . (string)$r['variant']][] = $r;

// Turnaje s přípravou
$events = db()->query('SELECT * FROM events WHERE status = "done" AND date IS NOT NULL ORDER BY date DESC LIMIT 12')->fetchAll();
$avgR = db()->prepare('SELECT ROUND(AVG(rating)) FROM rounds WHERE event_id = ?');

// Měsíce
$months = [];
for ($k = 0; $k < 6; $k++) {
    $ms = date('Y-m-01', strtotime(date('Y-m-01') . " -$k month")); $me = date('Y-m-t', strtotime($ms));
    $q = db()->prepare('SELECT kind, COUNT(*) n FROM health_sessions WHERE d BETWEEN ? AND ? GROUP BY kind'); $q->execute([$ms, $me]); $c = array_column($q->fetchAll(), 'n', 'kind');
    $r = db()->prepare('SELECT ROUND(AVG(r.rating)) a, COUNT(DISTINCT e.id) n FROM rounds r JOIN events e ON e.id = r.event_id WHERE e.date BETWEEN ? AND ? AND r.rating IS NOT NULL'); $r->execute([$ms, $me]); $rr = $r->fetch();
    $s = tr_plan() ? tr_summary($ms, min($me, $today)) : ['planned' => 0, 'done' => 0];
    $months[] = ['m' => $ms, 'gym' => (int)($c['gym'] ?? 0), 'drill' => (int)($c['drill'] ?? 0), 'pct' => $s['planned'] ? (int)round($s['done'] / $s['planned'] * 100) : null, 'rating' => $rr['a'] ? (int)$rr['a'] : null, 'ev' => (int)$rr['n']];
}
$mn = [1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben', 5 => 'květen', 6 => 'červen', 7 => 'červenec', 8 => 'srpen', 9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec'];

page_start('Příprava a výsledky', 'trenink.php');
?>
<div class="ph">Příprava a výsledky <small><a href="trenink.php">← Trénink</a></small></div>
<?php if (!health_conf()): ?>
  <div class="card empty"><b>Moje Zdraví není propojené</b><span class="sub">Posilovna a putting cvičení se stáhnou z aplikace Moje Zdraví a objeví se tady i v plánu.</span><a class="btn" href="nastaveni.php#zdravi">Propojit</a></div>
<?php endif; ?>

<section class="card">
  <h2 style="margin-top:0">Před turnaji (4 týdny předem)</h2>
  <?php if (!$events) echo '<p class="muted">Zatím žádný odehraný turnaj s datem.</p>'; ?>
  <?php foreach ($events as $e): $p = health_prep((string)$e['date']); $avgR->execute([(int)$e['id']]); $ar = (int)$avgR->fetchColumn(); [$c1x, $c2] = event_putting((int)$e['id']); ?>
    <a href="turnaj.php?id=<?= (int)$e['id'] ?>" class="prep" style="color:inherit;text-decoration:none">
      <div class="row"><strong><?= h($e['name']) ?></strong><span class="muted"><?= h(fmt_date((string)$e['date'])) ?></span></div>
      <div class="pills" style="margin-top:6px">
        <span class="pill"><?= h((string)$e['place']) ?></span>
        <?= $ar ? '<span class="pill">rating kola ' . $ar . '</span>' : '' ?>
        <?= $c1x !== null ? '<span class="pill">C1X ' . $c1x . ' %</span>' : '' ?>
        <?= $c2 !== null ? '<span class="pill">C2 ' . $c2 . ' %</span>' : '' ?>
      </div>
      <div class="sub" style="margin-top:6px">
        <?php if ($p['has'] || $p['planned']): ?>
          Příprava: posilovna <b><?= $p['gym'] ?>×</b> · putting cvičení <b><?= $p['drill'] ?>×</b><?= $p['pct'] !== null ? ' · plán splněn <b>' . $p['pct'] . ' %</b>' : '' ?>
        <?php else: ?>Z té doby nemám žádná data o tréninku.<?php endif; ?>
      </div></a>
  <?php endforeach; ?>
  <p class="sub" style="margin-bottom:0">Je to orientační přehled. Při pár turnajích nejde z čísel poznat, co z přípravy opravdu pomohlo – spíš si hlídej trend.</p>
</section>

<section class="card">
  <h2 style="margin-top:0">Po měsících</h2>
  <?php foreach ($months as $m): ?>
    <div class="prep"><div class="row"><strong><?= $mn[(int)date('n', strtotime($m['m']))] ?> <?= date('Y', strtotime($m['m'])) ?></strong>
      <span class="muted"><?= $m['rating'] ? 'rating kol Ø ' . $m['rating'] . ' (' . $m['ev'] . ' turn.)' : 'bez turnaje' ?></span></div>
      <div class="sub">Posilovna <b><?= $m['gym'] ?>×</b> · putting cvičení <b><?= $m['drill'] ?>×</b><?= $m['pct'] !== null ? ' · plán <b>' . $m['pct'] . ' %</b>' : '' ?></div></div>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2 style="margin-top:0">Putting cvičení – pokrok</h2>
  <?php if (!$drills) echo '<p class="muted">Zatím žádná cvičení z Moje Zdraví' . (health_conf() ? ' – zkus Synchronizovat v Nastavení.' : '.') . '</p>'; ?>
  <?php foreach ($drills as $key => $rows): $last = end($rows); $vals = array_map(fn($r) => (float)$r['score'], $rows); $better = $last['better'] === 'down' ? 'down' : 'up';
      $best = $better === 'up' ? max($vals) : min($vals); $recent = array_slice($rows, -8); $mx = max(array_map(fn($r) => (float)$r['score'], $recent)) ?: 1; ?>
    <div class="prep">
      <div class="row"><strong><?= h($last['title']) ?><?= $last['variant'] ? ' <span class="muted">· ' . h((string)$last['variant']) . '</span>' : '' ?></strong><span class="muted"><?= count($rows) ?>×</span></div>
      <div class="sub"><?= h((string)$last['metric']) ?> · poslední <b><?= $fnum((float)$last['score']) ?></b> · rekord <b><?= $fnum($best) ?></b> <?= $better === 'down' ? '(méně je lépe)' : '' ?></div>
      <div class="spark"><?php foreach ($recent as $r): ?><i title="<?= h(date('j. n.', strtotime($r['d']))) ?>: <?= $fnum((float)$r['score']) ?>" style="height:<?= max(8, (int)round((float)$r['score'] / $mx * 100)) ?>%"></i><?php endforeach; ?></div>
    </div>
  <?php endforeach; ?>
</section>
<?php page_end('trenink.php');
