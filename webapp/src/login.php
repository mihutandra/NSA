<?php
require_once __DIR__ . '/config.php';
session_start();
requireGuest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare(
                'SELECT id, username, password_hash, confirmed
                   FROM users
                  WHERE email = :identifier_email OR username = :identifier_username
               ORDER BY CASE WHEN email = :priority_email THEN 0 ELSE 1 END
                  LIMIT 1'
            );
            $stmt->execute([
                ':identifier_email'    => $identifier,
                ':identifier_username' => $identifier,
                ':priority_email'      => $identifier,
            ]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Invalid credentials.';
            } elseif (!$user['confirmed']) {
                $error = 'Please confirm your email address before logging in.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: /dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="container">
    <div class="card form-card">
        <h1><?= APP_NAME ?></h1>
        <h2 style="text-align:center;margin-bottom:1.5rem">Sign In</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="form-group">
                <label for="identifier">Username or Email</label>
                <input type="text" id="identifier" name="identifier"
                       value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                       autofocus required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Sign In</button>
        </form>

        <p class="auth-link">No account? <a href="/register.php">Register</a></p>
    </div>
</div>
</body>
</html>
