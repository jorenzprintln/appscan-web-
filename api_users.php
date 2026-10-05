<?php
require __DIR__ . '/api_common.php';

$rows = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();
json_out(200, ['users' => $rows]);