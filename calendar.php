<?php
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$page_title = "Lịch của tôi";
$user_id = $_SESSION['user_id'];

// Lấy danh sách sự kiện trong lịch
$sql = "SELECT e.*, uc.note, uc.is_purchased, uc.added_date 
        FROM user_calendar uc 
        JOIN events e ON uc.event_id = e.id 
        WHERE uc.user_id = ? 
        ORDER BY e.start_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

include 'includes/header.php';
?>

<main class="container">
    <br><br>
    <h2>Lịch sự kiện của tôi</h2>
    
    <?php if ($result->num_rows > 0): ?>
        <div class="calendar-list">
            <?php while ($item = $result->fetch_assoc()): ?>
                <div class="calendar-item">
                    <div class="calendar-event-info">
                        <?php
                        // Lấy ảnh thumbnail của sự kiện
                        $img_sql = "SELECT image_url FROM event_images WHERE event_id = ? ORDER BY is_thumbnail DESC, display_order ASC LIMIT 1";
                        $img_stmt = $conn->prepare($img_sql);
                        $img_stmt->bind_param("i", $item['id']);
                        $img_stmt->execute();
                        $img_result = $img_stmt->get_result();
                        $image_url = $img_result->num_rows > 0 ? $img_result->fetch_assoc()['image_url'] : 'https://via.placeholder.com/200x130?text=No+Image';
                        ?>
                        <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="calendar-img" />
                        <div class="calendar-details">
                            <h3><a href="event.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['title']); ?></a></h3>
                            <p class="event-date">📅 <?php echo date('d/m/Y', strtotime($item['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($item['end_date'])); ?></p>
                            <p class="event-location">📍 <?php echo htmlspecialchars($item['location']); ?></p>
                            
                            <?php if ($item['is_purchased'] === 'approved'): ?>
                                <span class="badge badge-success">✅ Đã mua vé</span>
                            <?php elseif ($item['is_purchased'] === 'pending'): ?>
                                <span class="badge badge-warning">⏳ Chờ duyệt</span>
                            <?php else: ?>
                                <span class="badge badge-info">📝 Đã lưu lịch</span>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['note'])): ?>
                                <p class="calendar-note"><strong>Ghi chú:</strong> <?php echo htmlspecialchars($item['note']); ?></p>
                            <?php endif; ?>
                            
                            <p class="calendar-added">Thêm vào lịch: <?php echo date('d/m/Y H:i', strtotime($item['added_date'])); ?></p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="empty-message">Bạn chưa có sự kiện nào trong lịch. <a href="events.php">Khám phá sự kiện</a></p>
    <?php endif; ?>
</main>

<style>
.badge-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}
</style>

<?php include 'includes/footer.php'; ?>