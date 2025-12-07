<?php
require_once '../config/database.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($event_id > 0) {
    $sql = "DELETE FROM events WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Xóa sự kiện thành công!";
    } else {
        $_SESSION['message'] = "Có lỗi xảy ra khi xóa sự kiện!";
    }
}

redirect('manage_events.php');
?>