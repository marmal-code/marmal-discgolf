<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
migrate_if_needed();
start_session();

// Průvodce prvním spuštěním: 1) účet  2) QR kód pro Google Authenticator  3) záložní kódy + otisk prstu
if (user_count() > 0 && empty($_SESSION['setup_uid'])) redirect('login.php');

$err = null; $step = 1; $uid = (int)($_SESSION['setup_uid'] ?? 0); $user = null;
if ($uid) { $st = db()->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([$uid]); $user = $st->fetch() ?: null; }
if ($user) $step = $user['totp_confirmed'] ? 3 : 2;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['a'] ?? '';
    if ($a === 'account' && $step === 1) {
        $key = (string)(config()['setup_key'] ?? '');
        $username = trim((string)($_POST['username'] ?? '')); $pw = (string)($_POST['password'] ?? '');
        if ($key !== '' && !hash_equals($key, trim((string)($_POST['setup_key'] ?? '')))) { $err = 'Nesprávný instalační klíč (je v config.php).'; failed_attempt(); }
        elseif (throttled()) $err = 'Příliš mnoho pokusů, zkus to za chvíli.';
        elseif (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username)) $err = 'Uživatelské jméno: 3–40 znaků, písmena, čísla, tečka, pomlčka.';
        elseif (strlen($pw) < 10) $err = 'Heslo musí mít aspoň 10 znaků.';
        elseif ($pw !== (string)($_POST['password2'] ?? '')) $err = 'Hesla se neshodují.';
        else {
            db()->exec('DELETE FROM users WHERE totp_confirmed = 0');
            db()->prepare('INSERT INTO users (username, pass_hash, totp_secret, totp_confirmed, created) VALUES (?,?,?,0,?)')
                ->execute([$username, password_hash($pw, PASSWORD_DEFAULT), totp_new_secret(), now()]);
            session_regenerate_id(true); $_SESSION['setup_uid'] = (int)db()->lastInsertId(); $_SESSION['csrf'] = bin2hex(random_bytes(24));
            redirect('setup.php');
        }
    } elseif ($a === 'verify' && $step === 2) {
        if (totp_verify($user['totp_secret'], (string)($_POST['code'] ?? ''))) {
            db()->prepare('UPDATE users SET totp_confirmed = 1 WHERE id = ?')->execute([$uid]);
            $codes = gen_recovery_codes($uid);
            login_user($uid); $_SESSION['setup_uid'] = $uid; $_SESSION['codes'] = $codes;
            redirect('setup.php');
        }
        $err = 'Kód nesedí. Zkontroluj čas v telefonu a zkus nový kód.';
    } elseif ($a === 'done' && $step === 3) {
        unset($_SESSION['setup_uid'], $_SESSION['codes']); redirect('index.php');
    }
}

echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="claude:viewport-fit-sides" content="cover"><title>První spuštění · Discgolf</title>'
   . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Inter+Tight:wght@400;500;600&display=swap">'
   . '<link rel="stylesheet" href="' . asset('assets/app.css') . '"></head><body><div class="auth">';
echo '<a class="brand" href="#"><i></i>Discgolf</a>';
if ($err) echo '<div class="flash err" style="margin-top:14px">' . h($err) . '</div>';

if ($step === 1) { ?>
  <h1 style="margin-top:22px">První spuštění</h1>
  <p class="muted">Vytvoř si účet. Pak si v dalším kroku nastavíš přihlašování přes Google Authenticator.</p>
  <form method="post" class="card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="a" value="account">
    <?php if ((string)(config()['setup_key'] ?? '') !== ''): ?>
    <div class="field"><label>Instalační klíč (z config.php)</label><input type="password" name="setup_key" required></div>
    <?php endif; ?>
    <div class="field"><label>Uživatelské jméno</label><input type="text" name="username" required autocapitalize="off" autocomplete="username"></div>
    <div class="field"><label>Heslo (min. 10 znaků)</label><input type="password" name="password" required autocomplete="new-password"></div>
    <div class="field"><label>Heslo znovu</label><input type="password" name="password2" required autocomplete="new-password"></div>
    <button class="btn" style="width:100%">Pokračovat</button>
  </form>
<?php } elseif ($step === 2) { $uri = totp_uri($user['totp_secret'], $user['username']); ?>
  <h1 style="margin-top:22px">Nastav ověřování</h1>
  <p class="muted">1. V telefonu otevři <b>Google Authenticator</b> → „+“ → <b>Naskenovat QR kód</b>.<br>2. Naskenuj kód a opiš 6místné číslo, které se objeví.</p>
  <div class="card">
    <div class="qr" id="qr" data-uri="<?= h($uri) ?>"></div>
    <p class="muted" style="margin:6px 0">Nejde naskenovat? Zadej ručně klíč:</p>
    <div class="secret"><?= h(trim(chunk_split($user['totp_secret'], 4, ' '))) ?></div>
  </div>
  <form method="post" class="card" style="margin-top:12px" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="a" value="verify">
    <div class="field"><label>6místný kód z aplikace</label><input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required style="font-size:24px;letter-spacing:.2em;text-align:center"></div>
    <button class="btn" style="width:100%">Ověřit a dokončit</button>
  </form>
  <script src="<?= h(asset('assets/vendor/qrcode.js')) ?>"></script>
  <script>(function(){var e=document.getElementById('qr');var q=qrcode(0,'M');q.addData(e.dataset.uri);q.make();e.innerHTML=q.createSvgTag({cellSize:5,margin:0,scalable:true});e.querySelector('svg').style.width='220px';e.querySelector('svg').style.height='220px';})();</script>
<?php } else { $codes = $_SESSION['codes'] ?? []; ?>
  <h1 style="margin-top:22px">Hotovo ✓</h1>
  <p class="muted">Google Authenticator je propojený. Teď ještě dvě věci:</p>
  <div class="card">
    <h3>Záložní kódy</h3>
    <p class="muted" style="margin-top:0">Pro případ, že ztratíš telefon. Každý kód jde použít jednou. <b>Ulož si je teď</b> (heslovník, vytisknout) – znovu se už nezobrazí.</p>
    <?php if ($codes): ?><div class="codes"><?php foreach ($codes as $c) echo '<span>' . h($c) . '</span>'; ?></div><?php else: ?><p class="muted">Kódy už byly zobrazeny. Nové vygeneruješ v Nastavení.</p><?php endif; ?>
  </div>
  <div class="card" style="margin-top:12px">
    <h3>Otisk prstu / Face ID (záloha)</h3>
    <p class="muted" style="margin-top:0">Přihlašování přes telefon bez kódu. Doporučuju přidat hned na telefonu, který budeš používat.</p>
    <button class="btn ghost" id="addkey" type="button" data-csrf="<?= h(csrf()) ?>">Přidat otisk prstu</button>
    <p class="muted" id="keymsg" style="margin:8px 0 0"></p>
  </div>
  <form method="post" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="a" value="done"><button class="btn" style="width:100%">Vstoupit do aplikace</button></form>
  <script src="<?= h(asset('assets/passkey.js')) ?>"></script>
<?php }
echo '</div></body></html>';
