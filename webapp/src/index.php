<?php
require_once __DIR__ . '/config.php';
session_start();

// Redirect to dashboard if already logged in, otherwise to login
header('Location: ' . (isLoggedIn() ? '/dashboard.php' : '/login.php'));
exit;
