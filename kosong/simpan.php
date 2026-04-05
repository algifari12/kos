<?php
require_once 'config/session.php';
requireRole('pencari');
require_once 'config/database.php';


$user_id = (int)$_SESSION['user_id'];

// Ambil kos yang difavoritkan
$query = "
    SELECT k.*, u.username as nama_pemilik,
           COALESCE(AVG(r.rating), 0) as avg_rating,
           COUNT(DISTINCT r.review_id) as jumlah_review,
           f.disimpan_at
    FROM favorit f
    JOIN kos k ON f.kos_id = k.kos_id
    JOIN users u ON k.pemilik_id = u.user_id
    LEFT JOIN booking b2 ON b2.kos_id = k.kos_id
    LEFT JOIN review r ON r.booking_id = b2.booking_id
    WHERE f.user_id = $user_id
    GROUP BY k.kos_id, f.disimpan_at
    ORDER BY f.disimpan_at DESC
";
$result = mysqli_query($conn, $query);
$favorit_list = [];
if ($result) while ($row = mysqli_fetch_assoc($result)) $favorit_list[] = $row;

// Badge notif
$r = mysqli_query($conn, "SELECT COUNT(*) as total FROM booking WHERE user_id = $user_id AND status IN ('diterima','ditolak')");
$total_notif = $r ? mysqli_fetch_assoc($r)['total'] : 0;

$r2 = mysqli_query($conn, "SELECT COUNT(*) as total FROM chat WHERE receiver_id = $user_id AND dibaca = 0");
$total_chat_unread = $r2 ? mysqli_fetch_assoc($r2)['total'] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kos Tersimpan - Kos'ong?</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .simpan-wrapper {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        .simpan-header {
            margin-bottom: 30px;
        }

        .simpan-title {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 6px;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.2);
        }

        .simpan-subtitle {
            font-size: 15px;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
        }

        /* Grid kos tersimpan */
        .favorit-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 24px;
        }

        .favorit-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid #f0f0f0;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            position: relative;
        }

        .favorit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.13);
        }

        .favorit-card-img {
            width: 100%;
            height: 190px;
            object-fit: cover;
            display: block;
        }

        /* Tombol hapus simpan (pojok kanan atas foto) */
        .btn-unsave {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.92);
            border: 2px solid #FF4444;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            cursor: pointer;
            transition: all 0.2s;
            backdrop-filter: blur(4px);
            z-index: 2;
        }
        .btn-unsave:hover {
            background: #FF4444;
            transform: scale(1.15);
        }
        .btn-unsave:hover .unsave-icon { filter: brightness(10); }

        .favorit-body {
            padding: 16px;
        }

        .favorit-nama {
            font-size: 16px;
            font-weight: 800;
            color: #222;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .favorit-alamat {
            font-size: 12px;
            color: #999;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .favorit-badge {
            display: inline-block;
            background: #FFF3CD;
            border: 1.5px solid #FFD700;
            color: #856404;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 10px;
        }

        .favorit-rating {
            font-size: 13px;
            color: #f1c40f;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .favorit-rating span { color: #aaa; font-size: 11px; }

        .favorit-harga {
            font-size: 18px;
            font-weight: 800;
            color: #00B4D8;
            margin-bottom: 12px;
        }

        .favorit-actions {
            display: flex;
            gap: 8px;
        }

        .btn-lihat-detail {
            flex: 1;
            background: #FFD700;
            border: 2px solid #000;
            padding: 9px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            color: #000;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
        }
        .btn-lihat-detail:hover { background: #e6c200; transform: scale(1.03); }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #fff;
        }
        .empty-state .empty-icon { font-size: 70px; margin-bottom: 20px; opacity: 0.7; }
        .empty-state h3 { font-size: 22px; font-weight: 800; margin-bottom: 10px; }
        .empty-state p  { font-size: 15px; opacity: 0.85; margin-bottom: 25px; }
        .btn-cari-kos {
            background: #FFD700;
            border: 2px solid #000;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            color: #000;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
            display: inline-block;
        }
        .btn-cari-kos:hover { background: #e6c200; transform: scale(1.05); }

        /* Toast notifikasi */
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

    <header class="navbar">
        <div class="navbar-container">
            <div class="navbar-left-owner">
                <div class="nav-icon-group">
                    <div class="profile-dropdown-container">
                        <a href="javascript:void(0)" class="icon-link" id="profileToggle" title="Profil Saya">
                            <img src="foto/profil.png" alt="Profil" class="icon-img"
                                onerror="this.src='https://cdn-icons-png.flaticon.com/512/1144/1144760.png'">
                        </a>
                        <div id="logoutDropdown" class="dropdown-menu">
                            <a href="dashboard-pencari.php">🏠 Dashboard</a>
                            <a href="logout.php" style="color:#e53e3e;">🚪 Keluar</a>
                        </div>
                    </div>
                    <!-- Chat icon → buka modal chat -->
                    <a href="javascript:void(0)" class="icon-link" title="Pesan" onclick="cwToggle()" style="position:relative;">
                        <img src="foto/chat.png" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/589/589708.png'">
                        <?php if($total_chat_unread > 0): ?>
                        <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                            <?= $total_chat_unread ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <!-- Icon simpan aktif -->
                    <a href="simpan.php" class="icon-link" title="Tersimpan">
                        <img src="foto/simpan.png" alt="Simpan" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/5662/5662990.png'">
                    </a>
                    <a href="notifikasi-pencari.php" class="icon-link" title="Notifikasi" style="position:relative;">
                        <img src="foto/notif.png" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/3119/3119338.png'">
                        <?php if($total_notif > 0): ?>
                        <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                            <?= $total_notif ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </div>
                <a href="#" class="help-center">Pusat Bantuan</a>
            </div>
            <div class="navbar-right">
                <div class="logo"><img src="foto/gool.png" alt="Kos'ong?"></div>
            </div>
        </div>
    </header>

    <div class="simpan-wrapper">
        <div class="simpan-header">
            <h1 class="simpan-title">🔖 Kos Tersimpan</h1>
            <p class="simpan-subtitle">
                <?= count($favorit_list) ?> kos yang kamu simpan
            </p>
        </div>

        <?php if (count($favorit_list) > 0): ?>
        <div class="favorit-grid">
            <?php foreach ($favorit_list as $k):
                $foto_arr = explode(',', $k['foto']);
                $foto_url = !empty(trim($foto_arr[0])) ? 'uploads/'.trim($foto_arr[0]) : 'https://via.placeholder.com/300x200?text=Foto+Kos';
                $rating   = round($k['avg_rating'], 1);
            ?>
            <div class="favorit-card" id="card-kos-<?= $k['kos_id'] ?>">
                <img src="<?= $foto_url ?>" alt="<?= htmlspecialchars($k['nama_kos']) ?>" class="favorit-card-img"
                     onerror="this.src='https://via.placeholder.com/300x200?text=Foto+Kos'">

                <!-- Tombol hapus dari simpanan -->
                <button class="btn-unsave" onclick="hapusFavorit(<?= $k['kos_id'] ?>, this)" title="Hapus dari simpanan">
                    <span class="unsave-icon">🗑</span>
                </button>

                <div class="favorit-body">
                    <div class="favorit-nama"><?= htmlspecialchars($k['nama_kos']) ?></div>
                    <div class="favorit-alamat">📍 <?= htmlspecialchars($k['alamat']) ?></div>
                    <div class="favorit-badge">KOS <?= strtoupper($k['jenis_kos']) ?></div>
                    <div class="favorit-rating">
                        ★ <?= $rating > 0 ? $rating : '-' ?>
                        <span>(<?= $k['jumlah_review'] ?> ulasan)</span>
                    </div>
                    <div class="favorit-harga">Rp <?= number_format($k['harga'], 0, ',', '.') ?> / Bulan</div>
                    <div class="favorit-actions">
                        <a href="detail-kos.php?id=<?= $k['kos_id'] ?>" class="btn-lihat-detail">
                            🔍 Lihat Detail
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🔖</div>
            <h3>Belum ada kos tersimpan</h3>
            <p>Simpan kos favoritmu agar mudah ditemukan kembali</p>
            <a href="dashboard-pencari.php" class="btn-cari-kos">🔍 Cari Kos Sekarang</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="toast-notif" id="toastNotif"></div>

    <footer class="footer">
        <div class="footer-bottom">
            <div class="footer-logo">
                <img src="foto/gool.png" width="100" alt="logo kosong">
            </div>
            <p>©2025 Kos'ong?.com All right reserved</p>
        </div>
    </footer>

    <script>
        // Dropdown profile
        const profileToggle  = document.getElementById('profileToggle');
        const logoutDropdown = document.getElementById('logoutDropdown');
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            logoutDropdown.classList.toggle('show-menu');
        });
        window.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target)) logoutDropdown.classList.remove('show-menu');
        });

        // Toast

        // Hapus dari favorit langsung di halaman ini
        function lanjutHapusFavorit(kosId, btn) {
            const fd = new FormData();
            fd.append('kos_id', kosId);
            fd.append('aksi', 'hapus');
            fetch('proses/proses-favorit.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        const card = document.getElementById('card-kos-' + kosId);
                        card.style.transition = 'all 0.35s ease';
                        card.style.opacity    = '0';
                        card.style.transform  = 'scale(0.85)';
                        setTimeout(() => card.remove(), 350);
                        kosToast('Kos dihapus dari simpanan', 'info');
                    } else {
                        kosAlert({ ikon: '❌', judul: 'Gagal', pesan: 'Gagal menghapus, coba lagi.', tipe: 'gagal' });
                    }
                })
                .catch(() => kosAlert({ ikon: '❌', judul: 'Koneksi Gagal', pesan: 'Periksa koneksi kamu.', tipe: 'gagal' }));
        }

        function hapusFavorit(kosId, btn) {
            kosConfirm({
                ikon: '🗑️',
                judul: 'Hapus dari Simpanan?',
                pesan: 'Kos ini akan dihapus dari daftar simpanan kamu.',
                labelYa: 'Ya, Hapus',
                tipeYa: 'merah',
                onYa: function() { lanjutHapusFavorit(kosId, btn); }
            });
            return;
        }
    // Modal & Toast System
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

<?php include 'chat-widget.php'; ?>
</body>
</html>