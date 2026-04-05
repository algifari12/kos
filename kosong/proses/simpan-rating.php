<?php
// proses/simpan-rating.php
// Endpoint AJAX: pencari kirim rating + komentar untuk kos yang sudah diterima bookingnya

ob_start();
ini_set('display_errors', 0);
error_reporting(0);

session_start();
header('Content-Type: application/json');

set_exception_handler(function($e) {
    if (ob_get_level() > 0) ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
});

require_once '../config/database.php';

function kirimJSON($data) {
    if (ob_get_level() > 0) ob_clean();
    echo json_encode($data);
    exit();
}

// Hanya pencari yang login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pencari') {
    kirimJSON(['status' => 'error', 'message' => 'Akses ditolak.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kirimJSON(['status' => 'error', 'message' => 'Method tidak valid.']);
}

$user_id    = (int)$_SESSION['user_id'];
$booking_id = (int)($_POST['booking_id'] ?? 0);
$rating     = (int)($_POST['rating']     ?? 0);
$komentar   = trim($_POST['komentar']    ?? '');

// Validasi
if ($booking_id <= 0) {
    kirimJSON(['status' => 'error', 'message' => 'Booking tidak valid.']);
}
if ($rating < 1 || $rating > 5) {
    kirimJSON(['status' => 'error', 'message' => 'Rating harus antara 1 - 5.']);
}
if (strlen($komentar) < 5) {
    kirimJSON(['status' => 'error', 'message' => 'Komentar terlalu pendek (minimal 5 karakter).']);
}
if (strlen($komentar) > 500) {
    kirimJSON(['status' => 'error', 'message' => 'Komentar terlalu panjang (maksimal 500 karakter).']);
}

// Pastikan booking ini milik pencari yang login + statusnya diterima
$cek = mysqli_query($conn,
    "SELECT b.booking_id, b.kos_id FROM booking b
     WHERE b.booking_id = $booking_id
       AND b.user_id    = $user_id
       AND b.status     = 'diterima'
     LIMIT 1"
);

if (!$cek || mysqli_num_rows($cek) === 0) {
    kirimJSON(['status' => 'error', 'message' => 'Booking tidak ditemukan atau belum diterima.']);
}

// Pastikan belum pernah review booking ini sebelumnya (1 booking = 1 review)
$cek_duplikat = mysqli_query($conn,
    "SELECT review_id FROM review WHERE booking_id = $booking_id LIMIT 1"
);

if ($cek_duplikat && mysqli_num_rows($cek_duplikat) > 0) {
    kirimJSON(['status' => 'error', 'message' => 'Kamu sudah memberikan penilaian untuk booking ini.']);
}

// Escape komentar
$komentar_safe = mysqli_real_escape_string($conn, $komentar);

// Simpan review
$insert = mysqli_query($conn,
    "INSERT INTO review (booking_id, rating, komentar, tanggal_review)
     VALUES ($booking_id, $rating, '$komentar_safe', NOW())"
);

if (!$insert) {
    kirimJSON(['status' => 'error', 'message' => 'Gagal menyimpan: ' . mysqli_error($conn)]);
}

kirimJSON([
    'status'     => 'success',
    'review_id'  => mysqli_insert_id($conn),
    'booking_id' => $booking_id,
    'rating'     => $rating,
]);