<?php
require_once '../config/database.php';

if (is_logged_in() && is_admin()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $sql = "SELECT id, username, password, role, full_name FROM users WHERE (username = ? OR email = ?) AND role = 'admin'";
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
            redirect('index.php');
        } else {
            $error = "Sai mật khẩu!";
        }
    } else {
        $error = "Tài khoản admin không tồn tại!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h2>Đăng nhập Admin</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <label>Tên đăng nhập</label>
            <input type="text" name="username" required />
            
            <label>Mật khẩu</label>
            <input type="password" name="password" required />
            
            <button type="submit">Đăng nhập</button>
        </form>
        
        <p><a href="../index.php">← Quay lại trang chủ</a></p>
    </div>
</body>
</html>