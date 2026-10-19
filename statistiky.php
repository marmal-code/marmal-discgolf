<?php
declare(strict_types=1);
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/stats.php';
migrate_if_needed();
require_login();
page_start('Statistiky', 'statistiky.php');
?>
<div class="ph">Statistiky <small id="sub"></small></div>
<div class="seg" id="years" role="group" aria-label="Sezóna" style="width:fit-content"></div>
<div class="stabs" id="tabs" role="tablist"></div>
<div id="app"></div>
<script>window.DG_DATA = <?= json_encode(stats_data(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= h(asset('assets/stats.js')) ?>"></script>
<?php page_end('statistiky.php');
