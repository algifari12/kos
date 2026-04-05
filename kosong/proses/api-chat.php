<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

function kirimJSON($data) {
    ob_clean();
    echo json_encode($data);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    kirimJSON(['status' => 'error', 'message' => 'Belum login.']);
}

$user_id  = (int)$_SESSION['user_id'];
$aksi     = $_REQUEST['aksi'] ?? '';

// ===== KIRIM PESAN =====
if ($aksi === 'kirim' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $teman_id = isset($_POST['teman_id']) ? (int)$_POST['teman_id'] : 0;
    $pesan    = trim($_POST['pesan'] ?? '');

    if ($teman_id <= 0 || $pesan === '') {
        kirimJSON(['status' => 'error', 'message' => 'Data tidak lengkap.']);
    }

    // Validasi: pastikan teman_id adalah user yang valid di database
    $cek_user = mysqli_query($conn, "SELECT user_id FROM users WHERE user_id = $teman_id LIMIT 1");
    if (!$cek_user || mysqli_num_rows($cek_user) === 0) {
        kirimJSON(['status' => 'error', 'message' => 'Pengguna tidak ditemukan.']);
    }
    // Pastikan tidak chat dengan diri sendiri
    if ($teman_id === $user_id) {
        kirimJSON(['status' => 'error', 'message' => 'Tidak dapat mengirim pesan ke diri sendiri.']);
    }

    $pesan_esc = mysqli_real_escape_string($conn, $pesan);
    $now = date('Y-m-d H:i:s');

    $insert = mysqli_query($conn, "
        INSERT INTO chat (sender_id, receiver_id, pesan, dikirim_at, dibaca)
        VALUES ($user_id, $teman_id, '$pesan_esc', '$now', 0)
    ");

    if ($insert) {
        kirimJSON(['status' => 'success', 'chat_id' => mysqli_insert_id($conn)]);
    } else {
        kirimJSON(['status' => 'error', 'message' => 'Gagal kirim: ' . mysqli_error($conn)]);
    }
}

// ===== AMBIL SEMUA PESAN (load awal) =====
if ($aksi === 'ambil') {
    $teman_id = isset($_GET['teman_id']) ? (int)$_GET['teman_id'] : 0;

    if ($teman_id <= 0) kirimJSON(['pesan' => []]);

    // Tandai pesan masuk sebagai dibaca
    mysqli_query($conn, "UPDATE chat SET dibaca = 1 
                         WHERE sender_id = $teman_id AND receiver_id = $user_id AND dibaca = 0");

    $result = mysqli_query($conn, "
        SELECT c.chat_id, c.sender_id, c.receiver_id, c.pesan, c.dikirim_at, c.dibaca,
               u.username as sender_name
        FROM chat c
        JOIN users u ON c.sender_id = u.user_id
        WHERE (c.sender_id = $user_id AND c.receiver_id = $teman_id)
           OR (c.sender_id = $teman_id AND c.receiver_id = $user_id)
        ORDER BY c.dikirim_at ASC, c.chat_id ASC
        LIMIT 200
    ");

    $pesan = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $pesan[] = $row;
        }
    }

    kirimJSON(['pesan' => $pesan]);
}

// ===== CEK PESAN BARU (polling) =====
if ($aksi === 'baru') {
    $teman_id = isset($_GET['teman_id']) ? (int)$_GET['teman_id'] : 0;
    $last_id  = isset($_GET['last_id'])  ? (int)$_GET['last_id']  : 0;

    if ($teman_id <= 0) kirimJSON(['pesan' => []]);

    // Tandai pesan masuk sebagai dibaca
    mysqli_query($conn, "UPDATE chat SET dibaca = 1 
                         WHERE sender_id = $teman_id AND receiver_id = $user_id AND dibaca = 0");

    $result = mysqli_query($conn, "
        SELECT c.chat_id, c.sender_id, c.receiver_id, c.pesan, c.dikirim_at, c.dibaca,
               u.username as sender_name
        FROM chat c
        JOIN users u ON c.sender_id = u.user_id
        WHERE ((c.sender_id = $user_id AND c.receiver_id = $teman_id)
            OR (c.sender_id = $teman_id AND c.receiver_id = $user_id))
          AND c.chat_id > $last_id
        ORDER BY c.dikirim_at ASC, c.chat_id ASC
        LIMIT 50
    ");

    $pesan = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $pesan[] = $row;
        }
    }

    kirimJSON(['pesan' => $pesan]);
}

// ===== HAPUS PESAN =====
if ($aksi === 'hapus' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
    if ($chat_id <= 0) kirimJSON(['status' => 'error', 'message' => 'ID tidak valid.']);

    // Hanya pengirim yang boleh menghapus pesannya sendiri
    $cek = mysqli_query($conn, "SELECT chat_id FROM chat WHERE chat_id = $chat_id AND sender_id = $user_id");
    if (!$cek || mysqli_num_rows($cek) === 0) {
        kirimJSON(['status' => 'error', 'message' => 'Tidak diizinkan menghapus pesan ini.']);
    }

    $del = mysqli_query($conn, "DELETE FROM chat WHERE chat_id = $chat_id AND sender_id = $user_id");
    if ($del) {
        kirimJSON(['status' => 'success']);
    } else {
        kirimJSON(['status' => 'error', 'message' => 'Gagal hapus: ' . mysqli_error($conn)]);
    }
}

// Aksi tidak dikenal
kirimJSON(['status' => 'error', 'message' => 'Aksi tidak valid.']);