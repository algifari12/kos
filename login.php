<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'pemilik') {
        header('Location: dashboard-pemilik.php');
    } else {
        header('Location: dashboard-pencari.php');
    }
    exit();
}

$role = isset($_GET['role']) ? $_GET['role'] : 'pencari';
if ($role !== 'pemilik' && $role !== 'pencari') { $role = 'pencari'; }
$role_text = $role === 'pemilik' ? 'Pemilik Kos' : 'Pencari Kos';
$wrapper_class = ($role === 'pencari') ? 'layout-reverse' : '';

$content_left = '
    <div class="header-left-container">
        <div class="back-nav-absolute">
            <a href="index.php" class="btn-back-arrow" title="Kembali">&#8592;</a>
        </div>
        <h1 class="header-title">Log In</h1>
    </div>
';

$content_right = '
    <div class="header-right-container">
        <div class="mini-logo">
            <img src="foto/logo.png" alt="Logo" class="logo-foto">
        </div>
    </div>
';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login <?php echo $role_text; ?> - Kos'ong?</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="main-wrapper <?php echo $wrapper_class; ?>">

        <div class="panel panel-branding">
            <div class="panel-header">
                <?php echo ($role === 'pencari') ? $content_right : $content_left; ?>
            </div>
            <div class="yellow-box">
                <div class="brand-container">
                    <div class="logo-box">
                        <img src="foto/logo.png" width="150" alt="Logo kosong">
                    </div>
                    <p class="tagline">Cari kos dengan sekali klik</p>
                </div>
            </div>
            <div class="panel-footer branding-footer-content">
                &copy; 2024 Kos'ong?
            </div>
        </div>

        <div class="panel panel-form">
            <div class="panel-header">
                <?php echo ($role === 'pencari') ? $content_left : $content_right; ?>
            </div>
            <div class="yellow-box">
                <div class="black-card">
                    <div class="form-title">Log In <?php echo $role_text; ?></div>
                    <div class="white-inner-box">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        <form action="proses/proses-login.php" method="POST">
                            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                            <input type="text" name="username" placeholder="Username" required>
                            <input type="password" name="password" placeholder="Password" required>
                            <button type="submit" class="btn-action">Berikutnya</button>
                            <div class="form-footer-link">
                                Belum punya akun?<br>
                                <a href="daftar.php?role=<?php echo $role; ?>">Silahkan daftar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="panel-footer form-footer-content">
                Privacy Policy
            </div>
        </div>

    </div>
</body>
</html>