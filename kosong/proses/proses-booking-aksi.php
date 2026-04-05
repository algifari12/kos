<?php
// proses/proses-booking-aksi.php
// Endpoint AJAX: pemilik terima atau tolak booking

// Tangkap semua error PHP agar tidak merusak JSON response
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

// Set error handler agar fatal error pun return JSON
set_exception_handler(function($e) {
    if (ob_get_level() > 0) ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
});

set_error_handler(function($errno, $errstr) {
    if (ob_get_level() > 0) ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $errstr]);
    exit();
});

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

function kirimJSON($data) {
    if (ob_get_level() > 0) ob_clean();
    echo json_encode($data);
    exit();
}

// Proteksi: hanya pemilik yang login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pemilik') {
    kirimJSON(['status' => 'error', 'message' => 'Akses ditolak.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kirimJSON(['status' => 'error', 'message' => 'Method tidak valid.']);
}

$pemilik_id = (int)$_SESSION['user_id'];
$booking_id = (int)($_POST['booking_id'] ?? 0);
$aksi       = trim($_POST['aksi'] ?? '');

// Validasi input
if ($booking_id <= 0) {
    kirimJSON(['status' => 'error', 'message' => 'Booking tidak valid.']);
}
if (!in_array($aksi, ['terima', 'tolak'])) {
    kirimJSON(['status' => 'error', 'message' => 'Aksi tidak valid.']);
}

// Pastikan booking ini memang milik kos pemilik yang login
// (keamanan: pemilik tidak bisa terima/tolak booking kos orang lain)
$cek = mysqli_query($conn,
    "SELECT b.booking_id, b.status, b.user_id, k.nama_kos
     FROM booking b
     JOIN kos k ON b.kos_id = k.kos_id
     WHERE b.booking_id = $booking_id
       AND k.pemilik_id = $pemilik_id"
);

if (!$cek || mysqli_num_rows($cek) === 0) {
    kirimJSON(['status' => 'error', 'message' => 'Booking tidak ditemukan atau bukan milik Anda.']);
}

$booking = mysqli_fetch_assoc($cek);

// Hanya bisa aksi jika masih pending
if ($booking['status'] !== 'pending') {
    kirimJSON(['status' => 'error', 'message' => 'Booking ini sudah diproses sebelumnya (status: ' . $booking['status'] . ').']);
}

// Update status
$status_baru = ($aksi === 'terima') ? 'diterima' : 'ditolak';
$update = mysqli_query($conn,
    "UPDATE booking SET status = '$status_baru' WHERE booking_id = $booking_id"
);

if (!$update) {
    kirimJSON(['status' => 'error', 'message' => 'Gagal memperbarui database: ' . mysqli_error($conn)]);
}

kirimJSON([
    'status'      => 'success',
    'booking_id'  => $booking_id,
    'status_baru' => $status_baru,
    'nama_kos'    => $booking['nama_kos'],
    'pencari_id'  => $booking['user_id'],
]);