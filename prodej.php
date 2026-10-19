<?php
declare(strict_types=1);
// VEŘEJNÁ stránka (bez přihlášení): disky označené „Na prodej“ + poptávkový formulář.
require __DIR__ . '/inc/layout.php';
migrate_if_needed();
start_session();

$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['a'] ?? '') === 'inquiry') {
    csrf_check();
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100); $contact = mb_substr(trim((string)($_POST['contact'] ?? '')), 0, 150);
    $text = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 1500); $did = (int)($_POST['disc_id'] ?? 0);
    $recent = db()->prepare('SELECT COUNT(*) FROM inquiries WHERE ip = ? AND created > ?'); $recent->execute([client_ip(), date('Y-m-d H:i:s', time() - 3600)]);
    if (!empty($_POST['website'])) { $msg = 'Díky, ozvu se.'; }                         // honeypot
    elseif ($name === '' || $contact === '') $err = 'Vyplň jméno a kontakt (telefon nebo e‑mail).';
    elseif ((int)$recent->fetchColumn() >= 5) $err = 'Příliš mnoho zpráv, zkus to později.';
    else {
        $disc = null; if ($did) { $q = db()->prepare('SELECT brand, model FROM discs WHERE id = ? AND for_sale = 1'); $q->execute([$did]); $disc = $q->fetch() ?: null; }
        db()->prepare('INSERT INTO inquiries (disc_id, name, contact, message, ip, created) VALUES (?,?,?,?,?,?)')->execute([$disc ? $did : null, $name, $contact, $text, client_ip(), now()]);
        $to = setting('notify_email', '');
        if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $body = "Nová poptávka z veřejného prodeje\n\nDisk: " . ($disc ? $disc['brand'] . ' ' . $disc['model'] : '(obecně)') . "\nJméno: $name\nKontakt: $contact\n\n$text\n";
            @mail($to, '=?UTF-8?B?' . base64_encode('Poptávka disku: ' . ($disc ? $disc['model'] : 'obecně')) . '?=', $body, "Content-Type: text/plain; charset=UTF-8\r\nFrom: discgolf@" . preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
        }
        $msg = 'Díky! Zprávu jsem dostal a ozvu se ti.';
    }
}

$search = trim((string)($_GET['q'] ?? '')); $cat = (string)($_GET['cat'] ?? ''); $sort = (string)($_GET['sort'] ?? 'new');
$sql = 'SELECT * FROM discs WHERE for_sale = 1 AND is_sold = 0 AND is_lost = 0'; $par = [];
if ($search !== '') { $sql .= ' AND (model LIKE ? OR brand LIKE ? OR plastic LIKE ?)'; array_push($par, "%$search%", "%$search%", "%$search%"); }
if (in_array($cat, ['putter', 'approach', 'midrange', 'fairway', 'distance'], true)) { $sql .= ' AND category = ?'; $par[] = $cat; }
$sql .= match ($sort) { 'asc' => ' ORDER BY price ASC, id DESC', 'desc' => ' ORDER BY price DESC, id DESC', 'model' => ' ORDER BY model ASC', default => ' ORDER BY id DESC' };
$st = db()->prepare($sql); $st->execute($par); $discs = $st->fetchAll();

$title = setting('sale_title', 'Disky na prodej');
$intro = setting('sale_intro', 'Nabídka vyřazených a přebytečných disků k odkupu. Budu rád, když budou lítat, proto držím ceny nízko. Vyber si disk, klepni na „Mám zájem“ a domluvíme se na předání – osobní odběr, Zásilkovna, Alzabox, doprava jde za tebou.');
$contactLine = setting('sale_contact', 'WhatsApp 724 257 563 · info@marmal.cz');
public_start($title);
?>
<div class="pub-head">
  <h1><?= h($title) ?></h1>
  <p class="muted" style="margin:0;max-width:70ch"><?= nl2br(h($intro)) ?></p>
  <p style="margin:0"><b><?= h($contactLine) ?></b></p>
  <div><span class="pill b" style="font-size:14px">Na prodej: <?= count($discs) ?> ks</span></div>
</div>
<?php if ($msg) echo '<div class="flash">' . h($msg) . '</div>'; if ($err) echo '<div class="flash err">' . h($err) . '</div>'; ?>
<form method="get" class="card" style="margin-bottom:16px">
  <div class="row2" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <input type="text" name="q" placeholder="Hledat model, značku, plast…" value="<?= h($search) ?>">
    <select name="cat"><option value="">Všechny typy</option><?php foreach (['putter', 'approach', 'midrange', 'fairway', 'distance'] as $c) echo '<option value="' . $c . '"' . ($cat === $c ? ' selected' : '') . '>' . h(category_name($c)) . '</option>'; ?></select>
    <select name="sort"><option value="new">Nejnovější</option><option value="asc"<?= $sort === 'asc' ? ' selected' : '' ?>>Cena ↑</option><option value="desc"<?= $sort === 'desc' ? ' selected' : '' ?>>Cena ↓</option><option value="model"<?= $sort === 'model' ? ' selected' : '' ?>>Název A–Z</option></select>
    <button class="btn">Filtrovat</button>
  </div>
</form>
<?php if (!$discs): ?><div class="card empty"><b>Právě nic nenabízím</b>Zkus to později nebo uprav filtr.</div><?php else: ?>
<div class="disc-grid">
<?php foreach ($discs as $d): ?>
  <div class="disc">
    <div class="ph-img"><?php if ($d['image_path']): ?><img src="<?= h($d['image_path']) ?>" alt="<?= h($d['brand'] . ' ' . $d['model']) ?>" loading="lazy"><?php endif; ?></div>
    <div class="b">
      <div class="nm"><?= h($d['model']) ?></div>
      <div class="br"><?= h($d['brand']) ?> · <?= h(category_name($d['category'])) ?><?= $d['plastic'] ? ' · ' . h($d['plastic']) : '' ?><?= $d['weight'] ? ' · ' . (int)$d['weight'] . ' g' : '' ?></div>
      <div class="flight" title="speed / glide / turn / fade"><span><?= num((float)$d['speed']) ?></span><span><?= num((float)$d['glide']) ?></span><span><?= num((float)$d['turn']) ?></span><span><?= num((float)$d['fade']) ?></span></div>
      <?php if ($d['note']): ?><div class="muted" style="font-size:13px"><?= nl2br(h($d['note'])) ?></div><?php endif; ?>
      <div class="price"><?= $d['price'] ? (int)$d['price'] . ' Kč' : 'Cena dohodou' ?></div>
      <button class="btn sm" data-inq="<?= (int)$d['id'] ?>" data-name="<?= h($d['brand'] . ' ' . $d['model']) ?>">Mám zájem</button>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="modal" id="inq" role="dialog" aria-modal="true" aria-labelledby="inqt">
  <form method="post" class="sheet"><?= csrf_field() ?><input type="hidden" name="a" value="inquiry"><input type="hidden" name="disc_id" value="">
    <h2>Mám zájem: <span id="inqt"></span></h2>
    <div class="field"><label>Jméno</label><input type="text" name="name" required maxlength="100" autocomplete="name"></div>
    <div class="field"><label>Telefon nebo e‑mail</label><input type="text" name="contact" required maxlength="150" autocomplete="email"></div>
    <div class="field"><label>Zpráva (nepovinné)</label><textarea name="message" maxlength="1500" placeholder="Např. kde bys chtěl převzít…"></textarea></div>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div style="display:flex;gap:8px"><button class="btn">Odeslat</button><button type="button" class="btn ghost" data-close>Zavřít</button></div>
  </form>
</div>
<?php public_end();
