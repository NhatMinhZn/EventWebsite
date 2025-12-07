<?php
require_once 'config/database.php';

// Nếu đã đăng nhập rồi thì chuyển về trang chủ
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/index.php');
    } else {
        redirect('index.php');
    }
}

$page_title = "Đăng nhập";
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } else {
        $sql = "SELECT id, username, password, role, full_name FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Chuyển hướng theo vai trò
                if ($user['role'] === 'admin') {
                    redirect('admin/index.php');
                } else {
                    redirect('index.php');
                }
            } else {
                $error = "Sai mật khẩu!";
            }
        } else {
            $error = "Tên đăng nhập không tồn tại!";
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<main class="container">
    <div class="auth-wrapper">
        <h2>Đăng nhập</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" class="auth-form">
            <label>Tên đăng nhập hoặc Email</label>
            <input type="text" name="username" required />
            
            <label>Mật khẩu</label>
            <input type="password" name="password" required />
            
            <button type="submit">Đăng nhập</button>
        </form>
        
        <p class="auth-link">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
        <p class="auth-link"><a href="forgot_password.php"><strong>Quên mật khẩu?</strong></a></p>
        <p class="auth-note">Nếu bạn là Admin, <a href="admin/login.php"><strong>đăng nhập tại đây</strong></a></p>
    </div>
</main>

<?php include 'includes/footer.php'; ?>