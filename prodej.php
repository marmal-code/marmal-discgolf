<?php
declare(strict_types=1);
// VEŘEJNÁ stránka (bez přihlášení): disky označené „Na prodej“ + poptávkový formulář.
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/push.php';
migrate_if_needed();
start_session();

$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['a'] ?? '') === 'inquiry') {
    csrf_check();
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100); $contact = mb_substr(trim((string)($_POST['contact'] ?? '')), 0, 150);
    $text = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 1500);
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    $ids = array_slice($ids, 0, 60);
    $recent = db()->prepare('SELECT COUNT(*) FROM inquiries WHERE ip = ? AND created > ?'); $recent->execute([client_ip(), date('Y-m-d H:i:s', time() - 3600)]);
    $items = [];
    if ($ids) { $q = db()->prepare('SELECT id, brand, model, price FROM discs WHERE for_sale = 1 AND is_sold = 0 AND is_lost = 0 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'); $q->execute($ids); $items = $q->fetchAll(); }
    if (!empty($_POST['website'])) { $msg = 'Díky, ozvu se.'; }                         // honeypot
    elseif (!$items) $err = 'Vyber alespoň jeden disk (už mohl být prodán).';
    elseif ($name === '' || $contact === '') $err = 'Vyplň jméno a kontakt (telefon nebo e‑mail).';
    elseif ((int)$recent->fetchColumn() >= 5) $err = 'Příliš mnoho zpráv, zkus to později.';
    else {
        db()->prepare('INSERT INTO inquiries (disc_id, name, contact, message, ip, created) VALUES (?,?,?,?,?,?)')->execute([count($items) === 1 ? (int)$items[0]['id'] : null, $name, $contact, $text, client_ip(), now()]);
        $iid = (int)db()->lastInsertId();
        $ins = db()->prepare('INSERT INTO inquiry_items (inquiry_id, disc_id) VALUES (?,?)'); foreach ($items as $it) $ins->execute([$iid, (int)$it['id']]);
        $list = implode("\n", array_map(fn($it) => '• ' . $it['brand'] . ' ' . $it['model'] . ($it['price'] ? ' – ' . (int)$it['price'] . ' Kč' : ''), $items));
        $to = setting('notify_email', '');
        if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $body = "Nová poptávka z veřejného prodeje\n\nDisky:\n$list\n\nJméno: $name\nKontakt: $contact\n\n$text\n";
            @mail($to, '=?UTF-8?B?' . base64_encode('Poptávka disků (' . count($items) . ')') . '?=', $body, "Content-Type: text/plain; charset=UTF-8\r\nFrom: discgolf@" . preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
        }
        try { push_all(); } catch (Throwable $e) { /* push nesmí shodit odeslání */ }
        $msg = 'Díky! Zprávu jsem dostal a ozvu se ti.';
    }
}

$sql = 'SELECT * FROM discs WHERE for_sale = 1 AND is_sold = 0 AND is_lost = 0 ORDER BY id DESC';
$discs = db()->query($sql)->fetchAll();

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
<div class="card" style="margin-bottom:16px" id="pf">
  <div class="row2" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <input type="text" id="pq" placeholder="Hledat model, značku, plast…">
    <select id="pcat"><option value="">Všechny typy</option><?php foreach (['putter', 'approach', 'midrange', 'fairway', 'distance'] as $c) echo '<option value="' . $c . '">' . h(category_name($c)) . '</option>'; ?></select>
    <select id="psort"><option value="new">Nejnovější</option><option value="asc">Cena ↑</option><option value="desc">Cena ↓</option><option value="model">Název A–Z</option></select>
  </div>
</div>
<form method="post" action="prodej.html" id="saleform">
<?php if (!$discs): ?><div class="card empty"><b>Právě nic nenabízím</b>Zkus to později.</div><?php else: ?>
<p class="muted" style="margin:0 0 10px">Klepni na „Chci“ u disků, které tě zajímají (můžeš jich vybrat víc), a pak je pošli jedním formulářem.</p>
<div class="disc-grid" id="pgrid">
<?php foreach ($discs as $d): ?>
  <label class="disc pick" data-cat="<?= h($d['category']) ?>" data-price="<?= (int)$d['price'] ?>" data-model="<?= h(mb_strtolower($d['model'])) ?>" data-t="<?= h(mb_strtolower($d['brand'] . ' ' . $d['model'] . ' ' . $d['plastic'])) ?>" data-label="<?= h($d['brand'] . ' ' . $d['model']) ?>">
    <div class="ph-img"><?php if ($d['image_path']): ?><img src="<?= h($d['image_path']) ?>" alt="<?= h($d['brand'] . ' ' . $d['model']) ?>" loading="lazy"><?php endif; ?></div>
    <div class="b">
      <div class="nm"><?= h($d['model']) ?></div>
      <div class="br"><?= h($d['brand']) ?> · <?= h(category_name($d['category'])) ?><?= $d['plastic'] ? ' · ' . h($d['plastic']) : '' ?><?= $d['weight'] ? ' · ' . (int)$d['weight'] . ' g' : '' ?></div>
      <div class="flight" title="speed / glide / turn / fade"><span><?= num((float)$d['speed']) ?></span><span><?= num((float)$d['glide']) ?></span><span><?= num((float)$d['turn']) ?></span><span><?= num((float)$d['fade']) ?></span></div>
      <?php if ($d['note']): ?><div class="muted" style="font-size:13px"><?= nl2br(h($d['note'])) ?></div><?php endif; ?>
      <div class="price"><?= $d['price'] ? (int)$d['price'] . ' Kč' : 'Cena dohodou' ?></div>
      <span class="pickbtn"><input type="checkbox" name="ids[]" value="<?= (int)$d['id'] ?>" data-price="<?= (int)$d['price'] ?>"><span class="off">Chci tento disk</span><span class="on">✓ Vybráno</span></span>
    </div>
  </label>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="selbar" id="selbar"><span><b id="selcnt">0</b> vybráno<span id="selsum" class="muted"></span></span><button type="button" class="btn" id="selgo">Odeslat zájem</button></div>
<div class="modal" id="inq" role="dialog" aria-modal="true" aria-labelledby="inqt">
  <div class="sheet"><?= csrf_field() ?><input type="hidden" name="a" value="inquiry">
    <h2 id="inqt">Odeslat zájem</h2>
    <ul class="sellist" id="sellist"></ul>
    <div class="field"><label>Jméno</label><input type="text" name="name" required maxlength="100" autocomplete="name"></div>
    <div class="field"><label>Telefon nebo e‑mail</label><input type="text" name="contact" required maxlength="150" autocomplete="email"></div>
    <div class="field"><label>Zpráva (nepovinné)</label><textarea name="message" maxlength="1500" placeholder="Např. kde bys chtěl převzít…"></textarea></div>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div style="display:flex;gap:8px"><button class="btn">Odeslat</button><button type="button" class="btn ghost" data-close>Zpět k výběru</button></div>
  </div>
</div>
</form>
<script src="<?= h(asset('assets/sale.js')) ?>"></script>
<?php public_end();
