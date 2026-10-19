<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/drills.php';
require_once __DIR__ . '/inc/training.php';
require_once __DIR__ . '/inc/health.php';
migrate_if_needed();
require_login();
health_sync_maybe();

$today = date('Y-m-d');
$todayPutting = tr_plan() ? array_values(array_filter(tr_day($today), fn($i) => $i['kind'] === 'putting')) : [];
$recent = db()->query('SELECT * FROM drill_sessions ORDER BY d DESC, id DESC LIMIT 8')->fetchAll();
$week = db()->prepare('SELECT COUNT(*) n, COALESCE(SUM(minutes),0) m FROM drill_sessions WHERE d >= ?'); $week->execute([date('Y-m-d', strtotime('monday this week'))]); $w = $week->fetch();
page_start('Cvičení', 'cviceni.php');
?>
<div class="ph">Cvičení <small><?= (int)$w['n'] ?>× tento týden<?= $w['m'] ? ' · ' . (int)$w['m'] . ' min' : '' ?></small></div>
<?php if ($todayPutting): ?>
  <div class="card today" style="border-color:var(--accent)"><h3 style="margin:0 0 6px">Dnes v plánu</h3>
    <?php foreach ($todayPutting as $it): ?><div class="trrow st-<?= $it['status'] ?: 'open' ?>"><div class="trmain"><div class="trt"><span class="kd"></span><b><?= h($it['title']) ?></b><?= $it['minutes'] ? ' <span class="muted">' . (int)$it['minutes'] . ' min</span>' : '' ?></div>
      <div class="sub"><?= $it['status'] === 'done' ? '✓ ' . h((string)($it['log_note'] ?: 'Splněno')) : 'Zahraj cvičení níže – po uložení se v plánu samo odškrtne.' ?></div></div></div><?php endforeach; ?>
  </div>
<?php endif; ?>
<section class="card">
  <?php foreach (DRILLS as $k => $d): $last = drill_list($k, 1)[0] ?? null; $best = drill_best($k); $cnt = (int)db()->query('SELECT COUNT(*) FROM drill_sessions WHERE drill = ' . db()->quote($k))->fetchColumn(); ?>
    <a class="drillcard" href="cviceni-hra.php?k=<?= h($k) ?>"><span class="dic"><?= $d['icon'] ?></span>
      <span class="grow"><b><?= h($d['name']) ?></b><small class="muted"><?= h($d['kind']) ?> · asi <?= (int)$d['minutes'] ?> min<?= $last ? ' · naposledy ' . h(date('j. n.', strtotime($last['d']))) : '' ?><?= $best !== null ? ' · rekord ' . h(drill_num($best)) . ' ' . h($d['unit']) : '' ?><?= $cnt ? ' · ' . $cnt . '×' : '' ?></small></span>
      <span class="btn sm">Hrát ›</span></a>
  <?php endforeach; ?>
</section>
<?php if ($recent): ?>
<section class="card"><h3 style="margin:0 0 6px">Poslední cvičení</h3>
  <?php foreach ($recent as $r): $D = DRILLS[$r['drill']] ?? null; if (!$D) continue; ?>
    <a class="prep" href="cviceni-hra.php?k=<?= h($r['drill']) ?>&v=<?= h($r['variant']) ?>" style="color:inherit;text-decoration:none"><div class="row"><strong><?= $D['icon'] ?> <?= h($D['name']) ?></strong><span class="muted"><?= h(date('j. n.', strtotime($r['d']))) ?></span></div>
      <div class="sub"><b><?= h(drill_num((float)$r['score'])) ?> <?= h($D['unit']) ?></b> · <?= (int)$r['minutes'] ?> min<?= $r['src'] === 'zdravi' ? ' · ze Zdraví' : '' ?></div></a>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<p class="sub" style="margin:10px 4px">Odehraná cvičení se ukládají do historie, odškrtnou putting v plánu a posílají se do aplikace Moje Zdraví<?= health_conf() ? '' : ' (propojení nastavíš v Nastavení → Moje Zdraví)' ?>.</p>
<?php page_end('cviceni.php');
