<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/pdga.php';
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
            if (!$tid) throw new RuntimeException('Z odkazu se mi nepodařilo vyčíst číslo turnaje. Vlož odkaz z PDGA Live (např. pdga.com/live/event/12345) nebo jen číslo turnaje.');
            $m = pdga_map(pdga_lookup($tid, $num, $name, setting('player_division', 'MP40') ?: 'MP40'));
            $_SESSION['pdga_import'] = $m;
        } catch (RuntimeException $x) { $err = $x->getMessage(); }
    } elseif ($a === 'save' && !empty($_SESSION['pdga_import'])) {
        $id = pdga_save($_SESSION['pdga_import'], $target ?: null); unset($_SESSION['pdga_import']);
        flash('Výsledky naimportovány z PDGA Live. Zkontroluj údaje a doplň počasí a statistiky.'); redirect("turnaj.php?id=$id");
    }
}
$num = (int)preg_replace('/\D+/', '', (string)setting('player_pdga', '113434')); $pname = (string)setting('player_name', 'Martin Malý');
page_start('Import z PDGA Live', 'turnaje.php');
?>
<div class="ph">Import z PDGA Live <small><a href="<?= $te ? 'turnaj.php?id=' . (int)$te['id'] : 'turnaje.php' ?>">← zpět</a></small></div>
<?php if ($err) echo '<div class="flash err">' . h($err) . '</div>'; ?>
<?php if (!$m): ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="a" value="find"><input type="hidden" name="event" value="<?= $target ?>">
  <p class="muted" style="margin-top:0">Vlož odkaz na turnaj z PDGA Live (nebo jen jeho číslo). Najdu tě podle PDGA čísla a stáhnu kola, skóre, rating kola a umístění.<?= $te ? ' Výsledky se doplní do turnaje „' . h($te['name']) . '“.' : '' ?></p>
  <div class="field"><label>Odkaz na turnaj</label><input type="text" name="url" required placeholder="https://www.pdga.com/live/event/90000" inputmode="url" autocomplete="off"></div>
  <div class="row2"><div class="field"><label>PDGA číslo</label><input type="number" name="pdga" value="<?= $num ?: '' ?>"></div>
    <div class="field"><label>nebo jméno (když číslo není)</label><input type="text" name="name" value="<?= $num ? '' : h($pname) ?>"></div></div>
  <button class="btn">Najít moje výsledky</button>
  <p class="sub" style="margin-bottom:0">Fairway, C1/C2 putting a podobné detailní statistiky PDGA Live přes toto rozhraní neposkytuje, ty můžeš doplnit ručně u kola.</p>
</form>
<?php else: $e = $m['event']; ?>
<div class="card">
  <h2 style="margin-top:0"><?= h($e['name']) ?></h2>
  <div class="sub"><?= h(fmt_date($e['date'])) ?><?= $e['end_date'] && $e['end_date'] !== $e['date'] ? '–' . h(fmt_date($e['end_date'])) : '' ?> · <?= h((string)$e['venue']) ?><?= $e['city'] ? ', ' . h($e['city']) : '' ?> · <?= h($e['tier']) ?></div>
  <div class="pills" style="margin:10px 0"><span class="pill"><?= h($m['player']) ?></span><span class="pill"><?= h($m['division']) ?></span><?php if ($e['place']): ?><span class="pill g">umístění <?= h($e['place']) ?></span><?php endif; ?><?php if ($e['pdga_rating']): ?><span class="pill">rating <?= (int)$e['pdga_rating'] ?></span><?php endif; ?></div>
  <div class="scroll"><table><thead><tr><th>Kolo</th><th>Datum</th><th>Hody</th><th>Par</th><th>Skóre</th><th>Rating</th></tr></thead><tbody>
  <?php foreach ($m['rounds'] as $r): ?><tr><td><?= $r['idx'] + 1 ?></td><td><?= h(fmt_date($r['rdate'])) ?></td><td><?= $r['throws'] ?></td><td><?= $r['par'] ?></td><td><?= $r['score'] > 0 ? '+' . $r['score'] : ($r['score'] < 0 ? '−' . abs($r['score']) : 'E') ?></td><td><?= $r['rating'] ?: '–' ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php $dup = db()->prepare('SELECT id, name FROM events WHERE pdga_id = ?'); $dup->execute([$m['pdga_id']]); $d = $dup->fetch();
    if ($te || $d) echo '<p class="sub">' . ($te ? 'Aktualizuje se turnaj „' . h($te['name']) . '“.' : 'Tento turnaj už máš v aplikaci („' . h($d['name']) . '“), kola se aktualizují.') . '</p>'; else echo '<p class="sub">Založí se nový odehraný turnaj. Počasí, bag a poznámky doplníš potom.</p>'; ?>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="event" value="<?= $target ?>"><button class="btn" name="a" value="save">Uložit do aplikace</button><a class="btn ghost" href="import.php<?= $target ? '?event=' . $target : '' ?>">Zrušit</a></form>
</div>
<?php endif; ?>
<?php page_end('turnaje.php');
