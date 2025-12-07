<?php
require_once 'config/database.php';

$page_title = "Quên mật khẩu";
$error = '';
$accounts = [];
$email_submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Vui lòng nhập email!";
    } else {
        // Tìm tất cả tài khoản có email này
        $sql = "SELECT id, username, full_name, email FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $accounts[] = $row;
            }
            $email_submitted = true;
        } else {
            $error = "Không tìm thấy tài khoản nào với email này!";
        }
    }
}

include 'includes/header.php';
?>

<main class="container">
    <div class="auth-wrapper">
        <h2>Quên mật khẩu</h2>
        <p style="text-align: center; color: #666; margin-bottom: 20px;">
            Nhập email đã đăng ký để tìm tài khoản của bạn
        </p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!$email_submitted): ?>
            <!-- Form nhập email -->
            <form method="POST" class="auth-form">
                <label>Email đã đăng ký</label>
                <input type="email" name="email" placeholder="example@email.com" required />
                <button type="submit">Tìm tài khoản</button>
            </form>
        <?php else: ?>
            <!-- Hiển thị danh sách tài khoản -->
            <div class="account-list">
                <h3>Tìm thấy <?php echo count($accounts); ?> tài khoản:</h3>
                <?php foreach ($accounts as $account): ?>
                    <div class="account-item">
                        <div class="account-info">
                            <p><strong><?php echo htmlspecialchars($account['full_name']); ?></strong></p>
                            <p style="color: #666;">@<?php echo htmlspecialchars($account['username']); ?></p>
                        </div>
                        <form method="POST" action="send_reset_code.php" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $account['id']; ?>" />
                            <button type="submit" class="btn-send-code">Lấy mã khôi phục</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <p class="auth-link">
                <a href="forgot_password.php">← Thử email khác</a>
            </p>
        <?php endif; ?>
        
        <p class="auth-link">
            <a href="login.php">← Quay lại đăng nhập</a>
        </p>
    </div>
</main>

<style>
.account-list {
    margin: 20px 0;
}

.account-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    margin: 10px 0;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.account-info p {
    margin: 5px 0;
}

.btn-send-code {
    padding: 8px 16px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
}

.btn-send-code:hover {
    background: #218838;
}

body.dark .account-item {
    background: #2a2a2a;
    border-color: #444;
}
</style>

<?php include 'includes/footer.php'; ?>