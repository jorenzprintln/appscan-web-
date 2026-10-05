<?php
require __DIR__ . '/auth.php';

$error = '';
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
</head>
<body>
  <div class="card narrow">
    <h2>AppScan</h2>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <input type="email" name="email" placeholder="Company email" required autofocus>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit">Log in</button>
    </form>
  </div>
</body>
</html>