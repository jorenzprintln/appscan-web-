<?php
require __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['error' => 'POST only.']);

$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT id, full_name, password_hash FROM users WHERE username = ?");
$stmt->execute([$email]);
$u = $stmt->fetch();

if (!$u || !password_verify($pass, $u['password_hash'])) {
    usleep(500000);   // slows down password guessing
    json_out(401, ['error' => 'Wrong email or password.']);
}

$token = bin2hex(random_bytes(32));
$pdo->prepare("INSERT INTO tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL " . TOKEN_DAYS . " DAY))")
    ->execute([(int)$u['id'], hash('sha256', $token)]);

// housekeeping: remove expired tokens
$pdo->exec("DELETE FROM tokens WHERE expires_at < NOW()");

json_out(200, [
    'token' => $token,
    'user'  => ['id' => (int)$u['id'], 'full_name' => $u['full_name']],
]);