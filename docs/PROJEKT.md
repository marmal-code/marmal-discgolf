# Discgolf – projektová dokumentace

## Základ
| | |
|---|---|
| Adresa | https://discgolf.marmal.cz (veřejný prodej: `/prodej.html`, interně `prodej.php` přes rewrite v `.htaccess`) |
| Stack | PHP 8.x, MariaDB (PDO), bez frameworku, mobilní PWA, nasazení FTP / tlačítko Aktualizovat |
| Přihlášení | Uživatel + heslo + Google Authenticator (TOTP, QR při prvním spuštění) · záloha otisk prstu / Face ID (WebAuthn passkey) · 8 záložních kódů |
| Repozitář | `marmal-code/marmal-discgolf` (soukromý) |
| Vzhled | Tokeny z artefaktu statistik (světlý/tmavý), Bricolage Grotesque + Inter Tight, spodní navigace na mobilu |

## Struktura
- `index.php` přehled · `bag.php` + `disc.php` bag a editace disků · `poptavky.php` poptávky z veřejného prodeje · `statistiky.php` statistiky turnajů (roky přepínač) · `nastaveni.php` zabezpečení, texty prodeje, profil, aktualizace
- `prodej.php` (adresa `prodej.html`) **veřejná** stránka disků „Na prodej“: výběr více disků + jeden formulář (jméno, kontakt, zpráva); uloží se do `inquiries` + `inquiry_items`, volitelně e‑mail a push; honeypot, limit 5/h/IP
- `inc/push.php`, `push.php`, `sw.js`, `assets/push.js` – Web Push (VAPID, klíče v `settings`: `vapid_private`/`vapid_public`; push bez payloadu, SW zobrazí „Nová poptávka disků“); zařízení v tabulce `push_subs`, zapnutí v Nastavení
- `turnaje.php` (seznam: plán + odehrané), `turnaj.php` (údaje, plán/cíl/strategie, úkoly, bag na turnaj, kola), `kolo.php` (ruční zápis kola: hody, par → skóre, rating, volitelné statistiky, počasí), `weather.php` + `inc/weather.php` + `assets/weather.js` (Open-Meteo: geokódování města, předpověď do 16 dní / archiv; jen PŘEDVYPLNÍ, vše lze upravit; `w_src` auto/manual), `inc/events.php`
- `import.php` + `inc/pdga.php` – import z PDGA Live (veřejné API `pdga.com/apps/tournament/live-api/`: `live_results_fetch_event.php`, `live_results_fetch_round.php`); z odkazu vyčte ID turnaje, hráče najde podle PDGA čísla (příp. jména, ignoruje diakritiku), preferuje divizi z Nastavení; ukládá kola (hody, par, skóre, rating kola, datum, výsledky po jamkách), umístění, rating, hřiště; opakovaný import turnaje (`events.pdga_id`) jen aktualizuje. Statistiky kola se berou z `https://www.pdga.com/api/v1/feat/stats/round-stats/{live_round_id}` (`live_round_id` je v odpovědi `live_results_fetch_round.php`; hráč se páruje přes `score.resultId` = `ResultID`). statId: 1 Fairway, 2 C1R, 3 C2R, 4 Parked, 5 Scramble, 6 C1 putting, 7 C1X, 8 C2 putting, 16 celk. putt, 17 nejdelší throw-in, 18 průměrný putt, 21 OB; strokes gained: `/feat/stats/strokes-gained/{id}` (zatím se nestahuje). Vzdálenosti API dává ve stopách, ukládají se v jednotce turnaje. Lokální test: env `DG_HTTP_MOCK_DIR` = složka s JSON odpověďmi (viz `http_json()`).
- `trenink.php` (týdenní přehled, odškrtávání Splněno / Nesplněno s důvodem nemoc·zranění·jiné + poznámka, vlastní trénink, grafy posledních 8 týdnů), `trenink-plan.php` (aktivní plán po fázích, úprava, import plánu z textu s náhledem, archiv), `inc/training.php`; na Přehledu karta „Dnešní trénink“ a „Odkazy“ (spravují se v Nastavení → Odkazy)
- Formát importu plánu: `# Název plánu`, `## Fáze | od | do` (+ řádek popisu), `- dny | název | minuty | popis` (dny: denně, po-pá, út,pá). Dny bez záznamu v minulosti se ukazují jako „Bez záznamu“.
- `inc/health.php`, `priprava.php` – propojení s Moje Zdraví: `api-export.php` (jen čtení, token = `api_token` z Moje Zdraví) vrací dokončené tréninky v posilovně a putting cvičení; stahuje se do `health_sessions` (jednou za hodinu při otevření Přehledu / Tréninku / Přípravy, nebo tlačítkem v Nastavení → Moje Zdraví; `settings`: `health_url`, `health_token`, `health_last`, `health_err`). Cvičení z Moje Zdraví se samo odškrtne v plánu (posilovna → položka typu gym, putting cvičení → putting), jen pokud ten den ještě není ruční záznam; `training_log.src='zdravi'`, `health_sessions.marked` brání opětovnému odškrtnutí po ručním vrácení. `priprava.php`: turnaje s přípravou 4 týdny předem (počet tréninků, plnění plánu) vedle výsledků (rating kola, C1X, C2), měsíční přehled, pokrok cvičení; karta „Příprava“ i na `turnaj.php`.
- `assets/sale.js` – výběr disků a filtr na veřejné stránce
- `setup.php` první spuštění (účet → QR → záložní kódy → passkey) · `login.php`, `logout.php`, `passkey.php`
- `update.php` + `inc/updater.php` aktualizace z GitHubu (zipball přes API s read-only tokenem), pak `migrate()`
- `migrations/` číslované migrace (SQL nebo PHP), tabulka `migrations` eviduje provedené
- `seed/` jednorázová data (disky z původního bag.marmal.cz, statistiky 2026) · `lib/WebAuthn` (lbuchs/WebAuthn, MIT)

## Databáze
`settings, users, passkeys, login_attempts, discs, inquiries, inquiry_items, push_subs, events, rounds, event_discs, event_todo, rating_history, training_plans, training_phases, training_items, training_log, links, health_sessions, migrations`

## Aktualizace tlačítkem
`config.php` → `github.token` (fine‑grained token, jen Contents: Read‑only pro tento repozitář). Nastavení → „Aktualizovat aplikaci“: stáhne větev `main`, přepíše soubory (kromě `config.php`, `uploads/`), spustí nové migrace.

## Historie verzí
| Verze | Obsah |
|---|---|
| v1 | Základ: přihlášení (heslo + TOTP + passkey), bag (135 disků z původní aplikace, filtry, rychlé přepínače bag/prodej), veřejný prodej s poptávkami, statistiky 2026 (11 turnajů, historie ratingu 2019–2026, přepínač let, slovník), aktualizace z GitHubu |
| v2 | Bag: stavy jako zaškrtávátka vedle sebe (V bagu, Trénink [dřív „Doma“], Testuji, Ztraceno, Na prodej, Prodáno) = barevné štítky a filtry (OR), rámeček karty v barvě štítku, řazení skupiny → speed ↑ → turn ↑ → fade ↑, počty bez ztracených a prodaných, oprava nefunkčních filtrů (`[hidden]`). Veřejný prodej na `.html` s výběrem více disků a formulářem. Push notifikace o nové poptávce. Migrace 004 (stavy), 005 (`inquiry_items`, `push_subs`). Odstraněno `action.php` (rychlé přepínače). |
| v3 | Turnaje: plán turnaje (cíl, strategie, úkoly), bag na turnaj (předvyplní se z aktuálního bagu, výběr z mých disků), ruční zápis kol bez screenshotů (hody + par → skóre, rating, volitelné PDGA Live statistiky), počasí z Open-Meteo s předvyplněním a ruční úpravou, přepočet celkového skóre/paru, plánované turnaje se nepočítají do statistik, 5. položka navigace. Veřejný prodej: výrazný součet ceny vybraných disků. Migrace 006 (`events`: status, end_date, city, lat, lon, goal, strategy; `rounds`: rdate, par, w_*, note; tabulky `event_discs`, `event_todo`). |
| v4 | Import výsledků z PDGA Live z odkazu (dohledání podle PDGA čísla/jména), aktualizace existujícího turnaje, divize hráče v Nastavení. Migrace 007 (`events.pdga_id`, `rounds.hole_scores/hole_pars`). Neověřeno proti živému API (z vývojového prostředí nedostupné) – ověřeno na vzorových datech podle struktury API. |
| v4.1 | Oprava importu z PDGA Live: seznam hráčů se bere z `data.scores` (dřív se omylem vzal seznam hřišť `layouts`, který má také klíč `Name`); PDGA číslo se čistí od nečíslic. Ověřeno na reálné struktuře odpovědi (turnaj 105098, MČR Masters 2026). |
| v5 | Import z PDGA Live stahuje i statistiky kola (fairway, C1R, C2R, scramble, C1X, C2 putting, OB, průměrný a nejdelší putt); neúspěch stahování statistik import nezastaví; ručně zadané hodnoty se nepřepisují prázdnými. |
| v5.1 | Oprava barev ve statistikách turnaje: porovnání už není jen s ostatními koly stejné sezóny (u sezóny s jediným turnajem se obě kola porovnávala mezi sebou), ale s průměrem tvých ostatních PDGA kol z 12 měsíců před turnajem (min. 8 kol), jinak ze všech ostatních turnajů; málo dat = bez barev; statistiky z <3 pokusů se nebarví. |
| v6 | Trénink: plán po fázích (předvyplněn „Plán do března 2027“), odškrtávání Splněno / Nesplněno s důvodem, vlastní trénink, import celého plánu z textu, karta „Dnešní trénink“ na Přehledu. Odkazy na weby (Tagovačka, Kladno, PDGA profil, IDG) na Přehledu + správa v Nastavení. 6. položka navigace. Migrace 008 (tabulky `training_*`, `links`), 009 (ukázkový plán). |
| v7 | Propojení s Moje Zdraví: stahování posilovny a putting cvičení (jen čtení), automatické odškrtnutí plánu, stránka Příprava a výsledky, karta Příprava u turnaje, nastavení propojení. Migrace 010 (`health_sessions`, `training_log.src`). Vyžaduje v15 Moje Zdraví (`api-export.php`). |

## Plán
- v2: ruční zápis turnaje a kol formulářem, počasí a podmínky (automaticky + úprava), bag na turnaj, plán na turnaj
- import ČADG / ne-PDGA turnajů (potřeba vzorový odkaz), strokes gained z PDGA
