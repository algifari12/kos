<?php
require_once 'config/session.php';
requireLogin();

require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$role      = $_SESSION['role'];
$username  = $_SESSION['username'] ?? 'User';

if ($role === 'pencari') {
    // Pencari: tampil semua pemilik yang pernah dihubungi via chat
    // ATAU pemilik yang kosnya pernah dibooking (untuk kemudahan akses)
    $q_kontak = "
        SELECT 
            u.user_id, u.username,
            (SELECT k2.nama_kos FROM kos k2 
             WHERE k2.pemilik_id = u.user_id 
             ORDER BY k2.kos_id DESC LIMIT 1) as nama_kos,
            (SELECT COUNT(*) FROM chat c 
             WHERE c.sender_id = u.user_id AND c.receiver_id = $user_id AND c.dibaca = 0) as unread,
            (SELECT MAX(c2.dikirim_at) FROM chat c2
             WHERE (c2.sender_id = u.user_id AND c2.receiver_id = $user_id)
                OR (c2.sender_id = $user_id AND c2.receiver_id = u.user_id)) as last_chat
        FROM users u
        WHERE u.role = 'pemilik'
          AND u.user_id IN (
            -- Pemilik yang pernah dihubungi (ada di tabel chat)
            SELECT DISTINCT 
                CASE WHEN c.sender_id = $user_id THEN c.receiver_id ELSE c.sender_id END
            FROM chat c
            WHERE c.sender_id = $user_id OR c.receiver_id = $user_id
          )
        ORDER BY last_chat DESC, u.username ASC
    ";
} else {
    // Pemilik: tampil semua pencari yang pernah menghubungi via chat
    $q_kontak = "
        SELECT 
            u.user_id, u.username,
            NULL as nama_kos,
            (SELECT COUNT(*) FROM chat c 
             WHERE c.sender_id = u.user_id AND c.receiver_id = $user_id AND c.dibaca = 0) as unread,
            (SELECT MAX(c2.dikirim_at) FROM chat c2
             WHERE (c2.sender_id = u.user_id AND c2.receiver_id = $user_id)
                OR (c2.sender_id = $user_id AND c2.receiver_id = u.user_id)) as last_chat
        FROM users u
        WHERE u.user_id IN (
            SELECT DISTINCT
                CASE WHEN c.sender_id = $user_id THEN c.receiver_id ELSE c.sender_id END
            FROM chat c
            WHERE c.sender_id = $user_id OR c.receiver_id = $user_id
        )
        ORDER BY last_chat DESC, u.username ASC
    ";
}

$result_kontak = mysqli_query($conn, $q_kontak);
$daftar_kontak = [];
if ($result_kontak) {
    while ($row = mysqli_fetch_assoc($result_kontak)) {
        $daftar_kontak[] = $row;
    }
}

$teman_id   = isset($_GET['dengan']) ? (int)$_GET['dengan'] : 0;
$teman_info = null;

if ($teman_id > 0) {
    $r = mysqli_query($conn, "SELECT user_id, username FROM users WHERE user_id = $teman_id");
    if ($r) $teman_info = mysqli_fetch_assoc($r);
    mysqli_query($conn, "UPDATE chat SET dibaca = 1 WHERE sender_id = $teman_id AND receiver_id = $user_id AND dibaca = 0");
}

if ($role === 'pencari') {
    $r_badge = mysqli_query($conn, "SELECT COUNT(*) as total FROM booking WHERE user_id = $user_id AND status IN ('diterima','ditolak')");
} else {
    $r_badge = mysqli_query($conn, "SELECT COUNT(*) as total FROM booking b JOIN kos k ON b.kos_id = k.kos_id WHERE k.pemilik_id = $user_id AND b.status = 'pending'");
}
$total_notif = $r_badge ? mysqli_fetch_assoc($r_badge)['total'] : 0;

$r_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM chat WHERE receiver_id = $user_id AND dibaca = 0");
$total_unread = $r_unread ? mysqli_fetch_assoc($r_unread)['total'] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Kos'ong?</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        * { box-sizing: border-box; }

        /* Kunci utama: html & body harus punya tinggi penuh */
        html, body { height: 100%; }

        .chat-wrapper {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
            /* Tinggi tetap agar chat tidak ikut scroll halaman */
            height: calc(100vh - 120px);
            min-height: 500px;
        }

        .chat-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            /* KUNCI: tinggi harus 100% dari wrapper */
            height: 100%;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            border: 3px solid #FFD700;
        }

        /* ====== SIDEBAR ====== */
        .chat-sidebar {
            border-right: 2px solid #f0f0f0;
            display: flex;
            flex-direction: column;
            background: #fafafa;
            /* KUNCI: overflow hidden agar sidebar tidak meluber */
            overflow: hidden;
        }

        .sidebar-header {
            background: #FFD700;
            padding: 20px 18px;
            border-bottom: 2px solid #e6c200;
            flex-shrink: 0;
        }

        .sidebar-header h2 { font-size: 18px; font-weight: 800; color: #000; margin: 0; }
        .sidebar-header p  { font-size: 12px; color: #555; margin: 3px 0 0; }

        .kontak-list {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .kontak-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .kontak-item:hover  { background: #fff8e1; }
        .kontak-item.active { background: #FFF3CD; border-left: 4px solid #FFD700; }

        .kontak-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: #FFD700;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700;
            color: #333;
            border: 2px solid #e6c200;
            flex-shrink: 0;
        }

        .kontak-detail { flex: 1; min-width: 0; }
        .kontak-nama {
            font-size: 14px; font-weight: 700; color: #333;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .kontak-kos {
            font-size: 11px; color: #888;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .kontak-badge {
            background: #FF4444; color: #fff;
            border-radius: 50%; width: 20px; height: 20px;
            font-size: 11px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .empty-kontak { padding: 40px 20px; text-align: center; color: #aaa; font-size: 13px; }
        .empty-kontak .icon { font-size: 40px; margin-bottom: 10px; }

        /* ====== AREA CHAT ====== */
        .chat-area {
            display: flex;
            flex-direction: column;
            background: #f7f7f7;
            /* KUNCI: overflow hidden agar flex child bisa scroll */
            overflow: hidden;
            height: 100%;
        }

        .chat-header {
            background: #fff;
            padding: 16px 22px;
            border-bottom: 2px solid #f0f0f0;
            display: flex; align-items: center; gap: 14px;
            flex-shrink: 0; /* jangan menyusut */
        }

        .chat-header-avatar {
            width: 44px; height: 44px;
            border-radius: 50%; background: #FFD700;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700;
            border: 2px solid #e6c200; flex-shrink: 0;
        }
        .chat-header-info h3 { font-size: 16px; font-weight: 700; color: #333; margin: 0; }
        .chat-header-info p  { font-size: 12px; color: #888; margin: 0; }
        .online-dot {
            width: 9px; height: 9px; background: #34D399;
            border-radius: 50%; display: inline-block; margin-right: 5px;
        }

        .chat-placeholder {
            flex: 1;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            color: #bbb; text-align: center; padding: 40px;
        }
        .chat-placeholder .icon { font-size: 70px; margin-bottom: 15px; opacity: 0.4; }
        .chat-placeholder h3   { font-size: 18px; font-weight: 700; color: #ccc; margin-bottom: 8px; }
        .chat-placeholder p    { font-size: 13px; }

        /* ====== MESSAGES — INI KUNCINYA ====== */
        .chat-messages {
            flex: 1;          /* ambil semua sisa tinggi */
            min-height: 0;    /* WAJIB agar flex child bisa scroll di dalam flex parent */
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            scroll-behavior: smooth;
        }

        /* ====== BUBBLE PESAN ====== */
        .msg-row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            /* JANGAN pakai width:100% atau flex membuat teks terpecah */
        }

        .msg-row.sent     { flex-direction: row-reverse; }
        .msg-row.received { flex-direction: row; }

        .msg-avatar {
            width: 30px; height: 30px;
            border-radius: 50%; background: #FFD700;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700;
            flex-shrink: 0; /* avatar tidak menyusut */
        }

        /* Wrapper untuk bubble + waktu + tombol hapus */
        .msg-wrapper {
            /* KUNCI: max-width di sini, BUKAN di bubble */
            max-width: 60%;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .msg-row.sent     .msg-wrapper { align-items: flex-end; }
        .msg-row.received .msg-wrapper { align-items: flex-start; }

        .msg-bubble {
            /* Tidak perlu max-width di sini karena sudah di wrapper */
            padding: 10px 14px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.6;
            font-family: 'Poppins', sans-serif;

            /* ====== FIX TEKS TERPECAH ====== */
            /* white-space: pre-wrap  → respek newline tapi wrap normal */
            /* word-break: break-word → pecah kata panjang kalau perlu */
            /* overflow-wrap          → fallback */
            white-space: pre-wrap;
            word-break: break-word;
            overflow-wrap: break-word;

            /* Lebar mengikuti konten, tidak dipaksa penuh */
            display: inline-block;
        }

        .msg-row.received .msg-bubble {
            background: #fff;
            color: #333;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .msg-row.sent .msg-bubble {
            background: #FFD700;
            color: #000;
            border-bottom-right-radius: 4px;
            box-shadow: 0 2px 8px rgba(255,215,0,0.3);
        }

        .msg-time {
            font-size: 10px; color: #aaa;
        }

        /* Tombol hapus — hanya muncul saat hover */
        .msg-actions { display: none; }
        .msg-row:hover .msg-actions { display: flex; gap: 5px; }

        .btn-hapus {
            background: none;
            border: 1px solid #f0a0a0;
            border-radius: 10px;
            padding: 2px 10px;
            font-size: 11px;
            color: #e57373;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
        }
        .btn-hapus:hover { background: #FEE2E2; border-color: #F87171; color: #c62828; }

        /* Tanggal pemisah */
        .date-divider { text-align: center; margin: 8px 0; }
        .date-divider span {
            background: rgba(0,0,0,0.07);
            color: #888; font-size: 11px; font-weight: 600;
            padding: 4px 14px; border-radius: 20px;
        }

        /* Input */
        .chat-input-area {
            background: #fff;
            padding: 12px 18px;
            border-top: 2px solid #f0f0f0;
            display: flex; gap: 10px; align-items: flex-end;
            flex-shrink: 0;
        }

        .chat-input-area textarea {
            flex: 1;
            border: 2px solid #e0e0e0;
            border-radius: 22px;
            padding: 11px 18px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            resize: none;
            outline: none;
            transition: border 0.3s;
            max-height: 120px;
            min-height: 46px;
            line-height: 1.5;
        }
        .chat-input-area textarea:focus { border-color: #FFD700; }

        .btn-kirim {
            width: 46px; height: 46px;
            background: #FFD700;
            border: 2px solid #000;
            border-radius: 50%;
            font-size: 20px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s;
        }
        .btn-kirim:hover    { background: #51CF66; transform: scale(1.1); }
        .btn-kirim:disabled { background: #eee; border-color: #ccc; cursor: not-allowed; transform: none; }

        @media (max-width: 768px) {
            .chat-wrapper    { height: calc(100vh - 90px); margin: 10px auto; }
            .chat-container  { grid-template-columns: 1fr; }
            .chat-sidebar    { display: <?= $teman_id > 0 ? 'none' : 'flex' ?>; height: 100%; }
            .chat-area       { display: <?= $teman_id > 0 ? 'flex' : 'none' ?>; }
            .msg-wrapper     { max-width: 75%; }
        }
    
/* ========================================
   CUSTOM MODAL SYSTEM — pengganti alert/confirm bawaan browser
   ======================================== */
.kos-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 99999;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
    opacity: 0; pointer-events: none;
    transition: opacity 0.2s ease;
}
.kos-modal-overlay.aktif {
    opacity: 1; pointer-events: all;
}
.kos-modal-box {
    background: #fff;
    border-radius: 18px;
    border: 2.5px solid #FFD700;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    padding: 32px 28px 24px;
    max-width: 360px; width: 100%;
    text-align: center;
    transform: scale(0.88) translateY(16px);
    transition: transform 0.25s cubic-bezier(.34,1.56,.64,1);
    font-family: 'Poppins', sans-serif;
}
.kos-modal-overlay.aktif .kos-modal-box {
    transform: scale(1) translateY(0);
}
.kos-modal-icon { font-size: 52px; margin-bottom: 12px; line-height: 1; }
.kos-modal-judul {
    font-size: 18px; font-weight: 800; color: #222;
    margin-bottom: 8px;
}
.kos-modal-pesan {
    font-size: 14px; color: #666; line-height: 1.6;
    margin-bottom: 24px;
}
.kos-modal-btns { display: flex; gap: 10px; justify-content: center; }
.kos-modal-btn {
    padding: 10px 24px; border-radius: 25px;
    font-size: 14px; font-weight: 700;
    cursor: pointer; border: 2px solid #000;
    font-family: 'Poppins', sans-serif;
    transition: all 0.2s ease;
    min-width: 100px;
}
.kos-modal-btn:hover { transform: scale(1.04); }
.kos-modal-btn.btn-ya    { background: #FFD700; color: #000; }
.kos-modal-btn.btn-ya:hover { background: #e6c200; }
.kos-modal-btn.btn-ya.merah { background: #FF4444; color: #fff; border-color: #FF4444; }
.kos-modal-btn.btn-ya.merah:hover { background: #e03333; }
.kos-modal-btn.btn-ya.hijau { background: #51CF66; color: #fff; border-color: #51CF66; }
.kos-modal-btn.btn-tidak  { background: #f0f0f0; color: #555; border-color: #ddd; }
.kos-modal-btn.btn-tidak:hover { background: #e0e0e0; }

/* Toast notifikasi */
.kos-toast {
    position: fixed; bottom: 32px; left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: #222; color: #fff;
    padding: 11px 22px; border-radius: 30px;
    font-size: 13px; font-weight: 600;
    font-family: 'Poppins', sans-serif;
    z-index: 99998; opacity: 0; pointer-events: none;
    transition: all 0.3s ease; white-space: nowrap;
    box-shadow: 0 6px 20px rgba(0,0,0,0.18);
    max-width: calc(100vw - 40px);
}
.kos-toast.tampil {
    opacity: 1; transform: translateX(-50%) translateY(0);
}

</style>


    <!-- ── Navbar mobile fix: paksa satu baris horizontal ── -->
    <style>
    @media (max-width: 768px) {
        .navbar-container {
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            gap: 0 !important;
            padding: 8px 12px !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .navbar-left,
        .navbar-left-owner {
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            gap: 6px !important;
            align-items: center !important;
            flex: 1 !important;
            min-width: 0 !important;
            overflow: hidden !important;
        }
        .nav-icon-group {
            flex-wrap: nowrap !important;
            gap: 8px !important;
        }
        .icon-img {
            width: 22px !important;
            height: 22px !important;
        }
        .navbar-right {
            flex-shrink: 0 !important;
            margin-left: 6px !important;
        }
        .logo img {
            height: 44px !important;
            transform: scale(1) !important;
            transform-origin: center !important;
            margin-left: 0 !important;
        }
        .help-center {
            font-size: 11px !important;
            white-space: nowrap !important;
        }
        .btn-masuk-nav {
            padding: 6px 12px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
        }
    }
    @media (max-width: 480px) {
        .navbar-container {
            padding: 7px 10px !important;
        }
        .logo img {
            height: 36px !important;
        }
        .icon-img {
            width: 20px !important;
            height: 20px !important;
        }
        .nav-icon-group {
            gap: 6px !important;
        }
        .help-center {
            display: none !important;
        }
    }
    </style>
</head>
<body>
    <div class="bubble-container">
        <div class="bubble"></div><div class="bubble"></div>
        <div class="bubble"></div><div class="bubble"></div>
        <div class="bubble"></div><div class="bubble"></div>
        <div class="bubble"></div><div class="bubble"></div>
    </div>

    <header class="navbar">
        <div class="navbar-container">
            <div class="navbar-left-owner">
                <div class="nav-icon-group">
                    <div class="profile-dropdown-container">
                        <a href="javascript:void(0)" class="icon-link" id="profileToggle" title="Profil Saya">
                            <img src="foto/profil-icon.png" alt="Profil" class="icon-img"
                                onerror="this.src='https://cdn-icons-png.flaticon.com/512/1144/1144760.png'">
                        </a>
                        <div id="logoutDropdown" class="dropdown-menu">
                            <?php if ($role === 'pemilik'): ?>
                            <a href="dashboard-pemilik.php">🏠 Dashboard</a>
                            <?php else: ?>
                            <a href="dashboard-pencari.php">🏠 Dashboard</a>
                            <?php endif; ?>
                            <a href="logout.php" style="color:#e53e3e;">🚪 Keluar</a>
                        </div>
                    </div>
                    <a href="chat.php" class="icon-link" title="Pesan" style="position:relative;">
                        <img src="foto/chat-icon.png" alt="Chat" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/589/589708.png'">
                        <?php if ($total_unread > 0): ?>
                        <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                            <?= $total_unread ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php if ($role === 'pencari'): ?>
                        <a href="simpan.php" class="icon-link" title="Tersimpan">
                            <img src="foto/simpan.png" alt="Simpan" class="icon-img"
                                onerror="this.src='https://cdn-icons-png.flaticon.com/512/5662/5662990.png'">
                        </a>
                        <a href="notifikasi-pencari.php" class="icon-link" title="Notifikasi" style="position:relative;">
                            <img src="foto/notif-icon.png" alt="Notifikasi" class="icon-img"
                                onerror="this.src='https://cdn-icons-png.flaticon.com/512/3119/3119338.png'">
                            <?php if ($total_notif > 0): ?>
                            <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                                <?= $total_notif ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <a href="tambah-kos.php" class="icon-link" title="Tambah Kos">
                            <img src="foto/tambah-icon.png" alt="Tambah" class="icon-img"
                                onerror="this.src='https://cdn-icons-png.flaticon.com/512/992/992651.png'">
                        </a>
                        <a href="notifikasi.php" class="icon-link" title="Notifikasi" style="position:relative;">
                            <img src="foto/notif-icon.png" alt="Notifikasi" class="icon-img"
                                onerror="this.src='https://cdn-icons-png.flaticon.com/512/3119/3119338.png'">
                            <?php if ($total_notif > 0): ?>
                            <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                                <?= $total_notif ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </div>
                <a href="#pusat-bantuan" class="help-center">Pusat Bantuan</a>
            </div>
            <div class="navbar-right">
                <div class="logo"><img src="foto/gool.png" alt="Kos'ong?"></div>
            </div>
        </div>
    </header>

    <div class="chat-wrapper">
        <div class="chat-container">

            <!-- SIDEBAR -->
            <div class="chat-sidebar">
                <div class="sidebar-header">
                    <h2>💬 Pesan</h2>
                    <p>Halo, <strong><?= htmlspecialchars($username) ?></strong>!</p>
                </div>
                <div class="kontak-list">
                    <?php if (count($daftar_kontak) > 0): ?>
                        <?php foreach ($daftar_kontak as $k): ?>
                            <a href="chat.php?dengan=<?= $k['user_id'] ?>"
                               class="kontak-item <?= $teman_id === (int)$k['user_id'] ? 'active' : '' ?>">
                                <div class="kontak-avatar">
                                    <?= strtoupper(substr($k['username'], 0, 1)) ?>
                                </div>
                                <div class="kontak-detail">
                                    <div class="kontak-nama"><?= htmlspecialchars($k['username']) ?></div>
                                    <div class="kontak-kos">🏠 <?= htmlspecialchars($k['nama_kos'] ?? '-') ?></div>
                                </div>
                                <?php if ($k['unread'] > 0): ?>
                                    <div class="kontak-badge"><?= $k['unread'] ?></div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-kontak">
                            <div class="icon">💬</div>
                            <p>Belum ada kontak.<br>Ajukan sewa kos untuk mulai chat.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- AREA CHAT -->
            <div class="chat-area">
                <?php if ($teman_id > 0 && $teman_info): ?>
                    <div class="chat-header">
                        <div class="chat-header-avatar">
                            <?= strtoupper(substr($teman_info['username'], 0, 1)) ?>
                        </div>
                        <div class="chat-header-info">
                            <h3><?= htmlspecialchars($teman_info['username']) ?></h3>
                            <p><span class="online-dot"></span>Online</p>
                        </div>
                    </div>

                    <div class="chat-messages" id="chatMessages"></div>

                    <div class="chat-input-area">
                        <textarea id="inputPesan" placeholder="Ketik pesan..." rows="1"
                            onkeydown="handleEnter(event)"></textarea>
                        <button class="btn-kirim" id="btnKirim" onclick="kirimPesan()">➤</button>
                    </div>

                <?php else: ?>
                    <div class="chat-placeholder">
                        <div class="icon">💬</div>
                        <h3>Pilih percakapan</h3>
                        <p>Pilih kontak di sebelah kiri<br>untuk mulai mengobrol.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        const TEMAN_ID = <?= $teman_id ?>;
        const USER_ID  = <?= $user_id ?>;
        let lastMsgId  = 0;

        // Dropdown profile
        const profileToggle  = document.getElementById('profileToggle');
        const logoutDropdown = document.getElementById('logoutDropdown');
        if (profileToggle) {
            profileToggle.addEventListener('click', e => {
                e.stopPropagation();
                logoutDropdown.classList.toggle('show-menu');
            });
            window.addEventListener('click', e => {
                if (!profileToggle.contains(e.target)) logoutDropdown.classList.remove('show-menu');
            });
        }

        // Auto resize textarea
        const textarea = document.getElementById('inputPesan');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }

        function handleEnter(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                kirimPesan();
            }
        }

        function formatTanggal(dateStr) {
            const d   = new Date(dateStr);
            const now = new Date();
            const yes = new Date(now); yes.setDate(now.getDate() - 1);
            const same = (a, b) =>
                a.getDate()===b.getDate() && a.getMonth()===b.getMonth() && a.getFullYear()===b.getFullYear();
            if (same(d, now))  return 'Hari ini';
            if (same(d, yes))  return 'Kemarin';
            return d.toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' });
        }

        function formatJam(dateStr) {
            return new Date(dateStr).toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });
        }

        // Escape HTML — TANPA konversi \n ke <br>
        // white-space:pre-wrap di CSS sudah menangani newline dengan benar
        function esc(text) {
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // Buat elemen satu baris pesan
        function buatElemenPesan(msg) {
            const isSent = parseInt(msg.sender_id) === USER_ID;
            const inisial = msg.sender_name ? msg.sender_name[0].toUpperCase() : '?';

            const row = document.createElement('div');
            row.className = `msg-row ${isSent ? 'sent' : 'received'}`;
            row.dataset.chatId = msg.chat_id;

            // Avatar
            const av = document.createElement('div');
            av.className = 'msg-avatar';
            av.textContent = isSent ? 'Aku' : inisial;

            // Wrapper (bubble + waktu + hapus)
            const wrapper = document.createElement('div');
            wrapper.className = 'msg-wrapper';

            // Bubble
            const bubble = document.createElement('div');
            bubble.className = 'msg-bubble';
            bubble.textContent = msg.pesan;   // ← textContent, bukan innerHTML
                                              //   jadi tidak perlu escape manual
                                              //   dan tidak ada masalah <br>

            // Waktu
            const time = document.createElement('div');
            time.className = 'msg-time';
            time.textContent = formatJam(msg.dikirim_at);

            wrapper.appendChild(bubble);
            wrapper.appendChild(time);

            // Tombol hapus (hanya untuk pesan sendiri)
            if (isSent) {
                const actions = document.createElement('div');
                actions.className = 'msg-actions';
                const btnHapus = document.createElement('button');
                btnHapus.className = 'btn-hapus';
                btnHapus.textContent = '🗑 Hapus';
                btnHapus.onclick = () => hapusPesan(msg.chat_id, row);
                actions.appendChild(btnHapus);
                wrapper.appendChild(actions);
            }

            // Susun: avatar dulu atau terakhir tergantung arah
            if (!isSent) { row.appendChild(av); row.appendChild(wrapper); }
            else         { row.appendChild(wrapper); row.appendChild(av); }

            return row;
        }

        // Render semua pesan dari awal
        function renderPesan(messages) {
            const box = document.getElementById('chatMessages');
            if (!box) return;
            box.innerHTML = '';
            let lastDate = '';

            messages.forEach(msg => {
                const tgl = formatTanggal(msg.dikirim_at);
                if (tgl !== lastDate) {
                    const div = document.createElement('div');
                    div.className = 'date-divider';
                    div.innerHTML = `<span>${tgl}</span>`;
                    box.appendChild(div);
                    lastDate = tgl;
                }
                box.appendChild(buatElemenPesan(msg));
            });

            box.scrollTop = box.scrollHeight;
        }

        // Tambah pesan baru saja (polling)
        function tambahPesanBaru(messages) {
            const box = document.getElementById('chatMessages');
            if (!box) return;
            const isBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 100;

            messages.forEach(msg => {
                box.appendChild(buatElemenPesan(msg));
            });

            if (isBottom) box.scrollTop = box.scrollHeight;
        }

        function muatPesan() {
            if (!TEMAN_ID) return;
            fetch(`proses/api-chat.php?aksi=ambil&teman_id=${TEMAN_ID}`)
                .then(r => r.json())
                .then(data => {
                    if (data.pesan && data.pesan.length > 0) {
                        renderPesan(data.pesan);
                        lastMsgId = data.pesan[data.pesan.length - 1].chat_id;
                    }
                })
                .catch(err => console.error('Gagal muat pesan:', err));
        }

        function cekPesanBaru() {
            if (!TEMAN_ID) return;
            fetch(`proses/api-chat.php?aksi=baru&teman_id=${TEMAN_ID}&last_id=${lastMsgId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.pesan && data.pesan.length > 0) {
                        tambahPesanBaru(data.pesan);
                        lastMsgId = data.pesan[data.pesan.length - 1].chat_id;
                    }
                })
                .catch(() => {});
        }

        function kirimPesan() {
            const input = document.getElementById('inputPesan');
            const btn   = document.getElementById('btnKirim');
            const pesan = input.value.trim();
            if (!pesan || !TEMAN_ID) return;

            btn.disabled = true;
            input.value  = '';
            input.style.height = 'auto';

            const fd = new FormData();
            fd.append('aksi', 'kirim');
            fd.append('teman_id', TEMAN_ID);
            fd.append('pesan', pesan);

            fetch('proses/api-chat.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => { if (data.status === 'success') cekPesanBaru(); })
                .catch(() => {})
                .finally(() => { btn.disabled = false; input.focus(); });
        }

        function hapusPesan(chatId, rowEl) {
            kosConfirm({
                ikon: '🗑️',
                judul: 'Hapus Pesan?',
                pesan: 'Pesan ini akan dihapus secara permanen dan tidak bisa dikembalikan.',
                labelYa: 'Ya, Hapus',
                tipeYa: 'merah',
                onYa: function() {
                    const fd = new FormData();
                    fd.append('aksi', 'hapus');
                    fd.append('chat_id', chatId);

                    fetch('proses/api-chat.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                rowEl.style.transition = 'opacity 0.3s, transform 0.3s';
                                rowEl.style.opacity = '0';
                                rowEl.style.transform = 'scaleY(0)';
                                setTimeout(() => rowEl.remove(), 300);
                                kosToast('Pesan berhasil dihapus', 'sukses');
                            } else {
                                kosAlert({ ikon: '❌', judul: 'Gagal Menghapus', pesan: data.message || 'Coba lagi.', tipe: 'gagal' });
                            }
                        })
                        .catch(() => kosAlert({ ikon: '❌', judul: 'Koneksi Gagal', pesan: 'Periksa koneksi internet kamu.', tipe: 'gagal' }));
                }
            });
        }

        if (TEMAN_ID > 0) {
            muatPesan();
            setInterval(cekPesanBaru, 3000);
        }
    // Modal system
// ============================================================
//  CUSTOM MODAL SYSTEM — kosModal & kosToast
//  Pengganti alert() dan confirm() bawaan browser
// ============================================================
(function() {
    // Buat elemen modal & toast — pastikan body sudah siap & tidak duplikat
    function initModalDOM() {
        if (document.getElementById('kosModalOverlay')) return; // sudah ada

        const overlay = document.createElement('div');
        overlay.className = 'kos-modal-overlay';
        overlay.id = 'kosModalOverlay';
        overlay.innerHTML = `
            <div class="kos-modal-box" id="kosModalBox">
                <div class="kos-modal-icon"  id="kosModalIcon"></div>
                <div class="kos-modal-judul" id="kosModalJudul"></div>
                <div class="kos-modal-pesan" id="kosModalPesan"></div>
                <div class="kos-modal-btns"  id="kosModalBtns"></div>
            </div>`;
        document.body.appendChild(overlay);

        if (!document.getElementById('kosToastEl')) {
            const toast = document.createElement('div');
            toast.className = 'kos-toast';
            toast.id = 'kosToastEl';
            document.body.appendChild(toast);
        }
    }

    // Jalankan saat DOM siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModalDOM);
    } else {
        initModalDOM(); // DOM sudah siap
    }

    let toastTimer = null;

    // ── Toast ──────────────────────────────────────────
    window.kosToast = function(pesan, tipe) {
        const el = document.getElementById('kosToastEl');
        if (!el) return;
        const warna = tipe === 'sukses' ? '#2d8f4e'
                    : tipe === 'gagal'  ? '#c0392b'
                    : '#222';
        const ikon  = tipe === 'sukses' ? '✅ '
                    : tipe === 'gagal'  ? '❌ '
                    : 'ℹ️ ';
        el.textContent = ikon + pesan;
        el.style.background = warna;
        el.classList.add('tampil');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('tampil'), 2800);
    };

    // ── Alert (hanya tombol OK) ─────────────────────────
    window.kosAlert = function(opts) {
        const ov = document.getElementById('kosModalOverlay');
        if (!ov) return;
        const ikon     = opts.ikon  || (opts.tipe === 'sukses' ? '✅' : opts.tipe === 'gagal' ? '❌' : 'ℹ️');
        const judul    = opts.judul || 'Informasi';
        const warnaBtn = opts.tipe === 'gagal' ? 'merah' : opts.tipe === 'sukses' ? 'hijau' : '';

        document.getElementById('kosModalIcon').textContent  = ikon;
        document.getElementById('kosModalJudul').textContent = judul;
        document.getElementById('kosModalPesan').textContent = opts.pesan || '';
        document.getElementById('kosModalBtns').innerHTML =
            `<button class="kos-modal-btn btn-ya ${warnaBtn}" id="kosModalOk">Oke</button>`;

        ov.classList.add('aktif');

        document.getElementById('kosModalOk').onclick = function() {
            ov.classList.remove('aktif');
            if (opts.onOk) opts.onOk();
        };
    };

    // ── Confirm (Ya / Batal) ───────────────────────────
    window.kosConfirm = function(opts) {
        const ov = document.getElementById('kosModalOverlay');
        if (!ov) return;
        const ikon    = opts.ikon    || '❓';
        const judul   = opts.judul   || 'Konfirmasi';
        const labelYa = opts.labelYa || 'Ya';
        const tipeYa  = opts.tipeYa  || '';

        document.getElementById('kosModalIcon').textContent  = ikon;
        document.getElementById('kosModalJudul').textContent = judul;
        document.getElementById('kosModalPesan').textContent = opts.pesan || '';
        document.getElementById('kosModalBtns').innerHTML =
            `<button class="kos-modal-btn btn-tidak" id="kosModalTidak">Batal</button>
             <button class="kos-modal-btn btn-ya ${tipeYa}" id="kosModalYa">${labelYa}</button>`;

        ov.classList.add('aktif');

        document.getElementById('kosModalYa').onclick = function() {
            ov.classList.remove('aktif');
            if (opts.onYa) opts.onYa();
        };
        document.getElementById('kosModalTidak').onclick = function() {
            ov.classList.remove('aktif');
            if (opts.onTidak) opts.onTidak();
        };
        ov.onclick = function(e) {
            if (e.target === ov) {
                ov.classList.remove('aktif');
                if (opts.onTidak) opts.onTidak();
            }
        };
    };
})();

    </script>
</body>
</html>