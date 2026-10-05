<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/lib.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT f.*, u.full_name FROM folders f
     JOIN users u ON u.id = f.created_by WHERE f.id = ?"
);
$stmt->execute([$id]);
$folder = $stmt->fetch();
if (!$folder) { http_response_code(404); exit('Folder not found.'); }

$stmt = $pdo->prepare("SELECT id, file_path, page_no FROM pages WHERE folder_id = ? ORDER BY page_no");
$stmt->execute([$id]);
$pages = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    foreach ($pages as $p) @unlink(UPLOAD_DIR . '/' . $p['file_path']);
    @rmdir(UPLOAD_DIR . '/' . $id);
    $pdo->prepare("DELETE FROM folders WHERE id = ?")->execute([$id]);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($folder['name']) ?> - AppScan</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="topbar">
    <strong><a href="index.php">← AppScan</a></strong>
    <span><?= e($_SESSION['full_name']) ?> · <a href="logout.php">Log out</a></span>
  </div>

  <div class="card">
    <h2><?= e($folder['name']) ?></h2>
    <p class="muted">
      <?= count($pages) ?> page(s) · scanned by <?= e($folder['full_name']) ?>
      · <?= e(date('M d, Y h:i A', strtotime($folder['created_at']))) ?>
    </p>

    <div class="actions">
    <button type="button" id="dlAll">Download all pages</button>
    <form method="post" onsubmit="return confirm('Delete this folder and all its pages?');">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="danger">Delete folder</button>
    </form>
    </div>

    <div class="grid">
    <?php foreach ($pages as $p): ?>
        <div class="thumb">
        <a href="image.php?id=<?= (int)$p['id'] ?>" target="_blank">
            <img src="image.php?id=<?= (int)$p['id'] ?>" alt="Page <?= (int)$p['page_no'] ?>" loading="lazy">
        </a>
        <span>Page <?= (int)$p['page_no'] ?></span>
        <a class="btn small dl" href="download.php?id=<?= (int)$p['id'] ?>">Download</a>
        </div>
    <?php endforeach; ?>
    </div>

    <script>
    document.getElementById('dlAll').addEventListener('click', function () {
    const links = document.querySelectorAll('a.dl');
    links.forEach(function (a, i) {
        setTimeout(function () {
        const f = document.createElement('iframe');
        f.style.display = 'none';
        f.src = a.href;
        document.body.appendChild(f);
        }, i * 700);
    });
    });
    </script>
  </div>
</body>
</html>