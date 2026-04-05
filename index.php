<?php
require_once 'config/session.php';
// Halaman publik — session opsional
require_once 'config/database.php';

// Jika sudah login, arahkan ke dashboard sesuai role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'pemilik') {
        header("Location: dashboard-pemilik.php");
        exit();
    } elseif ($_SESSION['role'] === 'pencari') {
        header("Location: dashboard-pencari.php");
        exit();
    }
}

$query = "SELECT k.*, u.username as nama_pemilik, 
          COALESCE(AVG(r.rating), 5.0) as avg_rating,
          COUNT(DISTINCT r.review_id) as jumlah_review
          FROM kos k 
          LEFT JOIN users u ON k.pemilik_id = u.user_id
          LEFT JOIN booking b ON k.kos_id = b.kos_id
          LEFT JOIN review r ON b.booking_id = r.booking_id
          WHERE k.status = 'tersedia'
          GROUP BY k.kos_id
          ORDER BY k.kos_id DESC 
          LIMIT 6";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kos'ong? - Cari Kos Jadi Gampang</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        /* ── Filter card: active & dimmed state (sama dengan dashboard) ── */
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
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
    </div>

    <header class="navbar">
        <div class="navbar-container">
            <div class="navbar-left">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="nav-icon-group">
                        <a href="chat.php" class="icon-link"><img src="foto/chat.png" style="height:24px;"></a>
                        <a href="notifikasi.php" class="icon-link"><img src="foto/notif.png" style="height:24px;"></a>
                    </div>
                <?php else: ?>
                    <button class="btn-masuk-nav" onclick="openRoleModal()">Masuk<span class="arrow-icon">→</span></button>
                <?php endif; ?>
                <a href="#pusat-bantuan" class="help-center">Pusat Bantuan</a>
            </div>
            
            <div class="navbar-right">
                <div class="logo"><img src="foto/gool.png" alt="Logo"></div>
            </div>
        </div>
    </header>

    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-icon">🏠</div>
            <h1 class="hero-title">Cari kos jadi gampang</h1>
            <p class="hero-subtitle">tinggal klik - kos idamanmu ada di genggaman.</p>
            
            <div class="search-box-center">
                <input type="text" id="searchInput" placeholder="cth. ac, 500.000" class="search-input">
                <button class="search-btn" onclick="performSearch()">🔍</button>
            </div>
        </div>
    </section>

    <!-- ── FILTER SECTION: pakai data-tipe, sama persis dengan dashboard ── -->
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
            <?php if($result && mysqli_num_rows($result) > 0): ?>
                <?php while($kos = mysqli_fetch_assoc($result)): ?>
                <div class="kos-card" data-gender="<?php echo $kos['jenis_kos']; ?>" 
                    onclick="window.location.href='detail-kos.php?id=<?php echo $kos['kos_id']; ?>'">
                    
                    <div class="kos-image">
                        <?php 
                        $foto_array = explode(',', $kos['foto']);
                        $foto_utama = trim($foto_array[0]);
                        
                        if(!empty($foto_utama) && file_exists('uploads/' . $foto_utama)) {
                            $path_foto = 'uploads/' . $foto_utama;
                        } else {
                            $path_foto = 'https://via.placeholder.com/300x200?text=Foto+Kos';
                        }
                        ?>
                        <img src="<?php echo $path_foto; ?>" alt="<?php echo htmlspecialchars($kos['nama_kos']); ?>">
                    </div>

                    <div class="kos-info">
                        <div class="kos-price">Rp. <?php echo number_format($kos['harga'], 0, ',', '.'); ?>/Bulan</div>
                        <h3 class="kos-name"><?php echo htmlspecialchars($kos['nama_kos']); ?></h3>
                        <p class="kos-facilities">
                            <?php 
                                $teks = htmlspecialchars($kos['fasilitas']);
                                echo (strlen($teks) > 45) ? substr($teks, 0, 45) . '...' : $teks;
                            ?>
                        </p>
                        <div class="kos-footer">
                            <div class="kos-rating">
                                <?php echo number_format($kos['avg_rating'], 1); ?>
                                <span class="stars">
                                    <?php
                                    $rating = round($kos['avg_rating']);
                                    for($i = 1; $i <= 5; $i++) { echo $i <= $rating ? '⭐' : '☆'; }
                                    ?>
                                </span>
                            </div>
                            <button class="btn-gender">
                                <?php echo strtoupper($kos['jenis_kos']); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">🏠</div>
                    <h3>Belum ada kos yang tersedia</h3>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['role'] == 'pemilik'): ?>
                            <p>Mulai kelola properti Anda. <a href="tambah-kos.php">Tambah kos sekarang!</a></p>
                        <?php else: ?>
                            <p>Maaf, saat ini belum ada kos yang tersedia. Silakan cek kembali nanti.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Jadilah yang pertama untuk <a href="javascript:void(0)" onclick="openRoleModal()">mendaftarkan kos Anda</a></p>
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
                <p>Aplikasi <img src="foto/logo.png" width="50" alt="Logo Kos'ong"> ini hadir untuk memudahkan pencari hunian menemukan tempat tinggal yang sesuai kebutuhan dengan cepat dan transparan. Pengguna dapat mencari kos berdasarkan lokasi, harga, dan fasilitas, lalu langsung terhubung dengan pemilik kos. Bagi pemilik, aplikasi ini membantu mengelola dan mempromosikan kos secara praktis. Tujuan kami sederhana: menjadikan proses mencari kos lebih mudah, aman, dan menyenangkan</p>
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
                    <ul class="footer-social-list">
                        <li>
                            <img src="foto/wa.png" class="social-icon"> 
                            <span>+62 812 3456 7891</span>
                        </li>
                        <li>
                            <img src="foto/gmail.png" class="social-icon"> 
                            <span>Kos_ong@gmail.com</span>
                        </li>
                        <li>
                            <img src="foto/ig.png" class="social-icon"> 
                            <span>Kos'ong?</span>
                        </li>
                        <li>
                            <img src="foto/fb.png" class="social-icon"> 
                            <span>Kos'ong?</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-logo">
                <img src="foto/goool.png" width="100" alt="logo kosong">
            </div>
            <p>©2025 Kos'ong?.com All right reserved</p>
        </div>
    </footer>

    <script src="js/script.js"></script>

    <!-- Modal Pilih Role -->
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
        function openRoleModal() {
            document.getElementById('roleModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeRoleModal() {
            document.getElementById('roleModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        window.onclick = function(event) {
            if (event.target == document.getElementById('roleModal')) closeRoleModal();
        }

    </script>
</body>
</html>