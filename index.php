<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/training.php';
require_once __DIR__ . '/inc/health.php';
require_once __DIR__ . '/inc/cadg.php';
migrate_if_needed();
require_login();
cadg_sync_maybe();

$hist = db()->query('SELECT d, rating FROM rating_history ORDER BY d DESC LIMIT 2')->fetchAll();
$rating = $hist ? (int)$hist[0]['rating'] : 0; $delta = count($hist) > 1 ? $rating - (int)$hist[1]['rating'] : 0;
$goal = (int)setting('rating_goal', '980');
$last = db()->query('SELECT e.*, (SELECT ROUND(AVG(rating)) FROM rounds WHERE event_id = e.id) AS avg_r FROM events e ORDER BY COALESCE(date, "0000-00-00") DESC, id DESC LIMIT 1')->fetch();
$cnt = db()->query('SELECT SUM(in_bag) bag, SUM(for_sale) sale, COUNT(*) total FROM discs WHERE is_sold = 0 AND is_lost = 0')->fetch();
$inq = (int)db()->query('SELECT COUNT(*) FROM inquiries WHERE handled = 0')->fetchColumn();
$seasonEv = (int)db()->query('SELECT COUNT(*) FROM events WHERE year = ' . (int)date('Y'))->fetchColumn();
$wins = (int)db()->query('SELECT COUNT(*) FROM events WHERE year = ' . (int)date('Y') . ' AND place = "1."')->fetchColumn();

health_sync_maybe();
$todayItems = tr_plan() ? tr_day(date('Y-m-d')) : [];
$links = db()->query('SELECT * FROM links ORDER BY sort, id')->fetchAll();
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
<?php if ($todayItems): ?>
<section class="card trday today" style="margin-top:16px">
  <h3 style="margin:0 0 8px"><a href="trenink.php" style="color:inherit;text-decoration:none">Dnešní trénink →</a></h3>
  <?php foreach ($todayItems as $it): $st = $it['status']; ?>
    <div class="trrow k-<?= h($it['kind']) ?> st-<?= $st ?: 'open' ?>"><div class="trmain"><div class="trt"><span class="kd"></span><b><?= h($it['title']) ?></b><?= $it['minutes'] ? ' <span class="muted">' . (int)$it['minutes'] . ' min</span>' : '' ?></div>
      <?php if ($st === 'skip') echo '<div class="sub" style="color:var(--bad)">Vynecháno – ' . h(TR_REASONS[$it['reason'] ?: 'jine']) . '</div>'; ?></div>
      <div class="trctl"><form method="post" action="trenink.php"><?= csrf_field() ?><input type="hidden" name="date" value="<?= date('Y-m-d') ?>"><input type="hidden" name="item" value="<?= (int)$it['id'] ?>"><input type="hidden" name="ret" value="index.php">
        <?php if ($st) echo '<button class="btn sm ghost" name="a" value="undo">' . ($st === 'done' ? '✓ Splněno' : 'Vrátit') . '</button>'; else echo '<button class="btn sm" name="a" value="done">Splněno</button>'; ?></form></div></div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php $pf = cadg_profile(); if ($pf):
  $until = function (?string $d): string { if (!$d) return ''; $days = (int)floor((strtotime($d) - strtotime(date('Y-m-d'))) / 86400); $cls = $days < 0 ? 'b' : ($days < 60 ? 'w' : 'g'); return '<span class="pill ' . $cls . '">' . h(date('j. n. Y', strtotime($d))) . ($days < 0 ? ' · vypršelo' : ($days < 60 ? ' · za ' . $days . ' dní' : '')) . '</span>'; }; ?>
<section class="card profile" style="margin-top:16px">
  <div class="pfhead">
    <?php if (!empty($pf['photo'])): ?><img class="pfphoto" src="<?= h((string)$pf['photo']) ?>" alt="" referrerpolicy="no-referrer" onerror="this.remove()"><?php endif; ?>
    <div><h3 style="margin:0"><?= h((string)$pf['name']) ?><?= !empty($pf['nick']) ? ' <small class="muted" style="font-weight:500">„' . h((string)$pf['nick']) . '“</small>' : '' ?></h3>
      <div class="pfpills"><?php if ($pf['club']) echo '<span class="pill c">' . h((string)$pf['club']) . '</span>'; if ($pf['city']) echo '<span class="pill">' . h((string)$pf['city']) . '</span>';
        if (!empty($pf['nat'])) echo '<span class="pill">' . h(['CZ' => 'Česká republika', 'SK' => 'Slovensko'][$pf['nat']] ?? (string)$pf['nat']) . '</span>';
        if (!empty($pf['birth'])) echo '<span class="pill">' . (int)$pf['birth'] . '</span>';
        $cOk = $pf['cadg_until'] && strtotime((string)$pf['cadg_until']) >= strtotime(date('Y-m-d')); echo '<span class="pill ' . ($cOk ? 'g' : 'b') . '">' . ($cOk ? 'Členství platné' : 'Členství neplatné') . '</span>'; ?></div></div>
  </div>
  <div class="pftwo">
    <div class="pfbox cadg"><h4>ČADG</h4>
      <div class="pfgrid">
        <div><b><?= (int)$pf['idg_rating'] ?: '–' ?></b><span>iDG rating</span></div>
        <div><b><?= h((string)($pf['cadg_no'] ?: '–')) ?></b><span>ČADG číslo</span></div>
        <div><b><?= $cOk ? 'Platné' : 'Neplatné' ?></b><span>stav členství</span></div>
        <div><b><?= $pf['cadg_until'] ? h(date('j. n. Y', strtotime((string)$pf['cadg_until']))) : '–' ?></b><span>platnost do</span></div>
      </div></div>
    <div class="pfbox pdga"><h4>PDGA</h4>
      <div class="pfgrid">
        <div><b><?= (int)$pf['pdga_rating'] ?: '–' ?></b><span>PDGA rating</span></div>
        <div><b><?= $pf['pdga_no'] ? '<a href="https://www.pdga.com/player/' . (int)$pf['pdga_no'] . '" target="_blank" rel="noopener noreferrer" style="color:inherit">' . (int)$pf['pdga_no'] . '</a>' : '–' ?></b><span>PDGA číslo</span></div>
        <div><b><?= $pf['pdga_active'] ? ($pf['pdga_pro'] ? 'Profesionál' : 'Aktivní') : 'Neaktivní' ?></b><span>PDGA členství</span></div>
        <div><b><?= $pf['pdga_until'] ? h(date('j. n. Y', strtotime((string)$pf['pdga_until']))) : '–' ?></b><span>platnost PDGA do</span></div>
        <div class="wide"><b><?= $pf['ref'] === 'yes' ? 'Ano' . ($pf['ref_until'] ? ' (' . h(date('j. n. Y', strtotime((string)$pf['ref_until']))) . ')' : '') : 'Ne' ?></b><span>PDGA rozhodčí</span></div>
      </div></div>
  </div>
  <?php if (!empty($pf['years'])): ?><div class="pfyears"><span class="muted">Historie členství</span><?php foreach ($pf['years'] as $y) echo '<span class="pill">' . (int)$y . '</span>'; ?></div><?php endif; ?>
  <p class="sub" style="margin:8px 0 0">Aktualizuje se samo z ceskydiscgolf.cz<?= setting('cadg_last', '') ? ' · naposledy ' . h(date('j. n. H:i', strtotime((string)setting('cadg_last')))) : '' ?>. <a href="nastaveni.php#cadg">Nastavení</a></p>
</section>
<?php endif; ?>
<?php if ($links): ?>
<section class="card" style="margin-top:16px"><h3 style="margin:0 0 10px">Odkazy</h3>
  <div class="linkgrid"><?php foreach ($links as $l) echo '<a class="btn ghost" href="' . h($l['url']) . '" target="_blank" rel="noopener noreferrer">' . h($l['title']) . ' ↗</a>'; ?></div>
</section>
<?php endif; ?>
<?php page_end('index.php');
