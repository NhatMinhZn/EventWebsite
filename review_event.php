<?php
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = "Đánh giá sự kiện";
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
$user_id = $_SESSION['user_id'];

// Kiểm tra user đã tham gia sự kiện chưa
$check_sql = "SELECT id FROM user_calendar WHERE user_id = ? AND event_id = ? AND is_purchased = 1";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $event_id);
$check_stmt->execute();
$can_review = $check_stmt->get_result()->num_rows > 0;

// Kiểm tra đã đánh giá chưa
$existing_sql = "SELECT rating, comment FROM reviews WHERE user_id = ? AND event_id = ?";
$existing_stmt = $conn->prepare($existing_sql);
$existing_stmt->bind_param("ii", $user_id, $event_id);
$existing_stmt->execute();
$existing_result = $existing_stmt->get_result();
$existing_review = $existing_result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    if (!$can_review) {
        $error = "Bạn cần mua vé sự kiện này để đánh giá!";
    } elseif ($rating < 1 || $rating > 5) {
        $error = "Vui lòng chọn số sao từ 1-5!";
    } else {
        if ($existing_review) {
            // Cập nhật đánh giá cũ
            $update_sql = "UPDATE reviews SET rating = ?, comment = ? WHERE user_id = ? AND event_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("isii", $rating, $comment, $user_id, $event_id);
            $update_stmt->execute();
            $success = "Cập nhật đánh giá thành công!";
        } else {
            // Thêm đánh giá mới
            $insert_sql = "INSERT INTO reviews (user_id, event_id, rating, comment) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiis", $user_id, $event_id, $rating, $comment);
            $insert_stmt->execute();
            $success = "Đánh giá thành công!";
        }
        
        // Cập nhật avg_rating trong bảng events
        $avg_sql = "UPDATE events SET 
                    avg_rating = (SELECT AVG(rating) FROM reviews WHERE event_id = ?),
                    total_reviews = (SELECT COUNT(*) FROM reviews WHERE event_id = ?)
                    WHERE id = ?";
        $avg_stmt = $conn->prepare($avg_sql);
        $avg_stmt->bind_param("iii", $event_id, $event_id, $event_id);
        $avg_stmt->execute();
        
        header("refresh:1;url=event.php?id=" . $event_id);
    }
}

include 'includes/header.php';
?>

<main class="container">
    <div class="auth-wrapper">
        <h2>Đánh giá: <?php echo htmlspecialchars($event['title']); ?></h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($can_review): ?>
            <form method="POST" class="review-form">
                <div class="rating-input">
                    <label>Đánh giá của bạn:</label>
                    <div class="star-rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" 
                                <?php echo ($existing_review && $existing_review['rating'] == $i) ? 'checked' : ''; ?> required />
                            <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> sao">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <label>Nhận xét (tuỳ chọn):</label>
                <textarea name="comment" rows="5" placeholder="Chia sẻ trải nghiệm của bạn về sự kiện này..."><?php echo $existing_review ? htmlspecialchars($existing_review['comment']) : ''; ?></textarea>
                
                <button type="submit">
                    <?php echo $existing_review ? 'Cập nhật đánh giá' : 'Gửi đánh giá'; ?>
                </button>
            </form>
        <?php else: ?>
            <div class="alert alert-error">
                <p>Bạn cần <strong>mua vé</strong> sự kiện này để có thể đánh giá!</p>
                <a href="event.php?id=<?php echo $event_id; ?>" class="btn-primary">Quay lại trang sự kiện</a>
            </div>
        <?php endif; ?>
        
        <p class="auth-link">
            <a href="event.php?id=<?php echo $event_id; ?>">← Quay lại trang sự kiện</a>
        </p>
    </div>
</main>

<style>
.review-form {
    margin-top: 20px;
}

.rating-input {
    margin-bottom: 25px;
}

.rating-input label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
}

.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    gap: 5px;
}

.star-rating input {
    display: none;
}

.star-rating label {
    font-size: 50px;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s;
}

.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label {
    color: #ffc107;
}

.review-form textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-family: inherit;
    resize: vertical;
}

.review-form button {
    width: 100%;
    padding: 12px;
    background: #0066cc;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 15px;
}

.review-form button:hover {
    background: #0052a3;
}

body.dark .review-form textarea {
    background: #444;
    border-color: #666;
    color: #fff;
}
</style>

<?php include 'includes/footer.php'; ?>