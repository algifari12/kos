<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$user_id = (int)$_SESSION['user_id'];
$r  = mysqli_query($conn, "SELECT COUNT(*) as total FROM booking b JOIN kos k ON b.kos_id = k.kos_id WHERE k.pemilik_id = $user_id AND b.status = 'pending'");
$r2 = mysqli_query($conn, "SELECT COUNT(*) as total FROM chat WHERE receiver_id = $user_id AND dibaca = 0");
$total_notif      = $r  ? mysqli_fetch_assoc($r)['total']  : 0;
$total_chat_unread = $r2 ? mysqli_fetch_assoc($r2)['total'] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kos - Kos'ong?</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/tambah-kos.css">
</head>
<body>

    <div class="bubble-container">
        <div class="bubble"></div><div class="bubble"></div>
        <div class="bubble"></div><div class="bubble"></div>
        <div class="bubble"></div><div class="bubble"></div>
        <div class="bubble"></div><div class="bubble"></div>
    </div>

    <!-- ── HEADER ── -->
    <div class="tambah-subheader">
        <a href="dashboard-pemilik.php" class="btn-back-sub">❮</a>
        <div class="title-yellow-box">Tambah Kos</div>
        <div class="header-logo-right">
            <img src="foto/gool.png" alt="Kos'ong?">
        </div>
    </div>

    <!-- ── STEPPER ── -->
    <nav class="stepper">
        <div class="step active" id="step-nav-1">
            <img src="https://cdn-icons-png.flaticon.com/512/25/25694.png" class="step-icon" alt="">
            Data Kos
        </div>
        <div class="step" id="step-nav-2">
            <img src="https://cdn-icons-png.flaticon.com/512/93/93158.png" class="step-icon" alt="">
            Fasilitas
        </div>
        <div class="step" id="step-nav-3">
            <img src="https://cdn-icons-png.flaticon.com/512/685/685655.png" class="step-icon" alt="">
            Foto
        </div>
        <div class="step" id="step-nav-4">
            <img src="https://cdn-icons-png.flaticon.com/512/1250/1250615.png" class="step-icon" alt="">
            Deskripsi
        </div>
    </nav>

    <form action="proses/proses-tambah.php" method="POST" enctype="multipart/form-data" id="formTambahKos">

        <!-- ══════════════════════════════
             STEP 1 — Data Kos
        ══════════════════════════════ -->
        <div class="form-container" id="form-step-1">
            <h3>🏠 Informasi Dasar Kos</h3>
            <hr class="divider">

            <div class="form-group">
                <label>Nama Kos <span style="color:#FF4444;">*</span></label>
                <input type="text" name="nama_kos" class="form-control"
                       placeholder="Contoh: Kos Melati Indah" required>
            </div>

            <div class="form-group">
                <label>Titik Koordinat Lokasi</label>
                <div class="input-with-icon">
                    <span class="input-icon">📍</span>
                    <input type="text" name="koordinat" class="form-control"
                           placeholder="Contoh: -0.5486, 123.0614">
                </div>
                <small class="input-hint">*Buka G-Maps → Tekan lama lokasi → Klik kotak koordinat → Salin angka</small>
            </div>

            <div class="form-group">
                <label>Jenis Kos <span style="color:#FF4444;">*</span></label>
                <div class="select-wrapper">
                    <select name="jenis_kos" id="jenis_kos" class="form-control form-select" required>
                        <option value="" disabled selected>— Pilih Jenis Kos —</option>
                        <option value="pria">👦 Kos Pria</option>
                        <option value="wanita">👧 Kos Wanita</option>
                        <option value="campur">👫 Kos Campur</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Kamar Tersedia (Stok) <span style="color:#FF4444;">*</span></label>
                <input type="number" name="stok_kamar" class="form-control"
                       placeholder="Contoh: 5" min="1" required>
            </div>

            <div class="form-group">
                <label>Harga per Bulan <span style="color:#FF4444;">*</span></label>
                <div class="input-with-icon">
                    <span class="input-icon">Rp</span>
                    <input type="number" name="harga" class="form-control"
                           placeholder="500000" min="0" required>
                </div>
            </div>

            <div class="form-group">
                <label>Ukuran Kamar</label>
                <div class="input-with-icon">
                    <input type="text" name="ukuran" class="form-control"
                           placeholder="Contoh: 3x4">
                    <span class="input-unit">Meter</span>
                </div>
            </div>

            <div class="form-group">
                <label>Alamat Lengkap <span style="color:#FF4444;">*</span></label>
                <textarea name="alamat" class="form-control" rows="3"
                          placeholder="Jl. Sudirman No. 123, Kel. Xxx, Kec. Xxx..."></textarea>
            </div>

            <div class="btn-next-container">
                <button type="button" class="btn-next" onclick="validasiStep1()">
                    Berikutnya ➔
                </button>
            </div>
        </div>

        <!-- ══════════════════════════════
             STEP 2 — Fasilitas
        ══════════════════════════════ -->
        <div class="form-container" id="form-step-2" style="display:none;">
            <h3>✅ Fasilitas Kos</h3>
            <hr class="divider">

            <div class="facilities-grid">
                <label class="facility-card">
                    <input type="checkbox" name="fasilitas[]" value="Wi-Fi">
                    <img src="https://cdn-icons-png.flaticon.com/512/93/93158.png" class="facility-icon" alt="">
                    <span class="facility-label">Wi-Fi</span>
                </label>
                <label class="facility-card">
                    <input type="checkbox" name="fasilitas[]" value="Listrik">
                    <img src="https://cdn-icons-png.flaticon.com/512/616/616494.png" class="facility-icon" alt="">
                    <span class="facility-label">Listrik</span>
                </label>
                <label class="facility-card">
                    <input type="checkbox" name="fasilitas[]" value="Air">
                    <img src="https://cdn-icons-png.flaticon.com/512/3105/3105807.png" class="facility-icon" alt="">
                    <span class="facility-label">Air</span>
                </label>
                <label class="facility-card">
                    <input type="checkbox" name="fasilitas[]" value="AC">
                    <img src="https://cdn-icons-png.flaticon.com/512/911/911409.png" class="facility-icon" alt="">
                    <span class="facility-label">AC</span>
                </label>
                <label class="facility-card">
                    <input type="checkbox" name="fasilitas[]" value="Parkiran">
                    <img src="https://cdn-icons-png.flaticon.com/512/2830/2830305.png" class="facility-icon" alt="">
                    <span class="facility-label">Parkiran</span>
                </label>
                <label class="facility-card">
                    <input type="checkbox" name="fasilitas[]" value="Dapur Umum">
                    <img src="https://cdn-icons-png.flaticon.com/512/1698/1698691.png" class="facility-icon" alt="">
                    <span class="facility-label">Dapur</span>
                </label>
            </div>

            <div class="form-group" style="margin-top:24px;">
                <label>Fasilitas Tambahan Lainnya</label>
                <textarea name="fasilitas_tambahan" class="form-control" rows="3"
                          placeholder="Contoh: Kasur, Lemari, Kamar mandi dalam, dll."></textarea>
            </div>

            <div class="btn-next-container" style="justify-content:space-between;">
                <button type="button" class="btn-next btn-back-gray" onclick="goToStep(1)">❮ Kembali</button>
                <button type="button" class="btn-next" onclick="goToStep(3)">Berikutnya ➔</button>
            </div>
        </div>

        <!-- ══════════════════════════════
             STEP 3 — Foto
        ══════════════════════════════ -->
        <div class="form-container" id="form-step-3" style="display:none;">
            <h3>📷 Upload Foto Kos</h3>
            <hr class="divider">

            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                <div class="upload-instruction">
                    <img src="https://cdn-icons-png.flaticon.com/512/338/338914.png"
                         style="width:58px;opacity:0.45;" alt="">
                    <p>Klik untuk memilih foto kos</p>
                    <small style="color:#bbb;">JPG, PNG, WEBP — Maks. 5MB per foto</small>
                </div>
                <input type="file" name="foto_kos[]" id="fileInput"
                       multiple accept="image/*" style="display:none;">
            </div>

            <p style="margin-top:18px;font-size:14px;color:#555;">
                Foto terpilih: <strong id="countText">0</strong> file
            </p>
            <div class="photo-preview-grid" id="previewGrid"></div>

            <div class="btn-next-container" style="justify-content:space-between;margin-top:28px;">
                <button type="button" class="btn-next btn-back-gray" onclick="goToStep(2)">❮ Kembali</button>
                <button type="button" class="btn-next" onclick="goToStep(4)">Berikutnya ➔</button>
            </div>
        </div>

        <!-- ══════════════════════════════
             STEP 4 — Deskripsi
        ══════════════════════════════ -->
        <div class="form-container" id="form-step-4" style="display:none;">
            <h3>📝 Deskripsi & Peraturan</h3>
            <hr class="divider">

            <div class="form-group">
                <label>Deskripsi Singkat</label>
                <textarea name="deskripsi" class="form-control" rows="4"
                          placeholder="Jelaskan keunggulan kos Anda — lokasi, suasana, keamanan, dll."></textarea>
            </div>

            <div class="form-group">
                <label>Peraturan Kos</label>
                <textarea name="peraturan" class="form-control" rows="4"
                          placeholder="Contoh: Jam malam pukul 22.00, dilarang merokok di dalam kamar, tamu dilarang menginap..."></textarea>
            </div>

            <div class="form-group">
                <label>Nomor WhatsApp Aktif <span style="color:#FF4444;">*</span></label>
                <div class="input-with-icon">
                    <span class="input-icon">📱</span>
                    <input type="text" name="whatsapp" class="form-control"
                           placeholder="Contoh: 081234567890" required>
                </div>
                <small class="input-hint">*Nomor ini akan digunakan pencari untuk menghubungi Anda</small>
            </div>

            <div class="btn-next-container" style="justify-content:space-between;">
                <button type="button" class="btn-next btn-back-gray" onclick="goToStep(3)">❮ Kembali</button>
                <button type="button" class="btn-next btn-simpan" onclick="validasiSubmit()">
                    🚀 Publikasikan Kos
                </button>
            </div>
        </div>

    </form>

    <!-- ── FOOTER ── -->
    <footer class="footer-yellow">
        <div class="footer-logo">
            <img src="foto/goool.png" alt="Kos'ong?">
        </div>
        <div class="footer-copyright">©2025 Kos'ong?.com All right reserved</div>
    </footer>

    <script>
        // ── Navigasi Stepper ──
        function goToStep(step) {
            document.querySelectorAll('.form-container').forEach(f => f.style.display = 'none');
            document.getElementById('form-step-' + step).style.display = 'block';
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById('step-nav-' + step).classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ── Validasi Step 1 ──
        function validasiStep1() {
            const nama   = document.querySelector('[name="nama_kos"]').value.trim();
            const jenis  = document.getElementById('jenis_kos').value;
            const stok   = document.querySelector('[name="stok_kamar"]').value;
            const harga  = document.querySelector('[name="harga"]').value;
            const alamat = document.querySelector('[name="alamat"]').value.trim();

            if (!nama)   return kosAlert({ ikon:'⚠️', judul:'Nama Kosong',    pesan:'Nama kos wajib diisi.',                       tipe:'gagal' });
            if (!jenis)  return kosAlert({ ikon:'⚠️', judul:'Jenis Belum Dipilih', pesan:'Silakan pilih jenis kos dari dropdown.', tipe:'gagal' });
            if (!stok)   return kosAlert({ ikon:'⚠️', judul:'Stok Kosong',     pesan:'Jumlah kamar tersedia wajib diisi.',          tipe:'gagal' });
            if (!harga)  return kosAlert({ ikon:'⚠️', judul:'Harga Kosong',    pesan:'Harga kos per bulan wajib diisi.',            tipe:'gagal' });
            if (!alamat) return kosAlert({ ikon:'⚠️', judul:'Alamat Kosong',   pesan:'Alamat kos wajib diisi.',                    tipe:'gagal' });

            goToStep(2);
        }

        // ── Validasi Step 4 & Submit ──
        function validasiSubmit() {
            const wa = document.querySelector('[name="whatsapp"]').value.trim();
            if (!wa) return kosAlert({ ikon:'⚠️', judul:'WhatsApp Kosong', pesan:'Nomor WhatsApp wajib diisi agar pencari bisa menghubungi Anda.', tipe:'gagal' });

            kosConfirm({
                ikon: '🏠',
                judul: 'Publikasikan Kos?',
                pesan: 'Kos akan langsung tampil di halaman pencarian setelah disimpan.',
                labelYa: '🚀 Ya, Publikasikan',
                tipeYa: 'hijau',
                onYa: function() {
                    document.getElementById('formTambahKos').submit();
                }
            });
        }

        // ── Preview Foto ──
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const files = e.target.files;
            const grid  = document.getElementById('previewGrid');
            document.getElementById('countText').textContent = files.length;
            grid.innerHTML = '';
            Array.from(files).forEach(function(file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const div = document.createElement('div');
                    div.className = 'preview-box';
                    div.innerHTML = `<img src="${ev.target.result}" alt="">`;
                    grid.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        });

        // ── Cek URL params (sukses/gagal dari redirect) ──
        window.addEventListener('DOMContentLoaded', function() {
            const p = new URLSearchParams(window.location.search);
            if (p.get('status') === 'sukses') {
                kosAlert({
                    ikon:  '🎉',
                    judul: 'Kos Berhasil Dipublikasikan!',
                    pesan: 'Kos kamu sudah tampil di halaman pencarian. Tunggu booking masuk ya!',
                    tipe:  'sukses',
                    onOk:  function() { window.location.href = 'dashboard-pemilik.php'; }
                });
            } else if (p.get('status') === 'gagal') {
                kosAlert({
                    ikon:  '❌',
                    judul: 'Gagal Menyimpan',
                    pesan: p.get('pesan') || 'Terjadi kesalahan, silakan coba lagi.',
                    tipe:  'gagal'
                });
            }
        });

        // ──────────────────────────────────────────
        //  CUSTOM MODAL SYSTEM
        // ──────────────────────────────────────────
        (function() {
            function initModalDOM() {
                if (document.getElementById('kosModalOverlay')) return;
                const ov = document.createElement('div');
                ov.className = 'kos-modal-overlay';
                ov.id = 'kosModalOverlay';
                ov.innerHTML = `
                    <div class="kos-modal-box">
                        <div class="kos-modal-icon"  id="kosModalIcon"></div>
                        <div class="kos-modal-judul" id="kosModalJudul"></div>
                        <div class="kos-modal-pesan" id="kosModalPesan"></div>
                        <div class="kos-modal-btns"  id="kosModalBtns"></div>
                    </div>`;
                document.body.appendChild(ov);
                if (!document.getElementById('kosToastEl')) {
                    const t = document.createElement('div');
                    t.className = 'kos-toast'; t.id = 'kosToastEl';
                    document.body.appendChild(t);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initModalDOM);
            } else { initModalDOM(); }

            let toastTimer = null;

            window.kosToast = function(pesan, tipe) {
                const el = document.getElementById('kosToastEl');
                if (!el) return;
                const warna = tipe==='sukses' ? '#2d8f4e' : tipe==='gagal' ? '#c0392b' : '#1a1a2e';
                const ikon  = tipe==='sukses' ? '✅ ' : tipe==='gagal' ? '❌ ' : 'ℹ️ ';
                el.textContent = ikon + pesan;
                el.style.background = warna;
                el.classList.add('tampil');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(() => el.classList.remove('tampil'), 2800);
            };

            window.kosAlert = function(opts) {
                const ov = document.getElementById('kosModalOverlay');
                if (!ov) return;
                const ikon    = opts.ikon || (opts.tipe==='sukses' ? '✅' : opts.tipe==='gagal' ? '❌' : 'ℹ️');
                const warnaBtn = opts.tipe==='gagal' ? 'merah' : opts.tipe==='sukses' ? 'hijau' : '';
                document.getElementById('kosModalIcon').textContent  = ikon;
                document.getElementById('kosModalJudul').textContent = opts.judul || 'Informasi';
                document.getElementById('kosModalPesan').textContent = opts.pesan || '';
                document.getElementById('kosModalBtns').innerHTML =
                    `<button class="kos-modal-btn btn-ya ${warnaBtn}" id="kosModalOk">Oke</button>`;
                ov.classList.add('aktif');
                document.getElementById('kosModalOk').onclick = function() {
                    ov.classList.remove('aktif');
                    if (opts.onOk) opts.onOk();
                };
            };

            window.kosConfirm = function(opts) {
                const ov = document.getElementById('kosModalOverlay');
                if (!ov) return;
                document.getElementById('kosModalIcon').textContent  = opts.ikon  || '❓';
                document.getElementById('kosModalJudul').textContent = opts.judul || 'Konfirmasi';
                document.getElementById('kosModalPesan').textContent = opts.pesan || '';
                document.getElementById('kosModalBtns').innerHTML =
                    `<button class="kos-modal-btn btn-tidak" id="kosModalTidak">Batal</button>
                     <button class="kos-modal-btn btn-ya ${opts.tipeYa||''}" id="kosModalYa">${opts.labelYa||'Ya'}</button>`;
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
                    if (e.target === ov) { ov.classList.remove('aktif'); if (opts.onTidak) opts.onTidak(); }
                };
            };
        })();
    </script>

</body>
</html>