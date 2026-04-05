<?php
session_start();
require_once '../config/database.php';

// Pastikan tidak ada output apapun sebelum header
ob_clean();
header('Content-Type: application/json');

// Fungsi helper kirim JSON lalu exit
function kirimJSON($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit();
}

// Cek login
if (!isset($_SESSION['user_id'])) {
    kirimJSON('error', 'Anda harus login terlebih dahulu.');
}

// Cek role - hanya pencari yang boleh booking
if ($_SESSION['role'] !== 'pencari') {
    kirimJSON('error', 'Hanya pencari kos yang dapat mengajukan sewa.');
}

// Ambil kos_id
$kos_id = isset($_POST['kos_id']) ? (int)$_POST['kos_id'] : 0;
$user_id = (int)$_SESSION['user_id'];

if ($kos_id <= 0) {
    kirimJSON('error', 'ID kos tidak valid.');
}

// Cek duplikat pending
$stmt = $conn->prepare("SELECT booking_id FROM booking WHERE user_id = ? AND kos_id = ? AND status = 'pending'");
$stmt->bind_param("ii", $user_id, $kos_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    kirimJSON('already', 'Anda sudah mengajukan sewa kos ini dan sedang menunggu konfirmasi.');
}
$stmt->close();

// Cek kos ada
$stmt2 = $conn->prepare("SELECT kos_id FROM kos WHERE kos_id = ?");
$stmt2->bind_param("i", $kos_id);
$stmt2->execute();
$stmt2->store_result();
if ($stmt2->num_rows === 0) {
    $stmt2->close();
    kirimJSON('error', 'Kos tidak ditemukan.');
}
$stmt2->close();

// Insert booking
$now = date('Y-m-d H:i:s');
$stmt3 = $conn->prepare("INSERT INTO booking (user_id, kos_id, tanggal_booking, status, created_at) VALUES (?, ?, ?, 'pending', ?)");
$stmt3->bind_param("iiss", $user_id, $kos_id, $now, $now);

if ($stmt3->execute()) {
    $stmt3->close();
    kirimJSON('success', 'Pengajuan sewa berhasil dikirim!');
} else {
    $err = $stmt3->error;
    $stmt3->close();
    kirimJSON('error', 'Gagal menyimpan: ' . $err);
}