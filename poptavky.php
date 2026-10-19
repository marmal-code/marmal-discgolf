<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
migrate_if_needed();
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['a'] ?? '') === 'done') db()->prepare('UPDATE inquiries SET handled = 1 - handled WHERE id = ?')->execute([$id]);
    if (($_POST['a'] ?? '') === 'del') db()->prepare('DELETE FROM inquiries WHERE id = ?')->execute([$id]);
    redirect('poptavky.php');
}
$rows = db()->query('SELECT * FROM inquiries ORDER BY handled, id DESC')->fetchAll();
$its = [];
foreach (db()->query('SELECT ii.inquiry_id, d.brand, d.model, d.price, d.is_sold FROM inquiry_items ii JOIN discs d ON d.id = ii.disc_id ORDER BY ii.id')->fetchAll() as $it) $its[$it['inquiry_id']][] = $it;
page_start('Poptávky', 'bag.php');
echo '<div class="ph">Poptávky <small><a href="bag.php">← Bag</a></small></div>';
if (!$rows) echo '<div class="card empty"><b>Zatím nic</b>Poptávky z veřejné stránky prodeje se objeví tady.</div>';
foreach ($rows as $r) {
    echo '<div class="card"' . ($r['handled'] ? ' style="opacity:.6"' : '') . '><div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><b>' . h($r['name']) . '</b><span class="sub">' . h(date('j. n. Y H:i', strtotime($r['created']))) . '</span></div>'
       . '<ul class="sellist" style="margin:8px 0">' . implode('', array_map(fn($it) => '<li><span>' . h($it['brand'] . ' ' . $it['model']) . ($it['is_sold'] ? ' <span class="tg t-sold">prodáno</span>' : '') . '</span><b>' . ($it['price'] ? (int)$it['price'] . ' Kč' : 'dohodou') . '</b></li>', $its[$r['id']] ?? [])) . '</ul>'
       . '<p style="margin:8px 0"><b>' . h($r['contact']) . '</b></p>' . ($r['message'] ? '<p style="margin:0 0 8px">' . nl2br(h($r['message'])) . '</p>' : '')
       . '<form method="post" style="display:flex;gap:8px">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '">'
       . '<button class="btn sm ghost" name="a" value="done">' . ($r['handled'] ? 'Vrátit mezi nové' : 'Vyřízeno') . '</button><button class="btn sm danger" name="a" value="del">Smazat</button></form></div>';
}
page_end('bag.php');
