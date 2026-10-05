<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/lib.php';
require_login();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $r = save_scan($pdo, (int)$_SESSION['user_id'], $_POST['folder_name'] ?? '', normalize_files('pages'));
        header('Location: folder.php?id=' . $r['folder_id']);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upload - AppScan</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="topbar">
    <strong><a href="index.php">← AppScan</a></strong>
    <span><?= e($_SESSION['full_name']) ?></span>
  </div>
  <div class="card narrow">
    <h2>Upload pages</h2>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="text" name="folder_name" placeholder="Folder name" required>
      <input type="file" name="pages[]" accept="image/*" multiple required>
      <button type="submit">Save folder</button>
    </form>
  </div>
</body>
</html>