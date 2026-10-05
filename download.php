<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/lib.php';
require_login();

// download.php?id=PAGE_ID
$stmt = $pdo->prepare(
    "SELECT p.file_path, f.name
     FROM pages p JOIN folders f ON f.id = p.folder_id
     WHERE p.id = ?"
);
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$row = $stmt->fetch();

$path = $row ? realpath(UPLOAD_DIR . '/' . $row['file_path']) : false;
$base = realpath(UPLOAD_DIR);
if (!$path || strpos($path, $base) !== 0) {
    http_response_code(404);
    exit('File not found.');
}

$safe = preg_replace('/[^A-Za-z0-9._ -]/', '_', $row['name']);
$ext  = pathinfo($path, PATHINFO_EXTENSION);
$num  = pathinfo($path, PATHINFO_FILENAME);

header('Content-Type: ' . mime_content_type($path));
header('Content-Disposition: attachment; filename="' . $safe . '_' . $num . '.' . $ext . '"');
header('Content-Length: ' . filesize($path));
readfile($path);