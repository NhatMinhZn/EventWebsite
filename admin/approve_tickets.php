<?php
require_once '../config/database.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$page_title = "Duyệt vé";
$message = '';

// Xử lý duyệt vé
if (isset($_GET['action']) && isset($_GET['ticket_id'])) {
    $ticket_id = (int)$_GET['ticket_id'];
    $action = $_GET['action'];
    $admin_id = $_SESSION['user_id'];
    
    if ($action === 'approve') {
        $conn->begin_transaction();
        try {
            // Lấy thông tin ticket
            $ticket_sql = "SELECT user_id, event_id, quantity FROM tickets WHERE id = ?";
            $ticket_stmt = $conn->prepare($ticket_sql);
            $ticket_stmt->bind_param("i", $ticket_id);
            $ticket_stmt->execute();
            $ticket = $ticket_stmt->get_result()->fetch_assoc();
            
            // Cập nhật status ticket = 'approved'
            $update_sql = "UPDATE tickets SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $admin_id, $ticket_id);
            $update_stmt->execute();
            
            // Cập nhật user_calendar thành 'approved'
            $cal_sql = "UPDATE user_calendar SET is_purchased = 'approved' WHERE user_id = ? AND event_id = ?";
            $cal_stmt = $conn->prepare($cal_sql);
            $cal_stmt->bind_param("ii", $ticket['user_id'], $ticket['event_id']);
            $cal_stmt->execute();
            
            // Trừ số vé available
            $event_sql = "UPDATE events SET available_tickets = available_tickets - ? WHERE id = ?";
            $event_stmt = $conn->prepare($event_sql);
            $event_stmt->bind_param("ii", $ticket['quantity'], $ticket['event_id']);
            $event_stmt->execute();
            
            $conn->commit();
            $message = '<div class="alert alert-success">✅ Đã duyệt vé thành công!</div>';
        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-error">❌ Lỗi: ' . $e->getMessage() . '</div>';
        }
    } elseif ($action === 'reject') {
        $update_sql = "UPDATE tickets SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $admin_id, $ticket_id);
        
        if ($update_stmt->execute()) {
            // Cập nhật user_calendar về 'none'
            $ticket_sql = "SELECT user_id, event_id FROM tickets WHERE id = ?";
            $ticket_stmt = $conn->prepare($ticket_sql);
            $ticket_stmt->bind_param("i", $ticket_id);
            $ticket_stmt->execute();
            $ticket = $ticket_stmt->get_result()->fetch_assoc();
            
            $cal_sql = "UPDATE user_calendar SET is_purchased = 'none' WHERE user_id = ? AND event_id = ?";
            $cal_stmt = $conn->prepare($cal_sql);
            $cal_stmt->bind_param("ii", $ticket['user_id'], $ticket['event_id']);
            $cal_stmt->execute();
            
            $message = '<div class="alert alert-error">❌ Đã từ chối vé!</div>';
        }
    }
}

// Lấy danh sách vé chờ duyệt
$sql = "SELECT t.*, u.username, u.full_name, e.title as event_title 
        FROM tickets t 
        JOIN users u ON t.user_id = u.id 
        JOIN events e ON t.event_id = e.id 
        WHERE t.status = 'pending' 
        ORDER BY t.purchase_date DESC";
$pending_result = $conn->query($sql);

// Lấy danh sách vé đã duyệt
$approved_sql = "SELECT t.*, u.username, u.full_name, e.title as event_title, a.username as approved_by_name
                 FROM tickets t 
                 JOIN users u ON t.user_id = u.id 
                 JOIN events e ON t.event_id = e.id 
                 LEFT JOIN users a ON t.approved_by = a.id
                 WHERE t.status IN ('approved', 'rejected')
                 ORDER BY t.approved_at DESC 
                 LIMIT 20";
$approved_result = $conn->query($approved_sql);
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
                    <li><a href="manage_events.php">📅 Quản lý sự kiện</a></li>
                    <li><a href="add_event.php">➕ Thêm sự kiện mới</a></li>
                    <li><a href="approve_tickets.php" class="active">🎫 Duyệt vé</a></li>
                    <li><a href="../index.php" target="_blank">🌐 Xem website</a></li>
                    <li><a href="logout.php">🚪 Đăng xuất</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="admin-content">
            <h1>🎫 Duyệt vé</h1>
            
            <?php echo $message; ?>
            
            <!-- VÉ CHỜ DUYỆT -->
            <h2 style="color: #ffc107;">⏳ Vé chờ duyệt (<?php echo $pending_result->num_rows; ?>)</h2>
            <?php if ($pending_result->num_rows > 0): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Người mua</th>
                            <th>Sự kiện</th>
                            <th>SL vé</th>
                            <th>Tổng tiền</th>
                            <th>Ngày mua</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ticket = $pending_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $ticket['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($ticket['full_name']); ?></strong><br>
                                    <small>@<?php echo htmlspecialchars($ticket['username']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($ticket['event_title']); ?></td>
                                <td><?php echo $ticket['quantity']; ?></td>
                                <td><strong><?php echo number_format($ticket['total_price'], 0, ',', '.'); ?> đ</strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($ticket['purchase_date'])); ?></td>
                                <td>
                                    <a href="approve_tickets.php?action=approve&ticket_id=<?php echo $ticket['id']; ?>" 
                                       class="btn btn-small btn-edit"
                                       onclick="return confirm('Xác nhận DUYỆT vé này?')">
                                        ✅ Duyệt
                                    </a>
                                    <a href="approve_tickets.php?action=reject&ticket_id=<?php echo $ticket['id']; ?>" 
                                       class="btn btn-small btn-delete"
                                       onclick="return confirm('Xác nhận TỪ CHỐI vé này?')">
                                        ❌ Từ chối
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-message">✅ Không có vé nào chờ duyệt!</p>
            <?php endif; ?>
            
            <hr style="margin: 40px 0;">
            
            <!-- VÉ ĐÃ XỬ LÝ -->
            <h2>📋 Lịch sử duyệt vé (20 gần nhất)</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Người mua</th>
                        <th>Sự kiện</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Người duyệt</th>
                        <th>Ngày duyệt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($ticket = $approved_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $ticket['id']; ?></td>
                            <td><?php echo htmlspecialchars($ticket['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['event_title']); ?></td>
                            <td><?php echo number_format($ticket['total_price'], 0, ',', '.'); ?> đ</td>
                            <td>
                                <?php if ($ticket['status'] === 'approved'): ?>
                                    <span class="badge badge-success">✅ Đã duyệt</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">❌ Từ chối</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['approved_by_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $ticket['approved_at'] ? date('d/m/Y H:i', strtotime($ticket['approved_at'])) : 'N/A'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    
    <style>
    .empty-message {
        text-align: center;
        padding: 40px;
        color: #999;
        font-size: 16px;
    }
    
    .badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .badge-success {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }
    </style>
</body>
</html>