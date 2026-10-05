<?php
require_once __DIR__ . '/db.php';

function json_out(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}