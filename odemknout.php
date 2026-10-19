<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
migrate_if_needed();
require_login();
$next = (string)($_GET['next'] ?? 'index.php');
if (!preg_match('~^[a-z0-9_-]+\.php(\?[A-Za-z0-9_=&%.-]*)?$~', $next)) $next = 'index.php';
echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="claude:viewport-fit-sides" content="cover"><title>Odemknout · Discgolf</title>'
   . '<link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/icon.svg" type="image/svg+xml">'
   . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Inter+Tight:wght@400;500;600&display=swap">'
   . '<link rel="stylesheet" href="' . asset('assets/app.css') . '"><script>try{if(localStorage.getItem("dgtheme")==="dark")document.documentElement.setAttribute("data-theme","dark")}catch(e){}</script></head><body><div class="auth">';
?>
<div class="lockscr">
  <a class="brand" href="#" style="justify-content:center"><i></i>Discgolf</a>
  <div class="fp"><svg viewBox="0 0 24 24"><path d="M12 11v3c0 2-.5 4-1.5 6M8 14c0-2.2 1.8-4 4-4s4 1.8 4 4c0 2.5-.4 4.5-1.2 6.5M5 12a7 7 0 0114 0c0 3-.3 5-1 7M3.5 9A9.5 9.5 0 0120.5 9"/></svg></div>
  <h1>Odemkni otiskem</h1>
  <p class="muted" id="keymsg" style="margin:0">Přilož prst nebo použij Face ID.</p>
  <button class="btn" id="pkunlock" type="button" data-csrf="<?= h(csrf()) ?>" data-next="<?= h($next) ?>" style="width:100%;max-width:320px">Odemknout</button>
  <form method="post" action="logout.php" style="margin:0"><?= csrf_field() ?><button class="btn ghost sm" type="submit">Přihlásit jinak (heslo)</button></form>
</div>
<script src="<?= h(asset('assets/passkey.js')) ?>"></script>
</div></body></html>
