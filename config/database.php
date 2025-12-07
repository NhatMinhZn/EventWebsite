<?php
// Cấu hình kết nối database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'event_portal');

// Tạo kết nối
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Kiểm tra kết nối
    if ($conn->connect_error) {
        die("Kết nối thất bại: " . $conn->connect_error);
    }
    
    // Set charset UTF-8
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

// Hàm helper để escape string
function escape_string($str) {
    global $conn;
    return $conn->real_escape_string($str);
}

// Hàm kiểm tra đăng nhập
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Hàm kiểm tra admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Hàm redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Start session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>