<?php
// proses/proses-favorit.php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pencari') {
    echo json_encode(['status' => 'error', 'message' => 'Login sebagai pencari terlebih dahulu']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$kos_id  = (int)($_POST['kos_id'] ?? 0);
$aksi    = $_POST['aksi'] ?? ''; // 'simpan' atau 'hapus'

if (!$kos_id) {
    echo json_encode(['status' => 'error', 'message' => 'Kos tidak valid']);
    exit();
}

if ($aksi === 'simpan') {
    $q = "INSERT IGNORE INTO favorit (user_id, kos_id) VALUES ($user_id, $kos_id)";
    if (mysqli_query($conn, $q)) {
        echo json_encode(['status' => 'success', 'tersimpan' => true, 'message' => 'Kos berhasil disimpan!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan']);
    }
} elseif ($aksi === 'hapus') {
    $q = "DELETE FROM favorit WHERE user_id = $user_id AND kos_id = $kos_id";
    if (mysqli_query($conn, $q)) {
        echo json_encode(['status' => 'success', 'tersimpan' => false, 'message' => 'Kos dihapus dari simpanan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid']);
}