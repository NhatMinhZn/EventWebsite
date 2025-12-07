<?php
require_once 'config/database.php';

$page_title = "Đặt lại mật khẩu";
$error = '';
$success = '';
$code_verified = false;
$user_id = 0;

// Xác thực mã
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code']) && isset($_GET['user_id'])) {
    $code = trim($_GET['code']);
    $user_id = (int)$_GET['user_id'];
    
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        $error = "Mã không hợp lệ!";
    } else {
        // Kiểm tra mã trong bảng users
        $sql = "SELECT id, username, reset_expires FROM users 
                WHERE id = ? AND reset_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_data = $result->fetch_assoc();
            
            // Kiểm tra hết hạn
            if (strtotime($user_data['reset_expires']) < time()) {
                $error = "Mã đã hết hạn! Vui lòng yêu cầu mã mới.";
            } else {
                $code_verified = true;
            }
        } else {
            $error = "Mã không đúng hoặc đã được sử dụng!";
        }
    }
}

// Đặt lại mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $user_id = (int)$_POST['user_id'];
    $code = trim($_POST['code']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        // Cập nhật mật khẩu mới
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            // Xóa mã reset sau khi dùng
            $clear_code = "UPDATE users SET reset_code = NULL, reset_expires = NULL WHERE id = ?";
            $clear_stmt = $conn->prepare($clear_code);
            $clear_stmt->bind_param("i", $user_id);
            $clear_stmt->execute();
            
            $success = "Đặt lại mật khẩu thành công!";
            header("refresh:2;url=login.php");
        } else {
            $error = "Có lỗi xảy ra. Vui lòng thử lại!";
        }
    }
}

include 'includes/header.php';
?>

<main class="container">
    <div class="auth-wrapper">
        <h2>Đặt lại mật khẩu</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p><strong>✅ <?php echo $success; ?></strong></p>
                <p>Bạn sẽ được chuyển đến trang đăng nhập...</p>
            </div>
        <?php elseif ($code_verified): ?>
            <div class="alert alert-success">
                <p><strong>✅ Xác thực thành công!</strong></p>
                <p>Vui lòng nhập mật khẩu mới của bạn.</p>
            </div>
            
            <form method="POST" class="auth-form">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>" />
                <input type="hidden" name="code" value="<?php echo htmlspecialchars($_GET['code']); ?>" />
                
                <label>Mật khẩu mới <span class="required">*</span></label>
                <input type="password" name="new_password" required minlength="6" />
                <small style="color: #666;">Ít nhất 6 ký tự</small>
                
                <label>Xác nhận mật khẩu <span class="required">*</span></label>
                <input type="password" name="confirm_password" required minlength="6" />
                
                <button type="submit">Đặt lại mật khẩu</button>
            </form>
        <?php endif; ?>
        
        <p class="auth-link">
            <a href="forgot_password.php">← Yêu cầu mã mới</a> |
            <a href="login.php">Đăng nhập</a>
        </p>
    </div>
</main>

<?php include 'includes/footer.php'; ?>