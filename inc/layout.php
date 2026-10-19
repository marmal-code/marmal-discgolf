<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

function asset(string $p): string { $f = ROOT . '/' . $p; return $p . '?v=' . (is_file($f) ? filemtime($f) : 0); }

const ICONS = [
    'home'  => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
    'bag'   => '<path d="M6 8h12l1 12H5L6 8z"/><path d="M9 8a3 3 0 016 0"/>',
    'chart' => '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
    'trophy'=> '<path d="M8 4h8v6a4 4 0 01-8 0V4z"/><path d="M8 6H4v2a3 3 0 003 3M16 6h4v2a3 3 0 01-3 3M12 14v4M8 20h8"/>',
    'timer' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 3h6"/>',
    'disc'  => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M5.5 8.5c3-1.5 8-1.5 13 0"/>',
    'cog'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
];
const NAV = [
    'bag.php' => ['Bag', 'bag'], 'turnaje.php' => ['Turnaje', 'trophy'], 'trenink.php' => ['Trénink', 'timer'], 'cviceni.php' => ['Cvičení', 'disc'], 'statistiky.php' => ['Statistiky', 'chart'],
    'nastaveni.php' => ['Nastavení', 'cog'],
];

/** Zámek při spuštění na mobilu: když je v aplikaci registrovaný otisk a zámek je zapnutý, každé spuštění (a návrat po 5 min) vyžaduje odemčení otiskem. */
function lock_gate(): string {
    try { if (setting('lock_on', '1') !== '1' || (int)db()->query('SELECT COUNT(*) FROM passkeys')->fetchColumn() < 1) return ''; } catch (Throwable $e) { return ''; }
    return '<script>(function(){try{if(!window.matchMedia("(pointer:coarse)").matches)return;var n=Date.now(),s=+localStorage.getItem("dgseen")||0;'
         . 'if(sessionStorage.getItem("dgun")==="1"&&n-s<300000){localStorage.setItem("dgseen",n);return}sessionStorage.removeItem("dgun");'
         . 'document.documentElement.style.visibility="hidden";location.replace("odemknout.php?next="+encodeURIComponent(location.pathname.split("/").pop()+location.search))}catch(e){}})();</script>';
}

function page_start(string $title, string $active = ''): void {
    $f = flash();
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
       . '<meta name="theme-color" content="#EEF2F6"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="claude:viewport-fit-sides" content="cover">'
       . '<title>' . h($title) . ' · Discgolf</title>'
       . '<link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/icon.svg" type="image/svg+xml"><link rel="apple-touch-icon" href="assets/icon-192.png">'
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
       . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Inter+Tight:wght@400;500;600&display=swap">'
       . '<link rel="stylesheet" href="' . asset('assets/app.css') . '">' . lock_gate() . '<script>try{if(localStorage.getItem("dgtheme")==="dark")document.documentElement.setAttribute("data-theme","dark")}catch(e){}</script></head><body><div class="wrap">'
       . '<div class="topbar"><a class="brand" href="index.php"><i></i>Discgolf</a><nav class="dnav">';
    foreach (NAV as $href => [$label]) echo '<a href="' . $href . '"' . ($active === $href ? ' class="on"' : '') . '>' . h($label) . '</a>';
    echo '</nav><button type="button" class="themebtn" id="themebtn" aria-label="Přepnout světlý / tmavý režim" title="Světlý / tmavý režim"><svg class="sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/></svg><svg class="moon" viewBox="0 0 24 24"><path d="M20 14A8 8 0 0110 4a8 8 0 1010 10z"/></svg></button></div><div class="page' . ($active === 'index.php' ? ' dash-page' : '') . '">';
    if ($f) echo '<div class="flash' . ($f[1] === 'err' ? ' err' : '') . '">' . h($f[0]) . '</div>';
}
function page_end(string $active = ''): void {
    echo '</div></div><nav class="bnav">';
    foreach (NAV as $href => [$label, $ic]) echo '<a href="' . $href . '"' . ($active === $href ? ' class="on"' : '') . '><svg viewBox="0 0 24 24">' . ICONS[$ic] . '</svg>' . h($label) . '</a>';
    echo '</nav><script src="' . asset('assets/app.js') . '"></script></body></html>';
}
function public_start(string $title): void {
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
       . '<meta name="claude:viewport-fit-sides" content="cover"><title>' . h($title) . '</title>'
       . '<link rel="icon" href="assets/icon.svg" type="image/svg+xml">'
       . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Inter+Tight:wght@400;500;600&display=swap">'
       . '<link rel="stylesheet" href="' . asset('assets/app.css') . '"><script>try{if(localStorage.getItem("dgtheme")==="dark")document.documentElement.setAttribute("data-theme","dark")}catch(e){}</script></head><body style="padding-bottom:96px"><div class="pub">';
}
function public_end(): void { echo '</div><script src="' . asset('assets/app.js') . '"></script></body></html>'; }

function category_name(string $c): string {
    return ['putter' => 'Putter', 'approach' => 'Approach', 'midrange' => 'Midrange', 'fairway' => 'Fairway driver', 'distance' => 'Distance driver'][$c] ?? $c;
}
function num(float $v): string { return rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ','); }
