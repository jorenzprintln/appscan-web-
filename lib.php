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

// "1/001.jpg" -> "1/thumbs/001.jpg"
function thumb_rel(string $filePath): string {
    return dirname($filePath) . '/thumbs/' . pathinfo($filePath, PATHINFO_FILENAME) . '.jpg';
}

function make_thumb(string $src, string $dest, int $max = 300): bool {
    $info = @getimagesize($src);
    if (!$info) return false;

    switch ($info[2]) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src);  break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($src); break;
        default: return false;
    }
    if (!$img) return false;

    // Phone photos are often stored sideways with an EXIF rotation flag
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif  = @exif_read_data($src);
        $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($angle) {
            $rot = imagerotate($img, $angle, 0);
            if ($rot) $img = $rot;
        }
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    $thumb = imagecreatetruecolor($nw, $nh);
    imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255)); // white behind PNG transparency
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $dir = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) return false;

    return imagejpeg($thumb, $dest, 75);
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
            $thumbDest = UPLOAD_DIR . '/' . thumb_rel($folderId . '/' . $name);
            if (make_thumb($dest, $thumbDest)) $moved[] = $thumbDest;
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

function clean_folder_name(string $name): string {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '' || mb_strlen($name) > 150) {
        throw new Exception('Folder name is required (max 150 characters).');
    }
    return $name;
}

function rename_folder(PDO $pdo, int $folderId, string $name): void {
    $pdo->prepare("UPDATE folders SET name = ? WHERE id = ?")
        ->execute([clean_folder_name($name), $folderId]);
}

function renumber_pages(PDO $pdo, int $folderId): void {
    $stmt = $pdo->prepare("SELECT id FROM pages WHERE folder_id = ? ORDER BY page_no, id");
    $stmt->execute([$folderId]);
    $upd = $pdo->prepare("UPDATE pages SET page_no = ? WHERE id = ?");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $i => $pid) {
        $upd->execute([$i + 1, (int)$pid]);
    }
}

function delete_page(PDO $pdo, int $folderId, int $pageId): void {
    $stmt = $pdo->prepare("SELECT id, file_path FROM pages WHERE id = ? AND folder_id = ?");
    $stmt->execute([$pageId, $folderId]);
    $p = $stmt->fetch();
    if (!$p) throw new Exception('Page not found.');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE folder_id = ?");
    $stmt->execute([$folderId]);
    if ((int)$stmt->fetchColumn() <= 1) {
        throw new Exception('A folder needs at least one page. Delete the folder instead.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM pages WHERE id = ?")->execute([$pageId]);
        renumber_pages($pdo, $folderId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    // remove files only after the database change succeeded
    @unlink(UPLOAD_DIR . '/' . $p['file_path']);
    @unlink(UPLOAD_DIR . '/' . thumb_rel($p['file_path']));
}

// $dir: -1 = earlier, +1 = later
function move_page(PDO $pdo, int $folderId, int $pageId, int $dir): void {
    $stmt = $pdo->prepare("SELECT id FROM pages WHERE folder_id = ? ORDER BY page_no, id");
    $stmt->execute([$folderId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $i = array_search($pageId, $ids, true);
    if ($i === false || !isset($ids[$i + $dir])) return;
    [$ids[$i], $ids[$i + $dir]] = [$ids[$i + $dir], $ids[$i]];

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("UPDATE pages SET page_no = ? WHERE id = ?");
        foreach ($ids as $k => $pid) $upd->execute([$k + 1, $pid]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function add_pages(PDO $pdo, int $folderId, array $files): int {
    if (!$files) throw new Exception('No pages received.');

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    // continue after the highest file number and page number already used
    $stmt = $pdo->prepare("SELECT file_path, page_no FROM pages WHERE folder_id = ?");
    $stmt->execute([$folderId]);
    $fileNo = 0; $pageNo = 0;
    foreach ($stmt->fetchAll() as $r) {
        $fileNo = max($fileNo, (int)pathinfo($r['file_path'], PATHINFO_FILENAME));
        $pageNo = max($pageNo, (int)$r['page_no']);
    }

    $dir = UPLOAD_DIR . '/' . $folderId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new Exception('Cannot create folder on disk.');

    $moved = [];
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT INTO pages (folder_id, file_path, page_no) VALUES (?, ?, ?)");
        $added = 0;
        foreach ($files as $f) {
            if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload failed (error ' . $f['error'] . ').');
            if ($f['size'] > MAX_FILE_SIZE)    throw new Exception('A page is larger than 15 MB.');
            $mime = $finfo->file($f['tmp_name']);
            if (!isset($allowed[$mime]))       throw new Exception('Only JPG, PNG or WEBP images are allowed.');

            $fileNo++; $pageNo++; $added++;
            $name = sprintf('%03d.%s', $fileNo, $allowed[$mime]);
            $dest = $dir . '/' . $name;
            if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('Could not save a page.');
            $moved[] = $dest;

            $thumbDest = UPLOAD_DIR . '/' . thumb_rel($folderId . '/' . $name);
            if (make_thumb($dest, $thumbDest)) $moved[] = $thumbDest;

            $ins->execute([$folderId, $folderId . '/' . $name, $pageNo]);
        }
        $pdo->commit();
        return $added;
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ($moved as $m) @unlink($m);
        throw $e;
    }
}

function delete_folder(PDO $pdo, int $folderId): void {
    $stmt = $pdo->prepare("SELECT file_path FROM pages WHERE folder_id = ?");
    $stmt->execute([$folderId]);
    $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM pages WHERE folder_id = ?")->execute([$folderId]);
        $pdo->prepare("DELETE FROM folders WHERE id = ?")->execute([$folderId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    foreach ($paths as $fp) {
        @unlink(UPLOAD_DIR . '/' . $fp);
        @unlink(UPLOAD_DIR . '/' . thumb_rel($fp));
    }
    @rmdir(UPLOAD_DIR . '/' . $folderId . '/thumbs');
    @rmdir(UPLOAD_DIR . '/' . $folderId);
}