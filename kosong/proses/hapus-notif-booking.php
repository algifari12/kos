<?php
// proses/hapus-notif-booking.php
// Endpoint AJAX: pencari hapus notifikasi booking yang ditolak

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
if (!isset($_SESSION['user_id'])) {
    kirimJSON(['status' => 'error', 'message' => 'Belum login.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kirimJSON(['status' => 'error', 'message' => 'Method tidak valid.']);
}

$user_id    = (int)$_SESSION['user_id'];
$booking_id = (int)($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    kirimJSON(['status' => 'error', 'message' => 'Booking tidak valid.']);
}

// Pastikan booking ini milik pencari yang login DAN statusnya ditolak
$cek = mysqli_query($conn,
    "SELECT booking_id, status FROM booking
     WHERE booking_id = $booking_id
       AND user_id    = $user_id
       AND status     = 'ditolak'"
);

if (!$cek || mysqli_num_rows($cek) === 0) {
    kirimJSON(['status' => 'error', 'message' => 'Booking tidak ditemukan atau belum ditolak.']);
}

// Hapus booking
$hapus = mysqli_query($conn, "DELETE FROM booking WHERE booking_id = $booking_id");

if (!$hapus) {
    kirimJSON(['status' => 'error', 'message' => 'Gagal menghapus: ' . mysqli_error($conn)]);
}

kirimJSON(['status' => 'success', 'booking_id' => $booking_id]);