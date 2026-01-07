<?php
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = "Mua vé sự kiện";
$error = '';
$success = '';
$show_qr = false;

// Lấy thông tin sự kiện
$sql = "SELECT * FROM events WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('events.php');
}

$event = $result->fetch_assoc();

// Lấy thông tin ngân hàng admin (nếu có)
$admin_bank_sql = "SELECT payment_qr FROM users WHERE role = 'admin' LIMIT 1";
$admin_bank_result = $conn->query($admin_bank_sql);
$admin_payment_qr = '';
if ($admin_bank_result && $admin_bank_result->num_rows > 0) {
    $admin_payment_qr = $admin_bank_result->fetch_assoc()['payment_qr'];
}

// Thông tin ngân hàng mặc định (THAY ĐỔI THEO THÔNG TIN CỦA BẠN)
$bank_code = 'MB';  // Mã ngân hàng: MB, VCB, TCB, ACB...
$account_number = '0362831345';  // Số tài khoản
$account_name = 'TA TRUONG NHAT MINH';  // Tên chủ TK

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity < 1) {
        $error = "Số lượng vé không hợp lệ!";
    } elseif ($quantity > $event['available_tickets']) {
        $error = "Số vé còn lại không đủ!";
    } else {
        // Bước 1: Hiển thị QR code
        if (!isset($_POST['confirm_payment'])) {
            $show_qr = true;
            $_SESSION['temp_quantity'] = $quantity;
            $_SESSION['temp_total_amount'] = $event['ticket_price'] * $quantity; // ⭐ TÍNH TỔNG TIỀN
        } 
        // Bước 2: Xác nhận đã thanh toán
        else {
            $user_id = $_SESSION['user_id'];
            $quantity = isset($_SESSION['temp_quantity']) ? $_SESSION['temp_quantity'] : $quantity;
            $total_price = $event['ticket_price'] * $quantity;
            
            // Bắt đầu transaction
            $conn->begin_transaction();
            
            try {
                // Thêm vé với status = 'pending'
                $insert_sql = "INSERT INTO tickets (user_id, event_id, quantity, total_price, status) VALUES (?, ?, ?, ?, 'pending')";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iiid", $user_id, $event_id, $quantity, $total_price);
                $insert_stmt->execute();
                
                // CẬP NHẬT hoặc THÊM vào lịch với is_purchased = 'pending'
                $calendar_check = "SELECT id FROM user_calendar WHERE user_id = ? AND event_id = ?";
                $check_stmt = $conn->prepare($calendar_check);
                $check_stmt->bind_param("ii", $user_id, $event_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Đã có trong lịch → CẬP NHẬT is_purchased = 'pending'
                    $update_calendar = "UPDATE user_calendar SET is_purchased = 'pending' WHERE user_id = ? AND event_id = ?";
                    $update_cal_stmt = $conn->prepare($update_calendar);
                    $update_cal_stmt->bind_param("ii", $user_id, $event_id);
                    $update_cal_stmt->execute();
                } else {
                    // Chưa có trong lịch → THÊM MỚI với is_purchased = 'pending'
                    $insert_calendar = "INSERT INTO user_calendar (user_id, event_id, is_purchased) VALUES (?, ?, 'pending')";
                    $insert_cal_stmt = $conn->prepare($insert_calendar);
                    $insert_cal_stmt->bind_param("ii", $user_id, $event_id);
                    $insert_cal_stmt->execute();
                }
                
                $conn->commit();
                unset($_SESSION['temp_quantity']);
                unset($_SESSION['temp_total_amount']); // ⭐ XÓA SESSION
                $success = "✅ Đã gửi yêu cầu mua vé! Vui lòng chờ admin duyệt.";
                header("refresh:2;url=calendar.php");
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Có lỗi xảy ra. Vui lòng thử lại!";
            }
        }
    }
}

include 'includes/header.php';
?>

<main class="container">
    <div class="auth-wrapper">
        <h2>Mua vé: <?php echo htmlspecialchars($event['title']); ?></h2>
        
        <div class="event-info-box">
            <p><strong>Ngày:</strong> <?php echo date('d/m/Y', strtotime($event['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($event['end_date'])); ?></p>
            <p><strong>Địa điểm:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
            <p><strong>Giá vé:</strong> <?php echo number_format($event['ticket_price'], 0, ',', '.'); ?> VNĐ</p>
            <p><strong>Số vé còn lại:</strong> <?php echo $event['available_tickets']; ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($show_qr): ?>
            <!-- HIỂN THỊ QR CODE THANH TOÁN -->
            <?php
            // ⭐ TẠO QR CODE ĐỘNG VỚI SỐ TIỀN CHÍNH XÁC
            $total_amount = $_SESSION['temp_total_amount'];
            $transfer_content = 'THANHTOAN ' . $event_id . ' ' . $_SESSION['username'];
            
            // Dùng QR tùy chỉnh của admin hoặc tạo mới
            if (!empty($admin_payment_qr)) {
                $qr_url = $admin_payment_qr;
            } else {
                // API VietQR: https://api.vietqr.io/
                $qr_url = "https://img.vietqr.io/image/{$bank_code}-{$account_number}-compact2.png?amount={$total_amount}&addInfo=" . urlencode($transfer_content) . "&accountName=" . urlencode($account_name);
            }
            ?>
            <div class="qr-payment-section">
                <h3>📱 Quét mã QR để thanh toán</h3>
                <div class="qr-container">
                    <img src="<?php echo htmlspecialchars($qr_url); ?>" alt="QR Code" class="qr-image">
                </div>
                <p class="qr-note">
                    🏦 <strong>Ngân hàng:</strong> <?php echo $bank_code; ?> - <?php echo $account_number; ?><br>
                    💰 <strong>Số tiền:</strong> <span style="font-size: 20px; color: #d9534f;"><?php echo number_format($total_amount, 0, ',', '.'); ?> VNĐ</span><br>
                    📝 <strong>Nội dung CK:</strong> <?php echo htmlspecialchars($transfer_content); ?><br>
                    <small style="color: #666;">⚠️ Vui lòng chuyển khoản <strong>ĐÚNG số tiền và nội dung</strong> để được duyệt nhanh!</small>
                </p>
                <form method="POST">
                    <input type="hidden" name="quantity" value="<?php echo (int)$_POST['quantity']; ?>">
                    <input type="hidden" name="confirm_payment" value="1">
                    <button type="submit" class="btn-confirm-payment">✅ Tôi đã chuyển khoản</button>
                    <a href="buy_ticket.php?id=<?php echo $event_id; ?>" class="btn-cancel">❌ Hủy</a>
                </form>
            </div>
        <?php elseif ($event['available_tickets'] > 0 && !$success): ?>
            <form method="POST" class="auth-form">
                <label>Số lượng vé <span class="required">*</span></label>
                <input type="number" name="quantity" min="1" max="<?php echo $event['available_tickets']; ?>" value="1" required />
                
                <div class="total-price">
                    <p><strong>Tổng tiền:</strong> <span id="totalPrice"><?php echo number_format($event['ticket_price'], 0, ',', '.'); ?></span> VNĐ</p>
                </div>
                
                <button type="submit">Tiếp tục thanh toán</button>
            </form>
        <?php endif; ?>
        
        <p class="auth-link"><a href="event.php?id=<?php echo $event_id; ?>">← Quay lại trang sự kiện</a></p>
    </div>
</main>

<style>
.qr-payment-section {
    background: #f0f8ff;
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    margin: 20px 0;
}

.qr-payment-section h3 {
    color: #0066cc;
    margin-bottom: 20px;
}

.qr-container {
    background: white;
    padding: 20px;
    border-radius: 10px;
    display: inline-block;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.qr-image {
    max-width: 300px;
    width: 100%;
    height: auto;
    border-radius: 8px;
}

.qr-note {
    margin: 20px 0;
    padding: 15px;
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    border-radius: 5px;
    text-align: left;
    line-height: 1.8;
}

.btn-confirm-payment {
    background: #28a745;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    margin: 10px 5px;
    transition: 0.3s;
}

.btn-confirm-payment:hover {
    background: #218838;
}

.btn-cancel {
    display: inline-block;
    background: #dc3545;
    color: white;
    padding: 12px 30px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    margin: 10px 5px;
    transition: 0.3s;
}

.btn-cancel:hover {
    background: #c82333;
}

body.dark .qr-payment-section {
    background: #2a2a2a;
}

body.dark .qr-container {
    background: #1a1a1a;
}

body.dark .qr-note {
    background: #3a3a3a;
    color: #f0f0f0;
}
</style>

<script>
const ticketPrice = <?php echo $event['ticket_price']; ?>;
const quantityInput = document.querySelector('input[name="quantity"]');
const totalPriceEl = document.getElementById('totalPrice');

if (quantityInput && totalPriceEl) {
    quantityInput.addEventListener('input', function() {
        const quantity = parseInt(this.value) || 0;
        const total = ticketPrice * quantity;
        totalPriceEl.textContent = total.toLocaleString('vi-VN');
    });
}
</script>

<?php include 'includes/footer.php'; ?>