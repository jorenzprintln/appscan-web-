<?php
require __DIR__ . '/api_common.php';

$user = api_user($pdo);
json_out(200, ['user' => ['id' => $user['id'], 'full_name' => $user['full_name']]]);