<?php
require_once 'config/session.php';
requireRole('pemilik');
require_once 'config/database.php';


$pemilik_id = $_SESSION['user_id'];

// Handle aksi terima / tolak
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'], $_POST['booking_id'])) {
    $aksi = $_POST['aksi'] === 'terima' ? 'diterima' : 'ditolak';
    $booking_id = (int)$_POST['booking_id'];
    $conn->query("UPDATE booking SET status = '$aksi' WHERE booking_id = $booking_id");
    header("Location: notifikasi.php");
    exit();
}

$query = "SELECT b.*, k.nama_kos, k.foto, k.harga, k.jenis_kos,
          u.username as nama_pencari
          FROM booking b
          JOIN kos k ON b.kos_id = k.kos_id
          JOIN users u ON b.user_id = u.user_id
          WHERE k.pemilik_id = $pemilik_id
          ORDER BY b.tanggal_booking DESC";
$result = mysqli_query($conn, $query);

$total_pending = 0;
$all_bookings = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_bookings[] = $row;
        if ($row['status'] === 'pending') $total_pending++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi Pemilik - Kos'ong?</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
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
        }

        .notif-card.pending { border-left: 6px solid #FFD700; }
        .notif-card.diterima { border-left: 6px solid #34D399; }
        .notif-card.ditolak { border-left: 6px solid #F87171; }

        .notif-card-inner {
            display: flex;
            align-items: center;
            padding: 20px 25px;
            gap: 20px;
        }

        .notif-kos-img {
            width: 100px;
            height: 75px;
            object-fit: cover;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .notif-info {
            flex: 1;
        }

        .notif-info-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }

        .notif-kos-name {
            font-size: 17px;
            font-weight: 700;
            color: #333;
        }

        .notif-user {
            font-size: 14px;
            color: #555;
            margin-bottom: 4px;
        }

        .notif-user strong {
            color: #00B4D8;
        }

        .notif-date {
            font-size: 12px;
            color: #aaa;
        }

        .notif-harga {
            font-size: 14px;
            font-weight: 700;
            color: #00B4D8;
            margin-bottom: 4px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pending { background: #FFF3CD; color: #856404; border: 2px solid #FFD700; }
        .status-diterima { background: #D1FAE5; color: #065F46; border: 2px solid #34D399; }
        .status-ditolak { background: #FEE2E2; color: #991B1B; border: 2px solid #F87171; }

        /* Action Buttons */
        .notif-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn-terima {
            background: #FFD700;
            border: 2px solid #000;
            padding: 9px 22px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .btn-terima:hover {
            background: #51CF66;
            transform: scale(1.05);
        }

        .btn-tolak {
            background: #fff;
            border: 2px solid #F87171;
            color: #991B1B;
            padding: 9px 22px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .btn-tolak:hover {
            background: #FEE2E2;
            transform: scale(1.05);
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
        .empty-state p { color: #888; font-size: 15px; }

        .hidden { display: none !important; }

        /* Paksa layout flex column agar footer nempel ke konten */
        html { height: 100%; }
        body {
            min-height: 100% !important;
            height: auto !important;
            display: flex !important;
            flex-direction: column !important;
            overflow-x: hidden !important;
        }

        /* Bubble terkurung di viewport */
        .bubble-container {
            position: fixed !important;
            top: 0 !important; left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            overflow: hidden !important;
            pointer-events: none !important;
            z-index: 0 !important;
        }

        /* Navbar & konten di atas bubble */
        .navbar        { position: relative !important; z-index: 100 !important; }
        .notif-wrapper { position: relative !important; z-index: 1 !important; flex: 1; }

        /* Footer solid langsung setelah konten */
        .footer {
            position: relative !important;
            z-index: 10 !important;
            background: #FFD700 !important;
            margin-top: 0 !important;
            flex-shrink: 0 !important;
        }

        @media (max-width: 768px) {
            .notif-card-inner { flex-direction: column; align-items: flex-start; }
            .notif-actions { flex-direction: row; width: 100%; }
            .btn-terima, .btn-tolak { flex: 1; text-align: center; }
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
                            <a href="dashboard-pemilik.php">🏠 Dashboard</a>
                            <a href="logout.php" style="color:#e53e3e;">🚪 Keluar</a>
                        </div>
                    </div>
                    <a href="javascript:void(0)" class="icon-link" title="Pesan" onclick="cwToggle()">
                        <img src="foto/chat-icon.png" alt="Chat" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/589/589708.png'">
                    </a>
                    <a href="tambah-kos.php" class="icon-link" title="Tambah Kos">
                        <img src="foto/tambah-icon.png" alt="Tambah" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/992/992651.png'">
                    </a>
                    <a href="notifikasi.php" class="icon-link" title="Notifikasi" style="position:relative;">
                        <img src="foto/notif-icon.png" alt="Notifikasi" class="icon-img"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/3119/3119338.png'">
                        <?php if ($total_pending > 0): ?>
                        <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                            <?= $total_pending ?>
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
            <?php if ($total_pending > 0): ?>
                <span class="badge-count"><?= $total_pending ?> Baru</span>
            <?php endif; ?>
        </div>
        <p class="notif-subtitle">Pengajuan sewa dari calon penyewa kos Anda.</p>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="tab-btn active" onclick="filterTab('semua', this)">Semua</button>
            <button class="tab-btn" onclick="filterTab('pending', this)">⏳ Menunggu</button>
            <button class="tab-btn" onclick="filterTab('diterima', this)">✅ Diterima</button>
            <button class="tab-btn" onclick="filterTab('ditolak', this)">❌ Ditolak</button>
        </div>

        <?php if (count($all_bookings) > 0): ?>
            <?php foreach ($all_bookings as $b): ?>
                <?php
                    $foto_arr = explode(',', $b['foto']);
                    $foto_url = !empty(trim($foto_arr[0])) ? 'uploads/' . trim($foto_arr[0]) : 'https://via.placeholder.com/100x75?text=Kos';
                    $tgl = date('d M Y, H:i', strtotime($b['tanggal_booking']));

                    $status = $b['status'];
                    if ($status === 'pending') { $badge_class = 'status-pending'; $badge_icon = '⏳'; $badge_text = 'Menunggu'; }
                    elseif ($status === 'diterima') { $badge_class = 'status-diterima'; $badge_icon = '✅'; $badge_text = 'Diterima'; }
                    else { $badge_class = 'status-ditolak'; $badge_icon = '❌'; $badge_text = 'Ditolak'; }
                ?>
                <div class="notif-card <?= $status ?>" data-status="<?= $status ?>" data-booking="<?= $b['booking_id'] ?>">
                    <div class="notif-card-inner">
                        <img src="<?= $foto_url ?>" alt="<?= htmlspecialchars($b['nama_kos']) ?>" class="notif-kos-img">

                        <div class="notif-info">
                            <div class="notif-info-top">
                                <div class="notif-kos-name"><?= htmlspecialchars($b['nama_kos']) ?></div>
                                <span class="status-badge <?= $badge_class ?>"><?= $badge_icon ?> <?= $badge_text ?></span>
                            </div>
                            <div class="notif-harga">Rp <?= number_format($b['harga'], 0, ',', '.') ?> / Bulan</div>
                            <div class="notif-user">
                                Diajukan oleh: <strong><?= htmlspecialchars($b['nama_pencari']) ?></strong>

                            </div>
                            <div class="notif-date">📅 <?= $tgl ?></div>
                        </div>

                        <?php if ($status === 'pending'): ?>
                        <div class="notif-actions" id="actions-<?= $b['booking_id'] ?>">
                            <button class="btn-terima" onclick="aksBooking(<?= $b['booking_id'] ?>, 'terima', '<?= htmlspecialchars($b['nama_pencari']) ?>')">✅ Terima</button>
                            <button class="btn-tolak"  onclick="aksBooking(<?= $b['booking_id'] ?>, 'tolak',  '<?= htmlspecialchars($b['nama_pencari']) ?>')">❌ Tolak</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🔔</div>
                <h3>Belum Ada Notifikasi</h3>
                <p>Saat ada calon penyewa yang mengajukan sewa kos Anda, notifikasi akan muncul di sini.</p>
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
        // ── Dropdown Profile ──
        const profileToggle  = document.getElementById('profileToggle');
        const logoutDropdown = document.getElementById('logoutDropdown');
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            logoutDropdown.classList.toggle('show-menu');
        });
        window.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target)) logoutDropdown.classList.remove('show-menu');
        });

        // ── Filter Tab ──
        function filterTab(status, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.notif-card').forEach(card => {
                card.classList.toggle('hidden', status !== 'semua' && card.dataset.status !== status);
            });
        }

        // ── Terima / Tolak Booking via Fetch + kosConfirm ──
        function aksBooking(bookingId, aksi, nama) {
            const label  = aksi === 'terima' ? 'Terima' : 'Tolak';
            const ikon   = aksi === 'terima' ? '✅' : '❌';
            const warna  = aksi === 'terima' ? 'hijau' : 'merah';
            const judul  = aksi === 'terima' ? 'Terima Pengajuan?' : 'Tolak Pengajuan?';
            const pesan  = aksi === 'terima'
                ? `Konfirmasi menerima pengajuan sewa dari ${nama}. Pencari akan diberitahu.`
                : `Konfirmasi menolak pengajuan sewa dari ${nama}. Tindakan ini tidak dapat dibatalkan.`;

            kosConfirm({
                ikon:    ikon,
                judul:   judul,
                pesan:   pesan,
                labelYa: label,
                tipeYa:  warna,
                onYa: function() {
                    // Disable tombol supaya tidak diklik dua kali
                    const actionsDiv = document.getElementById('actions-' + bookingId);
                    if (actionsDiv) {
                        actionsDiv.querySelectorAll('button').forEach(b => {
                            b.disabled = true;
                            b.style.opacity = '0.5';
                        });
                    }

                    const fd = new FormData();
                    fd.append('booking_id', bookingId);
                    fd.append('aksi', aksi);

                    fetch('proses/proses-booking-aksi.php', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
                        .then(r => {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.text(); // ambil sebagai text dulu
                        })
                        .then(txt => {
                            let d;
                            try { d = JSON.parse(txt); }
                            catch(e) {
                                console.error('Response bukan JSON:', txt);
                                throw new Error('Response tidak valid dari server');
                            }
                            return d;
                        })
                        .then(d => {
                            if (d.status === 'success') {
                                // Update tampilan kartu tanpa reload halaman
                                updateKartu(bookingId, aksi);
                                kosToast(
                                    aksi === 'terima'
                                        ? `✅ Booking ${nama} diterima!`
                                        : `❌ Booking ${nama} ditolak.`,
                                    aksi === 'terima' ? 'sukses' : 'gagal'
                                );
                            } else {
                                kosAlert({ ikon: '❌', judul: 'Gagal', pesan: d.message || 'Coba lagi.', tipe: 'gagal' });
                                // Re-enable tombol
                                if (actionsDiv) actionsDiv.querySelectorAll('button').forEach(b => {
                                    b.disabled = false; b.style.opacity = '1';
                                });
                            }
                        })
                        .catch((err) => {
                            console.error('Fetch error:', err);
                            kosAlert({ ikon: '❌', judul: 'Koneksi Gagal', pesan: 'Error: ' + err.message, tipe: 'gagal' });
                            if (actionsDiv) actionsDiv.querySelectorAll('button').forEach(b => {
                                b.disabled = false; b.style.opacity = '1';
                            });
                        });
                }
            });
        }

        // Update tampilan kartu setelah aksi berhasil (tanpa reload)
        function updateKartu(bookingId, aksi) {
            const card       = document.querySelector('.notif-card[data-booking="' + bookingId + '"]');
            const actionsDiv = document.getElementById('actions-' + bookingId);
            if (!card) return;

            // Update data-status untuk filter tab
            card.dataset.status = aksi === 'terima' ? 'diterima' : 'ditolak';

            // Ganti warna border kiri
            card.classList.remove('pending');
            card.classList.add(aksi === 'terima' ? 'diterima' : 'ditolak');

            // Ganti badge status
            const badge = card.querySelector('.status-badge');
            if (badge) {
                if (aksi === 'terima') {
                    badge.className = 'status-badge status-diterima';
                    badge.textContent = '✅ Diterima';
                } else {
                    badge.className = 'status-badge status-ditolak';
                    badge.textContent = '❌ Ditolak';
                }
            }

            // Hapus tombol aksi dengan animasi fade
            if (actionsDiv) {
                actionsDiv.style.transition = 'opacity 0.4s';
                actionsDiv.style.opacity = '0';
                setTimeout(() => actionsDiv.remove(), 400);
            }

            // Update badge counter di navbar
            const badgeEl = document.querySelector('.notif-title + .badge-count');
            if (badgeEl) {
                const current = parseInt(badgeEl.textContent) || 0;
                if (current > 1) {
                    badgeEl.textContent = (current - 1) + ' Baru';
                } else {
                    badgeEl.remove();
                }
            }
        }
    </script>

<?php include 'chat-widget.php'; ?>
</body>
</html>