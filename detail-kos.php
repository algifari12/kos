<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Pragma: no-cache");
require_once 'config/database.php';

$kos_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = "SELECT k.*, u.username as nama_pemilik, u.user_id as pemilik_id 
          FROM kos k 
          JOIN users u ON k.pemilik_id = u.user_id 
          WHERE k.kos_id = $kos_id";
$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    echo "<script>document.addEventListener('DOMContentLoaded',function(){kosAlert({ikon:'❌',judul:'Kos Tidak Ditemukan',pesan:'Kos yang kamu cari tidak tersedia.',tipe:'gagal',onOk:function(){window.location='index.php';}});});</script>";
    exit();
}
$kos = $result->fetch_assoc();

$query_review = "SELECT r.*, u.username as nama_pencari FROM review r 
                 JOIN booking b ON r.booking_id = b.booking_id 
                 JOIN users u ON b.user_id = u.user_id 
                 WHERE b.kos_id = $kos_id ORDER BY r.tanggal_review DESC";
$result_review = $conn->query($query_review);
$reviews = []; $total_rating = 0; $jumlah_review = 0;
if ($result_review && $result_review->num_rows > 0) {
    while ($row = $result_review->fetch_assoc()) {
        $reviews[] = $row; $total_rating += $row['rating']; $jumlah_review++;
    }
}
$rata_rating = $jumlah_review > 0 ? round($total_rating / $jumlah_review, 1) : 0;

$sudah_ajukan = false;
$total_notif_pemilik = 0;
$total_notif_pencari = 0;

if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];

    if ($_SESSION['role'] === 'pencari') {
        $cek = $conn->query("SELECT booking_id FROM booking WHERE user_id = $uid AND kos_id = $kos_id AND status = 'pending'");
        if ($cek && $cek->num_rows > 0) $sudah_ajukan = true;

        // Cek: punya booking diterima di kos ini?
        $cek_diterima = $conn->query("SELECT booking_id FROM booking WHERE user_id = $uid AND kos_id = $kos_id AND status = 'diterima' LIMIT 1");
        $booking_diterima = ($cek_diterima && $cek_diterima->num_rows > 0) ? $cek_diterima->fetch_assoc()['booking_id'] : null;

        // Cek: sudah pernah review kos ini?
        $sudah_review = false;
        if ($booking_diterima) {
            $cek_review = $conn->query("SELECT review_id FROM review WHERE booking_id = $booking_diterima LIMIT 1");
            $sudah_review = ($cek_review && $cek_review->num_rows > 0);
        }

        // Cek apakah sudah difavoritkan
        $cek_fav = $conn->query("SELECT favorit_id FROM favorit WHERE user_id = $uid AND kos_id = $kos_id");
        $sudah_favorit = ($cek_fav && $cek_fav->num_rows > 0);

        // Badge notif: diterima + ditolak
        $r = $conn->query("SELECT COUNT(*) as total FROM booking WHERE user_id = $uid AND status IN ('diterima','ditolak')");
        if ($r) $total_notif_pencari = $r->fetch_assoc()['total'];

    } elseif ($_SESSION['role'] === 'pemilik') {
        $r = $conn->query("SELECT COUNT(*) as total FROM booking b JOIN kos k ON b.kos_id = k.kos_id WHERE k.pemilik_id = $uid AND b.status = 'pending'");
        if ($r) $total_notif_pemilik = $r->fetch_assoc()['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail <?php echo htmlspecialchars($kos['nama_kos']); ?> - Kos'ong?</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/detail.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .detail-wrapper { max-width: 1200px; margin: 30px auto; padding: 0 20px; position: relative; z-index: 1; }

        /* ── Tombol buka modal rating ── */
        .btn-beri-rating {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FFD700;
            border: 2px solid #000;
            padding: 10px 24px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            margin-top: 12px;
        }
        .btn-beri-rating:hover { background: #51CF66; transform: scale(1.04); }


        /* ── Modal Rating ── */
        .rating-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .rating-modal-overlay.aktif { display: flex; }
        .rating-modal-box {
            background: #fff;
            border-radius: 20px;
            padding: 36px 32px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.3);
            text-align: center;
            position: relative;
            animation: modalMasuk 0.3s ease;
        }
        @keyframes modalMasuk {
            from { transform: scale(0.85); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .rating-modal-box .modal-close {
            position: absolute;
            top: 14px; right: 18px;
            font-size: 22px;
            cursor: pointer;
            color: #aaa;
            background: none;
            border: none;
            line-height: 1;
            transition: color 0.2s;
        }
        .rating-modal-box .modal-close:hover { color: #333; }
        .rating-modal-box h3 {
            font-size: 20px;
            font-weight: 800;
            color: #222;
            margin-bottom: 4px;
        }
        .rating-modal-box .modal-subtitle {
            font-size: 13px;
            color: #888;
            margin-bottom: 22px;
        }
        .star-row {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 8px;
            flex-direction: row-reverse;
        }
        .star-row input { display: none; }
        .star-row label {
            font-size: 44px;
            cursor: pointer;
            color: #ddd;
            transition: color 0.15s;
            line-height: 1;
        }
        .star-row input:checked ~ label,
        .star-row label:hover,
        .star-row label:hover ~ label { color: #FFD700; }
        .star-label-hint {
            font-size: 12px;
            color: #aaa;
            margin-bottom: 16px;
            min-height: 18px;
        }
        .komentar-area {
            width: 100%;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 12px 14px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            resize: vertical;
            min-height: 90px;
            box-sizing: border-box;
            transition: border 0.3s;
        }
        .komentar-area:focus { outline: none; border-color: #FFD700; }
        .komentar-count {
            font-size: 11px;
            color: #bbb;
            text-align: right;
            margin-top: 4px;
            margin-bottom: 16px;
        }
        .modal-rating-btns {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .btn-modal-batal {
            background: #f0f0f0;
            border: none;
            padding: 11px 28px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: background 0.2s;
        }
        .btn-modal-batal:hover { background: #e0e0e0; }
        .btn-modal-kirim {
            background: #FFD700;
            border: 2px solid #000;
            padding: 11px 28px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        .btn-modal-kirim:hover { background: #51CF66; }
        .btn-modal-kirim:disabled { opacity: 0.6; cursor: not-allowed; }
        .main-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 40px; }

        .gallery-container { display: flex; flex-direction: column; gap: 10px; }
        .main-photo { width: 100%; height: 450px; object-fit: cover; border-radius: 12px; border: 1px solid #ddd; }
        .thumb-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .thumb-grid img { width: 100%; height: 85px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 2px solid #eee; transition: 0.3s; }
        .thumb-grid img:hover, .thumb-grid img.active { border-color: #FFD700; transform: scale(1.05); }

        .info-panel h1 { font-size: 32px; font-weight: 800; margin-bottom: 10px; }
        .rating-summary { color: #f1c40f; font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .rating-summary span { color: #888; font-size: 14px; }
        .gender-tag { border: 2px solid #333; padding: 5px 20px; border-radius: 6px; display: inline-block; font-weight: bold; margin-bottom: 20px; font-family: 'Poppins', sans-serif; }

        .map-box { width: 100%; height: 250px; border-radius: 12px; border: 1px solid #ddd; overflow: hidden; margin-bottom: 25px; }
        .price-large { font-size: 28px; font-weight: 800; color: #00B4D8; margin-bottom: 20px; }

        .action-btns { display: flex; gap: 15px; }
        .btn-sewa-utama { background: #FFD700; flex: 2; border: 2px solid #000; padding: 15px; font-weight: 800; border-radius: 10px; cursor: pointer; font-size: 17px; font-family: 'Poppins', sans-serif; transition: all 0.3s; }
        .btn-sewa-utama:hover { background: #e6c200; transform: scale(1.02); }
        .btn-sewa-utama:disabled { background: #ccc; border-color: #aaa; cursor: not-allowed; transform: none; }

        /* ── Tombol Chat Pemilik internal ── */
        .btn-chat-pemilik {
            background: #000; color: #fff; flex: 1;
            padding: 15px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            text-decoration: none; font-weight: bold;
            font-family: 'Poppins', sans-serif; font-size: 15px;
            transition: all 0.3s;
            border: none; cursor: pointer;
        }
        .btn-chat-pemilik:hover { background: #333; transform: scale(1.02); }

        .desc-fasilitas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 50px; padding-top: 30px; border-top: 2px solid #FFD700; }
        .section-h3 { font-size: 22px; margin-bottom: 20px; font-weight: 800; color: #333; }
        .text-content { line-height: 1.8; color: #444; text-align: justify; font-family: 'Poppins', sans-serif; }

        .review-card { display: flex; gap: 15px; margin-bottom: 25px; background: #fdfdfd; padding: 15px; border-radius: 10px; border: 2px solid #eee; }
        .avatar-circle { width: 45px; height: 45px; background: #FFD700; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }

        .sudah-ajukan-banner {
            background: #FFF3CD; border: 2px solid #FFD700; border-radius: 10px;
            padding: 12px 18px; font-size: 14px; font-weight: 600; color: #856404;
            margin-top: 12px; display: flex; align-items: center; gap: 10px;
        }

        /* Modal */
        .modal-sewa { max-width: 480px; text-align: center; }
        .modal-sewa-icon { font-size: 70px; margin-bottom: 15px; }
        .modal-sewa h2 { font-size: 26px; font-weight: 800; margin-bottom: 10px; color: #333; }
        .modal-sewa-info { background: #f9f9f9; border-radius: 12px; padding: 15px 20px; margin: 15px 0 25px; text-align: left; border: 2px solid #FFD700; }
        .modal-sewa-info p { font-size: 14px; color: #555; margin-bottom: 6px; font-weight: 500; }
        .modal-sewa-info p strong { color: #333; }
        .modal-sewa-btns { display: flex; gap: 15px; justify-content: center; }
        .btn-modal-batal { background: #eee; border: none; padding: 13px 30px; border-radius: 25px; font-weight: 700; font-size: 15px; cursor: pointer; font-family: 'Poppins', sans-serif; transition: all 0.3s; }
        .btn-modal-batal:hover { background: #ddd; }
        .btn-modal-ya { background: #FFD700; border: 2px solid #000; padding: 13px 35px; border-radius: 25px; font-weight: 800; font-size: 15px; cursor: pointer; font-family: 'Poppins', sans-serif; transition: all 0.3s; }
        .btn-modal-ya:hover { background: #51CF66; transform: scale(1.05); }

        .modal-sukses { max-width: 420px; text-align: center; }
        .modal-sukses-icon { font-size: 80px; margin-bottom: 15px; }
        .modal-sukses h2 { font-size: 24px; font-weight: 800; color: #065F46; margin-bottom: 10px; }
        .modal-sukses p { color: #666; margin-bottom: 25px; font-size: 15px; }
        .btn-lihat-simpan { background: #FFD700; border: 2px solid #000; padding: 12px 30px; border-radius: 25px; font-weight: 700; font-size: 15px; cursor: pointer; text-decoration: none; color: #000; font-family: 'Poppins', sans-serif; transition: all 0.3s; display: inline-block; }
        .btn-lihat-simpan:hover { background: #51CF66; transform: scale(1.05); }

        /* Tombol Simpan Kos */
        .btn-simpan-kos {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 2px solid #FFD700;
            padding: 11px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.25s ease;
            color: #333;
            margin-top: 12px;
            width: 100%;
            justify-content: center;
        }
        .btn-simpan-kos:hover {
            background: #FFF9E6;
            transform: scale(1.02);
            box-shadow: 0 4px 14px rgba(255,215,0,0.35);
        }
        .btn-simpan-kos.tersimpan {
            background: #FFF3CD;
            border-color: #e6c200;
            color: #856404;
        }
        .btn-simpan-kos.tersimpan:hover {
            background: #FFE0E0;
            border-color: #FF4444;
            color: #FF4444;
        }

        /* Toast */
        .toast-notif {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #333;
            color: #fff;
            padding: 12px 24px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            z-index: 9999;
            opacity: 0;
            transition: all 0.3s ease;
            pointer-events: none;
            white-space: nowrap;
        }
        .toast-notif.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

                .footer { padding: 5px 0 !important; }
        .footer-bottom { padding: 10px 5% !important; border-top: 2px solid rgba(0,0,0,0.2) !important; }

        @media (max-width: 768px) {
            .main-grid { grid-template-columns: 1fr; }
            .desc-fasilitas-grid { grid-template-columns: 1fr; gap: 30px; }
            .action-btns { flex-direction: column; }
            .modal-sewa-btns { flex-direction: column; }
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
</head>
<body>

    <header class="navbar">
        <div class="navbar-container">
            <div class="navbar-left-owner">
                <div class="nav-icon-group">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <div class="profile-dropdown-container">
                            <a href="javascript:void(0)" class="icon-link" id="profileToggle" title="Profil Saya">
                                <img src="foto/profil.png" alt="Profil" class="icon-img"
                                    onerror="this.src='https://cdn-icons-png.flaticon.com/512/1144/1144760.png'">
                            </a>
                            <div id="logoutDropdown" class="dropdown-menu">
                                <?php if ($_SESSION['role'] === 'pemilik'): ?>
                                    <a href="dashboard-pemilik.php">🏠 Dashboard</a>
                                <?php else: ?>
                                    <a href="dashboard-pencari.php">🏠 Dashboard</a>
                                <?php endif; ?>
                                <a href="logout.php" style="color:#e53e3e;">🚪 Keluar</a>
                            </div>
                        </div>

                        <a href="javascript:void(0)" class="icon-link" title="Pesan" onclick="cwToggle()">
                            <img src="foto/chat.png" class="icon-img"
                                onerror="this.src='https://cdn-icons-png.flaticon.com/512/589/589708.png'">
                        </a>

                        <?php if($_SESSION['role'] === 'pemilik'): ?>
                            <a href="simpan.php" class="icon-link" title="Tersimpan">
                                <img src="foto/simpan.png" alt="Simpan" class="icon-img"
                                    onerror="this.src='https://cdn-icons-png.flaticon.com/512/5662/5662990.png'">
                            </a>
                            <a href="notifikasi.php" class="icon-link" title="Notifikasi" style="position:relative;">
                                <img src="foto/notif.png" class="icon-img"
                                    onerror="this.src='https://cdn-icons-png.flaticon.com/512/3119/3119338.png'">
                                <?php if($total_notif_pemilik > 0): ?>
                                <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                                    <?= $total_notif_pemilik ?>
                                </span>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <a href="simpan.php" class="icon-link" title="Tersimpan">
                                <img src="foto/simpan.png" alt="Simpan" class="icon-img"
                                    onerror="this.src='https://cdn-icons-png.flaticon.com/512/5662/5662990.png'">
                            </a>
                            <a href="notifikasi-pencari.php" class="icon-link" title="Notifikasi" style="position:relative;">
                                <img src="foto/notif.png" class="icon-img"
                                    onerror="this.src='https://cdn-icons-png.flaticon.com/512/3119/3119338.png'">
                                <?php if($total_notif_pencari > 0): ?>
                                <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                                    <?= $total_notif_pencari ?>
                                </span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>

                    <?php else: ?>
                        <button class="btn-masuk-nav" onclick="openRoleModal()">Masuk <span>→</span></button>
                    <?php endif; ?>
                </div>
                <a href="#pusat-bantuan" class="help-center">Pusat Bantuan</a>
            </div>
            <div class="navbar-right">
                <div class="logo"><img src="foto/gool.png" alt="Kos'ong?"></div>
            </div>
        </div>
    </header>

    <div class="detail-wrapper">
        <div class="main-grid">
            <!-- Gallery -->
            <div class="gallery-container">
                <?php 
                    $foto_array = explode(',', $kos['foto']);
                    $utama = !empty(trim($foto_array[0])) ? 'uploads/'.trim($foto_array[0]) : 'https://via.placeholder.com/600x400?text=Foto+Kos';
                ?>
                <img src="<?= $utama ?>" class="main-photo" id="mainView">
                <div class="thumb-grid">
                    <?php foreach($foto_array as $idx => $f): if(trim($f)): ?>
                        <img src="uploads/<?= trim($f) ?>" 
                             class="<?= $idx === 0 ? 'active' : '' ?>"
                             onclick="gantiGambar(this)">
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <!-- Info Panel -->
            <div class="info-panel">
                <h1><?= htmlspecialchars($kos['nama_kos']) ?></h1>
                <div class="rating-summary">
                    <i class="fas fa-star"></i> <?= $rata_rating ?>
                    <span>(<?= $jumlah_review ?> Penilaian)</span>
                </div>
                <div class="gender-tag">KOS <?= strtoupper($kos['jenis_kos']) ?></div>

                <div class="map-box">
                    <iframe width="100%" height="100%" frameborder="0" style="border:0"
                        src="https://maps.google.com/maps?q=<?= urlencode($kos['alamat']) ?>&t=&z=16&ie=UTF8&iwloc=B&output=embed">
                    </iframe>
                </div>

                <div class="price-large">Rp <?= number_format($kos['harga'], 0, ',', '.') ?> / Bulan</div>

                <div class="action-btns">
                    <!-- Tombol Ajukan Sewa -->
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'pencari'): ?>
                        <?php if ($sudah_ajukan): ?>
                            <button class="btn-sewa-utama" disabled>⏳ Sudah Diajukan</button>
                        <?php else: ?>
                            <button class="btn-sewa-utama" onclick="bukaModalSewa()">🏠 Ajukan Sewa</button>
                        <?php endif; ?>
                    <?php elseif(isset($_SESSION['user_id']) && $_SESSION['role'] === 'pemilik'): ?>
                        <button class="btn-sewa-utama" disabled title="Anda adalah pemilik kos">🏠 Ajukan Sewa</button>
                    <?php else: ?>
                        <button class="btn-sewa-utama" onclick="openRoleModal()">🏠 Ajukan Sewa</button>
                    <?php endif; ?>

                    <!-- ── Tombol Chat Pemilik — buka chat widget langsung ── -->
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'pencari'): ?>
                        <button class="btn-chat-pemilik"
                            onclick="cwBukaWidget(<?= (int)$kos['pemilik_id'] ?>, '<?= htmlspecialchars($kos['nama_pemilik'], ENT_QUOTES) ?>', '<?= htmlspecialchars($kos['nama_kos'], ENT_QUOTES) ?>')">
                            💬 Chat Pemilik
                        </button>
                    <?php elseif (isset($_SESSION['user_id']) && $_SESSION['role'] === 'pemilik'): ?>
                    <?php else: ?>
                        <button class="btn-chat-pemilik" onclick="openRoleModal()">
                            💬 Chat Pemilik
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Tombol Simpan / Favorit -->
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'pencari'): ?>
                <button class="btn-simpan-kos <?= isset($sudah_favorit) && $sudah_favorit ? 'tersimpan' : '' ?>"
                        id="btnSimpan"
                        onclick="toggleFavorit(<?= $kos_id ?>)">
                    <span id="simpanIcon"><?= isset($sudah_favorit) && $sudah_favorit ? '🔖' : '🔖' ?></span>
                    <span id="simpanTeks"><?= isset($sudah_favorit) && $sudah_favorit ? '✅ Tersimpan — Klik untuk hapus' : '🔖 Simpan Kos Ini' ?></span>
                </button>
                <?php endif; ?>

                <?php if ($sudah_ajukan): ?>
                <div class="sudah-ajukan-banner">
                    ⏳ Pengajuan sewa Anda sedang menunggu konfirmasi pemilik kos.
                    <a href="simpan.php" style="color:#00B4D8; text-decoration:none; font-weight:700;">Lihat Status →</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="desc-fasilitas-grid">
            <div>
                <h3 class="section-h3">Deskripsi Kos</h3>
                <p class="text-content"><?= nl2br(htmlspecialchars($kos['deskripsi'])) ?></p>

                <h3 class="section-h3" style="margin-top: 40px;">Penilaian Kos</h3>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'pencari'): ?>
                    <?php if ($booking_diterima && !$sudah_review): ?>
                        <button class="btn-beri-rating" id="btnBeriRating"
                            onclick="bukaModalRating(<?= $booking_diterima ?>)">
                            ⭐ Beri Penilaian
                        </button>
                    <?php endif; ?>
                    <?php /* Kalau sudah review: tidak tampilkan apapun */ ?>
                <?php endif; ?>

                <div id="reviewList">
                <?php if ($reviews): foreach ($reviews as $r): ?>
                    <div class="review-card">
                        <div class="avatar-circle">👤</div>
                        <div>
                            <strong style="font-size:14px;"><?= htmlspecialchars($r['nama_pencari']) ?></strong>
                            <div style="color:#f1c40f; font-size:13px; margin: 3px 0;">
                                <?php for($i=1;$i<=5;$i++) echo $i <= $r['rating'] ? '★' : '☆'; ?>
                            </div>
                            <p class="text-content" style="font-size:14px;"><?= htmlspecialchars($r['komentar']) ?></p>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <p class="text-content">Belum ada ulasan.</p>
                <?php endif; ?>
                </div><!-- /reviewList -->
            </div>

            <div>
                <h3 class="section-h3">Fasilitas Kos</h3>
                <div class="text-content">
                    <?php 
                        $fas = explode(',', $kos['fasilitas']);
                        foreach($fas as $f) { echo "• " . trim($f) . "<br>"; }
                    ?>
                    <br>
                    <strong>Ukuran Kamar:</strong> <?= htmlspecialchars($kos['ukuran_kamar']) ?> Meter<br>
                    <strong>Stok Kamar:</strong> <?= $kos['stok_kamar'] ?> Kamar Tersedia
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-bottom">
            <div class="footer-logo">
                <img src="foto/gool.png" width="100" alt="logo kosong">
            </div>
            <p>©2025 Kos'ong?.com All right reserved</p>
        </div>
    </footer>

    <!-- Modal Konfirmasi Sewa -->
    <div id="modalSewa" class="modal-overlay">
        <div class="modal-content modal-sewa">
            <span class="close-modal" onclick="tutupModalSewa()">&times;</span>
            <div class="modal-sewa-icon">🏠</div>
            <h2>Ajukan Sewa Kos?</h2>
            <p style="color:#666; margin-bottom:10px; font-size:15px;">Pastikan data kos di bawah ini sudah sesuai sebelum mengajukan sewa.</p>
            <div class="modal-sewa-info">
                <p><strong>📍 Nama Kos:</strong> <?= htmlspecialchars($kos['nama_kos']) ?></p>
                <p><strong>💰 Harga:</strong> Rp <?= number_format($kos['harga'], 0, ',', '.') ?> / Bulan</p>
                <p><strong>🚻 Jenis:</strong> Kos <?= ucfirst($kos['jenis_kos']) ?></p>
                <p><strong>📍 Alamat:</strong> <?= htmlspecialchars($kos['alamat']) ?></p>
            </div>
            <p style="font-size:13px; color:#888; margin-bottom:20px;">
                Pengajuan ini akan dikirim ke pemilik kos dan menunggu konfirmasi dari mereka.
            </p>
            <div class="modal-sewa-btns">
                <button class="btn-modal-batal" onclick="tutupModalSewa()">Batal</button>
                <button class="btn-modal-ya" onclick="kirimAjuanSewa()">✅ Ya, Ajukan!</button>
            </div>
        </div>
    </div>

    <!-- Modal Sukses -->
    <div id="modalSukses" class="modal-overlay">
        <div class="modal-content modal-sukses">
            <div class="modal-sukses-icon">🎉</div>
            <h2>Pengajuan Terkirim!</h2>
            <p>Pengajuan sewa kos <strong><?= htmlspecialchars($kos['nama_kos']) ?></strong> berhasil dikirim. Tunggu konfirmasi dari pemilik kos ya!</p>
            <a href="simpan.php" class="btn-lihat-simpan">📋 Lihat Status Pengajuan</a>
        </div>
    </div>

    <!-- Modal Login (tamu) -->
    <div id="roleModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="closeRoleModal()">&times;</span>
            <div style="text-align: center; margin-bottom: 30px;">
                <h1>Bergabung Sebagai</h1>
            </div>
            <div class="role-selection">
                <div class="role-option">
                    <div class="role-icon">🏠</div>
                    <h3>Pemilik Kos</h3>
                    <p>Daftarkan kos Anda dan kelola booking dengan mudah</p>
                    <a href="login.php?role=pemilik" class="btn-modal btn-masuk-modal">Masuk</a>
                    <a href="daftar.php?role=pemilik" class="btn-modal btn-daftar-modal">Daftar</a>
                </div>
                <div class="role-option">
                    <div class="role-icon">🔍</div>
                    <h3>Pencari Kos</h3>
                    <p>Temukan kos impian Anda dan booking langsung</p>
                    <a href="login.php?role=pencari" class="btn-modal btn-masuk-modal">Masuk</a>
                    <a href="daftar.php?role=pencari" class="btn-modal btn-daftar-modal">Daftar</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dropdown Profile
        const profileToggle  = document.getElementById('profileToggle');
        const logoutDropdown = document.getElementById('logoutDropdown');
        if (profileToggle) {
            profileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                logoutDropdown.classList.toggle('show-menu');
            });
            window.addEventListener('click', function(e) {
                if (!profileToggle.contains(e.target)) logoutDropdown.classList.remove('show-menu');
            });
        }

        // Galeri foto
        function gantiGambar(el) {
            document.getElementById('mainView').src = el.src;
            document.querySelectorAll('.thumb-grid img').forEach(i => i.classList.remove('active'));
            el.classList.add('active');
        }

        // Modal Sewa
        function bukaModalSewa() {
            document.getElementById('modalSewa').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function tutupModalSewa() {
            document.getElementById('modalSewa').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function kirimAjuanSewa() {
            const btn = document.querySelector('.btn-modal-ya');
            btn.textContent = '⏳ Memproses...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('kos_id', <?= $kos_id ?>);

            fetch('proses/proses-booking.php', { method: 'POST', body: formData })
            .then(res => res.text().then(text => {
                try { return JSON.parse(text); }
                catch(e) { throw new Error('Response tidak valid: ' + text.substring(0, 200)); }
            }))
            .then(data => {
                tutupModalSewa();
                if (data.status === 'success') {
                    document.getElementById('modalSukses').style.display = 'flex';
                    const btnSewa = document.querySelector('.btn-sewa-utama');
                    if (btnSewa) { btnSewa.textContent = '⏳ Sudah Diajukan'; btnSewa.disabled = true; }
                } else if (data.status === 'already') {
                    kosAlert({ ikon: 'ℹ️', judul: 'Informasi', pesan: data.message, tipe: 'info' });
                    btn.textContent = '✅ Ya, Ajukan!'; btn.disabled = false;
                } else {
                    kosAlert({ ikon: '❌', judul: 'Gagal', pesan: data.message, tipe: 'gagal' });
                    btn.textContent = '✅ Ya, Ajukan!'; btn.disabled = false;
                }
            })
            .catch(err => {
                tutupModalSewa();
                kosAlert({ ikon: '❌', judul: 'Terjadi Kesalahan', pesan: err.message, tipe: 'gagal' });
                btn.textContent = '✅ Ya, Ajukan!'; btn.disabled = false;
            });
        }

        // Modal Login
        function openRoleModal()  { document.getElementById('roleModal').style.display = 'flex'; document.body.style.overflow = 'hidden'; }
        function closeRoleModal() { document.getElementById('roleModal').style.display = 'none'; document.body.style.overflow = 'auto'; }

        window.onclick = function(e) {
            const ms = document.getElementById('modalSewa');
            const mk = document.getElementById('modalSukses');
            const mr = document.getElementById('roleModal');
            if (e.target === ms) tutupModalSewa();
            if (e.target === mr) closeRoleModal();
            if (e.target === mk) { mk.style.display = 'none'; document.body.style.overflow = 'auto'; }
        };
    </script>



    <script>
    // Buka chat widget dan langsung ke percakapan dengan pemilik kos ini
    function cwBukaWidget(userId, nama, kos) {
        var panel = document.getElementById('cwPanel');
        if (!panel.classList.contains('cw-open')) cwToggle();
        setTimeout(function() { cwBukaChat(userId, nama, kos); }, 150);
    }
    </script>

    <!-- Modal & Toast System -->
<style>
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
<script>
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

<!-- Toast + Favorit JS -->
    <div class="toast-notif" id="toastNotif"></div>
    <script>
        function tampilToast(msg, warna) {
            const t = document.getElementById('toastNotif');
            t.textContent = msg;
            t.style.background = warna || '#333';
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2500);
        }

        function toggleFavorit(kosId) {
            const btn  = document.getElementById('btnSimpan');
            const teks = document.getElementById('simpanTeks');
            const isSaved = btn.classList.contains('tersimpan');
            const aksi = isSaved ? 'hapus' : 'simpan';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('kos_id', kosId);
            fd.append('aksi', aksi);

            fetch('proses/proses-favorit.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        if (aksi === 'simpan') {
                            btn.classList.add('tersimpan');
                            teks.textContent = '\u2705 Tersimpan \u2014 Klik untuk hapus';
                            tampilToast('\uD83D\uDD16 Kos berhasil disimpan!', '#333');
                        } else {
                            btn.classList.remove('tersimpan');
                            teks.textContent = '\uD83D\uDD16 Simpan Kos Ini';
                            tampilToast('\uD83D\uDDD1 Dihapus dari simpanan', '#888');
                        }
                    } else {
                        tampilToast('\u274C ' + d.message, '#FF4444');
                    }
                    btn.disabled = false;
                })
                .catch(() => {
                    tampilToast('\u274C Terjadi kesalahan', '#FF4444');
                    btn.disabled = false;
                });
        }
    </script>

    <!-- Modal Rating Detail Kos -->
    <div class="rating-modal-overlay" id="ratingModalOverlay">
        <div class="rating-modal-box">
            <button class="modal-close" onclick="tutupModalRating()">✕</button>
            <h3>⭐ Beri Penilaian</h3>
            <p class="modal-subtitle">Bagaimana pengalamanmu tinggal di kos ini?</p>
            <input type="hidden" id="ratingBookingId">
            <div class="star-row">
                <input type="radio" name="ratingVal" id="dk5" value="5"><label for="dk5" title="Sangat Bagus">★</label>
                <input type="radio" name="ratingVal" id="dk4" value="4"><label for="dk4" title="Bagus">★</label>
                <input type="radio" name="ratingVal" id="dk3" value="3"><label for="dk3" title="Cukup">★</label>
                <input type="radio" name="ratingVal" id="dk2" value="2"><label for="dk2" title="Kurang">★</label>
                <input type="radio" name="ratingVal" id="dk1" value="1"><label for="dk1" title="Sangat Kurang">★</label>
            </div>
            <div class="star-label-hint" id="starHint">Pilih bintang</div>
            <textarea class="komentar-area" id="ratingKomentar"
                placeholder="Ceritakan pengalamanmu tinggal di kos ini..."
                maxlength="500" oninput="updateCount(this)"></textarea>
            <div class="komentar-count"><span id="charCount">0</span>/500</div>
            <div class="modal-rating-btns">
                <button class="btn-modal-batal" onclick="tutupModalRating()">Batal</button>
                <button class="btn-modal-kirim" id="btnKirimModal" onclick="kirimRatingModal()">⭐ Kirim</button>
            </div>
        </div>
    </div>

    <script>
        const starLabels = ['', 'Sangat Kurang 😞', 'Kurang 😐', 'Cukup 🙂', 'Bagus 😊', 'Sangat Bagus 🤩'];

        function bukaModalRating(bookingId) {
            document.getElementById('ratingBookingId').value = bookingId;
            // Reset
            document.querySelectorAll('.star-row input').forEach(i => i.checked = false);
            document.getElementById('ratingKomentar').value = '';
            document.getElementById('charCount').textContent = '0';
            document.getElementById('starHint').textContent = 'Pilih bintang';
            document.getElementById('btnKirimModal').disabled = false;
            document.getElementById('btnKirimModal').textContent = '⭐ Kirim';
            document.getElementById('ratingModalOverlay').classList.add('aktif');
            document.body.style.overflow = 'hidden';
        }

        function tutupModalRating() {
            document.getElementById('ratingModalOverlay').classList.remove('aktif');
            document.body.style.overflow = '';
        }

        // Tutup saat klik overlay
        document.getElementById('ratingModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) tutupModalRating();
        });

        // Update hint bintang
        document.querySelectorAll('.star-row input').forEach(function(input) {
            input.addEventListener('change', function() {
                document.getElementById('starHint').textContent = starLabels[this.value];
            });
        });

        // Hitung karakter komentar
        function updateCount(el) {
            document.getElementById('charCount').textContent = el.value.length;
        }

        function kirimRatingModal() {
            const bookingId = document.getElementById('ratingBookingId').value;
            const ratingEl  = document.querySelector('.star-row input[name="ratingVal"]:checked');
            const komentar  = document.getElementById('ratingKomentar').value.trim();

            if (!ratingEl) { kosToast('⭐ Pilih bintang dulu!', 'info'); return; }
            if (komentar.length < 5) { kosToast('✏️ Komentar minimal 5 karakter!', 'info'); return; }

            const rating = ratingEl.value;
            const btnKirim = document.getElementById('btnKirimModal');
            btnKirim.disabled = true;
            btnKirim.textContent = 'Mengirim...';

            const fd = new FormData();
            fd.append('booking_id', bookingId);
            fd.append('rating', rating);
            fd.append('komentar', komentar);

            fetch('proses/simpan-rating.php', { method: 'POST', body: fd })
                .then(r => r.text())
                .then(txt => {
                    let d;
                    try { d = JSON.parse(txt); }
                    catch(e) { throw new Error('Response tidak valid: ' + txt.substring(0, 80)); }

                    if (d.status === 'success') {
                        tutupModalRating();

                        // Tampilkan modal sukses dulu, lalu hilangkan tombol
                        kosAlert({
                            ikon:  '⭐',
                            judul: 'Penilaian Terkirim!',
                            pesan: 'Terima kasih sudah memberikan penilaian untuk kos ini.',
                            tipe:  'sukses',
                            onOk:  function() {
                                // Setelah user klik OK → sembunyikan tombol rating
                                const btnBeri = document.getElementById('btnBeriRating');
                                if (btnBeri) {
                                    btnBeri.style.transition = 'opacity 0.4s, transform 0.4s';
                                    btnBeri.style.opacity    = '0';
                                    btnBeri.style.transform  = 'scale(0.8)';
                                    setTimeout(() => btnBeri.remove(), 420);
                                }
                            }
                        });

                        // Tambah review baru ke list tanpa reload
                        const reviewList = document.getElementById('reviewList');
                        if (reviewList) {
                            const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
                            const noReview = reviewList.querySelector('p.text-content');
                            if (noReview && noReview.textContent.includes('Belum ada')) noReview.remove();
                            const card = document.createElement('div');
                            card.className = 'review-card';
                            card.style.cssText = 'border-color:#FFD700; animation: modalMasuk 0.3s ease;';
                            card.innerHTML = `
                                <div class="avatar-circle">👤</div>
                                <div>
                                    <strong style="font-size:14px;"><?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Kamu' ?></strong>
                                    <div style="color:#f1c40f;font-size:14px;margin:3px 0;">${stars}</div>
                                    <p class="text-content" style="font-size:14px;">${komentar}</p>
                                </div>`;
                            reviewList.prepend(card);
                        }
                    } else {
                        kosAlert({ ikon: '❌', judul: 'Gagal', pesan: d.message || 'Coba lagi.', tipe: 'gagal' });
                        btnKirim.disabled = false;
                        btnKirim.textContent = '⭐ Kirim';
                    }
                })
                .catch(err => {
                    kosAlert({ ikon: '❌', judul: 'Error', pesan: err.message, tipe: 'gagal' });
                    btnKirim.disabled = false;
                    btnKirim.textContent = '⭐ Kirim';
                });
        }
    </script>

<?php include 'chat-widget.php'; ?>
</body>
</html>