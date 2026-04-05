<?php
// proses/proses-login-admin.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login-admin.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Username dan password wajib diisi.';
    header("Location: ../login-admin.php");
    exit();
}

$username_esc = mysqli_real_escape_string($conn, $username);
$result = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username_esc' AND role = 'admin' LIMIT 1");

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Akun admin tidak ditemukan.';
    header("Location: ../login-admin.php");
    exit();
}

$user = mysqli_fetch_assoc($result);

$valid = password_verify($password, $user['password']) || ($password === $user['password']);

if (!$valid) {
    $_SESSION['error'] = 'Password salah.';
    header("Location: ../login-admin.php");
    exit();
}

$_SESSION['user_id']  = $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role']     = 'admin';

header("Location: ../admin.php");
exit();