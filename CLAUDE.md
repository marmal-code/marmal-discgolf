# Pokyny pro Claude – aplikace Discgolf

Osobní mobilní PWA aplikace Martina pro disc golf na **discgolf.marmal.cz** (PHP 8.x + MariaDB, nasazení FTP nebo tlačítkem „Aktualizovat aplikaci“ v Nastavení). Popis a rozhodnutí: [docs/PROJEKT.md](docs/PROJEKT.md) – přečti před prací.

## Postup při každé změně
1. Začni z aktuální verze na GitHubu (`marmal-code/marmal-discgolf`, větev `main`) – zdroj pravdy.
2. Změň, otestuj lokálně (viz níže).
3. Commit česky s verzí (`v2: …`), push, git tag `vN`.
4. Změny databáze **vždy jako novou migraci** `migrations/NNN_nazev.sql` nebo `.php` (idempotentní: `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`…). Aplikace je spustí sama po aktualizaci. Staré migrace nikdy neupravuj.
5. Uprav `docs/PROJEKT.md` (historie verzí, tabulky, soubory).
6. Martin aplikaci aktualizuje tlačítkem v Nastavení (stáhne z GitHubu + spustí migrace). Jen pokud to nejde, pošli ZIP se změněnými soubory.

## Pravidla
- `config.php` (hesla DB, GitHub token, setup_key) a `uploads/` do gitu **nikdy**.
- `declare(strict_types=1)`; časy z PHP (`now()`, Europe/Prague), ne `NOW()` z DB.
- Veřejné jsou jen `prodej.php` (adresa `prodej.html`) a `login.php`/`setup.php`; vše ostatní `require_login()`. Každý POST kontroluje `csrf_check()`, AJAX hlavičku `X-CSRF`.
- Vzhled: tokeny a komponenty v `assets/app.css` (světlý + tmavý režim), mobil 390 px první, spodní navigace. Statistiky jsou v `assets/stats.js` (data z `inc/stats.php`).
- Komunikace s Martinem česky.

## Testování lokálně
- `php -S localhost:8099`, MariaDB, `config.php` na testovací DB; migrace se spustí samy při prvním načtení.
- Passkey (WebAuthn) jde testovat jen na `localhost`/HTTPS, ne na IP. V Playwrightu přes virtuální authenticator.
- Kontroluj screenshoty mobil 390 px i desktop.
