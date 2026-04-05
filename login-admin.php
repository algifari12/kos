<?php
// login-admin.php — Halaman login khusus admin
// Akses via: kosong.test/login-admin.php
session_start();

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit();
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Kos'ong?</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #111;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 44px 40px;
            width: 100%;
            max-width: 400px;
            border: 3px solid #FFD700;
            box-shadow: 0 24px 60px rgba(0,0,0,0.4);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo h1 {
            font-size: 26px;
            font-weight: 800;
            color: #111;
        }
        .login-logo span {
            display: inline-block;
            background: #111;
            color: #FFD700;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 6px;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
            color: #333;
        }
        .form-group input {
            width: 100%;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 16px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            transition: border 0.2s;
        }
        .form-group input:focus { border-color: #FFD700; }
        .btn-login {
            width: 100%;
            background: #FFD700;
            border: 2.5px solid #000;
            padding: 13px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.25s;
            margin-top: 6px;
        }
        .btn-login:hover { background: #51CF66; }
        .alert-error {
            background: #FEE2E2;
            border: 1.5px solid #F87171;
            color: #991B1B;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: #888;
            text-decoration: none;
        }
        .back-link:hover { color: #333; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <h1>Kos'ong?</h1>
            <span>Admin Panel</span>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="proses/proses-login-admin.php" method="POST">
            <div class="form-group">
                <label>Username Admin</label>
                <input type="text" name="username" placeholder="admin" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">🔐 Masuk ke Panel Admin</button>
        </form>

        <a href="index.php" class="back-link">← Kembali ke halaman utama</a>
    </div>
</body>
</html>