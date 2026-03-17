<?php
require_once __DIR__ . '/config.php';
session_start();

$message = '';
$type    = 'info';

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    $message = 'Missing confirmation token.';
    $type    = 'error';
} else {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT id FROM users WHERE confirmation_token = :token AND confirmed = 0 LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            $message = 'Invalid or already-used confirmation token.';
            $type    = 'error';
        } else {
            $upd = $db->prepare(
                'UPDATE users SET confirmed = 1, confirmation_token = NULL WHERE id = :id'
            );
            $upd->execute([':id' => $user['id']]);
            $message = 'Your email has been confirmed! You can now log in.';
            $type    = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Database error. Please try again later.';
        $type    = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Email – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="container">
    <div class="card form-card">
        <h1><?= APP_NAME ?></h1>
        <h2 style="text-align:center;margin-bottom:1.5rem">Email Confirmation</h2>
        <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($message) ?></div>
        <p class="auth-link"><a href="/login.php">Go to Login</a></p>
    </div>
</div>
</body>
</html>
