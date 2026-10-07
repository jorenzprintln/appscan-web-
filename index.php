<?php
require __DIR__ . '/auth.php';
require_login();

const PER_PAGE = 20;

function valid_date(string $d): string {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : '';
}

$tab  = ($_GET['tab'] ?? '') === 'done' ? 'done' : 'active';
$q    = trim($_GET['q'] ?? '');
$user = (int)($_GET['user'] ?? 0);
$from = valid_date($_GET['from'] ?? '');
$to   = valid_date($_GET['to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

// counts for the two tabs (ignores filters)
$c = $pdo->query("SELECT SUM(done_at IS NULL) AS a, SUM(done_at IS NOT NULL) AS d FROM folders")->fetch();
$countActive = (int)$c['a'];
$countDone   = (int)$c['d'];

$conds  = [$tab === 'done' ? 'f.done_at IS NOT NULL' : 'f.done_at IS NULL'];
$params = [];
if ($q !== '')    { $conds[] = 'f.name LIKE ?';     $params[] = '%' . $q . '%'; }
if ($user > 0)    { $conds[] = 'f.created_by = ?';  $params[] = $user; }
if ($from !== '') { $conds[] = 'f.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to !== '')   { $conds[] = 'f.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
$where = ' WHERE ' . implode(' AND ', $conds);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM folders f" . $where);
$stmt->execute($params);
$total      = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * PER_PAGE;

$order = $tab === 'done' ? 'f.done_at DESC, f.id DESC' : 'f.created_at DESC, f.id DESC';
$sql = "SELECT f.id, f.name, f.created_at, f.done_at, u.full_name,
               (SELECT COUNT(*) FROM pages p WHERE p.folder_id = f.id) AS page_count,
               (SELECT p.id FROM pages p WHERE p.folder_id = f.id ORDER BY p.page_no, p.id LIMIT 1) AS cover_id
        FROM folders f
        JOIN users u ON u.id = f.created_by"
     . $where
     . " ORDER BY $order"
     . " LIMIT " . PER_PAGE . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$folders = $stmt->fetchAll();

$users = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$filters  = ['tab' => $tab === 'done' ? 'done' : '', 'q' => $q, 'user' => $user, 'from' => $from, 'to' => $to];
$filtered = (bool)array_filter([$q, $user, $from, $to]);

function page_url(int $p, array $f): string {
    $f['page'] = $p > 1 ? $p : '';
    return 'index.php?' . http_build_query(array_filter($f, fn($v) => $v !== '' && $v !== 0));
}
function tab_url(string $tab): string {
    return $tab === 'done' ? 'index.php?tab=done' : 'index.php';
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
    <?php if ($flash): ?>
      <p class="flash <?= $flash[0] === 'err' ? 'err' : '' ?>"><?= e($flash[1]) ?></p>
    <?php endif; ?>

    <div class="tabs">
      <a class="tab <?= $tab === 'active' ? 'on' : '' ?>" href="<?= tab_url('active') ?>">Active <span class="n"><?= $countActive ?></span></a>
      <a class="tab <?= $tab === 'done' ? 'on' : '' ?>" href="<?= tab_url('done') ?>">Done <span class="n"><?= $countDone ?></span></a>
    </div>

    <form method="get" class="filterbar">
      <?php if ($tab === 'done'): ?><input type="hidden" name="tab" value="done"><?php endif; ?>

      <div class="searchrow">
        <div class="searchbox">
          <?= icon('search') ?>
          <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search folder name...">
        </div>
        <button type="submit">Search</button>
      </div>

      <div class="filters">
        <label>Scanned by
          <select name="user">
            <option value="0">All users</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= $user === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>From
          <input type="date" name="from" value="<?= e($from) ?>">
        </label>
        <label>To
          <input type="date" name="to" value="<?= e($to) ?>">
        </label>
        <?php if ($filtered): ?>
          <a class="clear" href="<?= tab_url($tab) ?>"><?= icon('x') ?> Clear filters</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if (!$folders): ?>
      <div class="empty">
        <?= $tab === 'done' ? 'No folders marked as done yet.' : 'No active folders. New scans from the app show up here.' ?>
      </div>
    <?php else: ?>
      <p class="muted"><?= $total ?> folder(s)</p>
      <div class="list">
        <?php foreach ($folders as $f): ?>
          <a class="row" href="folder.php?id=<?= (int)$f['id'] ?>">
            <div class="cover" <?php if ($f['cover_id']): ?>style="background-image:url('image.php?id=<?= (int)$f['cover_id'] ?>&thumb=1')"<?php endif; ?>></div>
            <div class="info">
              <div class="name"><?= e($f['name']) ?></div>
              <div class="meta">
                <?= (int)$f['page_count'] ?> page(s) · <?= e($f['full_name']) ?> ·
                <?= e(date('M d, Y h:i A', strtotime($f['created_at']))) ?>
              </div>
            </div>
            <?php if ($f['done_at']): ?>
              <span class="badge done">Done · <?= e(date('M d', strtotime($f['done_at']))) ?></span>
            <?php else: ?>
              <span class="badge">Active</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="pager">
          <?php if ($page > 1): ?>
            <a class="btn small ghost" href="<?= e(page_url($page - 1, $filters)) ?>">← Prev</a>
          <?php else: ?>
            <span class="btn small off">← Prev</span>
          <?php endif; ?>

          <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>

          <?php if ($page < $totalPages): ?>
            <a class="btn small ghost" href="<?= e(page_url($page + 1, $filters)) ?>">Next →</a>
          <?php else: ?>
            <span class="btn small off">Next →</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>