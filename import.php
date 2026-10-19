<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/pdga.php';
require_once __DIR__ . '/inc/cadg.php';
migrate_if_needed();
require_login();

$target = (int)($_GET['event'] ?? $_POST['event'] ?? 0); $te = $target ? event_get($target) : null; if ($target && !$te) $target = 0;
$err = null; $m = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = (string)($_POST['a'] ?? '');
    if ($a === 'find') {
        $url = (string)($_POST['url'] ?? ''); $tid = pdga_tourn_id($url);
        $num = (int)preg_replace('/\D+/', '', (string)($_POST['pdga'] ?? '')); $name = trim((string)($_POST['name'] ?? ''));
        try {
            if (cadg_parse_url($url) || preg_match('~ceskydiscgolf\.cz~i', $url)) { $m = cadg_import($url); $_SESSION['pdga_import'] = $m; }
            else {
            if (!$tid) throw new RuntimeException('Z odkazu se mi nepodařilo vyčíst číslo turnaje. Vlož odkaz z PDGA Live (např. pdga.com/live/event/12345) nebo jen číslo turnaje.');
            $m = pdga_map(pdga_lookup($tid, $num, $name, setting('player_division', 'MP40') ?: 'MP40'));
            $_SESSION['pdga_import'] = $m;
            }
        } catch (RuntimeException $x) { $err = $x->getMessage(); }
    } elseif ($a === 'save' && !empty($_SESSION['pdga_import'])) {
        $imp = $_SESSION['pdga_import']; $id = pdga_save($imp, $target ?: null); unset($_SESSION['pdga_import']);
        flash(!empty($imp['cadg_id']) ? 'Výsledky naimportovány z Českého discgolfu. Zkontroluj údaje a doplň počasí.' : 'Výsledky naimportovány z PDGA Live. Zkontroluj údaje a doplň počasí a statistiky.'); redirect("turnaj.php?id=$id");
    }
}
$num = (int)preg_replace('/\D+/', '', (string)setting('player_pdga', '113434')); $pname = (string)setting('player_name', 'Martin Malý');
page_start('Import výsledků', 'turnaje.php');
?>
<div class="ph">Import výsledků <small><a href="<?= $te ? 'turnaj.php?id=' . (int)$te['id'] : 'turnaje.php' ?>">← zpět</a></small></div>
<?php if ($err) echo '<div class="flash err">' . h($err) . '</div>'; ?>
<?php if (!$m): ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="a" value="find"><input type="hidden" name="event" value="<?= $target ?>">
  <p class="muted" style="margin-top:0">Vlož odkaz na výsledky turnaje z <b>PDGA Live</b> nebo z <b>Live scoringu Českého discgolfu</b> (ceskydiscgolf.cz → Live scoring → Výsledky, s vybranou divizí). Najdu tě (PDGA číslo / ČADG profil) a stáhnu kola, skóre, hody po jamkách, umístění a statistiky.<?= $te ? ' Výsledky se doplní do turnaje „' . h($te['name']) . '“.' : '' ?></p>
  <div class="field"><label>Odkaz na turnaj</label><input type="text" name="url" required placeholder="https://www.pdga.com/live/event/90000 nebo https://ceskydiscgolf.cz/idg/live-scoring/vysledky?…" inputmode="url" autocomplete="off"></div>
  <div class="row2"><div class="field"><label>PDGA číslo</label><input type="number" name="pdga" value="<?= $num ?: '' ?>"></div>
    <div class="field"><label>nebo jméno (když číslo není)</label><input type="text" name="name" value="<?= $num ? '' : h($pname) ?>"></div></div>
  <button class="btn">Najít moje výsledky</button>
  <p class="sub" style="margin-bottom:0">Stáhnou se i statistiky kola (fairway, C1/C2, scramble, putting, OB), pokud je pro tvoje kolo někdo zapisoval. Z ČADG se počítají z hodů (rating kola tam není). Co chybí, doplníš ručně u kola.</p>
</form>
<?php else: $e = $m['event']; ?>
<div class="card">
  <h2 style="margin-top:0"><?= h($e['name']) ?></h2>
  <div class="sub"><?= h(fmt_date($e['date'])) ?><?= $e['end_date'] && $e['end_date'] !== $e['date'] ? '–' . h(fmt_date($e['end_date'])) : '' ?> · <?= h((string)$e['venue']) ?><?= $e['city'] ? ', ' . h($e['city']) : '' ?> · <?= h($e['tier']) ?></div>
  <div class="pills" style="margin:10px 0"><span class="pill"><?= h($m['player']) ?></span><span class="pill"><?= h($m['division']) ?></span><?php if ($e['place']): ?><span class="pill g">umístění <?= h($e['place']) ?></span><?php endif; ?><?php if ($e['pdga_rating']): ?><span class="pill">rating <?= (int)$e['pdga_rating'] ?></span><?php endif; if (!empty($e['event_rating'])): ?><span class="pill">rating turnaje <?= (int)$e['event_rating'] ?></span><?php endif; ?></div>
  <div class="scroll"><table><thead><tr><th>Kolo</th><th>Datum</th><th>Hody</th><th>Par</th><th>Skóre</th><th>Rating</th></tr></thead><tbody>
  <?php foreach ($m['rounds'] as $r): ?><tr><td><?= $r['idx'] + 1 ?></td><td><?= h(fmt_date($r['rdate'])) ?></td><td><?= $r['throws'] ?></td><td><?= $r['par'] ?></td><td><?= $r['score'] > 0 ? '+' . $r['score'] : ($r['score'] < 0 ? '−' . abs($r['score']) : 'E') ?></td><td><?= $r['rating'] ?: '–' ?></td></tr>
    <?php if (!empty($r['stats'])): $x = $r['stats']; $bits = []; foreach (['fw' => 'Fairway', 'c1r' => 'C1R', 'c2r' => 'C2R', 'sc' => 'Scramble', 'c1x' => 'C1X', 'c2' => 'C2'] as $k => $l) if (isset($x[$k . '_n'])) $bits[] = $l . ' ' . $x[$k . '_h'] . '/' . $x[$k . '_n']; if (isset($x['ob'])) $bits[] = 'OB ' . $x['ob']; ?>
    <tr><td></td><td colspan="5" class="sub"><?= h(implode(' · ', $bits)) ?></td></tr><?php endif; ?>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php $dup = db()->prepare(!empty($m['cadg_id']) ? 'SELECT id, name FROM events WHERE cadg_id = ?' : 'SELECT id, name FROM events WHERE pdga_id = ?'); $dup->execute([$m['cadg_id'] ?? $m['pdga_id']]); $d = $dup->fetch();
    if ($te || $d) echo '<p class="sub">' . ($te ? 'Aktualizuje se turnaj „' . h($te['name']) . '“.' : 'Tento turnaj už máš v aplikaci („' . h($d['name']) . '“), kola se aktualizují.') . '</p>'; else echo '<p class="sub">Založí se nový odehraný turnaj. Počasí, bag a poznámky doplníš potom.</p>'; ?>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="event" value="<?= $target ?>"><button class="btn" name="a" value="save">Uložit do aplikace</button><a class="btn ghost" href="import.php<?= $target ? '?event=' . $target : '' ?>">Zrušit</a></form>
</div>
<?php endif; ?>
<?php page_end('turnaje.php');
