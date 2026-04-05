<?php
// File koneksi database menggunakan MySQLi
$host = 'localhost';
$dbname = 'db_kosong';
$username = 'root';
$password = '';

// Koneksi ke database menggunakan MySQLi
$conn = new mysqli($host, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set charset UTF-8
$conn->set_charset("utf8");

// Fungsi helper
function mulai_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function cek_login() {
    mulai_session();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function cek_role($role_diperlukan) {
    mulai_session();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role_diperlukan) {
        header('Location: index.php');
        exit();
    }
}
?>