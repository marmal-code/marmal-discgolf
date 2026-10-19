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
$hs = db()->query('SELECT d, rating FROM rating_history ORDER BY d DESC LIMIT 14')->fetchAll(); $hs = array_reverse($hs);
$prog = $goal > 900 && $rating ? max(0, min(100, (int)round(($rating - 900) / ($goal - 900) * 100))) : 0;
$spark = function (array $v, int $w = 120, int $h = 30): string { if (count($v) < 2) return ''; $mn = min($v); $mx = max($v); $r = max(1, $mx - $mn); $pts = [];
    foreach ($v as $i => $x) $pts[] = round($i / (count($v) - 1) * $w, 1) . ',' . round($h - 3 - ($x - $mn) / $r * ($h - 6), 1);
    return '<svg class="sp" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none"><polyline points="' . implode(' ', $pts) . '" fill="none" stroke="rgba(8,33,15,.55)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>'; };
page_start('Dashboard', 'index.php');
?>
<div class="dtop"><div><h1>Dashboard</h1><small><?= h(date('j. n. Y')) ?> · <?= h((string)setting('player_name', 'Martin Malý')) ?></small></div>
  <div class="ratchip"><div><span>PDGA rating</span><b><?= $rating ?: '–' ?></b></div><div class="ring" style="--p:<?= $prog ?>"><i><?= $prog ?>%</i></div></div></div>
<div class="dgrid">
  <div class="dkpis c-kpi">
    <div class="dk g1"><span>PDGA rating</span><b><?= $rating ?: '–' ?></b><small><?= $delta ? ($delta > 0 ? '▲ +' : '▼ −') . abs($delta) . ' za měsíc' : 'beze změny' ?></small><?= $spark(array_map(fn($r) => (int)$r['rating'], $hs)) ?></div>
    <div class="dk g2"><svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/></svg><span>Do cíle <?= $goal ?></span><b><?= max(0, $goal - $rating) ?></b><small>bodů ratingu</small></div>
    <div class="dk g3"><svg class="ic" viewBox="0 0 24 24"><path d="M8 4h8v6a4 4 0 01-8 0V4z"/><path d="M8 6H4v2a3 3 0 003 3M16 6h4v2a3 3 0 01-3 3M12 14v4M8 20h8"/></svg><span>Turnajů <?= date('Y') ?></span><b><?= $seasonEv ?></b><small><?= $wins ?>× vítězství</small></div>
    <div class="dk g4"><svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg><span>V bagu</span><b><?= (int)$cnt['bag'] ?></b><small>disků z <?= (int)$cnt['total'] ?></small></div>
  </div>
  <section class="card dc c-rat"><h3>Sezónní rating <span class="muted" style="font-weight:500;text-transform:none;letter-spacing:0"><?= date('Y') ?></span></h3>
  <?php if (count($hs) > 1):
    $W = 640; $H = 230; $pl = 40; $pr = 16; $pt = 22; $pb = 28; $vs = array_map(fn($r) => (int)$r['rating'], $hs); $mn = min($vs) - 4; $mx = max($vs) + 4; $rg = max(1, $mx - $mn);
    $X = fn($i) => $pl + $i / (count($hs) - 1) * ($W - $pl - $pr); $Y = fn($v) => $pt + (1 - ($v - $mn) / $rg) * ($H - $pt - $pb);
    $line = ''; $dots = ''; foreach ($hs as $i => $r) { $x = round($X($i), 1); $y = round($Y((int)$r['rating']), 1); $line .= ($i ? 'L' : 'M') . "$x,$y"; }
    $mi = array_search(max($vs), $vs); $show = array_unique([0, count($hs) - 1, $mi]);
    foreach ($show as $i) { $x = round($X($i), 1); $y = round($Y($vs[$i]), 1); $dots .= '<circle class="pt" cx="' . $x . '" cy="' . $y . '" r="5"/><text class="lb" x="' . ($i === 0 ? $x + 8 : $x) . '" y="' . ($y - 10) . '" text-anchor="' . ($i === 0 ? 'start' : 'middle') . '">' . $vs[$i] . '</text><text class="ax" x="' . $x . '" y="' . ($H - 8) . '" text-anchor="' . ($i === 0 ? 'start' : 'middle') . '">' . h(date('j. n.', strtotime((string)$hs[$i]['d']))) . '</text>'; }
    $grid = ''; for ($k = 0; $k < 4; $k++) { $v = $mn + $rg * $k / 3; $y = round($Y($v), 1); $grid .= '<line class="gl" x1="' . $pl . '" x2="' . ($W - $pr) . '" y1="' . $y . '" y2="' . $y . '"/><text class="ax" x="' . ($pl - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . round($v) . '</text>'; } ?>
    <svg class="ratchart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Vývoj PDGA ratingu"><defs><linearGradient id="rg" x1="0" x2="1"><stop class="s1" offset="0"/><stop class="s2" offset="1"/></linearGradient><linearGradient id="ra" x1="0" x2="0" y1="0" y2="1"><stop class="a1" offset="0"/><stop class="a2" offset="1"/></linearGradient></defs>
      <?= $grid ?><path d="<?= $line ?>L<?= round($X(count($hs) - 1), 1) ?>,<?= $H - $pb ?>L<?= $pl ?>,<?= $H - $pb ?>Z" fill="url(#ra)"/><path class="ln" d="<?= $line ?>"/><?= $dots ?></svg>
  <?php else: ?><p class="sub">Graf se ukáže, až bude v historii aspoň několik hodnot ratingu.</p><?php endif; ?></section>
  <div class="c-side">
  <?php if ($last): ?>
    <a class="card lastev" href="statistiky.php"><h3 style="margin:0">Poslední turnaj</h3><div class="row"><strong><?= h($last['name']) ?></strong><span class="place<?= $last['place'] === '1.' ? ' win' : '' ?>"><?= h((string)$last['place']) ?></span></div>
      <div class="sub"><?= $last['date'] ? h(date('j. n. Y', strtotime($last['date']))) . ' · ' : '' ?>celkem <?= $last['total'] > 0 ? '+' . $last['total'] : ($last['total'] < 0 ? '−' . abs($last['total']) : 'E') ?><?= $last['avg_r'] ? ' · průměr kola ' . (int)$last['avg_r'] : '' ?></div></a>
  <?php endif; ?>
    <div class="duo">
      <a class="card bagc" href="bag.php"><h3>Bag</h3><b><?= (int)$cnt['total'] ?> disků</b><div class="sub"><?= (int)$cnt['sale'] ?> na prodej</div></a>
      <a class="card inqc" href="poptavky.php"><h3>Poptávky</h3><b><?= $inq ?> nových</b><div class="sub">z prodeje</div></a>
    </div>
  </div>
  <?php if ($todayItems): ?><div class="c-train">
<section class="card trday today">
  <h3 style="margin:0 0 8px"><a href="trenink.php" style="color:inherit;text-decoration:none">Dnešní trénink →</a></h3>
  <?php foreach ($todayItems as $it): $st = $it['status']; ?>
    <div class="trrow k-<?= h($it['kind']) ?> st-<?= $st ?: 'open' ?>"><div class="trmain"><div class="trt"><span class="kd"></span><b><?= h($it['title']) ?></b><?= $it['minutes'] ? ' <span class="muted">' . (int)$it['minutes'] . ' min</span>' : '' ?></div>
      <?php if ($st === 'skip') echo '<div class="sub" style="color:var(--bad)">Vynecháno – ' . h(TR_REASONS[$it['reason'] ?: 'jine']) . '</div>'; ?></div>
      <div class="trctl"><form method="post" action="trenink.php"><?= csrf_field() ?><input type="hidden" name="date" value="<?= date('Y-m-d') ?>"><input type="hidden" name="item" value="<?= (int)$it['id'] ?>"><input type="hidden" name="ret" value="index.php">
        <?php if ($st) echo '<button class="btn sm ghost" name="a" value="undo">' . ($st === 'done' ? '✓ Splněno' : 'Vrátit') . '</button>'; else echo '<button class="btn sm" name="a" value="done">Splněno</button>'; ?></form></div></div>
  <?php endforeach; ?>
</section>
</div><?php endif; ?>
  <?php if ($pf = cadg_profile()): ?><div class="c-prof">
<section class="card profile">
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
</div><?php endif; ?>
  <?php if ($links): ?><div class="c-links">
<section class="card"><h3 style="margin:0 0 10px">Odkazy</h3>
  <div class="linkgrid"><?php foreach ($links as $l) echo '<a class="btn ghost" href="' . h($l['url']) . '" target="_blank" rel="noopener noreferrer">' . h($l['title']) . ' ↗</a>'; ?></div>
</section>
</div><?php endif; ?>
</div>
<?php page_end('index.php');
