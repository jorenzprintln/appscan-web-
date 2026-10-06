<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/lib.php';
require_login();

$stmt = $pdo->prepare("SELECT file_path FROM pages WHERE id = ?");
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$row = $stmt->fetch();

$base = realpath(UPLOAD_DIR);
$path = $row ? realpath(UPLOAD_DIR . '/' . $row['file_path']) : false;
if (!$path || strpos($path, $base) !== 0) {
    http_response_code(404);
    exit;
}

// image.php?id=X&thumb=1 -> small version, created on first request if missing
if (isset($_GET['thumb'])) {
    $thumbPath = UPLOAD_DIR . '/' . thumb_rel($row['file_path']);
    if (!is_file($thumbPath)) make_thumb($path, $thumbPath);
    if (is_file($thumbPath)) $path = realpath($thumbPath);   // falls back to the original if it failed
}

header('Content-Type: ' . mime_content_type($path));
header('Cache-Control: private, max-age=86400');
readfile($path);