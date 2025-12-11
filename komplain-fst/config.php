<?php
// ==========================================
// CONFIG.PHP - VERSI LOCALHOST (XAMPP)
// ==========================================

// 1. BUFFER OUTPUT
// Mencegah error "Headers already sent" saat redirect
ob_start();

// 2. SESSION START
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 3. ATUR ZONA WAKTU (WIB)
date_default_timezone_set('Asia/Jakarta');

// 4. ERROR REPORTING (Aktifkan saat di Localhost)
// Supaya kalau ada error langsung kelihatan di layar
error_reporting(E_ALL);
ini_set('display_errors', 1); 

// ==========================
// DATABASE CONFIG (LOCAL)
// ==========================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');             // User default XAMPP
define('DB_PASS', '');                 // Password default XAMPP biasanya KOSONG
define('DB_NAME', 'komplain_fst');     // Sesuaikan dengan nama database di phpMyAdmin Anda

// ==========================
// DATABASE CONNECTION
// ==========================
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if (!$conn) {
    die("Koneksi Database Gagal: " . mysqli_connect_error() . 
        "<br>Pastikan XAMPP (MySQL) sudah di-Start dan nama database benar.");
}

// Set karakter encoding
mysqli_set_charset($conn, "utf8mb4");

// ==========================
// BASE URL WEBSITE (PENTING!)
// ==========================
// Ganti 'nama_folder_project' dengan nama folder asli di htdocs Anda.
// Contoh: jika folder Anda di C:/xampp/htdocs/sistem-komplain/
// Maka isi: http://localhost/sistem-komplain/

define('BASE_URL', 'http://localhost/komplain-fst/'); 

// ==========================
// HELPER FUNCTIONS
// ==========================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($url) {
    $url = ltrim($url, '/');
    header("Location: " . BASE_URL . $url);
    exit();
}

function clean($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// ==========================
// END CONFIG
// ==========================
?>