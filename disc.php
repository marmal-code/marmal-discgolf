<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/discs.php';
migrate_if_needed();
require_login();

$id = (int)($_GET['id'] ?? 0); $d = null;
if ($id) { $st = db()->prepare('SELECT * FROM discs WHERE id = ?'); $st->execute([$id]); $d = $st->fetch() ?: null; if (!$d) redirect('bag.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['a'] ?? '') === 'delete' && $d) {
        if ($d['image_path'] && str_starts_with($d['image_path'], 'uploads/discs/')) @unlink(ROOT . '/' . $d['image_path']);
        db()->prepare('DELETE FROM discs WHERE id = ?')->execute([$id]);
        flash('Disk smazán.'); redirect('bag.php');
    }
    $v = disc_form_values($_POST);
    if ($v['model'] === '' || $v['brand'] === '') { flash('Vyplň značku a model.', 'err'); redirect('disc.php' . ($id ? "?id=$id" : '')); }
    $img = save_disc_image($_FILES['image'] ?? []);
    if ($d) {
        $sql = 'UPDATE discs SET ' . implode(', ', array_map(fn($k) => "$k=?", array_keys($v))) . ($img ? ', image_path=?' : '') . ' WHERE id=?';
        $args = array_values($v); if ($img) { $args[] = $img; if ($d['image_path'] && str_starts_with($d['image_path'], 'uploads/discs/')) @unlink(ROOT . '/' . $d['image_path']); }
        $args[] = $id; db()->prepare($sql)->execute($args);
        flash('Uloženo.');
    } else {
        $v['image_path'] = $img; $v['created_at'] = now();
        db()->prepare('INSERT INTO discs (' . implode(',', array_keys($v)) . ') VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')')->execute(array_values($v));
        $id = (int)db()->lastInsertId(); flash('Disk přidán.');
    }
    redirect('bag.php');
}
$x = $d ?: ['brand' => '', 'model' => '', 'category' => 'putter', 'speed' => 3, 'glide' => 3, 'turn' => 0, 'fade' => 1, 'weight' => '', 'plastic' => '', 'in_bag' => 0, 'training' => 0, 'testing' => 0, 'note' => '', 'is_lost' => 0, 'is_sold' => 0, 'for_sale' => 0, 'price' => '', 'image_path' => null];
$brands = db()->query('SELECT DISTINCT brand FROM discs ORDER BY brand')->fetchAll(PDO::FETCH_COLUMN);
$plastics = db()->query('SELECT DISTINCT plastic FROM discs WHERE plastic IS NOT NULL AND plastic <> "" ORDER BY plastic')->fetchAll(PDO::FETCH_COLUMN);
page_start($d ? 'Upravit disk' : 'Nový disk', 'bag.php');
?>
<div class="ph"><?= $d ? h($d['model']) : 'Nový disk' ?> <small><a href="bag.php">← Bag</a></small></div>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?>
  <?php if ($x['image_path']): ?><img src="<?= h($x['image_path']) ?>" alt="" style="width:100%;max-width:320px;border-radius:12px;display:block;margin-bottom:12px"><?php endif; ?>
  <div class="field"><label>Fotka <?= $x['image_path'] ? '(nahrát novou)' : '' ?></label><input type="file" name="image" accept="image/*" capture="environment"></div>
  <div class="row2">
    <div class="field"><label>Značka</label><input type="text" name="brand" list="brands" value="<?= h($x['brand']) ?>" required></div>
    <div class="field"><label>Model</label><input type="text" name="model" value="<?= h($x['model']) ?>" required></div>
  </div>
  <datalist id="brands"><?php foreach ($brands as $b) echo '<option value="' . h($b) . '">'; ?></datalist>
  <div class="row2">
    <div class="field"><label>Typ</label><select name="category"><?php foreach (CATEGORIES as $c) echo '<option value="' . $c . '"' . ($x['category'] === $c ? ' selected' : '') . '>' . h(category_name($c)) . '</option>'; ?></select></div>
    <div class="field"><label>Plast</label><input type="text" name="plastic" list="plastics" value="<?= h((string)$x['plastic']) ?>"></div>
    <div class="field"><label>Hmotnost (g)</label><input type="number" name="weight" min="100" max="230" value="<?= h((string)$x['weight']) ?>"></div>
  </div>
  <datalist id="plastics"><?php foreach ($plastics as $p) echo '<option value="' . h($p) . '">'; ?></datalist>
  <div class="lbl" style="margin-bottom:6px">Let disku (speed / glide / turn / fade)</div>
  <div class="row2" style="grid-template-columns:repeat(4,1fr)">
    <?php foreach (['speed', 'glide', 'turn', 'fade'] as $k): ?><div class="field"><input type="text" inputmode="decimal" name="<?= $k ?>" value="<?= h(num((float)$x[$k])) ?>" aria-label="<?= $k ?>"></div><?php endforeach; ?>
  </div>
  <div class="field"><label>Poznámka</label><textarea name="note"><?= h((string)$x['note']) ?></textarea></div>
  <div class="lbl" style="margin:6px 0">Stav disku (lze kombinovat)</div>
  <div class="flags">
    <?php foreach (TAGS as $k => [$col, $label]): ?>
      <label class="flag" style="--c:var(--t-<?= $k ?>)"><input type="checkbox" name="<?= $col ?>" id="f_<?= $k ?>" <?= !empty($x[$col]) ? 'checked' : '' ?>><span><?= h($label) ?></span></label>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="font-size:13px;margin:6px 0 12px">Prodaný nebo ztracený disk se automaticky vyřadí z bagu a z prodeje.</p>
  <div class="field" id="pricebox"><label>Cena (Kč)</label><input type="number" name="price" min="0" value="<?= h((string)$x['price']) ?>"></div>
  <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap"><button class="btn">Uložit</button><a class="btn ghost" href="bag.php">Zrušit</a></div>
</form>
<?php if ($d): ?>
<form method="post" class="card" data-confirm="Opravdu smazat tento disk?"><?= csrf_field() ?><input type="hidden" name="a" value="delete"><button class="btn danger">Smazat disk</button></form>
<?php endif; ?>
<?php page_end('bag.php');
