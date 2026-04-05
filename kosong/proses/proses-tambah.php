<?php
session_start();
// Memanggil koneksi database dengan naik satu tingkat dari folder 'proses'
require_once '../config/database.php';

// Proteksi: Pastikan hanya user dengan role 'pemilik' yang bisa mengakses file ini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pemilik_id = $_SESSION['user_id'];

    // 1. Ambil & Sanitasi Data Dasar (Step 1)
    $nama_kos = mysqli_real_escape_string($conn, $_POST['nama_kos']);
    // Menyimpan Link Google Maps ke kolom koordinat
    $koordinat = mysqli_real_escape_string($conn, $_POST['koordinat']);
    $jenis_kos = mysqli_real_escape_string($conn, $_POST['jenis_kos']);
    $stok_kamar = (int)$_POST['stok_kamar'];
    $harga = (int)$_POST['harga'];
    $ukuran_kamar = mysqli_real_escape_string($conn, $_POST['ukuran']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

    // 2. Ambil Data Fasilitas (Step 2)
    // Menggabungkan pilihan checkbox fasilitas menjadi satu string teks
    $fasilitas_array = isset($_POST['fasilitas']) ? $_POST['fasilitas'] : [];
    $fasilitas_tambahan = mysqli_real_escape_string($conn, $_POST['fasilitas_tambahan']);
    
    if (!empty($fasilitas_tambahan)) {
        array_push($fasilitas_array, $fasilitas_tambahan);
    }
    $fasilitas_final = implode(', ', $fasilitas_array);

    // 3. Proses Media Upload Banyak Foto (Step 3)
    $daftar_foto = [];
    $target_dir = "../uploads/";

    // Membuat folder uploads secara otomatis jika belum ada
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    if (!empty($_FILES['foto_kos']['name'][0])) {
        foreach ($_FILES['foto_kos']['tmp_name'] as $key => $tmp_name) {
            // Memberikan nama unik menggunakan timestamp agar tidak duplikat
            $file_name = time() . "_" . $_FILES['foto_kos']['name'][$key];
            $target_file = $target_dir . basename($file_name);
            
            if (move_uploaded_file($tmp_name, $target_file)) {
                $daftar_foto[] = $file_name;
            }
        }
    }
    // Menggabungkan semua nama file foto menjadi satu string dipisahkan koma
    $foto_string = implode(',', $daftar_foto);

    // 4. Ambil Data Deskripsi & Kontak (Step 4)
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $peraturan = mysqli_real_escape_string($conn, $_POST['peraturan']);
    $whatsapp = mysqli_real_escape_string($conn, $_POST['whatsapp']);
    $status = 'tersedia';

    // 5. Eksekusi Query Insert
    $sql = "INSERT INTO kos (
                pemilik_id, nama_kos, koordinat, alamat, harga, 
                fasilitas, foto, deskripsi, peraturan, jenis_kos, 
                stok_kamar, ukuran_kamar, whatsapp, status
            ) VALUES (
                '$pemilik_id', '$nama_kos', '$koordinat', '$alamat', '$harga', 
                '$fasilitas_final', '$foto_string', '$deskripsi', '$peraturan', '$jenis_kos', 
                '$stok_kamar', '$ukuran_kamar', '$whatsapp', '$status'
            )";

    if (mysqli_query($conn, $sql)) {
        // Notifikasi sukses dan kembali ke dashboard
        echo "<script>
                alert('Berhasil mendaftarkan kos!');
                window.location.href='../dashboard-pemilik.php';
              </script>";
    } else {
        echo "Error database: " . mysqli_error($conn);
    }
}
?>