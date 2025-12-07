<?php
/**
 * CRON JOB - GỬI EMAIL NHẮC SỰ KIỆN
 * Chạy mỗi ngày 1 lần vào 9:00 sáng
 * 
 * Cách setup trên localhost:
 * 1. Windows: Task Scheduler → chạy: php C:\xampp\htdocs\EventWebsite\cron_send_reminders.php
 * 2. Linux/Mac: Crontab → 0 9 * * * /usr/bin/php /path/to/cron_send_reminders.php
 * 
 * Hoặc test thủ công: Truy cập http://localhost/EventWebsite/cron_send_reminders.php
 */

require_once 'config/database.php';

// Load PHPMailer
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ngày mai (nhắc trước 1 ngày)
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Tìm các sự kiện sẽ diễn ra vào ngày mai
$events_sql = "SELECT * FROM events WHERE start_date = ?";
$events_stmt = $conn->prepare($events_sql);
$events_stmt->bind_param("s", $tomorrow);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

$sent_count = 0;
$error_count = 0;

while ($event = $events_result->fetch_assoc()) {
    $event_id = $event['id'];
    
    // Tìm user đã thêm sự kiện này vào lịch
    $users_sql = "SELECT u.id, u.email, u.full_name, uc.is_purchased 
                  FROM user_calendar uc 
                  JOIN users u ON uc.user_id = u.id 
                  WHERE uc.event_id = ?";
    $users_stmt = $conn->prepare($users_sql);
    $users_stmt->bind_param("i", $event_id);
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    
    while ($user = $users_result->fetch_assoc()) {
        // Tạo email khác nhau cho "đã mua vé" vs "đã note"
        $is_purchased = $user['is_purchased'];
        
        $mail = new PHPMailer(true);
        
        try {
            // Cấu hình SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'minhttn.24itb@vku.udn.vn';
            $mail->Password = 'wxkj lopx nhpp pkuq';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            
            // Người gửi và nhận
            $mail->setFrom('minhttn.24itb@vku.udn.vn', 'Da Nang Event Portal');
            $mail->addAddress($user['email'], $user['full_name']);
            
            // Nội dung email khác nhau
            if ($is_purchased) {
                // ĐÃ MUA VÉ
                $mail->Subject = '🎫 Nhắc nhở: Sự kiện của bạn diễn ra vào NGÀY MAI!';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                            <h1 style='color: white; margin: 0;'>🎫 Sự kiện sắp diễn ra!</h1>
                        </div>
                        
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <p style='font-size: 16px;'>Xin chào <strong>{$user['full_name']}</strong>,</p>
                            
                            <p style='font-size: 16px;'>Đây là lời nhắc nhở về sự kiện bạn đã <strong style='color: #28a745;'>mua vé</strong>:</p>
                            
                            <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745; margin: 20px 0;'>
                                <h2 style='color: #333; margin-top: 0;'>{$event['title']}</h2>
                                <p style='margin: 10px 0;'><strong>📅 Thời gian:</strong> Ngày mai - " . date('d/m/Y', strtotime($event['start_date'])) . "</p>
                                <p style='margin: 10px 0;'><strong>📍 Địa điểm:</strong> {$event['location']}</p>
                                <p style='margin: 10px 0;'><strong>⏰ Đừng quên:</strong> Hãy đến đúng giờ để không bỏ lỡ!</p>
                            </div>
                            
                            <div style='background: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107;'>
                                <p style='margin: 0; color: #856404;'><strong>💡 Lưu ý:</strong> Vui lòng mang theo vé (email xác nhận) khi tham dự.</p>
                            </div>
                            
                            <p style='margin-top: 20px; text-align: center;'>
                                <a href='http://localhost/EventWebsite/event.php?id={$event_id}' style='display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;'>Xem chi tiết sự kiện</a>
                            </p>
                        </div>
                        
                        <div style='padding: 20px; text-align: center; color: #999; font-size: 12px;'>
                            <p>Email này được gửi tự động từ Da Nang Event Portal</p>
                            <p>© 2025 Da Nang Event Portal. All rights reserved.</p>
                        </div>
                    </div>
                ";
            } else {
                // CHỈ NOTE, CHƯA MUA VÉ
                $mail->Subject = '📌 Nhắc nhở: Sự kiện bạn quan tâm diễn ra vào NGÀY MAI!';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                            <h1 style='color: white; margin: 0;'>📌 Sự kiện sắp diễn ra!</h1>
                        </div>
                        
                        <div style='padding: 30px; background: #f9f9f9;'>
                            <p style='font-size: 16px;'>Xin chào <strong>{$user['full_name']}</strong>,</p>
                            
                            <p style='font-size: 16px;'>Bạn đã <strong style='color: #0066cc;'>lưu</strong> sự kiện này vào lịch của mình:</p>
                            
                            <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #0066cc; margin: 20px 0;'>
                                <h2 style='color: #333; margin-top: 0;'>{$event['title']}</h2>
                                <p style='margin: 10px 0;'><strong>📅 Thời gian:</strong> Ngày mai - " . date('d/m/Y', strtotime($event['start_date'])) . "</p>
                                <p style='margin: 10px 0;'><strong>📍 Địa điểm:</strong> {$event['location']}</p>
                            </div>
                            
                            <div style='background: #d1ecf1; padding: 15px; border-radius: 6px; border-left: 4px solid #0c5460;'>
                                <p style='margin: 0; color: #0c5460;'><strong>💡 Bạn chưa mua vé?</strong> Hãy đăng ký ngay để không bỏ lỡ!</p>
                            </div>
                            
                            <p style='margin-top: 20px; text-align: center;'>
                                <a href='http://localhost/EventWebsite/buy_ticket.php?id={$event_id}' style='display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;'>Mua vé ngay</a>
                                <a href='http://localhost/EventWebsite/event.php?id={$event_id}' style='display: inline-block; padding: 12px 30px; background: #6c757d; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin-left: 10px;'>Xem chi tiết</a>
                            </p>
                        </div>
                        
                        <div style='padding: 20px; text-align: center; color: #999; font-size: 12px;'>
                            <p>Email này được gửi tự động từ Da Nang Event Portal</p>
                            <p>© 2025 Da Nang Event Portal. All rights reserved.</p>
                        </div>
                    </div>
                ";
            }
            
            $mail->isHTML(true);
            $mail->send();
            $sent_count++;
            
        } catch (Exception $e) {
            $error_count++;
            error_log("Failed to send email to {$user['email']}: {$mail->ErrorInfo}");
        }
    }
}

// Log kết quả
$log_message = date('Y-m-d H:i:s') . " - Sent: $sent_count, Errors: $error_count\n";
file_put_contents('cron_log.txt', $log_message, FILE_APPEND);

// Output (để test)
echo "✅ Hoàn thành!\n";
echo "📧 Đã gửi: $sent_count email\n";
echo "❌ Lỗi: $error_count email\n";
?>