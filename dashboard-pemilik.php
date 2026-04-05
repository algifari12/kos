<?php
require_once 'config/session.php';
requireRole('pemilik');
require_once 'config/database.php';


$user_id = $_SESSION['user_id'];

// Hitung notifikasi pending milik pemilik ini (untuk badge)
$notif_result = mysqli_query($conn, 
    "SELECT COUNT(*) as total 
     FROM booking b 
     JOIN kos k ON b.kos_id = k.kos_id 
     WHERE k.pemilik_id = $user_id AND b.status = 'pending'"
);
$total_notif = $notif_result ? mysqli_fetch_assoc($notif_result)['total'] : 0;

// Tampilkan SEMUA kos dari semua pemilik (sama seperti dashboard-pencari & index)
$query = "SELECT k.*, u.username as nama_pemilik, 
          COALESCE(AVG(r.rating), 5.0) as avg_rating,
          COUNT(DISTINCT r.review_id) as jumlah_review
          FROM kos k 
          LEFT JOIN users u ON k.pemilik_id = u.user_id
          LEFT JOIN booking b ON k.kos_id = b.kos_id
          LEFT JOIN review r ON b.booking_id = r.booking_id
          WHERE k.status = 'tersedia'
          GROUP BY k.kos_id
          ORDER BY k.kos_id DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pemilik - Kos'ong?</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">


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
                        <?php if ($total_notif > 0): ?>
                        <span style="position:absolute;top:-5px;right:-5px;background:#FF4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                            <?= $total_notif ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </div>
                <a href="#pusat-bantuan" class="help-center">Pusat Bantuan</a>
            </div>

            <div class="navbar-right">
                <div class="logo">
                    <img src="foto/gool.png" alt="Kos'ong?">
                </div>
            </div>
        </div>
    </header>

    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-icon">🏠</div>
            <h1 class="hero-title">Cari kos jadi gampang</h1>
            <p class="hero-subtitle">tinggal klik - kos idamanmu ada di genggaman.</p>
            <div class="search-box-center">
                <input type="text" id="searchInput" placeholder="pria, ac, 500.000" class="search-input">
                <button class="search-btn" onclick="performSearch()">🔍</button>
            </div>
        </div>
    </section>

    <section class="filter-section">
        <div class="filter-container">
            <div class="filter-card" data-tipe="pria" onclick="filterKos('pria')">
                <div class="gender-icon">
                    <img src="foto/pria.png" alt="Pria" class="icon-img-filter">
                </div>
                <p class="filter-label">Pria</p>
            </div>
            <div class="filter-card filter-campur" data-tipe="campur" onclick="filterKos('campur')">
                <div class="gender-icon">
                    <img src="foto/campur.png" alt="Campur" class="icon-img-filter">
                </div>
                <p class="filter-label">Campur</p>
            </div>
            <div class="filter-card" data-tipe="wanita" onclick="filterKos('wanita')">
                <div class="gender-icon">
                    <img src="foto/wanita.png" alt="Wanita" class="icon-img-filter">
                </div>
                <p class="filter-label">Wanita</p>
            </div>
        </div>
    </section>

    <div class="search-advanced">
        <button class="btn-search-advanced" onclick="toggleAdvancedSearch()">
            <span class="double-arrow">«</span> Cari kos Berdasarkan <span class="double-arrow">»</span>
        </button>
    </div>

    <section class="kos-listing" id="kos-listing">
        <div class="kos-grid">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($kos = mysqli_fetch_assoc($result)): ?>
                <div class="kos-card" data-gender="<?= $kos['jenis_kos'] ?>"
                    onclick="window.location.href='detail-kos.php?id=<?= $kos['kos_id'] ?>'">

                    <div class="kos-image">
                        <?php
                            $foto_array = explode(',', $kos['foto']);
                            $foto_utama = trim($foto_array[0]);
                            $path_foto  = (!empty($foto_utama) && file_exists('uploads/' . $foto_utama))
                                ? 'uploads/' . $foto_utama
                                : 'https://via.placeholder.com/300x200?text=Foto+Kos';
                        ?>
                        <img src="<?= $path_foto ?>" alt="<?= htmlspecialchars($kos['nama_kos']) ?>">
                    </div>

                    <div class="kos-info">
                        <div class="kos-price">Rp. <?= number_format($kos['harga'], 0, ',', '.') ?>/Bulan</div>
                        <h3 class="kos-name"><?= htmlspecialchars($kos['nama_kos']) ?></h3>
                        <p class="kos-facilities">
                            <?php
                                $teks = htmlspecialchars($kos['fasilitas']);
                                echo (strlen($teks) > 45) ? substr($teks, 0, 45) . '...' : $teks;
                            ?>
                        </p>
                        <div class="kos-footer">
                            <div class="kos-rating">
                                <?= number_format($kos['avg_rating'], 1) ?>
                                <span class="stars">
                                    <?php
                                        $rating = round($kos['avg_rating']);
                                        for ($i = 1; $i <= 5; $i++) echo $i <= $rating ? '⭐' : '☆';
                                    ?>
                                </span>
                            </div>
                            <button class="btn-gender"><?= strtoupper($kos['jenis_kos']) ?></button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">🏠</div>
                    <h3>Belum ada kos yang tersedia</h3>
                    <?php if ($_SESSION['role'] == 'pemilik'): ?>
                        <p>Mulai kelola bisnis Anda. <a href="tambah-kos.php">Tambah kos pertama Anda sekarang!</a></p>
                    <?php else: ?>
                        <p>Coba cari dengan kata kunci lain.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="about-section">
        <div class="about-content">
            <div class="logo-large">
                <img src="foto/logo.png" width="200" alt="Logo Kos'ong">
            </div>
            <div class="about-text">
                <p>Aplikasi <img src="foto/logo.png" width="50" alt="Logo Kos'ong"> ini hadir untuk memudahkan pencari hunian menemukan tempat tinggal yang sesuai kebutuhan dengan cepat dan transparan. Pengguna dapat mencari kos berdasarkan lokasi, harga, dan fasilitas, lalu langsung terhubung dengan pemilik kos. Bagi pemilik, aplikasi ini membantu mengelola dan mempromosikan kos secara praktis. Tujuan kami sederhana: menjadikan proses mencari kos lebih mudah, aman, dan menyenangkan.</p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-left">
                <div class="footer-info">
                    <h4>Aplikasi ini masih dalam proses pengembangan akan segera hadir di</h4>
                    <img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" alt="Google Play" class="playstore-badge">
                </div>
            </div>
            <div class="footer-center">
                <div class="footer-column">
                    <h4>Kos'ong?</h4>
                    <ul>
                        <li><a href="#tentang">Tentang Kami</a></li>
                        <li><a href="#bantuan">Pusat Bantuan</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Kebijakan</h4>
                    <ul>
                        <li><a href="#privasi">Kebijakan Privasi</a></li>
                        <li><a href="#syarat">Syarat dan Ketentuan</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Hubungi Kami</h4>
                    <ul>
                        <li>📞 +62 812 3456 7891</li>
                        <li>✉️ Kos_ong@gmail.com</li>
                        <li>📷 Kos'ong?</li>
                        <li>💬 Kos'ong?</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-logo">
                <img src="foto/gool.png" width="100" alt="logo kosong">
            </div>
            <p>©2025 Kos'ong?.com All right reserved</p>
        </div>
    </footer>

    <script>
        const profileToggle = document.getElementById('profileToggle');
        const logoutDropdown = document.getElementById('logoutDropdown');
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            logoutDropdown.classList.toggle('show-menu');
        });
        window.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target)) logoutDropdown.classList.remove('show-menu');
        });
    </script>


    <script src="js/script.js"></script>

<?php include 'chat-widget.php'; ?>

    <!-- ====== FILTER CARD AKTIF/NONAKTIF ====== -->
    <style>
        .filter-card {
            cursor: pointer;
            transition: transform 0.25s ease, opacity 0.25s ease, box-shadow 0.25s ease;
        }
        .filter-card.fc-dipilih {
            transform: scale(1.1) !important;
            box-shadow: 0 8px 24px rgba(255,215,0,0.5) !important;
            opacity: 1 !important;
        }
        .filter-card.fc-redup {
            transform: scale(0.88) !important;
            opacity: 0.32 !important;
            filter: grayscale(100%) !important;
            box-shadow: none !important;
        }
    </style>
    <script>
        var fcAktif = null;
        function filterKos(gender) {
            var cards    = document.querySelectorAll('.filter-card');
            var kosCards = document.querySelectorAll('.kos-card');
            if (fcAktif === gender) {
                fcAktif = null;
                cards.forEach(function(c) {
                    c.classList.remove('fc-dipilih', 'fc-redup');
                });
                kosCards.forEach(function(k) { k.style.display = ''; });
            } else {
                fcAktif = gender;
                cards.forEach(function(c) {
                    var tipe = c.getAttribute('data-tipe');
                    if (tipe === gender) {
                        c.classList.add('fc-dipilih');
                        c.classList.remove('fc-redup');
                    } else {
                        c.classList.add('fc-redup');
                        c.classList.remove('fc-dipilih');
                    }
                });
                kosCards.forEach(function(k) {
                    k.style.display = (k.getAttribute('data-gender') === gender) ? '' : 'none';
                });
            }
        }
    </script>

</body>
</html>