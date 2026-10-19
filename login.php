<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
migrate_if_needed();
start_session();
if (user_count() === 0) redirect('setup.php');
if (current_user()) redirect('index.php');

$err = null; $stage = !empty($_SESSION['pending_uid']) ? 2 : 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (throttled()) { $err = 'Příliš mnoho neúspěšných pokusů. Zkus to za 15 minut.'; }
    elseif (($_POST['a'] ?? '') === 'pw') {
        $st = db()->prepare('SELECT * FROM users WHERE username = ? AND totp_confirmed = 1'); $st->execute([trim((string)($_POST['username'] ?? ''))]);
        $u = $st->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['pass_hash'])) { $_SESSION['pending_uid'] = (int)$u['id']; $_SESSION['pending_at'] = time(); $stage = 2; }
        else { failed_attempt(); $err = 'Špatné jméno nebo heslo.'; }
    } elseif (($_POST['a'] ?? '') === 'code' && !empty($_SESSION['pending_uid']) && time() - (int)($_SESSION['pending_at'] ?? 0) < 600) {
        $st = db()->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([$_SESSION['pending_uid']]); $u = $st->fetch();
        $code = (string)($_POST['code'] ?? '');
        if ($u && (totp_verify($u['totp_secret'], $code) || use_recovery_code($u, $code))) { login_user((int)$u['id']); redirect('index.php'); }
        failed_attempt(); $err = 'Kód nesedí.'; $stage = 2;
    } elseif (($_POST['a'] ?? '') === 'back') { unset($_SESSION['pending_uid']); $stage = 1; }
}
$hasKey = (int)db()->query('SELECT COUNT(*) FROM passkeys')->fetchColumn() > 0;
echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="claude:viewport-fit-sides" content="cover"><title>Přihlášení · Discgolf</title>'
   . '<link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/icon.svg" type="image/svg+xml">'
   . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Inter+Tight:wght@400;500;600&display=swap">'
   . '<link rel="stylesheet" href="' . asset('assets/app.css') . '"></head><body><div class="auth"><a class="brand" href="#"><i></i>Discgolf</a>';
if ($err) echo '<div class="flash err" style="margin-top:14px">' . h($err) . '</div>';
if ($stage === 1) { ?>
  <h1 style="margin-top:22px">Přihlášení</h1>
  <form method="post" class="card" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="a" value="pw">
    <div class="field"><label>Uživatelské jméno</label><input type="text" name="username" required autocapitalize="off" autocomplete="username"></div>
    <div class="field"><label>Heslo</label><input type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn" style="width:100%">Pokračovat</button>
  </form>
  <?php if ($hasKey): ?>
  <button class="btn ghost" id="pklogin" type="button" style="width:100%;margin-top:12px" data-csrf="<?= h(csrf()) ?>">Přihlásit otiskem prstu</button>
  <p class="muted" id="keymsg"></p>
  <script src="<?= h(asset('assets/passkey.js')) ?>"></script>
  <?php endif; ?>
<?php } else { ?>
  <h1 style="margin-top:22px">Ověřovací kód</h1>
  <p class="muted">Otevři Google Authenticator a opiš 6místný kód (nebo použij záložní kód).</p>
  <form method="post" class="card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="a" value="code">
    <div class="field"><input type="text" name="code" inputmode="text" autocomplete="one-time-code" autofocus required style="font-size:24px;letter-spacing:.2em;text-align:center"></div>
    <button class="btn" style="width:100%">Přihlásit</button>
  </form>
  <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="a" value="back"><button class="btn ghost sm">← Zpět</button></form>
<?php }
echo '</div></body></html>';
