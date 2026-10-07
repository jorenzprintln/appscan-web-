<?php
require_once __DIR__ . '/db.php';

const TOKEN_DAYS = 90;   // a saved app login stays valid this long after its last use

function json_out(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Reads the X-Auth-Token header, checks it against the tokens table and
// returns the logged-in user. Stops with 401 if the token is missing/invalid/expired.
function api_user(PDO $pdo): array {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) json_out(401, ['error' => 'Not logged in.']);

    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        "SELECT t.id AS token_id, u.id, u.full_name
         FROM tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.expires_at > NOW()"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) json_out(401, ['error' => 'Session expired. Please log in again.']);

    // sliding expiry: every use renews the token
    $pdo->prepare("UPDATE tokens SET last_used_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL " . TOKEN_DAYS . " DAY) WHERE id = ?")
        ->execute([$row['token_id']]);

    return ['id' => (int)$row['id'], 'full_name' => $row['full_name'], 'token_hash' => $hash];
}