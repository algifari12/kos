<?php
session_start();
require_once '../config/database.php';

// Cek apakah form disubmit dengan method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../daftar.php');
    exit();
}

// Ambil data dari form
$username = trim($_POST['username']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$role = $_POST['role']; // pemilik atau pencari

// Validasi input kosong
if (empty($username) || empty($password) || empty($confirm_password)) {
    $_SESSION['error'] = 'Semua field harus diisi!';
    header('Location: ../daftar.php?role=' . $role);
    exit();
}

// Validasi panjang username
if (strlen($username) < 4) {
    $_SESSION['error'] = 'Username minimal 4 karakter!';
    header('Location: ../daftar.php?role=' . $role);
    exit();
}

// Validasi panjang password
if (strlen($password) < 6) {
    $_SESSION['error'] = 'Password minimal 6 karakter!';
    header('Location: ../daftar.php?role=' . $role);
    exit();
}

// Validasi password dan konfirmasi password sama
if ($password !== $confirm_password) {
    $_SESSION['error'] = 'Password dan Konfirmasi Password tidak cocok!';
    header('Location: ../daftar.php?role=' . $role);
    exit();
}

// Escape string untuk keamanan
$username = $conn->real_escape_string($username);
$role = $conn->real_escape_string($role);

// Cek apakah username sudah ada
$query_check = "SELECT user_id FROM users WHERE username = '$username'";
$result_check = $conn->query($query_check);

if ($result_check->num_rows > 0) {
    // Username sudah digunakan
    $_SESSION['error'] = 'Username sudah digunakan! Silakan pilih username lain.';
    header('Location: ../daftar.php?role=' . $role);
    exit();
}

// Hash password untuk keamanan
// password_hash() membuat password menjadi aman dari hacker
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insert user baru ke database
$query = "INSERT INTO users (username, password, role) VALUES ('$username', '$password_hash', '$role')";

if ($conn->query($query) === TRUE) {
    // Pendaftaran berhasil
    $_SESSION['success'] = 'Pendaftaran berhasil! Silakan login.';
    header('Location: ../login.php?role=' . $role);
    exit();
} else {
    // Pendaftaran gagal
    $_SESSION['error'] = 'Pendaftaran gagal: ' . $conn->error;
    header('Location: ../daftar.php?role=' . $role);
    exit();
}

$conn->close();
?>