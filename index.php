<?php
require __DIR__ . '/auth.php';
require_login();

$q = trim($_GET['q'] ?? '');

$sql = "SELECT f.id, f.name, f.created_at, u.full_name,
               (SELECT COUNT(*) FROM pages p WHERE p.folder_id = f.id) AS page_count
        FROM folders f
        JOIN users u ON u.id = f.created_by";
$params = [];
if ($q !== '') {
    $sql .= " WHERE f.name LIKE ?";
    $params[] = '%' . $q . '%';
}
$sql .= " ORDER BY f.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$folders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AppScan</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="topbar">
    <strong>AppScan</strong>
    <span><a href="upload_web.php">+ Upload</a> · <?= e($_SESSION['full_name']) ?> · <a href="logout.php">Log out</a></span>
  </div>

  <div class="card">
    <form method="get" class="searchbar">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search folder name...">
      <button type="submit">Search</button>
    </form>

    <?php if (!$folders): ?>
      <p class="muted">No folders found.</p>
    <?php else: ?>
      <table>
        <tr><th>Folder</th><th>Pages</th><th>Scanned by</th><th>Date</th></tr>
        <?php foreach ($folders as $f): ?>
          <tr>
            <td><a href="folder.php?id=<?= (int)$f['id'] ?>"><?= e($f['name']) ?></a></td>
            <td><?= (int)$f['page_count'] ?></td>
            <td><?= e($f['full_name']) ?></td>
            <td><?= e(date('M d, Y h:i A', strtotime($f['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</body>
</html> 