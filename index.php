<?php
require __DIR__ . '/auth.php';
require_login();

const PER_PAGE = 20;

function valid_date(string $d): string {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : '';
}

$q    = trim($_GET['q'] ?? '');
$user = (int)($_GET['user'] ?? 0);
$from = valid_date($_GET['from'] ?? '');
$to   = valid_date($_GET['to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$conds = []; $params = [];
if ($q !== '')    { $conds[] = 'f.name LIKE ?';      $params[] = '%' . $q . '%'; }
if ($user > 0)    { $conds[] = 'f.created_by = ?';   $params[] = $user; }
if ($from !== '') { $conds[] = 'f.created_at >= ?';  $params[] = $from . ' 00:00:00'; }
if ($to !== '')   { $conds[] = 'f.created_at <= ?';  $params[] = $to . ' 23:59:59'; }
$where = $conds ? ' WHERE ' . implode(' AND ', $conds) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM folders f" . $where);
$stmt->execute($params);
$total      = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * PER_PAGE;

$sql = "SELECT f.id, f.name, f.created_at, u.full_name,
               (SELECT COUNT(*) FROM pages p WHERE p.folder_id = f.id) AS page_count
        FROM folders f
        JOIN users u ON u.id = f.created_by"
     . $where
     . " ORDER BY f.created_at DESC, f.id DESC"
     . " LIMIT " . PER_PAGE . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$folders = $stmt->fetchAll();

$users = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();

$filters = ['q' => $q, 'user' => $user, 'from' => $from, 'to' => $to];
$filtered = (bool)array_filter($filters);

function page_url(int $p, array $f): string {
    $f['page'] = $p > 1 ? $p : '';
    return 'index.php?' . http_build_query(array_filter($f, fn($v) => $v !== '' && $v !== 0));
}
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
    <form method="get">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search folder name...">
      <div class="filters">
        <select name="user">
          <option value="0">All users</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $user === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?= e($from) ?>" title="From date">
        <input type="date" name="to" value="<?= e($to) ?>" title="To date">
      </div>
      <button type="submit">Search</button>
      <?php if ($filtered): ?><p><a href="index.php">Clear filters</a></p><?php endif; ?>
    </form>

    <?php if (!$folders): ?>
      <p class="muted">No folders found.</p>
    <?php else: ?>
      <p class="muted"><?= $total ?> folder(s)</p>
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

      <?php if ($totalPages > 1): ?>
        <div class="pager">
          <?php if ($page > 1): ?>
            <a class="btn small" href="<?= e(page_url($page - 1, $filters)) ?>">← Prev</a>
          <?php else: ?>
            <span class="btn small off">← Prev</span>
          <?php endif; ?>

          <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>

          <?php if ($page < $totalPages): ?>
            <a class="btn small" href="<?= e(page_url($page + 1, $filters)) ?>">Next →</a>
          <?php else: ?>
            <span class="btn small off">Next →</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>