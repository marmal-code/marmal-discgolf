<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
migrate_if_needed();
$u = require_login();

$codes = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['a'] ?? '';
    if ($a === 'prodej') {
        foreach (['sale_title', 'sale_intro', 'sale_contact', 'notify_email'] as $k) set_setting($k, trim((string)($_POST[$k] ?? '')));
        flash('Nastavení prodeje uloženo.'); redirect('nastaveni.php');
    }
    if ($a === 'hrac') {
        set_setting('player_name', trim((string)($_POST['player_name'] ?? '')) ?: 'Hráč'); set_setting('player_pdga', (string)(int)($_POST['player_pdga'] ?? 0)); set_setting('rating_goal', (string)(int)($_POST['rating_goal'] ?? 980));
        flash('Profil uložen.'); redirect('nastaveni.php');
    }
    if ($a === 'heslo') {
        if (!password_verify((string)($_POST['old'] ?? ''), $u['pass_hash'])) flash('Současné heslo nesedí.', 'err');
        elseif (strlen((string)($_POST['new'] ?? '')) < 10) flash('Nové heslo musí mít aspoň 10 znaků.', 'err');
        elseif ($_POST['new'] !== ($_POST['new2'] ?? '')) flash('Nová hesla se neshodují.', 'err');
        else { db()->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')->execute([password_hash((string)$_POST['new'], PASSWORD_DEFAULT), $u['id']]); flash('Heslo změněno.'); }
        redirect('nastaveni.php');
    }
    if ($a === 'del_key') { db()->prepare('DELETE FROM passkeys WHERE id = ? AND user_id = ?')->execute([(int)$_POST['id'], $u['id']]); flash('Klíč odstraněn.'); redirect('nastaveni.php'); }
    if ($a === 'codes') { $codes = gen_recovery_codes((int)$u['id']); }
}
$keys = db()->prepare('SELECT * FROM passkeys WHERE user_id = ? ORDER BY id'); $keys->execute([$u['id']]); $keys = $keys->fetchAll();
$left = count(json_decode((string)$u['recovery_codes'], true) ?: []);
$gh = config()['github'] ?? []; $ghOk = !empty($gh['token']) && $gh['token'] !== 'DOPLNIT';

page_start('Nastavení', 'nastaveni.php');
?>
<div class="ph">Nastavení <small><?= h($u['username']) ?></small></div>

<section class="card">
  <h2>Aplikace</h2>
  <div class="kv"><span>Verze</span><b><?= h(substr(app_version(), 0, 7)) ?></b></div>
  <div class="kv"><span>Poslední aktualizace</span><b><?= h(setting('last_update') ? date('j. n. Y H:i', strtotime((string)setting('last_update'))) : '—') ?></b></div>
  <p class="muted" id="upmsg" style="margin:8px 0"><?= $ghOk ? 'Stáhne nejnovější verzi z GitHubu a sama aktualizuje databázi.' : 'Pro aktualizaci doplň v config.php GitHub token.' ?></p>
  <button class="btn" id="doupdate" <?= $ghOk ? '' : 'disabled' ?>>Aktualizovat aplikaci</button>
  <pre class="log" id="uplog" hidden></pre>
</section>

<section class="card">
  <h2>Přihlášení a zabezpečení</h2>
  <div class="kv"><span>Google Authenticator</span><b>aktivní ✓</b></div>
  <div class="kv"><span>Záložní kódy</span><b><?= $left ?> zbývá</b></div>
  <h3 style="margin-top:14px">Otisk prstu / Face ID</h3>
  <?php foreach ($keys as $k): ?>
    <form method="post" class="kv" data-confirm="Odstranit tento klíč?"><?= csrf_field() ?><input type="hidden" name="a" value="del_key"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
      <span><?= h($k['name']) ?> <span class="muted">· <?= h(date('j. n. Y', strtotime($k['created']))) ?></span></span><button class="btn sm danger">Odebrat</button></form>
  <?php endforeach; if (!$keys) echo '<p class="muted">Zatím žádný.</p>'; ?>
  <button class="btn ghost" id="addkey" type="button" data-csrf="<?= h(csrf()) ?>">Přidat toto zařízení</button> <span class="muted" id="keymsg"></span>
  <script>document.body.dataset.reload = '1';</script>
  <form method="post" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="a" value="codes"><button class="btn ghost sm">Vygenerovat nové záložní kódy</button></form>
  <?php if ($codes): ?><p class="muted">Ulož si je – znovu se nezobrazí a staré přestaly platit:</p><div class="codes"><?php foreach ($codes as $c) echo '<span>' . h($c) . '</span>'; ?></div><?php endif; ?>
  <h3 style="margin-top:18px">Změna hesla</h3>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="heslo">
    <div class="field"><input type="password" name="old" placeholder="Současné heslo" autocomplete="current-password" required></div>
    <div class="row2"><div class="field"><input type="password" name="new" placeholder="Nové heslo (min. 10)" autocomplete="new-password" required></div><div class="field"><input type="password" name="new2" placeholder="Nové heslo znovu" autocomplete="new-password" required></div></div>
    <button class="btn ghost sm">Změnit heslo</button></form>
</section>

<section class="card">
  <h2>Veřejný prodej disků</h2>
  <p class="muted" style="margin-top:0">Adresa: <a href="prodej.html" target="_blank" rel="noopener"><?= h((is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . dirname($_SERVER['SCRIPT_NAME'] ?? '/') ) ?>/prodej.html</a></p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="prodej">
    <div class="field"><label>Nadpis stránky</label><input type="text" name="sale_title" value="<?= h(setting('sale_title', 'Disky na prodej')) ?>"></div>
    <div class="field"><label>Úvodní text</label><textarea name="sale_intro"><?= h(setting('sale_intro', 'Nabídka vyřazených a přebytečných disků k odkupu. Budu rád, když budou lítat, proto držím ceny nízko. Vyber si disk, klepni na „Mám zájem“ a domluvíme se na předání – osobní odběr, Zásilkovna, Alzabox, doprava jde za tebou.')) ?></textarea></div>
    <div class="field"><label>Kontakt (zobrazí se veřejně)</label><input type="text" name="sale_contact" value="<?= h(setting('sale_contact', 'WhatsApp 724 257 563 · info@marmal.cz')) ?>"></div>
    <div class="field"><label>E‑mail pro upozornění na novou poptávku</label><input type="email" name="notify_email" value="<?= h(setting('notify_email', '')) ?>" placeholder="prázdné = jen v aplikaci"></div>
    <button class="btn">Uložit</button></form>
</section>

<section class="card">
  <h2>Upozornění na mobilu</h2>
  <p class="muted" style="margin-top:0">Když někdo pošle poptávku z veřejného prodeje, přijde ti notifikace. Zapni to na každém zařízení zvlášť. Na iPhonu to funguje jen v aplikaci přidané na plochu (iOS 16.4+).</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn" id="pushon" type="button">Zapnout na tomto zařízení</button><button class="btn ghost" id="pushtest" type="button">Poslat zkušební upozornění</button></div>
  <p class="muted" id="pushmsg" style="margin-bottom:0"></p>
  <?php $subs = db()->query('SELECT id, name, created FROM push_subs ORDER BY id')->fetchAll(); if ($subs): ?>
  <ul class="sellist" style="margin-top:10px"><?php foreach ($subs as $s) echo '<li><span>' . h($s['name']) . ' <span class="muted">· od ' . h(date('j. n. Y', strtotime($s['created']))) . '</span></span><button class="btn sm danger" type="button" data-pushrm="' . (int)$s['id'] . '">Odebrat</button></li>'; ?></ul>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Profil hráče</h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="hrac">
    <div class="row2"><div class="field"><label>Jméno</label><input type="text" name="player_name" value="<?= h(setting('player_name', 'Martin Malý')) ?>"></div>
    <div class="field"><label>PDGA číslo</label><input type="number" name="player_pdga" value="<?= h(setting('player_pdga', '113434')) ?>"></div>
    <div class="field"><label>Cíl ratingu</label><input type="number" name="rating_goal" value="<?= h(setting('rating_goal', '980')) ?>"></div></div>
    <button class="btn ghost">Uložit</button></form>
</section>
<script>window.CSRF = <?= json_encode(csrf()) ?>;</script>
<script src="<?= h(asset('assets/passkey.js')) ?>"></script>
<script src="<?= h(asset('assets/push.js')) ?>"></script>
<p><a class="btn ghost" href="logout.php">Odhlásit se</a></p>
<?php page_end('nastaveni.php');
