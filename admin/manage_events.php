<?php
require_once '../config/database.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$page_title = "Quản lý sự kiện";

// Lấy số vé chờ duyệt
$pending_tickets_sql = "SELECT COUNT(*) as total FROM tickets WHERE status = 'pending'";
$pending_tickets = $conn->query($pending_tickets_sql)->fetch_assoc()['total'];

// Lấy danh sách sự kiện
$sql = "SELECT e.*, 
        (SELECT image_url FROM event_images WHERE event_id = e.id ORDER BY display_order ASC LIMIT 1) as thumbnail
        FROM events e 
        ORDER BY e.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <ul>
                    <li><a href="index.php">📊 Dashboard</a></li>
                    <li><a href="manage_events.php" class="active">📅 Quản lý sự kiện</a></li>
                    <li><a href="add_event.php">➕ Thêm sự kiện mới</a></li>
                    <li><a href="approve_tickets.php">🎫 Duyệt vé <?php if($pending_tickets > 0) echo "($pending_tickets)"; ?></a></li>
                    <li><a href="../index.php" target="_blank">🌐 Xem website</a></li>
                    <li><a href="logout.php">🚪 Đăng xuất</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="admin-content">
            <h1>Quản lý sự kiện</h1>
            <a href="add_event.php" class="btn btn-primary">+ Thêm sự kiện mới</a>
            
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Hình ảnh</th>
                        <th>Tiêu đề</th>
                        <th>Ngày bắt đầu</th>
                        <th>Ngày kết thúc</th>
                        <th>Địa điểm</th>
                        <th>Giá vé</th>
                        <th>Vé còn lại</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php 
                        $stt = 1;
                        while ($event = $result->fetch_assoc()): 
                            $thumbnail = !empty($event['thumbnail']) ? $event['thumbnail'] : 'https://via.placeholder.com/60x40?text=No+Image';
                        ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="" style="width:60px;height:40px;object-fit:cover;"></td>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($event['start_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($event['end_date'])); ?></td>
                                <td><?php echo htmlspecialchars($event['location']); ?></td>
                                <td><?php echo number_format($event['ticket_price'], 0, ',', '.'); ?> VNĐ</td>
                                <td><?php echo $event['available_tickets']; ?></td>
                                <td>
                                    <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-small btn-edit">Sửa</a>
                                    <a href="delete_event.php?id=<?php echo $event['id']; ?>" class="btn btn-small btn-delete" onclick="return confirm('Bạn có chắc muốn xóa sự kiện này?')">Xóa</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">Chưa có sự kiện nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>
</body>
</html>