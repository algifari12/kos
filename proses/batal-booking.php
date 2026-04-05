<?php
session_start();
require_once '../config/database.php';

ob_clean();
header('Content-Type: application/json');

function kirimJSON($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit();
}

// Cek login dan role
if (!isset($_SESSION['user_id'])) {
    kirimJSON('error', 'Anda harus login terlebih dahulu.');
}

if ($_SESSION['role'] !== 'pencari') {
    kirimJSON('error', 'Akses tidak diizinkan.');
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$user_id    = (int)$_SESSION['user_id'];

if ($booking_id <= 0) {
    kirimJSON('error', 'ID booking tidak valid.');
}

// Pastikan booking milik user ini dan masih pending
$stmt = $conn->prepare("SELECT booking_id FROM booking WHERE booking_id = ? AND user_id = ? AND status = 'pending'");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    kirimJSON('error', 'Booking tidak ditemukan atau sudah diproses.');
}
$stmt->close();

// Hapus booking
$stmt2 = $conn->prepare("DELETE FROM booking WHERE booking_id = ? AND user_id = ?");
$stmt2->bind_param("ii", $booking_id, $user_id);

if ($stmt2->execute()) {
    $stmt2->close();
    kirimJSON('success', 'Pengajuan sewa berhasil dibatalkan.');
} else {
    $err = $stmt2->error;
    $stmt2->close();
    kirimJSON('error', 'Gagal membatalkan: ' . $err);
}