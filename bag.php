<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/discs.php';
migrate_if_needed();
require_login();

$discs = db()->query('SELECT * FROM discs ORDER BY in_bag DESC, FIELD(category,"putter","approach","midrange","fairway","distance"), speed, model')->fetchAll();
$cnt = ['bag' => 0, 'sale' => 0, 'sold' => 0, 'lost' => 0, 'other' => 0];
foreach ($discs as $d) $cnt[disc_status($d)]++;
$newInq = (int)db()->query('SELECT COUNT(*) FROM inquiries WHERE handled = 0')->fetchColumn();

page_start('Bag', 'bag.php');
?>
<div class="ph">Bag <small><?= count($discs) ?> disků</small></div>
<div class="kpis">
  <div class="kpi hl"><b><?= $cnt['bag'] ?></b><span>v bagu</span></div>
  <div class="kpi"><b><?= $cnt['other'] ?></b><span>doma / trénink</span></div>
  <div class="kpi"><b><?= $cnt['sale'] ?></b><span>na prodej</span></div>
  <div class="kpi"><b><?= $cnt['sold'] + $cnt['lost'] ?></b><span>prodáno / ztraceno</span></div>
</div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
  <a class="btn" href="disc.php">+ Přidat disk</a>
  <a class="btn ghost" href="poptavky.php">Poptávky<?= $newInq ? " ($newInq)" : '' ?></a>
  <a class="btn ghost" href="prodej.php" target="_blank" rel="noopener">Veřejný prodej ↗</a>
</div>
<div class="card filters" id="filters">
  <input type="text" id="q" placeholder="Hledat model, značku, plast…" autocomplete="off">
  <div class="fr">
    <select id="fcat"><option value="">Všechny typy</option><?php foreach (CATEGORIES as $c) echo '<option value="' . $c . '">' . h(category_name($c)) . '</option>'; ?></select>
    <select id="fspeed"><option value="">Speed</option><?php for ($i = 1; $i <= 15; $i++) echo "<option>$i</option>"; ?></select>
    <select id="fturn"><option value="">Turn</option><option value="neg">záporný (overstable ←)</option><option value="0">0</option></select>
    <select id="ffade"><option value="">Fade</option><?php for ($i = 0; $i <= 5; $i++) echo "<option>$i</option>"; ?></select>
  </div>
  <div class="chips" id="fstatus">
    <button class="chip" aria-pressed="false" data-s="bag">V bagu</button>
    <button class="chip" aria-pressed="false" data-s="other">Doma</button>
    <button class="chip" aria-pressed="false" data-s="sale">Na prodej</button>
    <button class="chip" aria-pressed="false" data-s="sold">Prodáno</button>
    <button class="chip" aria-pressed="false" data-s="lost">Ztraceno</button>
  </div>
</div>
<div class="disc-grid" id="grid">
<?php foreach ($discs as $d): $st = disc_status($d); ?>
  <div class="disc" data-id="<?= (int)$d["id"] ?>" data-price="<?= (int)$d["price"] ?>" data-s="<?= $st ?>" data-cat="<?= h($d['category']) ?>" data-speed="<?= (int)round($d['speed']) ?>" data-turn="<?= $d['turn'] < 0 ? 'neg' : '0' ?>" data-fade="<?= (int)round($d['fade']) ?>"
       data-t="<?= h(mb_strtolower($d['brand'] . ' ' . $d['model'] . ' ' . $d['plastic'] . ' ' . $d['note'])) ?>">
    <a class="ph-img" href="disc.php?id=<?= (int)$d['id'] ?>"><?php if ($d['image_path']): ?><img src="<?= h($d['image_path']) ?>" alt="" loading="lazy"><?php endif; ?></a>
    <div class="b">
      <a href="disc.php?id=<?= (int)$d['id'] ?>" style="color:inherit;text-decoration:none"><div class="nm"><?= h($d['model']) ?></div><div class="br"><?= h($d['brand']) ?><?= $d['plastic'] ? ' · ' . h($d['plastic']) : '' ?><?= $d['weight'] ? ' · ' . (int)$d['weight'] . ' g' : '' ?></div></a>
      <div class="flight"><span><?= num((float)$d['speed']) ?></span><span><?= num((float)$d['glide']) ?></span><span><?= num((float)$d['turn']) ?></span><span><?= num((float)$d['fade']) ?></span></div>
      <div class="bd">
        <?php if ($st === 'bag') echo '<span class="bdg bag">V bagu</span>'; if ($st === 'sale') echo '<span class="bdg sale">Na prodej</span>'; if ($st === 'sold') echo '<span class="bdg sold">Prodáno</span>'; if ($st === 'lost') echo '<span class="bdg lost">Ztraceno</span>'; ?>
        <?php if ($d['price'] && ($st === 'sale' || $st === 'sold')) echo '<span class="bdg">' . (int)$d['price'] . ' Kč</span>'; ?>
      </div>
      <?php if (!$d['is_sold'] && !$d['is_lost']): ?>
      <div class="quick">
        <button class="btn sm ghost" data-toggle="in_bag"><?= $d['in_bag'] ? 'Z bagu' : 'Do bagu' ?></button>
        <button class="btn sm ghost" data-toggle="for_sale"><?= $d['for_sale'] ? 'Stáhnout' : 'Prodat' ?></button>
      </div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<p class="muted" id="none" hidden>Nic neodpovídá filtru.</p>
<script>window.CSRF = <?= json_encode(csrf()) ?>;</script>
<?php page_end('bag.php');
