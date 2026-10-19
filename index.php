<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
migrate_if_needed();
require_login();

$hist = db()->query('SELECT d, rating FROM rating_history ORDER BY d DESC LIMIT 2')->fetchAll();
$rating = $hist ? (int)$hist[0]['rating'] : 0; $delta = count($hist) > 1 ? $rating - (int)$hist[1]['rating'] : 0;
$goal = (int)setting('rating_goal', '980');
$last = db()->query('SELECT e.*, (SELECT ROUND(AVG(rating)) FROM rounds WHERE event_id = e.id) AS avg_r FROM events e ORDER BY COALESCE(date, "0000-00-00") DESC, id DESC LIMIT 1')->fetch();
$cnt = db()->query('SELECT SUM(in_bag AND NOT is_sold AND NOT is_lost) bag, SUM(for_sale AND NOT is_sold AND NOT is_lost) sale, COUNT(*) total FROM discs')->fetch();
$inq = (int)db()->query('SELECT COUNT(*) FROM inquiries WHERE handled = 0')->fetchColumn();
$seasonEv = (int)db()->query('SELECT COUNT(*) FROM events WHERE year = ' . (int)date('Y'))->fetchColumn();
$wins = (int)db()->query('SELECT COUNT(*) FROM events WHERE year = ' . (int)date('Y') . ' AND place = "1."')->fetchColumn();

page_start('Přehled', 'index.php');
?>
<div class="ph">Přehled <small><?= h(date('j. n. Y')) ?></small></div>
<div class="kpis">
  <div class="kpi hl"><b><?= $rating ?></b><span>PDGA rating <?= $delta ? '(' . ($delta > 0 ? '+' : '−') . abs($delta) . ' za měsíc)' : '' ?></span></div>
  <div class="kpi"><b><?= max(0, $goal - $rating) ?></b><span>bodů do cíle <?= $goal ?></span></div>
  <div class="kpi"><b><?= $seasonEv ?></b><span>turnajů v sezóně <?= date('Y') ?> · <?= $wins ?>× vítězství</span></div>
  <div class="kpi"><b><?= (int)$cnt['bag'] ?></b><span>disků v bagu</span></div>
</div>
<?php if ($last): ?>
<a class="card ev" href="statistiky.php" style="text-decoration:none"><div class="row"><strong><?= h($last['name']) ?></strong><span class="place<?= $last['place'] === '1.' ? ' win' : '' ?>"><?= h((string)$last['place']) ?></span></div>
  <div class="sub"><?= $last['date'] ? h(date('j. n. Y', strtotime($last['date']))) . ' · ' : '' ?>celkem <?= $last['total'] > 0 ? '+' . $last['total'] : ($last['total'] < 0 ? '−' . abs($last['total']) : 'E') ?><?= $last['avg_r'] ? ' · průměr kola ' . (int)$last['avg_r'] : '' ?></div></a>
<?php endif; ?>
<div class="two">
  <a class="card" href="bag.php" style="color:inherit;text-decoration:none"><h3>Bag</h3><b style="font:700 22px var(--font-d)"><?= (int)$cnt['total'] ?> disků</b><div class="sub"><?= (int)$cnt['bag'] ?> v bagu · <?= (int)$cnt['sale'] ?> na prodej</div></a>
  <a class="card" href="poptavky.php" style="color:inherit;text-decoration:none"><h3>Poptávky z prodeje</h3><b style="font:700 22px var(--font-d)"><?= $inq ?> nových</b><div class="sub">z veřejné stránky prodeje</div></a>
</div>
<?php page_end('index.php');
