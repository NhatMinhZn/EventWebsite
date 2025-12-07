<?php
require_once 'config/database.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader (nếu dùng Composer)
// require 'vendor/autoload.php';

// Hoặc load thủ công (nếu tải PHPMailer về thư mục)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$page_title = "Liên hệ";
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $content = trim($_POST['content']);
    
    if (empty($name) || empty($email) || empty($subject) || empty($content)) {
        $message = '<div class="alert alert-error">Vui lòng điền đầy đủ thông tin!</div>';
    } else {
        // Tạo instance PHPMailer
        $mail = new PHPMailer(true);
        
        try {
            // Cấu hình SMTP
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';  // SMTP server của Gmail
            $mail->SMTPAuth   = true;
            $mail->Username   = 'minhttn.24itb@vku.udn.vn';  // Email của bạn
            $mail->Password   = 'wxkj lopx nhpp pkuq';     // ⚠️ THAY ĐỔI: App Password của Gmail
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            
            // Người gửi và người nhận
            $mail->setFrom('minhttn.24itb@vku.udn.vn', 'Da Nang Event Portal');
            $mail->addAddress('minhttn.24itb@vku.udn.vn');  // Email nhận
            $mail->addReplyTo($email, $name);  // Reply-To là email của người liên hệ
            
            // Nội dung email
            $mail->isHTML(true);
            $mail->Subject = 'Liên hệ từ website: ' . $subject;
            $mail->Body    = "
                <h3>Thông tin liên hệ mới từ website</h3>
                <p><strong>Họ tên:</strong> {$name}</p>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Tiêu đề:</strong> {$subject}</p>
                <hr>
                <p><strong>Nội dung:</strong></p>
                <p>" . nl2br(htmlspecialchars($content)) . "</p>
            ";
            $mail->AltBody = "Tên: {$name}\nEmail: {$email}\nTiêu đề: {$subject}\n\nNội dung:\n{$content}";
            
            // Gửi email
            $mail->send();
            $message = '<div class="alert alert-success">✅ Cảm ơn bạn đã liên hệ! Chúng tôi sẽ phản hồi sớm nhất.</div>';
            
        } catch (Exception $e) {
            $message = '<div class="alert alert-error">❌ Không thể gửi email. Lỗi: ' . $mail->ErrorInfo . '</div>';
        }
    }
}

include 'includes/header.php';
?>

<main class="container">
    <br>
    <h2>Thông tin liên hệ</h2>
    <div class="contact-info">
        <p><strong>📍 Địa chỉ:</strong> 470 Trần Đại Nghĩa, Q. Ngũ Hành Sơn, Tp. Đà Nẵng</p>
        <p><strong>📞 Điện thoại:</strong> 0362 831 345</p>
        <p><strong>📧 Email:</strong> minhttn.24itb@vku.udn.vn</p>
    </div>
    
    <br>
    <h2>Gửi tin nhắn cho chúng tôi</h2>
    
    <?php echo $message; ?>
    
    <form method="POST" class="contact-form">
        <div class="form-row">
            <div class="form-col">
                <label>Họ và tên <span class="required">*</span></label>
                <input type="text" name="name" required />
            </div>
            <div class="form-col">
                <label>Email <span class="required">*</span></label>
                <input type="email" name="email" required />
            </div>
        </div>
        
        <label>Tiêu đề <span class="required">*</span></label>
        <input type="text" name="subject" required />
        
        <label>Nội dung <span class="required">*</span></label>
        <textarea name="content" rows="6" required></textarea>
        
        <button type="submit">Gửi tin nhắn</button>
    </form>
</main>

<?php include 'includes/footer.php'; ?>