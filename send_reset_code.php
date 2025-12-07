<?php
require_once 'config/database.php';

// Load PHPMailer
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$page_title = "Gửi mã khôi phục";
$error = '';
$success = '';
$user_info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    // Lấy thông tin user
    $sql = "SELECT id, username, full_name, email FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_info = $result->fetch_assoc();
        
        // Tạo mã OTP 6 chữ số
        $reset_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Thời gian hết hạn: 15 phút
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Lưu mã vào bảng users (KHÔNG CẦN BẢNG MỚI!)
        $update_sql = "UPDATE users SET reset_code = ?, reset_expires = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $reset_code, $expires_at, $user_id);
        
        if ($update_stmt->execute()) {
            // Gửi email
            $mail = new PHPMailer(true);
            
            try {
                // Cấu hình SMTP
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'minhttn.24itb@vku.udn.vn';
                $mail->Password   = 'wxkj lopx nhpp pkuq';  // App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';
                
                // Người gửi và nhận
                $mail->setFrom('minhttn.24itb@vku.udn.vn', 'Da Nang Event Portal');
                $mail->addAddress($user_info['email'], $user_info['full_name']);
                
                // Nội dung email
                $mail->isHTML(true);
                $mail->Subject = 'Mã khôi phục mật khẩu - Da Nang Event Portal';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #333;'>Khôi phục mật khẩu</h2>
                        <p>Xin chào <strong>{$user_info['full_name']}</strong>,</p>
                        <p>Bạn đã yêu cầu khôi phục mật khẩu cho tài khoản: <strong>@{$user_info['username']}</strong></p>
                        
                        <div style='background: #f0f0f0; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;'>
                            <p style='margin: 0; color: #666;'>Mã khôi phục của bạn là:</p>
                            <h1 style='color: #0066cc; font-size: 36px; margin: 10px 0; letter-spacing: 5px;'>{$reset_code}</h1>
                            <p style='margin: 0; color: #999; font-size: 14px;'>Mã có hiệu lực trong 15 phút</p>
                        </div>
                        
                        <p style='color: #666;'>Nếu bạn không yêu cầu khôi phục mật khẩu, vui lòng bỏ qua email này.</p>
                        
                        <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                        <p style='color: #999; font-size: 12px;'>
                            Email này được gửi tự động, vui lòng không reply.<br>
                            © Da Nang Event Portal
                        </p>
                    </div>
                ";
                $mail->AltBody = "Mã khôi phục mật khẩu của bạn là: {$reset_code}\nMã có hiệu lực trong 15 phút.";
                
                $mail->send();
                $success = "Mã khôi phục đã được gửi đến email của bạn!";
                
            } catch (Exception $e) {
                $error = "Không thể gửi email. Lỗi: " . $mail->ErrorInfo;
            }
        } else {
            $error = "Có lỗi xảy ra. Vui lòng thử lại!";
        }
    } else {
        $error = "Không tìm thấy người dùng!";
    }
} else {
    redirect('forgot_password.php');
}

include 'includes/header.php';
?>

<main class="container">
    <div class="auth-wrapper">
        <h2>Mã khôi phục mật khẩu</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p><strong>✅ <?php echo $success; ?></strong></p>
                <p style="margin-top: 10px;">Vui lòng kiểm tra hộp thư và làm theo hướng dẫn.</p>
            </div>
            
            <?php if ($user_info): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <p><strong>Tài khoản:</strong> @<?php echo htmlspecialchars($user_info['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user_info['email']); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="GET" action="reset_password.php" class="auth-form">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>" />
                <label>Nhập mã khôi phục (6 chữ số)</label>
                <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus />
                <button type="submit">Xác nhận mã</button>
            </form>
        <?php endif; ?>
        
        <p class="auth-link">
            <a href="forgot_password.php">← Quay lại</a>
        </p>
    </div>
</main>

<?php include 'includes/footer.php'; ?>