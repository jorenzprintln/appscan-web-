<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/lib.php';
require_login();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
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
  <style>
    .upcard { max-width: 460px; margin-top: 40px; }
    .upcard label { display: block; font-size: 13px; font-weight: 600; margin: 14px 0 2px; }
    .upcard button[type=submit] { width: 100%; margin-top: 18px; padding: 13px; font-size: 15px; }
    .upcard .error[hidden] { display: none; }

    .busy { position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center;
      padding: 20px; background: rgba(15,23,42,.55); }
    .busy[hidden] { display: none; }
    .busybox { width: 100%; max-width: 340px; background: #fff; border-radius: 16px; padding: 28px 24px; text-align: center;
      box-shadow: 0 20px 60px rgba(15,23,42,.3); }
    .busybox strong { display: block; font-size: 17px; margin: 14px 0 12px; }
    .spin { width: 40px; height: 40px; margin: 0 auto; border: 4px solid #dbe5f7; border-top-color: var(--brand);
      border-radius: 50%; animation: spin .8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .bar { height: 8px; background: #e5e9f0; border-radius: 999px; overflow: hidden; }
    .bar > div { height: 100%; width: 0; background: var(--brand); border-radius: 999px; transition: width .2s; }
    .busybox p { margin: 12px 0 0; }
  </style>
</head>
<body>
  <div class="topbar">
    <strong><a href="index.php">← AppScan</a></strong>
    <span><?= e($_SESSION['full_name']) ?></span>
  </div>

  <div class="card upcard">
    <h2>Upload pages</h2>
    <p class="error" id="errBox" <?= $error ? '' : 'hidden' ?>><?= e($error) ?></p>

    <form method="post" enctype="multipart/form-data" id="f">
      <?= csrf_field() ?>
      <label for="folder_name">Folder name</label>
      <input type="text" id="folder_name" name="folder_name" maxlength="150" placeholder="e.g. Juan Dela Cruz" required>

      <label for="files">Pages (photos)</label>
      <input type="file" id="files" name="pages[]" accept="image/*" multiple required>
      <p class="muted" id="count"></p>

      <button type="submit" id="save">Save folder</button>
    </form>
  </div>

  <div class="busy" id="busy" hidden>
    <div class="busybox">
      <div class="spin"></div>
      <strong id="busyTitle">Uploading… 0%</strong>
      <div class="bar"><div id="barFill"></div></div>
      <p class="muted" id="busyNote">Please don't close or refresh this page.</p>
    </div>
  </div>

  <script>
    var form  = document.getElementById('f');
    var files = document.getElementById('files');
    var save  = document.getElementById('save');
    var busy  = document.getElementById('busy');
    var title = document.getElementById('busyTitle');
    var note  = document.getElementById('busyNote');
    var fill  = document.getElementById('barFill');
    var errBox = document.getElementById('errBox');
    var uploading = false;

    files.addEventListener('change', function () {
      document.getElementById('count').textContent =
        files.files.length ? files.files.length + ' page(s) selected' : '';
    });

    // warn if the user tries to leave while the upload is running
    window.addEventListener('beforeunload', function (e) {
      if (uploading) { e.preventDefault(); e.returnValue = ''; }
    });

    function fail(msg) {
      uploading = false;
      save.disabled = false;
      busy.hidden = true;
      errBox.textContent = msg;
      errBox.hidden = false;
      window.scrollTo(0, 0);
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (uploading) return;            // ignore extra taps

      uploading = true;
      save.disabled = true;
      errBox.hidden = true;
      fill.style.width = '0';
      title.textContent = 'Uploading… 0%';
      note.textContent = "Please don't close or refresh this page.";
      busy.hidden = false;

      var xhr = new XMLHttpRequest();
      xhr.open('POST', location.href);

      xhr.upload.onprogress = function (ev) {
        if (!ev.lengthComputable) return;
        var pct = Math.round(ev.loaded / ev.total * 100);
        fill.style.width = pct + '%';
        title.textContent = 'Uploading… ' + pct + '%';
      };
      xhr.upload.onload = function () {
        fill.style.width = '100%';
        title.textContent = 'Saving pages…';
        note.textContent = 'Almost done, preparing your folder.';
      };

      xhr.onload = function () {
        var url = xhr.responseURL || '';
        if (/folder\.php/.test(url) || /login\.php/.test(url)) {
          uploading = false;            // so the leave-warning doesn't appear
          location.href = url;
          return;
        }
        var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
        var err = doc.querySelector('.error');
        fail(err ? err.textContent.trim() : 'Upload failed. Please try again.');
      };
      xhr.onerror = function () { fail('Connection lost. Check your internet and try again.'); };
      xhr.ontimeout = function () { fail('The upload took too long. Try fewer pages at a time.'); };

      xhr.send(new FormData(form));
    });
  </script>
</body>
</html>