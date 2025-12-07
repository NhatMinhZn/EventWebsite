<?php
require_once '../config/database.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$page_title = "Trang quản trị";

// === THỐNG KÊ TỔNG QUAN ===
$total_events_sql = "SELECT COUNT(*) as total FROM events";
$total_events = $conn->query($total_events_sql)->fetch_assoc()['total'];

$total_users_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
$total_users = $conn->query($total_users_sql)->fetch_assoc()['total'];

$total_tickets_sql = "SELECT COUNT(*) as total, IFNULL(SUM(total_price), 0) as revenue FROM tickets";
$ticket_data = $conn->query($total_tickets_sql)->fetch_assoc();

// Thống kê theo tháng (6 tháng gần nhất)
$revenue_by_month = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i month"));
    $month_label = date('m/Y', strtotime("-$i month"));
    
    $month_sql = "SELECT IFNULL(SUM(total_price), 0) as revenue 
                  FROM tickets 
                  WHERE DATE_FORMAT(purchase_date, '%Y-%m') = ?";
    $month_stmt = $conn->prepare($month_sql);
    $month_stmt->bind_param("s", $month);
    $month_stmt->execute();
    $month_result = $month_stmt->get_result()->fetch_assoc();
    
    $revenue_by_month[] = [
        'label' => $month_label,
        'value' => $month_result['revenue']
    ];
}

// Top 5 sự kiện bán chạy
$top_events_sql = "SELECT e.title, COUNT(t.id) as ticket_count, SUM(t.total_price) as revenue
                   FROM events e
                   LEFT JOIN tickets t ON e.id = t.event_id
                   GROUP BY e.id
                   ORDER BY ticket_count DESC
                   LIMIT 5";
$top_events = $conn->query($top_events_sql);

// Người dùng mới hôm nay
$today = date('Y-m-d');
$new_users_sql = "SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = ?";
$new_users_stmt = $conn->prepare($new_users_sql);
$new_users_stmt->bind_param("s", $today);
$new_users_stmt->execute();
$new_users_today = $new_users_stmt->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <ul>
                    <li><a href="index.php" class="active">Dashboard</a></li>
                    <li><a href="manage_events.php">Quản lý sự kiện</a></li>
                    <li><a href="add_event.php">Thêm sự kiện mới</a></li>
                    <li><a href="../index.php" target="_blank">Xem website</a></li>
                    <li><a href="logout.php">Đăng xuất</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="admin-content">
            <h1>Dashboard</h1>
            <p>Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>!</p>
            
            <!-- THỐNG KÊ TỔNG QUAN -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-info">
                        <h3>Tổng sự kiện</h3>
                        <p class="stat-number"><?php echo $total_events; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3>Người dùng</h3>
                        <p class="stat-number"><?php echo $total_users; ?></p>
                        <small>+<?php echo $new_users_today; ?> hôm nay</small>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎫</div>
                    <div class="stat-info">
                        <h3>Vé đã bán</h3>
                        <p class="stat-number"><?php echo $ticket_data['total']; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3>Doanh thu</h3>
                        <p class="stat-number"><?php echo number_format($ticket_data['revenue'], 0, ',', '.'); ?> đ</p>
                    </div>
                </div>
            </div>
            
            <!-- BIỂU ĐỒ DOANH THU -->
            <div class="chart-section">
                <h2>📈 Doanh thu 6 tháng gần đây</h2>
                <canvas id="revenueChart"></canvas>
            </div>
            
            <!-- TOP SỰ KIỆN -->
            <div class="top-events-section">
                <h2>🏆 Top 5 sự kiện bán chạy</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tên sự kiện</th>
                            <th>Số vé đã bán</th>
                            <th>Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        while ($top = $top_events->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($top['title']); ?></td>
                                <td><?php echo $top['ticket_count']; ?></td>
                                <td><?php echo number_format($top['revenue'], 0, ',', '.'); ?> đ</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
    // Biểu đồ doanh thu
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const revenueData = <?php echo json_encode($revenue_by_month); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: revenueData.map(d => d.label),
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: revenueData.map(d => d.value),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('vi-VN') + ' đ';
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>