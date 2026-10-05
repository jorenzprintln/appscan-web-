<?php
require __DIR__ . '/api_common.php';
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['error' => 'POST only.']);

$userId = (int)($_POST['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$userId]);
if (!$stmt->fetch()) json_out(400, ['error' => 'Unknown user.']);

try {
    $r = save_scan($pdo, $userId, $_POST['folder_name'] ?? '', normalize_files('pages'));
    json_out(200, ['ok' => true, 'folder_id' => $r['folder_id'], 'pages' => $r['pages']]);
} catch (Throwable $e) {
    json_out(400, ['error' => $e->getMessage()]);
}