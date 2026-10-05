<?php
require_once __DIR__ . '/db.php';

const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_FILE_SIZE = 15 * 1024 * 1024; // 15 MB per page

function normalize_files(string $key): array {
    if (empty($_FILES[$key])) return [];
    $f = $_FILES[$key];
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $_) {
        $out[] = [
            'name'     => $f['name'][$i],
            'tmp_name' => $f['tmp_name'][$i],
            'error'    => $f['error'][$i],
            'size'     => $f['size'][$i],
        ];
    }
    return $out;
}

function save_scan(PDO $pdo, int $userId, string $folderName, array $files): array {
    $folderName = trim(preg_replace('/\s+/', ' ', $folderName));
    if ($folderName === '' || mb_strlen($folderName) > 150) {
        throw new Exception('Folder name is required (max 150 characters).');
    }
    if (!$files) throw new Exception('No pages received.');

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $moved = [];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO folders (name, created_by) VALUES (?, ?)")
            ->execute([$folderName, $userId]);
        $folderId = (int)$pdo->lastInsertId();

        $dir = UPLOAD_DIR . '/' . $folderId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new Exception('Cannot create folder on disk.');
        }

        $ins = $pdo->prepare("INSERT INTO pages (folder_id, file_path, page_no) VALUES (?, ?, ?)");
        $n = 0;
        foreach ($files as $f) {
            if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload failed (error ' . $f['error'] . ').');
            if ($f['size'] > MAX_FILE_SIZE)    throw new Exception('A page is larger than 15 MB.');
            $mime = $finfo->file($f['tmp_name']);
            if (!isset($allowed[$mime]))       throw new Exception('Only JPG, PNG or WEBP images are allowed.');

            $n++;
            $name = sprintf('%03d.%s', $n, $allowed[$mime]);
            $dest = $dir . '/' . $name;
            if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('Could not save a page.');
            $moved[] = $dest;
            $ins->execute([$folderId, $folderId . '/' . $name, $n]);
        }
        $pdo->commit();
        return ['folder_id' => $folderId, 'pages' => $n];
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ($moved as $m) @unlink($m);
        throw $e;
    }
}