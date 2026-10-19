# Discgolf – projektová dokumentace

## Základ
| | |
|---|---|
| Adresa | https://discgolf.marmal.cz (veřejný prodej: `/prodej.php`) |
| Stack | PHP 8.x, MariaDB (PDO), bez frameworku, mobilní PWA, nasazení FTP / tlačítko Aktualizovat |
| Přihlášení | Uživatel + heslo + Google Authenticator (TOTP, QR při prvním spuštění) · záloha otisk prstu / Face ID (WebAuthn passkey) · 8 záložních kódů |
| Repozitář | `marmal-code/marmal-discgolf` (soukromý) |
| Vzhled | Tokeny z artefaktu statistik (světlý/tmavý), Bricolage Grotesque + Inter Tight, spodní navigace na mobilu |

## Struktura
- `index.php` přehled · `bag.php` + `disc.php` bag a editace disků · `poptavky.php` poptávky z veřejného prodeje · `statistiky.php` statistiky turnajů (roky přepínač) · `nastaveni.php` zabezpečení, texty prodeje, profil, aktualizace
- `prodej.php` **veřejná** stránka disků s příznakem „Na prodej“, poptávkový formulář (uloží se do DB, volitelně e‑mail)
- `setup.php` první spuštění (účet → QR → záložní kódy → passkey) · `login.php`, `logout.php`, `passkey.php`
- `update.php` + `inc/updater.php` aktualizace z GitHubu (zipball přes API s read-only tokenem), pak `migrate()`
- `migrations/` číslované migrace (SQL nebo PHP), tabulka `migrations` eviduje provedené
- `seed/` jednorázová data (disky z původního bag.marmal.cz, statistiky 2026) · `lib/WebAuthn` (lbuchs/WebAuthn, MIT)

## Databáze
`settings, users, passkeys, login_attempts, discs, inquiries, events, rounds, rating_history, migrations`

## Aktualizace tlačítkem
`config.php` → `github.token` (fine‑grained token, jen Contents: Read‑only pro tento repozitář). Nastavení → „Aktualizovat aplikaci“: stáhne větev `main`, přepíše soubory (kromě `config.php`, `uploads/`), spustí nové migrace.

## Historie verzí
| Verze | Obsah |
|---|---|
| v1 | Základ: přihlášení (heslo + TOTP + passkey), bag (135 disků z původní aplikace, filtry, rychlé přepínače bag/prodej), veřejný prodej s poptávkami, statistiky 2026 (11 turnajů, historie ratingu 2019–2026, přepínač let, slovník), aktualizace z GitHubu |

## Plán
- v2: ruční zápis turnaje a kol formulářem, počasí a podmínky (automaticky + úprava), bag na turnaj, plán na turnaj
- v3: import z PDGA Live / ČADG
- v4: cvičení z Moje Zdraví, propojení tréninku s výsledky
