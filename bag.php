<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/discs.php';
migrate_if_needed();
require_login();

$discs = db()->query('SELECT * FROM discs ORDER BY ' . DISC_ORDER)->fetchAll();
$active = array_filter($discs, 'disc_active');
$cnt = array_fill_keys(array_keys(TAGS), 0);
foreach ($active as $d) foreach (disc_tags($d) as $t) $cnt[$t]++;
$cntAll = array_fill_keys(array_keys(TAGS), 0);
foreach ($discs as $d) foreach (disc_tags($d) as $t) $cntAll[$t]++;
$newInq = (int)db()->query('SELECT COUNT(*) FROM inquiries WHERE handled = 0')->fetchColumn();

page_start('Bag', 'bag.php');
?>
<div class="ph">Bag <small><?= count($active) ?> disků<?= count($discs) > count($active) ? ' · ' . (count($discs) - count($active)) . ' prodaných/ztracených' : '' ?></small></div>
<div class="kpis">
  <div class="kpi hl"><b><?= $cnt['bag'] ?></b><span>v bagu</span></div>
  <div class="kpi"><b><?= $cnt['train'] ?></b><span>trénink</span></div>
  <div class="kpi"><b><?= $cnt['test'] ?></b><span>testuji</span></div>
  <div class="kpi"><b><?= $cnt['sale'] ?></b><span>na prodej</span></div>
</div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
  <a class="btn" href="disc.php">+ Přidat disk</a>
  <a class="btn ghost" href="poptavky.php">Poptávky<?= $newInq ? " ($newInq)" : '' ?></a>
  <a class="btn ghost" href="prodej.html" target="_blank" rel="noopener">Veřejný prodej ↗</a>
</div>
<div class="card filters" id="filters">
  <input type="text" id="q" placeholder="Hledat model, značku, plast…" autocomplete="off">
  <div class="fr">
    <select id="fcat"><option value="">Všechny typy</option><?php foreach (CATEGORIES as $c) echo '<option value="' . $c . '">' . h(category_name($c)) . '</option>'; ?></select>
    <select id="fspeed"><option value="">Speed</option><?php for ($i = 1; $i <= 15; $i++) echo "<option>$i</option>"; ?></select>
    <select id="fturn"><option value="">Turn</option><option value="neg">záporný (←)</option><option value="0">0</option></select>
    <select id="ffade"><option value="">Fade</option><?php for ($i = 0; $i <= 5; $i++) echo "<option>$i</option>"; ?></select>
  </div>
  <div class="chips" id="fstatus">
    <?php foreach (TAGS as $k => [$col, $label]) echo '<button type="button" class="chip t t-' . $k . '" aria-pressed="false" data-s="' . $k . '">' . h($label) . ' <span class="muted" style="opacity:.8">' . ($cntAll[$k] ?? 0) . '</span></button>'; ?>
  </div>
  <p class="muted" style="margin:0;font-size:13px">Štítky fungují jako filtr – víc vybraných ukáže disky s kterýmkoli z nich. Klepnout lze i na štítek u disku.</p>
</div>
<div class="disc-grid" id="grid">
<?php foreach ($discs as $d): $tags = disc_tags($d); $pt = $tags[0] ?? null; ?>
  <div class="disc" style="<?= $pt ? '--c:var(--t-' . $pt . ')' : '' ?>" data-id="<?= (int)$d['id'] ?>" data-tags="<?= h(implode(' ', $tags)) ?>" data-cat="<?= h($d['category']) ?>" data-speed="<?= (int)round($d['speed']) ?>" data-turn="<?= $d['turn'] < 0 ? 'neg' : '0' ?>" data-fade="<?= (int)round($d['fade']) ?>"
       data-t="<?= h(mb_strtolower($d['brand'] . ' ' . $d['model'] . ' ' . $d['plastic'] . ' ' . $d['note'])) ?>">
    <a class="ph-img" href="disc.php?id=<?= (int)$d['id'] ?>"><?php if ($d['image_path']): ?><img src="<?= h($d['image_path']) ?>" alt="" loading="lazy"><?php endif; ?></a>
    <div class="b">
      <a href="disc.php?id=<?= (int)$d['id'] ?>" style="color:inherit;text-decoration:none"><div class="nm"><?= h($d['model']) ?></div><div class="br"><?= h($d['brand']) ?><?= $d['plastic'] ? ' · ' . h($d['plastic']) : '' ?><?= $d['weight'] ? ' · ' . (int)$d['weight'] . ' g' : '' ?></div></a>
      <div class="flight"><span><?= num((float)$d['speed']) ?></span><span><?= num((float)$d['glide']) ?></span><span><?= num((float)$d['turn']) ?></span><span><?= num((float)$d['fade']) ?></span></div>
      <div class="bd">
        <?php foreach ($tags as $t) echo '<button type="button" class="tg t-' . $t . '" data-s="' . $t . '">' . h(TAGS[$t][1]) . '</button>'; ?>
        <?php if ($d['price'] && ($d['for_sale'] || $d['is_sold'])) echo '<span class="tg" style="background:var(--line);color:var(--fg);cursor:default">' . (int)$d['price'] . ' Kč</span>'; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<p class="muted" id="none" hidden>Nic neodpovídá filtru.</p>
<?php page_end('bag.php');
