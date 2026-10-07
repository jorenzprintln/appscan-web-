<?php
require __DIR__ . '/auth.php';

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        if (!empty($_POST['remember'])) {
            $_SESSION['remember'] = true;
            send_remember_cookie();
        }
        header('Location: index.php');
        exit;
    }
    $error = 'Wrong email or password.';
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - AppScan</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body.auth { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
      background: radial-gradient(circle at 15% 10%, #dbe7ff 0, transparent 45%),
                  radial-gradient(circle at 85% 90%, #e0f2fe 0, transparent 45%), var(--bg); }
    .login { width: 100%; max-width: 400px; background: #fff; border-radius: 18px; padding: 36px 30px 30px;
      box-shadow: 0 10px 40px rgba(15,23,42,.10); }
    .brand { display: block; width: 100%; max-width: 280px; height: auto; margin: 0 auto 18px; }
    .login h1 { margin: 0; text-align: center; font-size: 24px; letter-spacing: -.4px; }
    .login .sub { text-align: center; color: var(--muted); font-size: 14px; margin: 4px 0 22px; }
    .login label { display: block; font-size: 13px; font-weight: 600; margin: 14px 0 2px; }
    .login input { margin: 4px 0 0; padding: 12px 14px; }
    .pw { position: relative; display: flow-root; }
    .pw input { padding-right: 52px; }
    .pw button { position: absolute; right: 8px; top: 11px; width: 34px; height: 34px; padding: 0;
      background: transparent; color: var(--muted); border: 0; border-radius: 8px; }
    .pw button:hover { background: #f1f5f9; color: var(--ink); }
    .pw svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2;
      stroke-linecap: round; stroke-linejoin: round; }
    .login input::placeholder { color: #6b7280; }
    .login label.remember { display: flex; align-items: center; gap: 10px; margin: 16px 0 0; font-size: 14px;
      font-weight: 500; color: var(--ink); cursor: pointer; }
    .login .remember input { width: 18px; height: 18px; margin: 0; padding: 0; flex: none; accent-color: var(--brand); }
    .login .submit { width: 100%; margin-top: 22px; padding: 13px; font-size: 15px; }
    .login .error { margin: 0 0 4px; font-size: 14px; }
    .foot { text-align: center; color: var(--muted); font-size: 12px; margin-top: 20px; }
  </style>
</head>
<body class="auth">
  <div class="login">
    <img class="brand" src="assets/logo/logo.png" alt="Converge ICT Solutions Inc.">
    <h1>AppScan</h1>
    <p class="sub">Sign in to manage your scanned documents</p>

    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

    <form method="post">
      <label for="email">Company email</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="name@company.com"
             autocomplete="username" required <?= $email === '' ? 'autofocus' : '' ?>>

      <label for="password">Password</label>
      <div class="pw">
        <input type="password" id="password" name="password" placeholder="Enter your password"
               autocomplete="current-password" required <?= $email !== '' ? 'autofocus' : '' ?>>
        <button type="button" id="toggle" aria-label="Show password" title="Show password">
          <svg id="eyeOn" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <svg id="eyeOff" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
        </button>
      </div>

      <label class="remember">
        <input type="checkbox" name="remember" value="1" <?= !empty($_POST['remember']) ? 'checked' : '' ?>>
        Keep me signed in
      </label>

      <button type="submit" class="submit">Log in</button>
    </form>
  </div>

  <script>
    document.getElementById('toggle').addEventListener('click', function () {
      var p = document.getElementById('password');
      var show = p.type === 'password';
      p.type = show ? 'text' : 'password';
      document.getElementById('eyeOn').style.display  = show ? 'none' : '';
      document.getElementById('eyeOff').style.display = show ? '' : 'none';
      this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      this.title = show ? 'Hide password' : 'Show password';
    });
  </script>
</body>
</html>