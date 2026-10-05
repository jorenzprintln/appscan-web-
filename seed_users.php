<?php
require __DIR__ . '/db.php';

$users = [
    ['pj_pablo.msp@leytenet.com',   'Paul Jorenz Pablo',    'Yeswecan123!'],
];

$stmt = $pdo->prepare(
    "INSERT INTO users (username, password_hash, full_name)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE
       password_hash = VALUES(password_hash),
       full_name = VALUES(full_name)"
);

foreach ($users as [$email, $name, $pass]) {
    $stmt->execute([strtolower(trim($email)), password_hash($pass, PASSWORD_DEFAULT), $name]);
    echo "Saved: $email ($name)<br>";
}