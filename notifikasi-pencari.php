<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Pragma: no-cache");
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Ambil semua notifikasi pencari:
// 1. Booking status update (diterima/ditolak/pending)
// 2. Diurutkan: diterima & ditolak (terbaru) duluan, lalu pending
$query = "SELECT b.*, 
          k.nama_kos, k.harga, k.foto, k.jenis_kos, k.alamat,
          COALESCE(k.whatsapp, '') as whatsapp,
          u.username as nama_pemilik, u.user_id as pemilik_id
          FROM booking b
          JOIN kos k ON b.kos_id = k.kos_id
          JOIN users u ON k.pemilik_id = u.user_id
          WHERE b.user_id = $user_id
          ORDER BY 
            FIELD(b.status, 'diterima', 'ditolak', 'pending'),
            b.tanggal_booking DESC";
$result = mysqli_query($conn, $query);

$all_notif   = [];
$total_baru  = 0;

// Tandai notif sebagai "sudah dibaca" saat halaman ini dibuka
// Simpan booking_id yang sudah dilihat di session
if (!isset($_SESSION['notif_dibaca'])) {
    $_SESSION['notif_dibaca'] = [];
}

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_notif[] = $row;
        // Hitung badge: diterima/ditolak yang BELUM pernah dilihat
        if (in_array($row['status'], ['diterima', 'ditolak'])) {
            if (!in_array($row['booking_id'], $_SESSION['notif_dibaca'])) {
                $total_baru++;
            }
        }
    }
}

// Setelah dihitung, tandai semua sebagai sudah dibaca
foreach ($all_notif as $n) {
    if (in_array($n['status'], ['diterima', 'ditolak'])) {
        if (!in_array($n['booking_id'], $_SESSION['notif_dibaca'])) {
            $_SESSION['notif_dibaca'][] = $n['booking_id'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Kos'ong?</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .notif-wrapper {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        .notif-header-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 8px;
        }

        .notif-title {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.2);
        }

        .badge-count {
            background: #FF4444;
            color: #fff;
            border-radius: 50px;
            padding: 4px 14px;
            font-size: 13px;
            font-weight: 700;
        }

        .notif-subtitle {
            font-size: 15px;
            color: rgba(255,255,255,0.85);
            margin-bottom: 30px;
            font-weight: 500;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.4);
            color: #fff;
            padding: 8px 22px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .tab-btn.active, .tab-btn:hover {
            background: #FFD700;
            border-color: #FFD700;
            color: #000;
        }

        /* Notif Card */
        .notif-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 6px 25px rgba(0,0,0,0.12);
            margin-bottom: 20px;
            border: 3px solid transparent;
            transition: all 0.3s;
        }

        .notif-card:hover {
            border-color: #FFD700;
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.18);
        }

        .notif-card.pending  { border-left: 6px solid #FFD700; }
        .notif-card.diterima { border-left: 6px solid #34D399; }
        .notif-card.ditolak  { border-left: 6px solid #F87171; }

        .notif-card-inner {
            display: flex;
            align-items: center;
            padding: 20px 25px;
            gap: 20px;
        }

        /* Ikon notif besar di kiri */
        .notif-type-icon {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }

        .icon-diterima { background: #D1FAE5; }
        .icon-ditolak  { background: #FEE2E2; }
        .icon-pending  { background: #FFF3CD; }

        .notif-kos-img {
            width: 100px;
            height: 75px;
            object-fit: cover;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .notif-info { flex: 1; }

        .notif-info-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5px;
            gap: 10px;
        }

        .notif-judul {
            font-size: 15px;
            font-weight: 700;
            color: #333;
            margin-bottom: 3px;
        }

        .notif-kos-name {
            font-size: 13px;
            color: #00B4D8;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .notif-harga {
            font-size: 13px;
            font-weight: 700;
            color: #555;
            margin-bottom: 3px;
        }

        .notif-pemilik {
            font-size: 12px;
            color: #888;
            margin-bottom: 3px;
        }

        .notif-pemilik strong { color: #555; }

        .notif-date {
            font-size: 11px;
            color: #bbb;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .status-pending  { background: #FFF3CD; color: #856404; border: 2px solid #FFD700; }
        .status-diterima { background: #D1FAE5; color: #065F46; border: 2px solid #34D399; }
        .status-ditolak  { background: #FEE2E2; color: #991B1B; border: 2px solid #F87171; }

        /* Banner bawah card */
        .notif-banner {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 20px;
            border-top: 1px solid #f0f0f0;
            flex-wrap: wrap;
        }

        .notif-banner.banner-pending  { background: #FFFBF0; }
        .notif-banner.banner-diterima { background: #F0FFF4; }
        .notif-banner.banner-ditolak  { background: #FFF5F5; }

        .banner-icon { font-size: 22px; flex-shrink: 0; }

        .banner-text { flex: 1; min-width: 150px; }

        .banner-text h4 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .banner-text p {
            font-size: 12px;
            font-weight: 500;
            color: #777;
            margin: 0;
        }

        .banner-pending  .banner-text h4 { color: #856404; }
        .banner-diterima .banner-text h4 { color: #065F46; }
        .banner-ditolak  .banner-text h4 { color: #991B1B; }

        .banner-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-aksi {
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            border: 2px solid transparent;
        }

        .btn-aksi-kuning {
            background: #FFD700;
            color: #000;
            border-color: #000;
        }

        .btn-aksi-kuning:hover { background: #51CF66; transform: scale(1.05); }

        .btn-aksi-wa {
            background: #25D366;
            color: #fff;
            border-color: #1ebe5d;
        }

        .btn-aksi-wa:hover { background: #1ebe5d; transform: scale(1.05); }

        .btn-aksi-abu {
            background: #f0f0f0;
            color: #555;
            border-color: #ddd;
        }

        .btn-aksi-abu:hover { background: #e0e0e0; }

        .btn-aksi-hapus {
            background: #fff0f0;
            color: #c0392b;
            border: 2px solid #F87171;
            transition: all 0.3s;
        }
        .btn-aksi-hapus:hover {
            background: #F87171;
            color: #fff;
            transform: scale(1.05);
        }

        .btn-aksi-rating {
            background: #fffbea;
            color: #b45309;
            border: 2px solid #FFD700;
            transition: all 0.3s;
        }
        .btn-aksi-rating:hover {
            background: #FFD700;
            color: #000;
            transform: scale(1.05);
        }

        /* Modal Form Rating */
        .rating-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .rating-modal-overlay.aktif { display: flex; }
        .rating-modal-box {
            background: #fff;
            border-radius: 20px;
            padding: 32px 28px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            text-align: center;
            position: relative;
        }
        .rating-modal-box h3 {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 4px;
            color: #222;
        }
        .rating-modal-box p {
            font-size: 13px;
            color: #888;
            margin-bottom: 20px;
        }
        .star-row {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 18px;
            flex-direction: row-reverse;
        }
        .star-row input { display: none; }
        .star-row label {
            font-size: 40px;
            cursor: pointer;
            color: #ddd;
            transition: color 0.15s;
            line-height: 1;
        }
        .star-row input:checked ~ label,
        .star-row label:hover,
        .star-row label:hover ~ label { color: #FFD700; }
        .komentar-area {
            width: 100%;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 14px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            resize: vertical;
            min-height: 90px;
            box-sizing: border-box;
            transition: border 0.3s;
        }
        .komentar-area:focus { outline: none; border-color: #FFD700; }
        .rating-modal-btns {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 16px;
        }
        .btn-rating-batal {
            background: #f0f0f0;
            border: none;
            padding: 10px 24px;
            border-radius: 20px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }
        .btn-rating-kirim {
            background: #FFD700;
            border: 2px solid #000;
            padding: 10px 24px;
            border-radius: 20px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        .btn-rating-kirim:hover { background: #51CF66; }

        /* Animasi fade out saat dihapus */
        .notif-card.fade-out {
            opacity: 0;
            transform: translateX(40px);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
        }

        .empty-icon { font-size: 70px; margin-bottom: 20px; opacity: 0.4; }
        .empty-state h3 { font-size: 22px; font-weight: 700; color: #333; margin-bottom: 10px; }
        .empty-state p  { color: #888; font-size: 15px; margin-bottom: 25px; }

        .btn-cari {
            background: #FFD700;
            border: none;
            padding: 12px 35px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            color: #000;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-cari:hover { background: #51CF66; transform: scale(1.05); }

        .hidden { display: none !important; }


        @media (max-width: 768px) {
            .notif-card-inner { flex-direction: column; align-items: flex-start; }
            .notif-info-top   { flex-direction: column; }
            .notif-banner     { flex-direction: column; align-items: flex-start; }
        }
    </style>

    <!-- Override bubble & footer — harus setelah style.css -->
    <style>
        /* Paksa layout flex column agar footer nempel ke konten */
        html {
            height: 100%;
        }
        body {
            min-height: 100% !important;
            height: auto !important;
            display: flex !important;
            flex-direction: column !important;
            overflow-x: hidden !important;
        }

        /* Bubble terkurung di viewport, tidak bisa meluber */
        .bubble-container {
            position: fixed !important;
            top: 0 !important; left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            overflow: hidden !important;
            pointer-events: none !important;
            z-index: 0 !important;
        }

        /* Navbar & wrapper konten di atas bubble */
        .navbar        { position: relative !important; z-index: 100 !important; }
        .notif-wrapper { position: relative !important; z-index: 1 !important; flex: 1; }

        /* Footer solid, langsung setelah konten */
        .footer {
            position: relative !important;
            z-index: 10 !important;
            background: #FFD700 !important;
            margin-top: 0 !important;
            flex-shrink: 0 !important;
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
                            <a href="dashboard-pencari.php">🏠 Dashboard</a>
                            <a href="logout.php" style="color:#e53e3e;">🚪 Keluar</a>
                        </div>
                    </div>

                    <a href="javascript:void(0)" class="icon-link" title="Pesan" onclick="cwToggle()">
                        <img src="foto/chat-icon.png" alt="Chat" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/589/589708.png'">
                    </a>

                    <a href="simpan.php" class="icon-link" title="Tersimpan">
                        <img src="foto/simpan.png" alt="Simpan" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/5662/5662990.png'">
                    </a>

                    <a href="notifikasi-pencari.php" class="icon-link" title="Notifikasi" style="position:relative;">
                        <img src="foto/notif-icon.png" alt="Notifikasi" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/3119/3119338.png'">
                        <?php if ($total_baru > 0): ?>
                        <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                            <?= $total_baru ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </div>
                <a href="#pusat-bantuan" class="help-center">Pusat Bantuan</a>
            </div>
            <div class="navbar-right">
                <div class="logo"><img src="foto/gool.png" alt="Kos'ong?"></div>
            </div>
        </div>
    </header>

    <div class="notif-wrapper">
        <div class="notif-header-row">
            <h1 class="notif-title">🔔 Notifikasi</h1>
            <?php if ($total_baru > 0): ?>
                <span class="badge-count"><?= $total_baru ?> Baru</span>
            <?php endif; ?>
        </div>
        <p class="notif-subtitle">Update terbaru seputar pengajuan sewa kos kamu.</p>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="tab-btn active" onclick="filterTab('semua', this)">Semua</button>
            <button class="tab-btn" onclick="filterTab('diterima', this)">✅ Diterima</button>
            <button class="tab-btn" onclick="filterTab('ditolak', this)">❌ Ditolak</button>
            <button class="tab-btn" onclick="filterTab('pending', this)">⏳ Menunggu</button>
        </div>

        <?php if (count($all_notif) > 0): ?>
            <?php foreach ($all_notif as $n): ?>
                <?php
                    $foto_arr = explode(',', $n['foto']);
                    $foto_url = !empty(trim($foto_arr[0]))
                        ? 'uploads/' . trim($foto_arr[0])
                        : 'https://via.placeholder.com/100x75?text=Kos';

                    $tgl    = date('d M Y, H:i', strtotime($n['tanggal_booking']));
                    $status = $n['status'];

                    // Cek sudah review (hanya untuk diterima)
                    $sudah_review_notif = false;
                    if ($status === 'diterima') {
                        $cr = mysqli_query($conn, "SELECT review_id FROM review WHERE booking_id = {$n['booking_id']} LIMIT 1");
                        $sudah_review_notif = ($cr && mysqli_num_rows($cr) > 0);
                    }

                    if ($status === 'diterima') {
                        $badge_class  = 'status-diterima';
                        $badge_icon   = '✅';
                        $badge_text   = 'Diterima';
                        $icon_class   = 'icon-diterima';
                        $icon_emoji   = '✅';
                        $banner_class = 'banner-diterima';
                        $ban_icon     = '🎉';
                        $ban_judul    = 'Pengajuan Sewa Diterima!';
                        $ban_pesan    = 'Selamat! Pemilik kos telah menerima pengajuan sewa kamu.';
                        $judul_notif  = 'Pengajuan kamu diterima!';

                    } elseif ($status === 'ditolak') {
                        $badge_class  = 'status-ditolak';
                        $badge_icon   = '❌';
                        $badge_text   = 'Ditolak';
                        $icon_class   = 'icon-ditolak';
                        $icon_emoji   = '❌';
                        $banner_class = 'banner-ditolak';
                        $ban_icon     = '😔';
                        $ban_judul    = 'Pengajuan Sewa Ditolak';
                        $ban_pesan    = 'Maaf, pemilik kos belum bisa menerima pengajuan sewa kamu saat ini.';
                        $judul_notif  = 'Pengajuan kamu ditolak';

                    } else {
                        $badge_class  = 'status-pending';
                        $badge_icon   = '⏳';
                        $badge_text   = 'Menunggu';
                        $icon_class   = 'icon-pending';
                        $icon_emoji   = '⏳';
                        $banner_class = 'banner-pending';
                        $ban_icon     = '🏠';
                        $ban_judul    = 'Menunggu Konfirmasi Pemilik';
                        $ban_pesan    = 'Pemilik kos sedang memproses pengajuan sewa kamu.';
                        $judul_notif  = 'Pengajuan sewa terkirim';
                    }
                ?>

                <div class="notif-card <?= $status ?>" data-status="<?= $status ?>" data-booking="<?= $n['booking_id'] ?>" id="notif-card-<?= $n['booking_id'] ?>">
                    <div class="notif-card-inner">

                        <!-- Ikon tipe notif -->
                        <div class="notif-type-icon <?= $icon_class ?>"><?= $icon_emoji ?></div>

                        <!-- Foto kos -->
                        <img src="<?= $foto_url ?>" alt="<?= htmlspecialchars($n['nama_kos']) ?>" class="notif-kos-img">

                        <!-- Info -->
                        <div class="notif-info">
                            <div class="notif-info-top">
                                <div>
                                    <div class="notif-judul"><?= $judul_notif ?></div>
                                    <div class="notif-kos-name">🏠 <?= htmlspecialchars($n['nama_kos']) ?></div>
                                    <div class="notif-harga">💰 Rp <?= number_format($n['harga'], 0, ',', '.') ?> / Bulan</div>
                                    <div class="notif-pemilik">👤 Pemilik: <strong><?= htmlspecialchars($n['nama_pemilik']) ?></strong></div>
                                    <div class="notif-date">📅 <?= $tgl ?></div>
                                </div>
                                <span class="status-badge <?= $badge_class ?>"><?= $badge_icon ?> <?= $badge_text ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Banner bawah dengan tombol aksi -->
                    <div class="notif-banner <?= $banner_class ?>">
                        <div class="banner-icon"><?= $ban_icon ?></div>
                        <div class="banner-text">
                            <h4><?= $ban_judul ?></h4>
                            <p><?= $ban_pesan ?></p>
                        </div>
                        <div class="banner-btns">
                            <?php if ($status === 'diterima'): ?>
                                <button class="btn-aksi btn-aksi-wa"
                                        onclick="cwBukaWidget(<?= (int)$n['pemilik_id'] ?>, '<?= htmlspecialchars($n['nama_pemilik'], ENT_QUOTES) ?>', '<?= htmlspecialchars($n['nama_kos'], ENT_QUOTES) ?>')">
                                    💬 Chat Pemilik
                                </button>
                                <a href="detail-kos.php?id=<?= $n['kos_id'] ?>" 
                                   class="btn-aksi btn-aksi-kuning">
                                    🏠 Lihat Kos
                                </a>
                                <?php if (!$sudah_review_notif): ?>
                                <button class="btn-aksi btn-aksi-rating"
                                        onclick="bukaFormRating(<?= $n['booking_id'] ?>, '<?= htmlspecialchars($n['nama_kos'], ENT_QUOTES) ?>')">
                                    ⭐ Beri Rating
                                </button>
                                <?php else: ?>
                                <span class="btn-aksi" style="background:#f0fff4;color:#065F46;border:2px solid #34D399;cursor:default;">
                                    ✅ Sudah Dinilai
                                </span>
                                <?php endif; ?>

                            <?php elseif ($status === 'ditolak'): ?>
                                <a href="dashboard-pencari.php" 
                                   class="btn-aksi btn-aksi-kuning">
                                    🔍 Cari Kos Lain
                                </a>
                                <button class="btn-aksi btn-aksi-hapus"
                                        onclick="hapusNotif(<?= $n['booking_id'] ?>)">
                                    🗑️ Hapus
                                </button>

                            <?php else: /* pending */ ?>
                                <a href="detail-kos.php?id=<?= $n['kos_id'] ?>" 
                                   class="btn-aksi btn-aksi-kuning">
                                    🏠 Lihat Kos
                                </a>
                                <a href="simpan.php" 
                                   class="btn-aksi btn-aksi-abu">
                                    📋 Lihat Status
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🔔</div>
                <h3>Belum Ada Notifikasi</h3>
                <p>Notifikasi akan muncul saat ada update dari pengajuan sewa kosmu.</p>
                <a href="dashboard-pencari.php" class="btn-cari">🔍 Cari Kos Sekarang</a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="footer-bottom">
            <div class="footer-logo">
                <img src="foto/gool.png" width="100" alt="logo kosong">
            </div>
            <p>©2025 Kos'ong?.com All right reserved</p>
        </div>
    </footer>

    <script>
        // Dropdown
        const profileToggle = document.getElementById('profileToggle');
        const logoutDropdown = document.getElementById('logoutDropdown');
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            logoutDropdown.classList.toggle('show-menu');
        });
        window.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target)) logoutDropdown.classList.remove('show-menu');
        });

        // Filter Tab
        function filterTab(status, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.notif-card').forEach(card => {
                card.classList.toggle('hidden', status !== 'semua' && card.dataset.status !== status);
            });
        }

        // ── Buka chat widget (sama seperti detail-kos.php) ──
        function cwBukaWidget(userId, nama, kos) {
            var panel = document.getElementById('cwPanel');
            if (!panel) {
                // Fallback: buka halaman chat jika widget tidak tersedia
                window.location.href = 'chat.php?dengan=' + userId;
                return;
            }
            if (!panel.classList.contains('cw-open')) cwToggle();
            setTimeout(function() { cwBukaChat(userId, nama, kos); }, 150);
        }

        // Hapus notif ditolak
        function hapusNotif(bookingId) {
            kosConfirm({
                ikon:    '🗑️',
                judul:   'Hapus Notifikasi?',
                pesan:   'Notifikasi penolakan ini akan dihapus dari daftarmu. Booking juga akan dihapus.',
                labelYa: 'Hapus',
                tipeYa:  'merah',
                onYa: function() {
                    const fd = new FormData();
                    fd.append('booking_id', bookingId);

                    fetch('proses/hapus-notif-booking.php', { method: 'POST', body: fd })
                        .then(r => r.text())
                        .then(txt => {
                            let d;
                            try { d = JSON.parse(txt); } catch(e) {
                                console.error('Response:', txt);
                                throw new Error('Response tidak valid');
                            }
                            if (d.status === 'success') {
                                // Animasi fade out lalu hapus dari DOM
                                const card = document.getElementById('notif-card-' + bookingId);
                                if (card) {
                                    card.classList.add('fade-out');
                                    setTimeout(() => {
                                        card.remove();
                                        // Cek apakah masih ada notif
                                        const sisa = document.querySelectorAll('.notif-card');
                                        if (sisa.length === 0) {
                                            document.querySelector('.notif-wrapper').insertAdjacentHTML('beforeend',
                                                `<div class="empty-state">
                                                    <div class="empty-icon">🔔</div>
                                                    <h3>Belum Ada Notifikasi</h3>
                                                    <p>Notifikasi akan muncul saat ada update dari pengajuan sewa kosmu.</p>
                                                    <a href="dashboard-pencari.php" class="btn-cari">🔍 Cari Kos Sekarang</a>
                                                </div>`
                                            );
                                        }
                                    }, 420);
                                }
                                kosToast('🗑️ Notifikasi dihapus', 'info');
                            } else {
                                kosAlert({ ikon: '❌', judul: 'Gagal', pesan: d.message || 'Coba lagi.', tipe: 'gagal' });
                            }
                        })
                        .catch(err => {
                            kosAlert({ ikon: '❌', judul: 'Error', pesan: err.message, tipe: 'gagal' });
                        });
                }
            });
        }
    </script>

    <!-- Modal Form Rating -->
    <div class="rating-modal-overlay" id="ratingModalOverlay">
        <div class="rating-modal-box">
            <h3>⭐ Beri Penilaian</h3>
            <p id="ratingModalSubtitle">Bagaimana pengalamanmu di kos ini?</p>
            <input type="hidden" id="ratingBookingId">
            <div class="star-row" id="starRow">
                <input type="radio" name="ratingVal" id="r5" value="5"><label for="r5">★</label>
                <input type="radio" name="ratingVal" id="r4" value="4"><label for="r4">★</label>
                <input type="radio" name="ratingVal" id="r3" value="3"><label for="r3">★</label>
                <input type="radio" name="ratingVal" id="r2" value="2"><label for="r2">★</label>
                <input type="radio" name="ratingVal" id="r1" value="1"><label for="r1">★</label>
            </div>
            <textarea class="komentar-area" id="ratingKomentar"
                placeholder="Ceritakan pengalamanmu tinggal di kos ini..." maxlength="500"></textarea>
            <div class="rating-modal-btns">
                <button class="btn-rating-batal" onclick="tutupFormRating()">Batal</button>
                <button class="btn-rating-kirim" onclick="kirimRatingNotif()">⭐ Kirim</button>
            </div>
        </div>
    </div>

    <script>
        function bukaFormRating(bookingId, namaKos) {
            document.getElementById('ratingBookingId').value = bookingId;
            document.getElementById('ratingModalSubtitle').textContent = namaKos;
            // Reset form
            document.querySelectorAll('#starRow input').forEach(i => i.checked = false);
            document.getElementById('ratingKomentar').value = '';
            document.getElementById('ratingModalOverlay').classList.add('aktif');
            document.body.style.overflow = 'hidden';
        }

        function tutupFormRating() {
            document.getElementById('ratingModalOverlay').classList.remove('aktif');
            document.body.style.overflow = '';
        }

        // Tutup saat klik overlay
        document.getElementById('ratingModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) tutupFormRating();
        });

        function kirimRatingNotif() {
            const bookingId = document.getElementById('ratingBookingId').value;
            const ratingEl  = document.querySelector('#starRow input[name="ratingVal"]:checked');
            const komentar  = document.getElementById('ratingKomentar').value.trim();

            if (!ratingEl) { kosToast('⭐ Pilih bintang dulu!', 'info'); return; }
            if (komentar.length < 5) { kosToast('✏️ Komentar minimal 5 karakter!', 'info'); return; }

            const fd = new FormData();
            fd.append('booking_id', bookingId);
            fd.append('rating', ratingEl.value);
            fd.append('komentar', komentar);

            document.querySelector('.btn-rating-kirim').disabled = true;
            document.querySelector('.btn-rating-kirim').textContent = 'Mengirim...';

            fetch('proses/simpan-rating.php', { method: 'POST', body: fd })
                .then(r => r.text())
                .then(txt => {
                    let d;
                    try { d = JSON.parse(txt); } catch(e) { throw new Error('Response tidak valid'); }

                    if (d.status === 'success') {
                        tutupFormRating();
                        kosToast('⭐ Penilaian berhasil dikirim! Terima kasih.', 'sukses');

                        // Ganti tombol "Beri Rating" → "Sudah Dinilai"
                        const btn = document.querySelector(`button[onclick*="bukaFormRating(${bookingId},"]`);
                        if (btn) {
                            btn.outerHTML = `<span class="btn-aksi" style="background:#f0fff4;color:#065F46;border:2px solid #34D399;cursor:default;">✅ Sudah Dinilai</span>`;
                        }
                    } else {
                        kosAlert({ ikon: '❌', judul: 'Gagal', pesan: d.message, tipe: 'gagal' });
                        document.querySelector('.btn-rating-kirim').disabled = false;
                        document.querySelector('.btn-rating-kirim').textContent = '⭐ Kirim';
                    }
                })
                .catch(err => {
                    kosAlert({ ikon: '❌', judul: 'Error', pesan: err.message, tipe: 'gagal' });
                    document.querySelector('.btn-rating-kirim').disabled = false;
                    document.querySelector('.btn-rating-kirim').textContent = '⭐ Kirim';
                });
        }
    </script>

<?php include 'chat-widget.php'; ?>
</body>
</html>