<?php
require_once 'config/database.php';

$page_title = "Đăng ký tài khoản";
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    
    // Validate
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "Vui lòng điền đầy đủ thông tin bắt buộc!";
    } elseif ($password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp!";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự!";
    } else {
        // Kiểm tra username đã tồn tại
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Tên đăng nhập hoặc email đã được sử dụng!";
        } else {
            // Tạo tài khoản mới
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, 'user')";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $phone);
            
            if ($stmt->execute()) {
                $success = "Đăng ký thành công! Vui lòng đăng nhập.";
                header("refresh:2;url=login.php");
            } else {
                $error = "Có lỗi xảy ra. Vui lòng thử lại!";
            }
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<main class="container">
    <div class="auth-wrapper">
        <h2>Đăng ký tài khoản</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" class="auth-form">
            <label>Tên đăng nhập <span class="required">*</span></label>
            <input type="text" name="username" required />
            
            <label>Email <span class="required">*</span></label>
            <input type="email" name="email" required />
            
            <label>Họ và tên <span class="required">*</span></label>
            <input type="text" name="full_name" required />
            
            <label>Số điện thoại</label>
            <input type="tel" name="phone" />
            
            <label>Mật khẩu <span class="required">*</span></label>
            <input type="password" name="password" required />
            
            <label>Xác nhận mật khẩu <span class="required">*</span></label>
            <input type="password" name="confirm_password" required />
            
            <button type="submit">Đăng ký</button>
        </form>
        
        <p class="auth-link">Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
    </div>
</main>

<?php include 'includes/footer.php'; ?>