<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/updater.php';
start_session();
header('Content-Type: application/json; charset=utf-8');
if (!current_user()) { http_response_code(401); echo '{"error":"Nepřihlášen"}'; exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) { http_response_code(419); echo '{"error":"csrf"}'; exit; }
try {
    if (($_GET['a'] ?? '') === 'check') {
        $r = remote_sha(); $l = app_version();
        echo json_encode(['remote' => substr($r, 0, 7), 'local' => substr($l, 0, 7), 'new' => $l === 'dev' || !str_starts_with($r, $l) ]);
        exit;
    }
    echo json_encode(run_update(), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'log' => ['Chyba: ' . $e->getMessage()]], JSON_UNESCAPED_UNICODE);
}
