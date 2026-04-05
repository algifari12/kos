<?php
// config/session.php
// Helper session untuk semua halaman
// Usage: require_once 'config/session.php';
//        requireLogin();           → redirect ke index.php jika belum login
//        requireRole('pemilik');   → redirect jika bukan role yang diminta
//        requireRole('pencari');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cache prevention — semua halaman yang pakai session tidak boleh di-cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Pragma: no-cache");

/**
 * Paksa login — redirect ke index.php jika belum login
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . getBasePath() . "index.php");
        exit();
    }
}

/**
 * Paksa role tertentu — redirect jika role tidak sesuai
 * @param string $role 'pemilik' atau 'pencari'
 */
function requireRole(string $role) {
    requireLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        $redirect = $_SESSION['role'] === 'pemilik'
            ? 'dashboard-pemilik.php'
            : 'dashboard-pencari.php';
        header("Location: " . getBasePath() . $redirect);
        exit();
    }
}

/**
 * Cek apakah sudah login (tanpa redirect)
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Ambil role user saat ini
 */
function getRole(): string {
    return $_SESSION['role'] ?? '';
}

/**
 * Ambil user_id saat ini
 */
function getUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Base path (root atau subfolder)
 * Mendeteksi otomatis apakah file ada di root atau di subfolder proses/
 */
function getBasePath(): string {
    // Jika file ini dipanggil dari subfolder (proses/), path ke root = ../
    $scriptDir = dirname($_SERVER['SCRIPT_FILENAME'] ?? '');
    $docRoot   = $_SERVER['DOCUMENT_ROOT'] ?? '';
    
    // Cek apakah SCRIPT_FILENAME ada di subdirektori
    if (!empty($docRoot) && str_contains($scriptDir, DIRECTORY_SEPARATOR . 'proses')) {
        return '../';
    }
    return '';
}