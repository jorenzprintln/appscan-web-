<?php
require __DIR__ . '/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['error' => 'POST only.']);

$user = api_user($pdo);
$pdo->prepare("DELETE FROM tokens WHERE token_hash = ?")->execute([$user['token_hash']]);
json_out(200, ['ok' => true]);