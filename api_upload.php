<?php
require __DIR__ . '/api_common.php';
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['error' => 'POST only.']);

$user = api_user($pdo);   // who is uploading comes from the login token, not from the request

try {
    $r = save_scan($pdo, $user['id'], $_POST['folder_name'] ?? '', normalize_files('pages'));
    json_out(200, ['ok' => true, 'folder_id' => $r['folder_id'], 'pages' => $r['pages']]);
} catch (Throwable $e) {
    json_out(400, ['error' => $e->getMessage()]);
}