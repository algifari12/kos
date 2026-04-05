<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Pragma: no-cache");
require_once 'config/database.php';

// ── Proteksi: hanya admin ──
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_username = $_SESSION['username'] ?? 'Admin';

// ── Handle AJAX request ──
if (isset($_GET['aksi'])) {
    header('Content-Type: application/json');
    $aksi = $_GET['aksi'];

    // Hapus user
    if ($aksi === 'hapus_user' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        // Jangan hapus diri sendiri
        if ($id === (int)$_SESSION['user_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Tidak dapat menghapus akun sendiri.']);
            exit();
        }
        // Hapus data terkait dulu
        mysqli_query($conn, "DELETE FROM chat WHERE sender_id = $id OR receiver_id = $id");
        mysqli_query($conn, "DELETE FROM favorit WHERE user_id = $id");
        // Hapus booking dan review terkait
        $bids = mysqli_query($conn, "SELECT booking_id FROM booking WHERE user_id = $id");
        if ($bids) while ($b = mysqli_fetch_assoc($bids)) {
            mysqli_query($conn, "DELETE FROM review WHERE booking_id = {$b['booking_id']}");
        }
        mysqli_query($conn, "DELETE FROM booking WHERE user_id = $id");
        // Hapus kos milik pemilik ini
        $kos_ids = mysqli_query($conn, "SELECT kos_id FROM kos WHERE pemilik_id = $id");
        if ($kos_ids) while ($k = mysqli_fetch_assoc($kos_ids)) {
            $kid = $k['kos_id'];
            $book_ids = mysqli_query($conn, "SELECT booking_id FROM booking WHERE kos_id = $kid");
            if ($book_ids) while ($bk = mysqli_fetch_assoc($book_ids)) {
                mysqli_query($conn, "DELETE FROM review WHERE booking_id = {$bk['booking_id']}");
            }
            mysqli_query($conn, "DELETE FROM booking WHERE kos_id = $kid");
            mysqli_query($conn, "DELETE FROM favorit WHERE kos_id = $kid");
        }
        mysqli_query($conn, "DELETE FROM kos WHERE pemilik_id = $id");
        $res = mysqli_query($conn, "DELETE FROM users WHERE user_id = $id");
        echo json_encode(['status' => $res ? 'success' : 'error', 'message' => $res ? 'User berhasil dihapus.' : mysqli_error($conn)]);
        exit();
    }

    // Edit user
    if ($aksi === 'edit_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id       = (int)($_POST['user_id'] ?? 0);
        $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        if (!$username) { echo json_encode(['status' => 'error', 'message' => 'Username tidak boleh kosong.']); exit(); }
        // Cek duplikat username
        $cek = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$username' AND user_id != $id");
        if ($cek && mysqli_num_rows($cek) > 0) { echo json_encode(['status' => 'error', 'message' => 'Username sudah dipakai.']); exit(); }
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $res  = mysqli_query($conn, "UPDATE users SET username = '$username', password = '$hash' WHERE user_id = $id");
        } else {
            $res = mysqli_query($conn, "UPDATE users SET username = '$username' WHERE user_id = $id");
        }
        echo json_encode(['status' => $res ? 'success' : 'error', 'message' => $res ? 'User berhasil diupdate.' : mysqli_error($conn)]);
        exit();
    }

    // Lihat password (plain text — hanya jika disimpan plain, atau tampilkan hash)
    if ($aksi === 'lihat_password' && isset($_GET['id'])) {
        $id  = (int)$_GET['id'];
        $res = mysqli_query($conn, "SELECT username, password FROM users WHERE user_id = $id");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            echo json_encode(['status' => 'success', 'username' => $row['username'], 'password' => $row['password']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User tidak ditemukan.']);
        }
        exit();
    }

    // Hapus kos
    if ($aksi === 'hapus_kos' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $bids = mysqli_query($conn, "SELECT booking_id FROM booking WHERE kos_id = $id");
        if ($bids) while ($b = mysqli_fetch_assoc($bids)) {
            mysqli_query($conn, "DELETE FROM review WHERE booking_id = {$b['booking_id']}");
        }
        mysqli_query($conn, "DELETE FROM booking WHERE kos_id = $id");
        mysqli_query($conn, "DELETE FROM favorit WHERE kos_id = $id");
        $res = mysqli_query($conn, "DELETE FROM kos WHERE kos_id = $id");
        echo json_encode(['status' => $res ? 'success' : 'error', 'message' => $res ? 'Kos berhasil dihapus.' : mysqli_error($conn)]);
        exit();
    }

    exit();
}

// ── Ambil data statistik ──
$stat_users   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM users WHERE role != 'admin'"))['t'] ?? 0;
$stat_pemilik = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM users WHERE role = 'pemilik'"))['t'] ?? 0;
$stat_pencari = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM users WHERE role = 'pencari'"))['t'] ?? 0;
$stat_kos     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM kos"))['t'] ?? 0;
$stat_booking = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM booking"))['t'] ?? 0;
$stat_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM booking WHERE status = 'pending'"))['t'] ?? 0;

// ── Data Users ──
$users_result = mysqli_query($conn, "SELECT user_id, username, role, password FROM users WHERE role != 'admin' ORDER BY role, username");
$all_users = [];
if ($users_result) while ($r = mysqli_fetch_assoc($users_result)) $all_users[] = $r;

// ── Data Kos ──
$kos_result = mysqli_query($conn, "
    SELECT k.*, u.username as nama_pemilik,
           COUNT(DISTINCT b.booking_id) as total_booking
    FROM kos k
    LEFT JOIN users u ON k.pemilik_id = u.user_id
    LEFT JOIN booking b ON k.kos_id = b.kos_id
    GROUP BY k.kos_id
    ORDER BY k.kos_id DESC
");
$all_kos = [];
if ($kos_result) while ($r = mysqli_fetch_assoc($kos_result)) $all_kos[] = $r;

// ── Data Riwayat Booking ──
$booking_result = mysqli_query($conn, "
    SELECT b.*,
           up.username as nama_pencari,
           k.nama_kos, k.harga, k.jenis_kos,
           uo.username as nama_pemilik
    FROM booking b
    JOIN users up ON b.user_id = up.user_id
    JOIN kos k ON b.kos_id = k.kos_id
    JOIN users uo ON k.pemilik_id = uo.user_id
    ORDER BY b.tanggal_booking DESC
");
$all_booking = [];
if ($booking_result) while ($r = mysqli_fetch_assoc($booking_result)) $all_booking[] = $r;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — Kos'ong?</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --yellow: #FFD700;
            --black:  #111111;
            --white:  #ffffff;
            --gray:   #f4f4f4;
            --border: #e0e0e0;
            --red:    #e53e3e;
            --green:  #2d8f4e;
            --blue:   #00B4D8;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gray);
            color: var(--black);
            min-height: 100vh;
        }

        /* ── Topbar ── */
        .topbar {
            background: var(--yellow);
            border-bottom: 3px solid var(--black);
            padding: 0 32px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-logo {
            font-size: 22px;
            font-weight: 800;
            color: var(--black);
            letter-spacing: -0.5px;
        }

        .topbar-badge {
            background: var(--black);
            color: var(--yellow);
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-user {
            font-size: 13px;
            font-weight: 600;
            color: var(--black);
        }

        .btn-logout {
            background: var(--black);
            color: var(--yellow);
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-logout:hover { background: #333; transform: scale(1.04); }

        /* ── Layout ── */
        .admin-layout {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 220px;
            background: var(--black);
            flex-shrink: 0;
            padding: 24px 0;
            position: sticky;
            top: 64px;
            height: calc(100vh - 64px);
            overflow-y: auto;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 4px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            color: var(--yellow);
            background: rgba(255,215,0,0.08);
            border-left-color: var(--yellow);
        }

        .sidebar-icon { font-size: 18px; width: 22px; text-align: center; }

        .sidebar-section {
            font-size: 10px;
            font-weight: 700;
            color: rgba(255,255,255,0.25);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 16px 24px 6px;
        }

        /* ── Main Content ── */
        .main-content {
            flex: 1;
            padding: 28px;
            overflow-x: auto;
        }

        /* ── Section panel ── */
        .section-panel {
            display: none;
        }
        .section-panel.active { display: block; }

        /* ── Stat cards ── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 14px;
            padding: 20px;
            border: 2px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }

        .stat-card-num {
            font-size: 32px;
            font-weight: 800;
            color: var(--black);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-card-label {
            font-size: 12px;
            color: #888;
            font-weight: 600;
        }

        .stat-card-icon {
            font-size: 28px;
            margin-bottom: 10px;
        }

        /* ── Panel box ── */
        .panel-box {
            background: var(--white);
            border-radius: 16px;
            border: 2px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 2px solid var(--border);
            background: #fafafa;
            flex-wrap: wrap;
            gap: 10px;
        }

        .panel-head h2 {
            font-size: 16px;
            font-weight: 800;
            color: var(--black);
        }

        .panel-search {
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 7px 16px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            outline: none;
            width: 220px;
            transition: border 0.2s;
        }
        .panel-search:focus { border-color: var(--yellow); }

        /* ── Table ── */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .admin-table th {
            background: var(--black);
            color: var(--yellow);
            padding: 12px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .admin-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: #fffbea; }

        /* ── Badge role/status ── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .badge-pemilik  { background: #E3F2FD; color: #1565C0; }
        .badge-pencari  { background: #F3E5F5; color: #6A1B9A; }
        .badge-pending  { background: #FFF3CD; color: #856404; border: 1px solid #FFD700; }
        .badge-diterima { background: #D1FAE5; color: #065F46; border: 1px solid #34D399; }
        .badge-ditolak  { background: #FEE2E2; color: #991B1B; border: 1px solid #F87171; }

        /* ── Action buttons ── */
        .btn-tbl {
            border: none;
            padding: 5px 14px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-edit   { background: var(--yellow); color: #000; border: 1.5px solid #000; }
        .btn-edit:hover { background: #51CF66; }
        .btn-hapus  { background: #FEE2E2; color: var(--red); border: 1.5px solid var(--red); }
        .btn-hapus:hover { background: var(--red); color: #fff; }
        .btn-lihat  { background: #E3F2FD; color: #1565C0; border: 1.5px solid #1565C0; }
        .btn-lihat:hover { background: #1565C0; color: #fff; }

        .tbl-actions { display: flex; gap: 6px; flex-wrap: wrap; }

        /* ── Password display ── */
        .password-display {
            font-family: monospace;
            font-size: 12px;
            background: #f4f4f4;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
        }

        /* ── Modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.aktif { display: flex; }

        .modal-box {
            background: var(--white);
            border-radius: 20px;
            border: 3px solid var(--yellow);
            padding: 32px 28px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.25);
            animation: modalIn 0.28s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes modalIn {
            from { transform: scale(0.85); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }

        .modal-box h3 {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--black);
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #333;
        }
        .form-group input {
            width: 100%;
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            transition: border 0.2s;
        }
        .form-group input:focus { border-color: var(--yellow); }

        .form-hint { font-size: 11px; color: #999; margin-top: 4px; }

        .modal-btns {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 22px;
        }

        .btn-batal {
            background: #f0f0f0;
            border: none;
            padding: 10px 24px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }
        .btn-batal:hover { background: #e0e0e0; }

        .btn-simpan {
            background: var(--yellow);
            border: 2px solid var(--black);
            padding: 10px 24px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
        }
        .btn-simpan:hover { background: #51CF66; }

        /* ── Toast ── */
        .kos-toast {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: #111;
            color: #fff;
            padding: 12px 28px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            z-index: 99999;
            opacity: 0;
            transition: all 0.35s cubic-bezier(.34,1.56,.64,1);
            white-space: nowrap;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            pointer-events: none;
        }
        .kos-toast.tampil { opacity: 1; transform: translateX(-50%) translateY(0); }

        /* ── Konfirmasi modal ── */
        .konfirm-box {
            max-width: 380px;
            text-align: center;
        }
        .konfirm-box .konfirm-icon { font-size: 52px; margin-bottom: 12px; }
        .konfirm-box h3 { font-size: 18px; font-weight: 800; margin-bottom: 8px; }
        .konfirm-box p { font-size: 14px; color: #666; margin-bottom: 22px; }

        /* ── Password modal ── */
        .pw-display {
            background: #1a1a2e;
            color: #FFD700;
            font-family: monospace;
            font-size: 13px;
            padding: 16px;
            border-radius: 10px;
            word-break: break-all;
            margin: 16px 0;
            border: 2px solid #FFD700;
            text-align: left;
            line-height: 1.6;
        }
        .pw-note {
            font-size: 12px;
            color: #e53e3e;
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* ── Foto kos kecil ── */
        .kos-thumb {
            width: 52px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid var(--border);
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { padding: 16px; }
            .stat-row { grid-template-columns: repeat(2, 1fr); }
            .admin-table { font-size: 12px; }
            .admin-table th, .admin-table td { padding: 8px 10px; }
        }

        /* ── Nomor baris ── */
        .row-num { color: #bbb; font-size: 12px; font-weight: 600; }

        .empty-row td {
            text-align: center;
            padding: 40px;
            color: #aaa;
            font-size: 14px;
        }
    </style>
</head>
<body>

<!-- ── TOPBAR ── -->
<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo">Kos'ong?</div>
        <div class="topbar-badge">Admin Panel</div>
    </div>
    <div class="topbar-right">
        <span class="topbar-user">👤 <?= htmlspecialchars($admin_username) ?></span>
        <a href="logout.php" class="btn-logout">🚪 Keluar</a>
    </div>
</header>

<div class="admin-layout">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar">
        <div class="sidebar-section">Menu</div>
        <ul class="sidebar-menu">
            <li>
                <a href="#" class="active" onclick="bukaPanel('overview', this)">
                    <span class="sidebar-icon">📊</span> Overview
                </a>
            </li>
        </ul>
        <div class="sidebar-section">Manajemen</div>
        <ul class="sidebar-menu">
            <li>
                <a href="#" onclick="bukaPanel('users', this)">
                    <span class="sidebar-icon">👥</span> Pengguna
                </a>
            </li>
            <li>
                <a href="#" onclick="bukaPanel('kos', this)">
                    <span class="sidebar-icon">🏠</span> Data Kos
                </a>
            </li>
            <li>
                <a href="#" onclick="bukaPanel('booking', this)">
                    <span class="sidebar-icon">📋</span> Riwayat Booking
                </a>
            </li>
        </ul>
    </aside>

    <!-- ── MAIN CONTENT ── -->
    <main class="main-content">

        <!-- OVERVIEW -->
        <div class="section-panel active" id="panel-overview">
            <h1 style="font-size:22px;font-weight:800;margin-bottom:20px;">📊 Overview Sistem</h1>

            <div class="stat-row">
                <div class="stat-card">
                    <div class="stat-card-icon">👥</div>
                    <div class="stat-card-num"><?= $stat_users ?></div>
                    <div class="stat-card-label">Total Pengguna</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">🏠</div>
                    <div class="stat-card-num"><?= $stat_pemilik ?></div>
                    <div class="stat-card-label">Pemilik Kos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">🔍</div>
                    <div class="stat-card-num"><?= $stat_pencari ?></div>
                    <div class="stat-card-label">Pencari Kos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">🏘️</div>
                    <div class="stat-card-num"><?= $stat_kos ?></div>
                    <div class="stat-card-label">Total Kos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">📋</div>
                    <div class="stat-card-num"><?= $stat_booking ?></div>
                    <div class="stat-card-label">Total Booking</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">⏳</div>
                    <div class="stat-card-num"><?= $stat_pending ?></div>
                    <div class="stat-card-label">Booking Pending</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap;">
                <div class="panel-box">
                    <div class="panel-head"><h2>👥 Pengguna Terbaru</h2></div>
                    <table class="admin-table">
                        <thead><tr><th>#</th><th>Username</th><th>Role</th></tr></thead>
                        <tbody>
                        <?php $cnt=0; foreach($all_users as $u): if($cnt>=5) break; $cnt++; ?>
                            <tr>
                                <td class="row-num"><?= $u['user_id'] ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="panel-box">
                    <div class="panel-head"><h2>📋 Booking Terbaru</h2></div>
                    <table class="admin-table">
                        <thead><tr><th>Pencari</th><th>Kos</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php $cnt=0; foreach($all_booking as $b): if($cnt>=5) break; $cnt++; ?>
                            <tr>
                                <td><?= htmlspecialchars($b['nama_pencari']) ?></td>
                                <td><?= htmlspecialchars($b['nama_kos']) ?></td>
                                <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- USERS -->
        <div class="section-panel" id="panel-users">
            <h1 style="font-size:22px;font-weight:800;margin-bottom:20px;">👥 Manajemen Pengguna</h1>
            <div class="panel-box">
                <div class="panel-head">
                    <h2>Semua Pengguna (<?= count($all_users) ?>)</h2>
                    <input type="text" class="panel-search" placeholder="🔍 Cari username..." oninput="filterTabel(this, 'tbl-users')">
                </div>
                <div style="overflow-x:auto;">
                <table class="admin-table" id="tbl-users">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Password (Hash)</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($all_users) === 0): ?>
                        <tr class="empty-row"><td colspan="5">Belum ada pengguna</td></tr>
                    <?php endif; ?>
                    <?php foreach ($all_users as $u): ?>
                        <tr>
                            <td class="row-num"><?= $u['user_id'] ?></td>
                            <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                            <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td>
                                <span class="password-display" title="Klik 'Lihat' untuk detail">
                                    <?= substr($u['password'], 0, 20) ?>...
                                </span>
                            </td>
                            <td>
                                <div class="tbl-actions">
                                    <button class="btn-tbl btn-lihat"
                                        onclick="lihatPassword(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                        👁 Lihat PW
                                    </button>
                                    <button class="btn-tbl btn-edit"
                                        onclick="bukaEditUser(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn-tbl btn-hapus"
                                        onclick="konfirmasiHapusUser(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                        🗑 Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- KOS -->
        <div class="section-panel" id="panel-kos">
            <h1 style="font-size:22px;font-weight:800;margin-bottom:20px;">🏠 Manajemen Kos</h1>
            <div class="panel-box">
                <div class="panel-head">
                    <h2>Semua Kos (<?= count($all_kos) ?>)</h2>
                    <input type="text" class="panel-search" placeholder="🔍 Cari nama kos..." oninput="filterTabel(this, 'tbl-kos')">
                </div>
                <div style="overflow-x:auto;">
                <table class="admin-table" id="tbl-kos">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Foto</th>
                            <th>Nama Kos</th>
                            <th>Pemilik</th>
                            <th>Jenis</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Booking</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($all_kos) === 0): ?>
                        <tr class="empty-row"><td colspan="9">Belum ada kos</td></tr>
                    <?php endif; ?>
                    <?php foreach ($all_kos as $k): ?>
                        <?php
                            $foto_arr = explode(',', $k['foto']);
                            $foto_url = !empty(trim($foto_arr[0])) && file_exists('uploads/'.trim($foto_arr[0]))
                                ? 'uploads/'.trim($foto_arr[0])
                                : 'https://via.placeholder.com/52x40?text=Kos';
                        ?>
                        <tr>
                            <td class="row-num"><?= $k['kos_id'] ?></td>
                            <td><img src="<?= $foto_url ?>" class="kos-thumb" alt=""></td>
                            <td><strong><?= htmlspecialchars($k['nama_kos']) ?></strong></td>
                            <td><?= htmlspecialchars($k['nama_pemilik']) ?></td>
                            <td><span class="badge badge-pencari"><?= ucfirst($k['jenis_kos']) ?></span></td>
                            <td style="color:#00B4D8;font-weight:700;">Rp <?= number_format($k['harga'], 0, ',', '.') ?></td>
                            <td><?= $k['stok_kamar'] ?> kamar</td>
                            <td><?= $k['total_booking'] ?> booking</td>
                            <td>
                                <button class="btn-tbl btn-hapus"
                                    onclick="konfirmasiHapusKos(<?= $k['kos_id'] ?>, '<?= htmlspecialchars($k['nama_kos'], ENT_QUOTES) ?>')">
                                    🗑 Hapus
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- BOOKING -->
        <div class="section-panel" id="panel-booking">
            <h1 style="font-size:22px;font-weight:800;margin-bottom:20px;">📋 Riwayat Booking</h1>
            <div class="panel-box">
                <div class="panel-head">
                    <h2>Semua Riwayat (<?= count($all_booking) ?>)</h2>
                    <input type="text" class="panel-search" placeholder="🔍 Cari nama/kos..." oninput="filterTabel(this, 'tbl-booking')">
                </div>
                <div style="overflow-x:auto;">
                <table class="admin-table" id="tbl-booking">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Pencari</th>
                            <th>Nama Kos</th>
                            <th>Pemilik Kos</th>
                            <th>Jenis</th>
                            <th>Harga</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($all_booking) === 0): ?>
                        <tr class="empty-row"><td colspan="8">Belum ada riwayat booking</td></tr>
                    <?php endif; ?>
                    <?php foreach ($all_booking as $b): ?>
                        <tr>
                            <td class="row-num"><?= $b['booking_id'] ?></td>
                            <td><strong><?= htmlspecialchars($b['nama_pencari']) ?></strong></td>
                            <td><?= htmlspecialchars($b['nama_kos']) ?></td>
                            <td><?= htmlspecialchars($b['nama_pemilik']) ?></td>
                            <td><?= ucfirst($b['jenis_kos']) ?></td>
                            <td style="color:#00B4D8;font-weight:700;">Rp <?= number_format($b['harga'], 0, ',', '.') ?></td>
                            <td style="font-size:12px;color:#888;"><?= date('d M Y', strtotime($b['tanggal_booking'])) ?></td>
                            <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- ── Modal Edit User ── -->
<div class="modal-overlay" id="modalEditUser">
    <div class="modal-box">
        <h3>✏️ Edit Pengguna</h3>
        <input type="hidden" id="editUserId">
        <div class="form-group">
            <label>Username</label>
            <input type="text" id="editUsername" placeholder="Username baru">
        </div>
        <div class="form-group">
            <label>Password Baru</label>
            <input type="password" id="editPassword" placeholder="Kosongkan jika tidak diubah">
            <div class="form-hint">* Kosongkan jika tidak ingin mengubah password</div>
        </div>
        <div class="modal-btns">
            <button class="btn-batal" onclick="tutupModal('modalEditUser')">Batal</button>
            <button class="btn-simpan" onclick="simpanEditUser()">💾 Simpan</button>
        </div>
    </div>
</div>

<!-- ── Modal Lihat Password ── -->
<div class="modal-overlay" id="modalLihatPw">
    <div class="modal-box konfirm-box" style="text-align:left;max-width:480px;">
        <h3>👁 Detail Password</h3>
        <p style="font-size:13px;color:#666;margin-bottom:8px;">Username: <strong id="pwUsername">-</strong></p>
        <p class="pw-note">⚠️ Password tersimpan dalam bentuk hash (terenkripsi). Tidak dapat dikembalikan ke teks asli.</p>
        <div class="pw-display" id="pwHash">-</div>
        <p style="font-size:12px;color:#888;margin-bottom:16px;">
            Jika user lupa password, gunakan tombol <strong>Edit</strong> untuk mengatur password baru.
        </p>
        <div class="modal-btns">
            <button class="btn-simpan" onclick="tutupModal('modalLihatPw')">Tutup</button>
        </div>
    </div>
</div>

<!-- ── Modal Konfirmasi Hapus ── -->
<div class="modal-overlay" id="modalKonfirmHapus">
    <div class="modal-box konfirm-box">
        <div class="konfirm-icon" id="konfirmIcon">🗑️</div>
        <h3 id="konfirmJudul">Hapus Data?</h3>
        <p id="konfirmPesan">Tindakan ini tidak dapat dibatalkan.</p>
        <div class="modal-btns" style="justify-content:center;">
            <button class="btn-batal" onclick="tutupModal('modalKonfirmHapus')">Batal</button>
            <button class="btn-hapus btn-tbl" id="btnKonfirmYa" style="padding:10px 24px;font-size:13px;">🗑 Ya, Hapus</button>
        </div>
    </div>
</div>

<!-- ── Toast ── -->
<div class="kos-toast" id="adminToast"></div>

<script>
    // ── Navigasi panel ──
    function bukaPanel(id, el) {
        document.querySelectorAll('.section-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('panel-' + id).classList.add('active');
        document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
        if (el) el.classList.add('active');
    }

    // ── Toast ──
    let toastTimer;
    function toast(pesan, tipe) {
        const el = document.getElementById('adminToast');
        el.textContent = (tipe==='sukses'?'✅ ':tipe==='gagal'?'❌ ':'ℹ️ ') + pesan;
        el.style.background = tipe==='sukses'?'#2d8f4e':tipe==='gagal'?'#c0392b':'#111';
        el.classList.add('tampil');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('tampil'), 3000);
    }

    // ── Modal utils ──
    function bukaModal(id) {
        document.getElementById(id).classList.add('aktif');
        document.body.style.overflow = 'hidden';
    }
    function tutupModal(id) {
        document.getElementById(id).classList.remove('aktif');
        document.body.style.overflow = '';
    }
    // Tutup saat klik overlay
    document.querySelectorAll('.modal-overlay').forEach(ov => {
        ov.addEventListener('click', function(e) {
            if (e.target === this) tutupModal(this.id);
        });
    });

    // ── Filter tabel ──
    function filterTabel(input, tblId) {
        const q = input.value.toLowerCase();
        document.querySelectorAll('#' + tblId + ' tbody tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    // ── Lihat password ──
    function lihatPassword(userId, username) {
        fetch('?aksi=lihat_password&id=' + userId)
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    document.getElementById('pwUsername').textContent = d.username;
                    document.getElementById('pwHash').textContent = d.password;
                    bukaModal('modalLihatPw');
                } else {
                    toast(d.message, 'gagal');
                }
            });
    }

    // ── Edit user ──
    function bukaEditUser(userId, username) {
        document.getElementById('editUserId').value = userId;
        document.getElementById('editUsername').value = username;
        document.getElementById('editPassword').value = '';
        bukaModal('modalEditUser');
    }

    function simpanEditUser() {
        const id       = document.getElementById('editUserId').value;
        const username = document.getElementById('editUsername').value.trim();
        const password = document.getElementById('editPassword').value.trim();
        if (!username) { toast('Username tidak boleh kosong.', 'gagal'); return; }

        const fd = new FormData();
        fd.append('user_id', id);
        fd.append('username', username);
        fd.append('password', password);

        fetch('?aksi=edit_user', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    tutupModal('modalEditUser');
                    toast(d.message, 'sukses');
                    // Update nama di tabel tanpa reload
                    setTimeout(() => location.reload(), 1200);
                } else {
                    toast(d.message, 'gagal');
                }
            });
    }

    // ── Hapus user ──
    let hapusFn = null;
    function konfirmasiHapusUser(userId, username) {
        document.getElementById('konfirmJudul').textContent = 'Hapus Pengguna?';
        document.getElementById('konfirmPesan').textContent = 'Akun "' + username + '" beserta semua datanya akan dihapus permanen.';
        document.getElementById('konfirmIcon').textContent = '👤🗑️';
        hapusFn = function() {
            fetch('?aksi=hapus_user&id=' + userId)
                .then(r => r.json())
                .then(d => {
                    tutupModal('modalKonfirmHapus');
                    toast(d.message, d.status === 'success' ? 'sukses' : 'gagal');
                    if (d.status === 'success') setTimeout(() => location.reload(), 1200);
                });
        };
        document.getElementById('btnKonfirmYa').onclick = hapusFn;
        bukaModal('modalKonfirmHapus');
    }

    // ── Hapus kos ──
    function konfirmasiHapusKos(kosId, namaKos) {
        document.getElementById('konfirmJudul').textContent = 'Hapus Kos?';
        document.getElementById('konfirmPesan').textContent = 'Kos "' + namaKos + '" beserta semua booking terkait akan dihapus permanen.';
        document.getElementById('konfirmIcon').textContent = '🏠🗑️';
        document.getElementById('btnKonfirmYa').onclick = function() {
            fetch('?aksi=hapus_kos&id=' + kosId)
                .then(r => r.json())
                .then(d => {
                    tutupModal('modalKonfirmHapus');
                    toast(d.message, d.status === 'success' ? 'sukses' : 'gagal');
                    if (d.status === 'success') setTimeout(() => location.reload(), 1200);
                });
        };
        bukaModal('modalKonfirmHapus');
    }
</script>

</body>
</html>