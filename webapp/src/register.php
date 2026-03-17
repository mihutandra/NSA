<?php
require_once __DIR__ . '/config.php';
session_start();
requireGuest();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $db    = getDB();
            $token = bin2hex(random_bytes(32));
            $hash  = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $db->prepare(
                'INSERT INTO users (username, email, password_hash, confirmation_token)
                 VALUES (:username, :email, :hash, :token)'
            );
            $stmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':hash'     => $hash,
                ':token'    => $token,
            ]);

            $link = APP_URL . '/confirm.php?token=' . urlencode($token);
            $body = "Hello {$username},\n\n"
                  . "Please confirm your registration by visiting:\n{$link}\n\n"
                  . "If you did not register, ignore this email.\n\n"
                  . "– " . APP_NAME;

            sendEmail($email, 'Confirm your registration – ' . APP_NAME, $body);

            $success = 'Registration successful! Check your email (or MailHog at /mail/) to confirm your account.';
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                $error = 'Username or email is already taken.';
            } else {
                $error = 'Database error. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="container">
    <div class="card form-card">
        <h1><?= APP_NAME ?></h1>
        <h2 style="text-align:center;margin-bottom:1.5rem">Create Account</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autofocus required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required>
            </div>
            <div class="form-group">
                <label for="password">Password <small>(min 8 characters)</small></label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Register</button>
        </form>
        <?php endif; ?>

        <p class="auth-link">Already have an account? <a href="/login.php">Sign in</a></p>
    </div>
</div>
</body>
</html>
