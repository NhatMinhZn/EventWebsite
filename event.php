<?php
require_once 'config/database.php';

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = "Chi tiết sự kiện";

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
$page_title = $event['title'];

// Lấy danh sách đánh giá
$reviews_sql = "SELECT r.*, u.username, u.full_name FROM reviews r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.event_id = ? 
                ORDER BY r.created_at DESC";
$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $event_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

// Kiểm tra user đã đánh giá chưa
$user_reviewed = false;
if (is_logged_in()) {
    $check_review_sql = "SELECT id FROM reviews WHERE user_id = ? AND event_id = ?";
    $check_review_stmt = $conn->prepare($check_review_sql);
    $check_review_stmt->bind_param("ii", $_SESSION['user_id'], $event_id);
    $check_review_stmt->execute();
    $user_reviewed = $check_review_stmt->get_result()->num_rows > 0;
}

// Xử lý thêm vào lịch
if (isset($_POST['add_to_calendar']) && is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $note = trim($_POST['note']);
    
    $check_sql = "SELECT id FROM user_calendar WHERE user_id = ? AND event_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $event_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $insert_sql = "INSERT INTO user_calendar (user_id, event_id, note) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iis", $user_id, $event_id, $note);
        $insert_stmt->execute();
        $calendar_message = "Đã thêm vào lịch của bạn!";
    } else {
        $calendar_message = "Sự kiện này đã có trong lịch của bạn!";
    }
}

include 'includes/header.php';
?>

<main class="container">
    <div class="event-detail">
        <!-- THÔNG TIN SỰ KIỆN -->
        <h2 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h2>
        
        <!-- RATING -->
        <?php if ($event['total_reviews'] > 0): ?>
            <div class="event-rating-summary">
                <div class="rating-stars">
                    <?php
                    $avg = $event['avg_rating'];
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= floor($avg)) {
                            echo '<span class="star filled">★</span>';
                        } elseif ($i == ceil($avg) && $avg - floor($avg) >= 0.5) {
                            echo '<span class="star half">★</span>';
                        } else {
                            echo '<span class="star">★</span>';
                        }
                    }
                    ?>
                </div>
                <span class="rating-number"><?php echo number_format($avg, 1); ?></span>
                <span class="review-count">(<?php echo $event['total_reviews']; ?> đánh giá)</span>
            </div>
        <?php endif; ?>
        
        <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" style="width:100%; max-height:400px; object-fit:cover; margin-bottom:20px; border-radius:10px;">
        
        <p class="event-date"><strong>Ngày tổ chức:</strong> Từ <?php echo date('d/m/Y', strtotime($event['start_date'])); ?> đến <?php echo date('d/m/Y', strtotime($event['end_date'])); ?></p>
        <p class="event-location"><strong>Địa điểm:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
        
        <?php if ($event['ticket_price'] > 0): ?>
            <p class="event-price"><strong>Giá vé:</strong> <?php echo number_format($event['ticket_price'], 0, ',', '.'); ?> VNĐ</p>
            <p class="event-tickets"><strong>Số vé còn lại:</strong> <?php echo $event['available_tickets']; ?></p>
        <?php else: ?>
            <p class="event-price"><strong>Giá vé:</strong> <span style="color:#28a745;">Miễn phí</span></p>
        <?php endif; ?>
        
        <p class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
        
        <!-- ACTIONS -->
        <?php if (is_logged_in()): ?>
            <div class="event-actions">
                <?php if ($event['ticket_price'] > 0 && $event['available_tickets'] > 0): ?>
                    <a href="buy_ticket.php?id=<?php echo $event['id']; ?>" class="register-btn">Mua vé</a>
                <?php elseif ($event['ticket_price'] == 0): ?>
                    <a href="buy_ticket.php?id=<?php echo $event['id']; ?>" class="register-btn" style="background:#28a745;">Đăng ký tham gia</a>
                <?php endif; ?>
                
                <button onclick="showCalendarForm()" class="register-btn" style="background:#6c757d;">Thêm vào lịch</button>
                
                <?php if ($user_reviewed): ?>
                    <a href="review_event.php?id=<?php echo $event['id']; ?>" class="register-btn" style="background:#ffc107; color:#333;">Sửa đánh giá</a>
                <?php else: ?>
                    <a href="review_event.php?id=<?php echo $event['id']; ?>" class="register-btn" style="background:#ff9800;">Đánh giá</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="margin-top:20px;"><a href="login.php" class="register-btn">Đăng nhập để tương tác</a></p>
        <?php endif; ?>
    </div>

    <!-- CALENDAR FORM -->
    <?php if (is_logged_in()): ?>
    <div id="calendarSection" class="registration-wrapper" style="display:none;">
        <h3>Thêm vào lịch của tôi</h3>
        <?php if (isset($calendar_message)): ?>
            <div class="alert alert-success"><?php echo $calendar_message; ?></div>
        <?php endif; ?>
        <form method="POST">
            <label>Ghi chú (tuỳ chọn)</label>
            <textarea name="note" rows="3" placeholder="Thêm ghi chú..."></textarea>
            <button type="submit" name="add_to_calendar">Xác nhận</button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- REVIEWS SECTION -->
    <div class="reviews-section">
        <h3>📝 Đánh giá từ người tham gia</h3>
        
        <?php if ($reviews_result->num_rows > 0): ?>
            <div class="reviews-list">
                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <strong><?php echo htmlspecialchars($review['full_name']); ?></strong>
                                <span class="review-date"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></span>
                            </div>
                            <div class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php if (!empty($review['comment'])): ?>
                            <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="no-reviews">Chưa có đánh giá nào. Hãy là người đầu tiên!</p>
        <?php endif; ?>
    </div>
</main>

<style>
.event-rating-summary {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 15px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.rating-stars {
    display: flex;
    gap: 2px;
}

.star {
    color: #ddd;
    font-size: 24px;
}

.star.filled {
    color: #ffc107;
}

.rating-number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.review-count {
    color: #666;
}

.reviews-section {
    margin-top: 50px;
    padding: 30px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.reviews-section h3 {
    margin-bottom: 25px;
    color: #333;
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.review-item {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #0066cc;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.reviewer-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.reviewer-info strong {
    color: #333;
}

.review-date {
    font-size: 13px;
    color: #999;
}

.review-rating .star {
    font-size: 18px;
}

.review-comment {
    margin-top: 10px;
    color: #555;
    line-height: 1.6;
}

.no-reviews {
    text-align: center;
    color: #999;
    padding: 40px;
}

body.dark .event-rating-summary,
body.dark .reviews-section,
body.dark .review-item {
    background: #2a2a2a;
}

body.dark .rating-number,
body.dark .reviews-section h3,
body.dark .reviewer-info strong {
    color: #f0f0f0;
}

body.dark .review-comment {
    color: #ccc;
}
</style>

<script>
function showCalendarForm() {
    document.getElementById('calendarSection').style.display = 'block';
    document.getElementById('calendarSection').scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php include 'includes/footer.php'; ?>