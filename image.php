<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/lib.php';
require_login();

$stmt = $pdo->prepare("SELECT file_path FROM pages WHERE id = ?");
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$row = $stmt->fetch();

$path = $row ? realpath(UPLOAD_DIR . '/' . $row['file_path']) : false;
$base = realpath(UPLOAD_DIR);
if (!$path || strpos($path, $base) !== 0) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . mime_content_type($path));
header('Cache-Control: private, max-age=3600');
readfile($path);