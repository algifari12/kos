<?php
session_start();
require_once '../config/database.php';

// Cek apakah form disubmit dengan method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit();
}

// Ambil data dari form
$username = trim($_POST['username']);
$password = $_POST['password'];
$role = $_POST['role'];

// Validasi input kosong
if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Username dan password harus diisi!';
    header('Location: ../login.php?role=' . $role);
    exit();
}

// Escape string untuk keamanan
$username = $conn->real_escape_string($username);
$role = $conn->real_escape_string($role);

// Cari user berdasarkan username dan role
$query = "SELECT * FROM users WHERE username = '$username' AND role = '$role'";
$result = $conn->query($query);

// Cek apakah user ditemukan
if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Verifikasi password
    $password_db = $user['password'];
    $password_cocok = false;
    
    // Cek apakah password di database sudah di-hash atau belum
    if (substr($password_db, 0, 4) === '$2y$' || substr($password_db, 0, 4) === '$2a$') {
        // Password sudah di-hash, gunakan password_verify
        $password_cocok = password_verify($password, $password_db);
    } else {
        // Password belum di-hash (plain text), bandingkan langsung
        $password_cocok = ($password === $password_db);
    }
    
    if ($password_cocok) {
        // Login berhasil, simpan data ke session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect sesuai role
        if ($user['role'] === 'pemilik') {
            header('Location: ../dashboard-pemilik.php');
        } else {
            header('Location: ../dashboard-pencari.php');
        }
        exit();
    } else {
        // Password salah
        $_SESSION['error'] = 'Password yang Anda masukkan salah!';
        header('Location: ../login.php?role=' . $role);
        exit();
    }
} else {
    // User tidak ditemukan
    $_SESSION['error'] = 'Username tidak ditemukan atau role tidak sesuai!';
    header('Location: ../login.php?role=' . $role);
    exit();
}

$conn->close();
?>