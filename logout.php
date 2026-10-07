<?php
require __DIR__ . '/auth.php';   // starts the session with the same settings as the rest of the site

$_SESSION = [];
if (!headers_sent()) {
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
}
session_destroy();
header('Location: login.php');
exit;