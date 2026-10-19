<?php
declare(strict_types=1);
// Jednorázový převod disků ze starého bagu (bag.marmal.cz) včetně fotek.
return function (PDO $pdo): void {
    $rows = json_decode((string)file_get_contents(ROOT . '/seed/discs.json'), true) ?: [];
    @mkdir(ROOT . '/uploads/discs', 0755, true);
    $st = $pdo->prepare('INSERT IGNORE INTO discs
        (id, brand, model, category, speed, glide, turn, fade, weight, plastic, in_bag, note, is_lost, is_sold, for_sale, price, image_path, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($rows as $r) {
        $img = null;
        if (!empty($r['image_path'])) {
            $src = ROOT . '/seed/discs/' . $r['image_path'];
            $dst = ROOT . '/uploads/discs/' . $r['image_path'];
            if (is_file($src) && !is_file($dst)) copy($src, $dst);
            if (is_file($dst)) $img = 'uploads/discs/' . $r['image_path'];
        }
        $st->execute([
            (int)$r['id'], $r['brand'], $r['model'], $r['category'],
            (float)$r['speed'], (float)$r['glide'], (float)$r['turn'], (float)$r['fade'],
            $r['weight'] !== null ? (int)$r['weight'] : null, $r['plastic'],
            (int)$r['in_bag'], $r['note'], (int)$r['is_lost'], (int)$r['is_sold'], (int)$r['for_sale'],
            $r['price'] !== null ? (int)$r['price'] : null, $img, $r['created_at'],
        ]);
    }
};
