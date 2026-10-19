<?php
declare(strict_types=1);

const CATEGORIES = ['putter', 'approach', 'midrange', 'fairway', 'distance'];

/** Štítky (příznaky disku) v pořadí řazení: klíč => [sloupec v DB, název]. */
const TAGS = [
    'bag'   => ['in_bag',   'V bagu'],
    'train' => ['training', 'Trénink'],
    'test'  => ['testing',  'Testuji'],
    'lost'  => ['is_lost',  'Ztraceno'],
    'sale'  => ['for_sale', 'Na prodej'],
    'sold'  => ['is_sold',  'Prodáno'],
];

/** Klíče štítků, které disk má (v pořadí TAGS). */
function disc_tags(array $d): array {
    $out = [];
    foreach (TAGS as $k => [$col]) if (!empty($d[$col])) $out[] = $k;
    return $out;
}
function disc_primary_tag(array $d): ?string { return disc_tags($d)[0] ?? null; }
/** Aktivní disk = není ztracený ani prodaný (do celkového počtu se počítají jen takové). */
function disc_active(array $d): bool { return !$d['is_lost'] && !$d['is_sold']; }

/** SQL ORDER BY: skupiny podle štítků, pak speed ↑, turn ↑, fade ↑. */
const DISC_ORDER = 'CASE WHEN in_bag=1 THEN 1 WHEN training=1 THEN 2 WHEN testing=1 THEN 3 WHEN is_lost=1 THEN 4 WHEN for_sale=1 THEN 5 WHEN is_sold=1 THEN 6 ELSE 7 END, speed, turn, fade, brand, model, id';

/** Zmenší nahranou fotku (max 900 px, JPEG) do uploads/discs/. Vrací relativní cestu nebo null. */
function save_disc_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return null;
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        default => @imagecreatefromgif($file['tmp_name']),
    };
    if (!$src) return null;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($file['tmp_name']);
        $o = $ex['Orientation'] ?? 1;
        if ($o === 3) $src = imagerotate($src, 180, 0); elseif ($o === 6) $src = imagerotate($src, -90, 0); elseif ($o === 8) $src = imagerotate($src, 90, 0);
    }
    [$w, $h] = [imagesx($src), imagesy($src)];
    $max = 900; $r = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $dir = ROOT . '/uploads/discs'; if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
    imagejpeg($dst, $dir . '/' . $name, 82);
    return 'uploads/discs/' . $name;
}

function disc_form_values(array $in): array {
    $f = fn($k) => (float)str_replace(',', '.', (string)($in[$k] ?? '0'));
    $cat = in_array($in['category'] ?? '', CATEGORIES, true) ? $in['category'] : 'putter';
    $v = [
        'brand' => mb_substr(trim((string)($in['brand'] ?? '')), 0, 50), 'model' => mb_substr(trim((string)($in['model'] ?? '')), 0, 50),
        'category' => $cat, 'speed' => $f('speed'), 'glide' => $f('glide'), 'turn' => $f('turn'), 'fade' => $f('fade'),
        'weight' => ($in['weight'] ?? '') !== '' ? (int)$in['weight'] : null, 'plastic' => mb_substr(trim((string)($in['plastic'] ?? '')), 0, 50) ?: null,
        'in_bag' => !empty($in['in_bag']) ? 1 : 0, 'training' => !empty($in['training']) ? 1 : 0, 'testing' => !empty($in['testing']) ? 1 : 0,
        'note' => trim((string)($in['note'] ?? '')) ?: null,
        'is_lost' => !empty($in['is_lost']) ? 1 : 0, 'is_sold' => !empty($in['is_sold']) ? 1 : 0, 'for_sale' => !empty($in['for_sale']) ? 1 : 0,
        'price' => ($in['price'] ?? '') !== '' ? (int)$in['price'] : null,
    ];
    // prodaný / ztracený disk už není v bagu ani na prodej
    if ($v['is_sold'] || $v['is_lost']) { $v['in_bag'] = 0; $v['for_sale'] = 0; }
    return $v;
}
