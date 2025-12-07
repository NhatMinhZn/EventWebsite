<?php
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$page_title = "Tài khoản của tôi";
$user_id = $_SESSION['user_id'];

// Lấy thông tin user
$sql = "SELECT username, email, full_name, phone, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Đếm số sự kiện trong lịch
$count_sql = "SELECT COUNT(*) as total FROM user_calendar WHERE user_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$event_count = $count_result->fetch_assoc()['total'];

// Đếm số vé đã mua
$ticket_sql = "SELECT COUNT(*) as total, IFNULL(SUM(quantity), 0) as tickets FROM tickets WHERE user_id = ?";
$ticket_stmt = $conn->prepare($ticket_sql);
$ticket_stmt->bind_param("i", $user_id);
$ticket_stmt->execute();
$ticket_result = $ticket_stmt->get_result();
$ticket_data = $ticket_result->fetch_assoc();

include 'includes/header.php';
?>

<main class="container">
    <div class="account-container">
        <h2>Tài khoản của tôi</h2>
        
        <!-- THÔNG TIN CÁ NHÂN -->
        <div class="account-section">
            <h3>📋 Thông tin cá nhân</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Tên đăng nhập:</span>
                    <span class="info-value">@<?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Họ và tên:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Số điện thoại:</span>
                    <span class="info-value"><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '<em style="color:#999;">Chưa cập nhật</em>'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Ngày tham gia:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
        </div>
        
        <!-- THỐNG KÊ -->
        <div class="account-section">
            <h3>📊 Thống kê hoạt động</h3>
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $event_count; ?></div>
                    <div class="stat-label">Sự kiện trong lịch</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $ticket_data['tickets']; ?></div>
                    <div class="stat-label">Vé đã mua</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $ticket_data['total']; ?></div>
                    <div class="stat-label">Lần đặt vé</div>
                </div>
            </div>
        </div>
        
        <!-- BẢO MẬT -->
        <div class="account-section">
            <h3>🔒 Bảo mật</h3>
            <div class="security-actions">
                <a href="change_password.php" class="action-btn btn-primary">
                    <span>🔑</span>
                    <div>
                        <strong>Đổi mật khẩu</strong>
                        <p>Thay đổi mật khẩu của bạn</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- ĐĂNG XUẤT -->
        <div class="account-section">
            <h3>⚙️ Hành động</h3>
            <div class="security-actions">
                <a href="logout.php" class="action-btn btn-danger" onclick="return confirm('Bạn có chắc muốn đăng xuất?')">
                    <span>🚪</span>
                    <div>
                        <strong>Đăng xuất</strong>
                        <p>Đăng xuất khỏi tài khoản</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</main>

<style>
.account-container {
    max-width: 900px;
    margin: 40px auto;
}

.account-section {
    background: white;
    padding: 25px;
    margin: 20px 0;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.account-section h3 {
    margin-bottom: 20px;
    color: #333;
    font-size: 20px;
}

.info-grid {
    display: grid;
    gap: 15px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

.info-label {
    font-weight: 600;
    color: #666;
}

.info-value {
    color: #333;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
}

.stat-box {
    text-align: center;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    color: white;
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
}

.security-actions {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 20px;
    border-radius: 8px;
    text-decoration: none;
    transition: 0.3s;
}

.action-btn span {
    font-size: 28px;
}

.action-btn strong {
    display: block;
    margin-bottom: 5px;
    font-size: 16px;
}

.action-btn p {
    margin: 0;
    font-size: 13px;
    opacity: 0.8;
}

.btn-primary {
    background: #0066cc;
    color: white;
}

.btn-primary:hover {
    background: #0052a3;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

/* Dark mode */
body.dark .account-section {
    background: #2a2a2a;
}

body.dark .account-section h3 {
    color: #f0f0f0;
}

body.dark .info-item {
    background: #333;
}

body.dark .info-label {
    color: #aaa;
}

body.dark .info-value {
    color: #eee;
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>