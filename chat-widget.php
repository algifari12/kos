<?php
/**
 * chat-widget.php
 * Include file ini di halaman manapun yang ingin punya chat bubble
 * Cara pakai: <?php include 'chat-widget.php'; ?>
 * Letakkan sebelum </body>
 */

if (!isset($_SESSION['user_id'])) return; // Hanya tampil jika sudah login

$cw_user_id = (int)$_SESSION['user_id'];
$cw_role    = $_SESSION['role'] ?? '';
$cw_username = $_SESSION['username'] ?? 'User';

// Ambil kontak (sama seperti chat.php)
if ($cw_role === 'pencari') {
    // Pencari: tampil pemilik yang pernah dihubungi via chat
    $cw_q = "
        SELECT u.user_id, u.username,
            (SELECT k2.nama_kos FROM kos k2
             WHERE k2.pemilik_id = u.user_id
             ORDER BY k2.kos_id DESC LIMIT 1) as nama_kos,
            (SELECT COUNT(*) FROM chat c WHERE c.sender_id = u.user_id AND c.receiver_id = $cw_user_id AND c.dibaca = 0) as unread
        FROM users u
        WHERE u.role = 'pemilik'
          AND u.user_id IN (
            SELECT DISTINCT
                CASE WHEN c.sender_id = $cw_user_id THEN c.receiver_id ELSE c.sender_id END
            FROM chat c
            WHERE c.sender_id = $cw_user_id OR c.receiver_id = $cw_user_id
          )
        ORDER BY u.username ASC
    ";
} else {
    // Pemilik: tampil pencari yang pernah menghubungi via chat
    $cw_q = "
        SELECT u.user_id, u.username,
            NULL as nama_kos,
            (SELECT COUNT(*) FROM chat c WHERE c.sender_id = u.user_id AND c.receiver_id = $cw_user_id AND c.dibaca = 0) as unread
        FROM users u
        WHERE u.user_id IN (
            SELECT DISTINCT
                CASE WHEN c.sender_id = $cw_user_id THEN c.receiver_id ELSE c.sender_id END
            FROM chat c
            WHERE c.sender_id = $cw_user_id OR c.receiver_id = $cw_user_id
        )
        ORDER BY u.username ASC
    ";
}
$cw_result  = isset($conn) ? mysqli_query($conn, $cw_q) : null;
$cw_kontak  = [];
$cw_unread_total = 0;
if ($cw_result) {
    while ($r = mysqli_fetch_assoc($cw_result)) {
        $cw_kontak[] = $r;
        $cw_unread_total += (int)$r['unread'];
    }
}
?>

<!-- ====================================================
     CHAT WIDGET — Modal gaya Shopee / marketplace
     ==================================================== -->
<style>
/* ── Tombol bubble ── */
.cw-bubble {
    /* Disembunyikan — chat dibuka via icon navbar */
    display: none !important;
}

.cw-bubble-badge {
    position: absolute;
    top: -4px; right: -4px;
    background: #FF4444;
    color: #fff;
    border-radius: 50%;
    width: 20px; height: 20px;
    font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid #fff;
    pointer-events: none;
}

/* ── Panel utama ── */
.cw-panel {
    position: fixed;
    bottom: 98px;
    right: 28px;
    width: 360px;
    height: 520px;
    background: #fff;
    border-radius: 20px;
    border: 2.5px solid #FFD700;
    box-shadow: 0 16px 50px rgba(0,0,0,0.18);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    /* Animasi masuk */
    transform: scale(0.85) translateY(30px);
    opacity: 0;
    pointer-events: none;
    transition: transform 0.28s cubic-bezier(.34,1.56,.64,1), opacity 0.22s ease;
    transform-origin: bottom right;
}

.cw-panel.cw-open {
    transform: scale(1) translateY(0);
    opacity: 1;
    pointer-events: all;
}

/* ── Header panel ── */
.cw-header {
    background: #FFD700;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    border-bottom: 2px solid #e6c200;
}

.cw-header-left { display: flex; align-items: center; gap: 10px; }

.cw-header-avatar {
    width: 36px; height: 36px;
    background: #000;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; font-weight: 800; color: #FFD700;
    border: 2px solid #000;
    flex-shrink: 0;
}

.cw-header-info h4 { font-size: 14px; font-weight: 800; color: #000; margin: 0; line-height: 1.2; }
.cw-header-info p  { font-size: 11px; color: #555; margin: 0; }

.cw-btn-close {
    background: rgba(0,0,0,0.1);
    border: none; border-radius: 50%;
    width: 30px; height: 30px;
    font-size: 16px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
    color: #000;
}
.cw-btn-close:hover { background: rgba(0,0,0,0.2); }

/* ── Layar: daftar kontak ── */
.cw-screen { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

.cw-kontak-list { flex: 1; overflow-y: auto; }

.cw-kontak-item {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 16px;
    border-bottom: 1px solid #f5f5f5;
    cursor: pointer;
    transition: background 0.18s;
    text-decoration: none; color: inherit;
}
.cw-kontak-item:hover { background: #FFF9E6; }
.cw-kontak-item:active { background: #FFF3CD; }

.cw-kontak-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: #FFD700;
    border: 2px solid #e6c200;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 800; color: #333;
    flex-shrink: 0;
}

.cw-kontak-detail { flex: 1; min-width: 0; }
.cw-kontak-nama {
    font-size: 13px; font-weight: 700; color: #222;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cw-kontak-kos {
    font-size: 11px; color: #999;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-top: 2px;
}

.cw-unread-dot {
    width: 20px; height: 20px;
    background: #FF4444; color: #fff;
    border-radius: 50%; font-size: 10px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.cw-empty {
    flex: 1;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 30px; text-align: center; color: #ccc;
}
.cw-empty .cw-empty-icon { font-size: 50px; margin-bottom: 12px; opacity: 0.5; }
.cw-empty p { font-size: 13px; color: #bbb; line-height: 1.6; }

/* ── Layar: percakapan ── */
.cw-chat-screen {
    flex: 1;
    display: none;
    flex-direction: column;
    overflow: hidden;
}

.cw-chat-screen.cw-active { display: flex; }

.cw-chat-topbar {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 14px;
    border-bottom: 1px solid #f0f0f0;
    background: #fff;
    flex-shrink: 0;
}

.cw-back-btn {
    background: none; border: none;
    font-size: 18px; cursor: pointer; color: #555;
    padding: 4px 6px; border-radius: 8px;
    transition: background 0.2s;
    line-height: 1;
}
.cw-back-btn:hover { background: #f5f5f5; }

.cw-chat-topbar-info { flex: 1; min-width: 0; }
.cw-chat-topbar-nama { font-size: 13px; font-weight: 700; color: #222; }
.cw-chat-topbar-kos  { font-size: 11px; color: #aaa; }

.cw-chat-topbar-avatar {
    width: 34px; height: 34px;
    border-radius: 50%; background: #FFD700; border: 2px solid #e6c200;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800; color: #333;
    flex-shrink: 0;
}

/* Tombol buka full chat.php */
.cw-fullscreen-btn {
    background: none; border: 1px solid #ddd;
    border-radius: 8px; padding: 5px 9px;
    font-size: 13px; cursor: pointer; color: #888;
    transition: all 0.2s; text-decoration: none;
    display: flex; align-items: center; gap: 4px;
    white-space: nowrap;
}
.cw-fullscreen-btn:hover { background: #FFF9E6; border-color: #FFD700; color: #000; }

/* Pesan */
.cw-messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 14px 14px 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 0;
    background: #f9f9f9;
}

.cw-msg-row { display: flex; align-items: flex-end; gap: 6px; }
.cw-msg-row.cw-sent     { flex-direction: row-reverse; }
.cw-msg-row.cw-received { flex-direction: row; }

.cw-msg-av {
    width: 24px; height: 24px;
    border-radius: 50%; background: #FFD700;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; flex-shrink: 0;
}

.cw-msg-wrap {
    max-width: 72%;
    display: flex; flex-direction: column; gap: 2px;
}
.cw-msg-row.cw-sent     .cw-msg-wrap { align-items: flex-end; }
.cw-msg-row.cw-received .cw-msg-wrap { align-items: flex-start; }

.cw-bubble-msg {
    padding: 8px 12px;
    border-radius: 16px;
    font-size: 13px;
    line-height: 1.55;
    font-family: 'Poppins', sans-serif;
    white-space: pre-wrap;
    word-break: break-word;
    overflow-wrap: break-word;
    display: inline-block;
}
.cw-msg-row.cw-received .cw-bubble-msg {
    background: #fff;
    color: #333;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 5px rgba(0,0,0,0.07);
}
.cw-msg-row.cw-sent .cw-bubble-msg {
    background: #FFD700;
    color: #000;
    border-bottom-right-radius: 4px;
}

/* Tombol hapus pesan */
.cw-msg-wrap {
    position: relative;
}
/* Tombol hapus inline — muncul di bawah bubble saat hover row */
.cw-hapus-btn {
    display: none;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 11px;
    color: #bbb;
    padding: 2px 6px;
    border-radius: 4px;
    line-height: 1;
    transition: color 0.2s, background 0.2s;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    margin-top: 2px;
    align-self: flex-end;
}
.cw-msg-row.cw-sent .cw-hapus-btn {
    align-self: flex-end;
}
/* Tampilkan saat hover pada ROW (bukan wrap) */
.cw-msg-row:hover .cw-hapus-btn { display: block; }
.cw-hapus-btn:hover {
    color: #FF4444;
    background: rgba(255,68,68,0.1);
}
/* Animasi hapus */
.cw-msg-row {
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.cw-msg-row.cw-deleting {
    opacity: 0;
    transform: scaleY(0) translateX(10px);
    pointer-events: none;
}

.cw-msg-time { font-size: 10px; color: #bbb; }

.cw-date-sep { text-align: center; margin: 6px 0; }
.cw-date-sep span {
    background: rgba(0,0,0,0.06); color: #aaa;
    font-size: 10px; font-weight: 600;
    padding: 3px 12px; border-radius: 20px;
}

/* Input */
.cw-input-area {
    display: flex; gap: 8px; align-items: flex-end;
    padding: 10px 12px;
    border-top: 1.5px solid #f0f0f0;
    background: #fff;
    flex-shrink: 0;
}

.cw-input-area textarea {
    flex: 1;
    border: 1.5px solid #e0e0e0;
    border-radius: 18px;
    padding: 9px 14px;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    resize: none;
    outline: none;
    min-height: 38px;
    max-height: 90px;
    line-height: 1.5;
    transition: border 0.2s;
}
.cw-input-area textarea:focus { border-color: #FFD700; }

.cw-send-btn {
    width: 38px; height: 38px;
    background: #FFD700; border: 2px solid #000;
    border-radius: 50%; font-size: 16px;
    cursor: pointer; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.cw-send-btn:hover  { background: #51CF66; transform: scale(1.1); }
.cw-send-btn:disabled { background: #eee; border-color: #ccc; cursor: not-allowed; transform: none; }

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

/* Responsif mobile */
@media (max-width: 480px) {
    .cw-panel { width: calc(100vw - 24px); right: 12px; bottom: 82px; height: 68vh; }
    .cw-bubble { bottom: 18px; right: 18px; }
}
</style>

<!-- Bubble tombol -->
<div class="cw-bubble" id="cwBubble" onclick="cwToggle()" title="Chat">
    💬
    <?php if ($cw_unread_total > 0): ?>
    <div class="cw-bubble-badge"><?= $cw_unread_total ?></div>
    <?php endif; ?>
</div>

<!-- Panel chat -->
<div class="cw-panel" id="cwPanel">

    <!-- Header -->
    <div class="cw-header">
        <div class="cw-header-left">
            <div class="cw-header-avatar">
                <?= strtoupper(substr($cw_username, 0, 1)) ?>
            </div>
            <div class="cw-header-info">
                <h4>Pesan</h4>
                <p>Halo, <?= htmlspecialchars($cw_username) ?>!</p>
            </div>
        </div>
        <button class="cw-btn-close" onclick="cwToggle()">✕</button>
    </div>

    <!-- Layar 1: Daftar Kontak -->
    <div class="cw-screen" id="cwScreenKontak">
        <div class="cw-kontak-list">
            <?php if (count($cw_kontak) > 0): ?>
                <?php foreach ($cw_kontak as $ck): ?>
                    <div class="cw-kontak-item"
                         onclick="cwBukaChat(<?= $ck['user_id'] ?>, '<?= htmlspecialchars($ck['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($ck['nama_kos'] ?? '-', ENT_QUOTES) ?>')">
                        <div class="cw-kontak-avatar">
                            <?= strtoupper(substr($ck['username'], 0, 1)) ?>
                        </div>
                        <div class="cw-kontak-detail">
                            <div class="cw-kontak-nama"><?= htmlspecialchars($ck['username']) ?></div>
                            <div class="cw-kontak-kos">🏠 <?= htmlspecialchars($ck['nama_kos'] ?? '-') ?></div>
                        </div>
                        <?php if ($ck['unread'] > 0): ?>
                            <div class="cw-unread-dot"><?= $ck['unread'] ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="cw-empty">
                    <div class="cw-empty-icon">💬</div>
                    <?php if ($cw_role === 'pencari'): ?>
                        <p>Belum ada percakapan.<br>Mulai chat dengan pemilik kos dari halaman detail kos.</p>
                    <?php else: ?>
                        <p>Belum ada percakapan.<br>Pesan dari pencari kos akan muncul di sini.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Layar 2: Percakapan -->
    <div class="cw-chat-screen" id="cwScreenChat">
        <div class="cw-chat-topbar">
            <button class="cw-back-btn" onclick="cwKembali()">‹</button>
            <div class="cw-chat-topbar-avatar" id="cwTopbarAvatar">?</div>
            <div class="cw-chat-topbar-info">
                <div class="cw-chat-topbar-nama" id="cwTopbarNama">-</div>
                <div class="cw-chat-topbar-kos"  id="cwTopbarKos">-</div>
            </div>
            <a href="#" id="cwFullBtn" class="cw-fullscreen-btn" title="Buka chat penuh">
                ⛶ Perluas
            </a>
        </div>

        <div class="cw-messages" id="cwMessages"></div>

        <div class="cw-input-area">
            <textarea id="cwInput" placeholder="Ketik pesan..." rows="1"
                onkeydown="cwEnter(event)"
                oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,90)+'px'">
            </textarea>
            <button class="cw-send-btn" id="cwSendBtn" onclick="cwKirim()">➤</button>
        </div>
    </div>
</div>

<script>
(function() {
    const CW_USER_ID = <?= $cw_user_id ?>;
    let cwTemanId    = 0;
    let cwLastMsgId  = 0;
    let cwPolling    = null;
    let cwIsOpen     = false;

    // ── Toggle buka/tutup panel ──
    window.cwToggle = function() {
        cwIsOpen = !cwIsOpen;
        const panel = document.getElementById('cwPanel');
        panel.classList.toggle('cw-open', cwIsOpen);

        if (!cwIsOpen && cwPolling) {
            clearInterval(cwPolling);
            cwPolling = null;
        }
    };

    // ── Buka percakapan dengan kontak tertentu ──
    window.cwBukaChat = function(temanId, nama, kos) {
        cwTemanId   = temanId;
        cwLastMsgId = 0;

        // Update topbar
        document.getElementById('cwTopbarAvatar').textContent = nama[0].toUpperCase();
        document.getElementById('cwTopbarNama').textContent   = nama;
        document.getElementById('cwTopbarKos').textContent    = '🏠 ' + kos;
        document.getElementById('cwFullBtn').href = 'chat.php?dengan=' + temanId;

        // Ganti layar
        document.getElementById('cwScreenKontak').style.display = 'none';
        document.getElementById('cwScreenChat').style.display   = 'flex';
        document.getElementById('cwScreenChat').classList.add('cw-active');

        // Muat pesan
        cwMuatPesan();
        if (cwPolling) clearInterval(cwPolling);
        cwPolling = setInterval(cwCekBaru, 3000);

        // Fokus input
        setTimeout(() => document.getElementById('cwInput')?.focus(), 100);
    };

    // ── Kembali ke daftar kontak ──
    window.cwKembali = function() {
        cwTemanId = 0;
        if (cwPolling) { clearInterval(cwPolling); cwPolling = null; }
        document.getElementById('cwScreenChat').style.display   = 'none';
        document.getElementById('cwScreenChat').classList.remove('cw-active');
        document.getElementById('cwScreenKontak').style.display = '';
        document.getElementById('cwMessages').innerHTML = '';
    };

    // ── Format tanggal & jam ──
    function cwFmtTgl(d) {
        const dt  = new Date(d);
        const now = new Date();
        const yes = new Date(now); yes.setDate(now.getDate() - 1);
        const same = (a,b) => a.getDate()===b.getDate() && a.getMonth()===b.getMonth() && a.getFullYear()===b.getFullYear();
        if (same(dt,now)) return 'Hari ini';
        if (same(dt,yes)) return 'Kemarin';
        return dt.toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'});
    }

    function cwFmtJam(d) {
        return new Date(d).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
    }

    // ── Buat elemen pesan ──
    function cwBuatPesan(msg) {
        const isSent = parseInt(msg.sender_id) === CW_USER_ID;
        const inisial = msg.sender_name ? msg.sender_name[0].toUpperCase() : '?';

        const row = document.createElement('div');
        row.className = `cw-msg-row ${isSent ? 'cw-sent' : 'cw-received'}`;
        row.dataset.chatId = msg.chat_id;

        const av = document.createElement('div');
        av.className = 'cw-msg-av';
        av.textContent = isSent ? 'Ku' : inisial;

        const wrap = document.createElement('div');
        wrap.className = 'cw-msg-wrap';

        const bubble = document.createElement('div');
        bubble.className = 'cw-bubble-msg';
        bubble.textContent = msg.pesan;

        const time = document.createElement('div');
        time.className = 'cw-msg-time';
        time.textContent = cwFmtJam(msg.dikirim_at);

        wrap.appendChild(bubble);
        wrap.appendChild(time);

        // Tombol hapus — hanya untuk pesan sendiri, append terakhir
        if (isSent) {
            const hapusBtn = document.createElement('button');
            hapusBtn.className = 'cw-hapus-btn';
            hapusBtn.textContent = '🗑 Hapus';
            hapusBtn.title = 'Hapus pesan ini';
            hapusBtn.onclick = function(e) {
                e.stopPropagation();
                cwHapusPesan(msg.chat_id, row);
            };
            wrap.appendChild(hapusBtn);
        }

        if (!isSent) { row.appendChild(av); row.appendChild(wrap); }
        else         { row.appendChild(wrap); row.appendChild(av); }

        return row;
    }

    // ── Hapus pesan di widget ──
    window.cwHapusPesan = function(chatId, rowEl) {
        kosConfirm({
            ikon: '🗑️',
            judul: 'Hapus Pesan?',
            pesan: 'Pesan ini akan dihapus secara permanen.',
            labelYa: 'Ya, Hapus',
            tipeYa: 'merah',
            onYa: function() {
                const fd = new FormData();
                fd.append('aksi', 'hapus');
                fd.append('chat_id', chatId);

                fetch('proses/api-chat.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (d.status === 'success') {
                            rowEl.classList.add('cw-deleting');
                            setTimeout(() => {
                                if (rowEl.parentNode) rowEl.parentNode.removeChild(rowEl);
                            }, 260);
                            kosToast('Pesan berhasil dihapus', 'sukses');
                        } else {
                            kosAlert({ ikon: '❌', judul: 'Gagal Menghapus', pesan: d.message || 'Coba lagi', tipe: 'gagal' });
                        }
                    })
                    .catch(() => kosAlert({ ikon: '❌', judul: 'Koneksi Gagal', pesan: 'Periksa koneksi internet kamu.', tipe: 'gagal' }));
            }
        });
    };

    // ── Muat semua pesan (awal) ──
    function cwMuatPesan() {
        if (!cwTemanId) return;
        fetch(`proses/api-chat.php?aksi=ambil&teman_id=${cwTemanId}`)
            .then(r => r.json())
            .then(data => {
                const box = document.getElementById('cwMessages');
                box.innerHTML = '';
                let lastDate = '';
                (data.pesan || []).forEach(msg => {
                    const tgl = cwFmtTgl(msg.dikirim_at);
                    if (tgl !== lastDate) {
                        const sep = document.createElement('div');
                        sep.className = 'cw-date-sep';
                        sep.innerHTML = `<span>${tgl}</span>`;
                        box.appendChild(sep);
                        lastDate = tgl;
                    }
                    box.appendChild(cwBuatPesan(msg));
                });
                box.scrollTop = box.scrollHeight;
                if (data.pesan && data.pesan.length > 0) {
                    cwLastMsgId = data.pesan[data.pesan.length - 1].chat_id;
                }
            })
            .catch(() => {});
    }

    // ── Cek pesan baru (polling) ──
    function cwCekBaru() {
        if (!cwTemanId) return;
        fetch(`proses/api-chat.php?aksi=baru&teman_id=${cwTemanId}&last_id=${cwLastMsgId}`)
            .then(r => r.json())
            .then(data => {
                const box = document.getElementById('cwMessages');
                if (!box || !data.pesan || data.pesan.length === 0) return;
                const isBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
                data.pesan.forEach(msg => box.appendChild(cwBuatPesan(msg)));
                cwLastMsgId = data.pesan[data.pesan.length - 1].chat_id;
                if (isBottom) box.scrollTop = box.scrollHeight;
            })
            .catch(() => {});
    }

    // ── Kirim pesan ──
    window.cwKirim = function() {
        const input = document.getElementById('cwInput');
        const btn   = document.getElementById('cwSendBtn');
        const teks  = input.value.trim();
        if (!teks || !cwTemanId) return;

        btn.disabled = true;
        input.value  = '';
        input.style.height = 'auto';

        const fd = new FormData();
        fd.append('aksi', 'kirim');
        fd.append('teman_id', cwTemanId);
        fd.append('pesan', teks);

        fetch('proses/api-chat.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.status === 'success') cwCekBaru(); })
            .catch(() => {})
            .finally(() => { btn.disabled = false; input.focus(); });
    };

    window.cwEnter = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); cwKirim(); }
    };
})();

    // Modal system
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