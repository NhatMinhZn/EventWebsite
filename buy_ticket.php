<?php
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = "Mua vé sự kiện";
$error = '';
$success = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity < 1) {
        $error = "Số lượng vé không hợp lệ!";
    } elseif ($quantity > $event['available_tickets']) {
        $error = "Số vé còn lại không đủ!";
    } else {
        $user_id = $_SESSION['user_id'];
        $total_price = $event['ticket_price'] * $quantity;
        
        // Bắt đầu transaction
        $conn->begin_transaction();
        
        try {
            // Thêm vé vào bảng tickets
            $insert_sql = "INSERT INTO tickets (user_id, event_id, quantity, total_price) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiid", $user_id, $event_id, $quantity, $total_price);
            $insert_stmt->execute();
            
            // Cập nhật số vé còn lại
            $update_sql = "UPDATE events SET available_tickets = available_tickets - ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $quantity, $event_id);
            $update_stmt->execute();
            
            // ⭐ QUAN TRỌNG: Cập nhật hoặc thêm vào lịch với is_purchased = TRUE
            $calendar_check = "SELECT id FROM user_calendar WHERE user_id = ? AND event_id = ?";
            $check_stmt = $conn->prepare($calendar_check);
            $check_stmt->bind_param("ii", $user_id, $event_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Đã có trong lịch → CẬP NHẬT is_purchased = TRUE
                $update_calendar = "UPDATE user_calendar SET is_purchased = 1 WHERE user_id = ? AND event_id = ?";
                $update_cal_stmt = $conn->prepare($update_calendar);
                $update_cal_stmt->bind_param("ii", $user_id, $event_id);
                $update_cal_stmt->execute();
            } else {
                // Chưa có trong lịch → THÊM MỚI với is_purchased = TRUE
                $insert_calendar = "INSERT INTO user_calendar (user_id, event_id, is_purchased) VALUES (?, ?, 1)";
                $insert_cal_stmt = $conn->prepare($insert_calendar);
                $insert_cal_stmt->bind_param("ii", $user_id, $event_id);
                $insert_cal_stmt->execute();
            }
            
            $conn->commit();
            $success = "Mua vé thành công! Cảm ơn bạn đã đăng ký.";
            header("refresh:2;url=calendar.php");
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Có lỗi xảy ra. Vui lòng thử lại!";
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
        
        <?php if ($event['available_tickets'] > 0): ?>
        <form method="POST" class="auth-form">
            <label>Số lượng vé <span class="required">*</span></label>
            <input type="number" name="quantity" min="1" max="<?php echo $event['available_tickets']; ?>" value="1" required />
            
            <div class="total-price">
                <p><strong>Tổng tiền:</strong> <span id="totalPrice"><?php echo number_format($event['ticket_price'], 0, ',', '.'); ?></span> VNĐ</p>
            </div>
            
            <button type="submit">Xác nhận mua vé</button>
        </form>
        <?php else: ?>
            <p class="alert alert-error">Sự kiện này đã hết vé!</p>
        <?php endif; ?>
        
        <p class="auth-link"><a href="event.php?id=<?php echo $event_id; ?>">← Quay lại trang sự kiện</a></p>
    </div>
</main>

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