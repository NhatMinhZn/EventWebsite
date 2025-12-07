<?php
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$page_title = "Đổi mật khẩu";
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Lấy mật khẩu hiện tại từ database
    $sql = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Validate
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Vui lòng điền đầy đủ thông tin!";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Mật khẩu hiện tại không đúng!";
    } elseif (strlen($new_password) < 6) {
        $error = "Mật khẩu mới phải có ít nhất 6 ký tự!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp!";
    } elseif ($current_password === $new_password) {
        $error = "Mật khẩu mới phải khác mật khẩu hiện tại!";
    } else {
        // Cập nhật mật khẩu mới
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            $success = "Đổi mật khẩu thành công!";
            // Tự động đăng xuất sau 3 giây
            header("refresh:3;url=logout.php");
        } else {
            $error = "Có lỗi xảy ra. Vui lòng thử lại!";
        }
    }
}

include 'includes/header.php';
?>

<main class="container">
    <div class="auth-wrapper">
        <h2>Đổi mật khẩu</h2>
        <p style="text-align: center; color: #666; margin-bottom: 20px;">
            Tài khoản: <strong>@<?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p><strong>✅ <?php echo $success; ?></strong></p>
                <p>Bạn sẽ được đăng xuất để đảm bảo an toàn...</p>
            </div>
        <?php else: ?>
            <form method="POST" class="auth-form">
                <label>Mật khẩu hiện tại <span class="required">*</span></label>
                <input type="password" name="current_password" required />
                
                <label>Mật khẩu mới <span class="required">*</span></label>
                <input type="password" name="new_password" required minlength="6" />
                <small style="color: #666;">Ít nhất 6 ký tự</small>
                
                <label>Xác nhận mật khẩu mới <span class="required">*</span></label>
                <input type="password" name="confirm_password" required minlength="6" />
                
                <button type="submit">Đổi mật khẩu</button>
            </form>
        <?php endif; ?>
        
        <p class="auth-link">
            <a href="index.php">← Quay lại trang chủ</a>
        </p>
    </div>
</main>

<?php include 'includes/footer.php'; ?>