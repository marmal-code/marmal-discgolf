<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
start_session();
header('Content-Type: application/json; charset=utf-8');
if (!current_user()) { http_response_code(401); echo '{"error":"auth"}'; exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) { http_response_code(419); echo '{"error":"csrf"}'; exit; }
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$id = (int)($in['id'] ?? 0); $field = (string)($in['field'] ?? '');
if (!in_array($field, ['in_bag', 'for_sale'], true)) { http_response_code(400); echo '{"error":"field"}'; exit; }
$st = db()->prepare('SELECT * FROM discs WHERE id = ?'); $st->execute([$id]); $d = $st->fetch();
if (!$d) { http_response_code(404); echo '{"error":"nf"}'; exit; }
$val = (int)!$d[$field];
if ($field === 'for_sale' && $val === 1 && empty($d['price'])) { echo json_encode(['needPrice' => true]); exit; }
db()->prepare("UPDATE discs SET $field = ? WHERE id = ?")->execute([$val, $id]);
echo json_encode(['ok' => true, 'value' => $val]);
