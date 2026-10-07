<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/lib.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT f.*, u.full_name, d.full_name AS done_name
     FROM folders f
     JOIN users u ON u.id = f.created_by
     LEFT JOIN users d ON d.id = f.done_by
     WHERE f.id = ?"
);
$stmt->execute([$id]);
$folder = $stmt->fetch();
if (!$folder) { http_response_code(404); exit('Folder not found.'); }
$isDone = $folder['done_at'] !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $action = $_POST['action'] ?? '';

        // a done folder is locked: only reopen or delete are allowed
        if ($isDone && !in_array($action, ['reopen', 'delete'], true)) {
            throw new Exception('This folder is marked done. Reopen it to make changes.');
        }

        switch ($action) {
            case 'done':
                set_folder_done($pdo, $id, (int)$_SESSION['user_id'], true);
                $_SESSION['flash'] = ['ok', '"' . $folder['name'] . '" moved to Done.'];
                header('Location: index.php');
                exit;
            case 'reopen':
                set_folder_done($pdo, $id, (int)$_SESSION['user_id'], false);
                $_SESSION['flash'] = ['ok', 'Folder moved back to Active.'];
                break;
            case 'rename':
                rename_folder($pdo, $id, $_POST['name'] ?? '');
                $_SESSION['flash'] = ['ok', 'Folder renamed.'];
                break;
            case 'delete_page':
                delete_page($pdo, $id, (int)($_POST['page_id'] ?? 0));
                $_SESSION['flash'] = ['ok', 'Page deleted.'];
                break;
            case 'add_pages':
                $n = add_pages($pdo, $id, normalize_files('pages'));
                $_SESSION['flash'] = ['ok', $n . ' page(s) added.'];
                break;
            case 'delete':
                delete_folder($pdo, $id);
                header('Location: index.php' . ($isDone ? '?tab=done' : ''));
                exit;
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['err', $e->getMessage()];
    }
    header('Location: folder.php?id=' . $id);   // avoids "resubmit form" on refresh
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$stmt = $pdo->prepare("SELECT id, file_path, page_no FROM pages WHERE folder_id = ? ORDER BY page_no, id");
$stmt->execute([$id]);
$pages = $stmt->fetchAll();
$count = count($pages);
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
    <strong><a href="index.php<?= $isDone ? '?tab=done' : '' ?>">← AppScan</a></strong>
    <span><?= e($_SESSION['full_name']) ?> · <a href="logout.php">Log out</a></span>
  </div>

  <div class="card">
    <?php if ($flash): ?>
      <p class="flash <?= $flash[0] === 'err' ? 'err' : '' ?>"><?= e($flash[1]) ?></p>
    <?php endif; ?>

    <div class="head">
      <div>
        <h2><?= e($folder['name']) ?></h2>
        <p class="muted">
          <?= $count ?> page(s) · scanned by <?= e($folder['full_name']) ?>
          · <?= e(date('M d, Y h:i A', strtotime($folder['created_at']))) ?>
        </p>
      </div>
      <div class="head-tools">
        <?php if (!$isDone): ?>
          <button type="button" class="icon-btn" data-open="dlgRename" title="Rename folder" aria-label="Rename folder"><?= icon('pencil') ?></button>
          <button type="button" class="icon-btn" data-open="dlgAdd" title="Add pages" aria-label="Add pages"><?= icon('plus') ?></button>
        <?php endif; ?>
        <span class="badge <?= $isDone ? 'done' : '' ?>"><?= $isDone ? 'Done' : 'Active' ?></span>
      </div>
    </div>

    <?php if ($isDone): ?>
      <div class="doneinfo">
        <?= icon('check') ?> Marked done<?= $folder['done_name'] ? ' by ' . e($folder['done_name']) : '' ?>
        on <?= e(date('M d, Y h:i A', strtotime($folder['done_at']))) ?>
      </div>
    <?php endif; ?>

    <div class="actions">
      <?php if ($isDone): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reopen">
          <button type="submit" class="ghost"><?= icon('undo') ?> Reopen</button>
        </form>
      <?php else: ?>
        <form method="post" onsubmit="return confirm('Mark this folder as done and move it to the Done section?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="done">
          <button type="submit" class="success"><?= icon('check') ?> Done</button>
        </form>
      <?php endif; ?>

      <button type="button" id="dlAll" class="ghost"><?= icon('download') ?> Download all pages</button>

      <form method="post" onsubmit="return confirm('Delete this folder and all its pages?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="danger">Delete folder</button>
      </form>
    </div>


    <div class="grid">
    <?php foreach ($pages as $i => $p): ?>
      <div class="thumb">
        <a href="image.php?id=<?= (int)$p['id'] ?>" target="_blank">
          <img src="image.php?id=<?= (int)$p['id'] ?>&thumb=1" alt="Page <?= $i + 1 ?>" loading="lazy">
        </a>
        <span>Page <?= $i + 1 ?></span>
        <a class="btn small ghost dl" href="download.php?id=<?= (int)$p['id'] ?>">Download</a>

        <?php if (!$isDone && $count > 1): ?>
        <div class="tools">
          <form method="post" onsubmit="return confirm('Delete page <?= $i + 1 ?>?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_page">
            <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="danger" title="Delete page"><?= icon('trash') ?> Delete</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>

    <?php if (!$isDone): ?>
    <dialog id="dlgRename">
      <form method="post" class="dlg">
        <h3>Rename folder</h3>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="rename">
        <input type="text" name="name" value="<?= e($folder['name']) ?>" maxlength="150" required>
        <div class="dlg-actions">
          <button type="button" class="ghost" data-close>Cancel</button>
          <button type="submit">Save</button>
        </div>
      </form>
    </dialog>

    <dialog id="dlgAdd">
      <form method="post" enctype="multipart/form-data" class="dlg">
        <h3>Add pages</h3>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_pages">
        <input type="file" name="pages[]" accept="image/*" multiple required>
        <div class="dlg-actions">
          <button type="button" class="ghost" data-close>Cancel</button>
          <button type="submit">Upload</button>
        </div>
      </form>
    </dialog>
    <?php endif; ?>

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

    document.querySelectorAll('[data-open]').forEach(function (b) {
      b.addEventListener('click', function () {
        var d = document.getElementById(b.dataset.open);
        d.showModal();
        var t = d.querySelector('input[type=text]');
        if (t) t.select();
      });
    });
    document.querySelectorAll('dialog').forEach(function (d) {
      d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
      d.querySelectorAll('[data-close]').forEach(function (x) {
        x.addEventListener('click', function () { d.close(); });
      });
    });
    </script>
  </div>
</body>
</html>