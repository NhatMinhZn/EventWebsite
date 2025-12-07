<?php
require_once 'config/database.php';

$page_title = "Tất cả sự kiện";

// === XỬ LÝ TÌM KIẾM & LỌC ===
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'all';
$price_filter = isset($_GET['price']) ? $_GET['price'] : 'all';
$location_filter = isset($_GET['location']) ? $_GET['location'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build SQL query
$where_conditions = ["1=1"];
$params = [];
$types = "";

// Tìm kiếm theo keyword
if (!empty($keyword)) {
    $where_conditions[] = "(title LIKE ? OR location LIKE ? OR description LIKE ?)";
    $search_term = "%{$keyword}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

// Lọc theo thời gian
$today = date('Y-m-d');
switch ($time_filter) {
    case 'ongoing':
        $where_conditions[] = "start_date <= ? AND end_date >= ?";
        $params[] = $today;
        $params[] = $today;
        $types .= "ss";
        break;
    case 'upcoming':
        $where_conditions[] = "start_date > ?";
        $params[] = $today;
        $types .= "s";
        break;
    case 'upcoming_7days':
        $next_7days = date('Y-m-d', strtotime('+7 days'));
        $where_conditions[] = "start_date > ? AND start_date <= ?";
        $params[] = $today;
        $params[] = $next_7days;
        $types .= "ss";
        break;
    case 'this_month':
        $first_day = date('Y-m-01');
        $last_day = date('Y-m-t');
        $where_conditions[] = "start_date >= ? AND start_date <= ?";
        $params[] = $first_day;
        $params[] = $last_day;
        $types .= "ss";
        break;
    case 'past':
        $where_conditions[] = "end_date < ?";
        $params[] = $today;
        $types .= "s";
        break;
}

// Lọc theo giá
switch ($price_filter) {
    case 'free':
        $where_conditions[] = "ticket_price = 0";
        break;
    case 'under_100k':
        $where_conditions[] = "ticket_price > 0 AND ticket_price < 100000";
        break;
    case '100k_500k':
        $where_conditions[] = "ticket_price >= 100000 AND ticket_price <= 500000";
        break;
    case 'above_500k':
        $where_conditions[] = "ticket_price > 500000";
        break;
}

// Lọc theo địa điểm
if ($location_filter !== 'all') {
    $where_conditions[] = "location LIKE ?";
    $location_search = "%{$location_filter}%";
    $params[] = $location_search;
    $types .= "s";
}

// Sắp xếp
$order_by = "created_at DESC"; // Mặc định
switch ($sort) {
    case 'oldest':
        $order_by = "created_at ASC";
        break;
    case 'upcoming':
        $order_by = "start_date ASC";
        break;
    case 'price_low':
        $order_by = "ticket_price ASC";
        break;
    case 'price_high':
        $order_by = "ticket_price DESC";
        break;
    case 'tickets':
        $order_by = "available_tickets DESC";
        break;
}

// Build final query
$where_sql = implode(" AND ", $where_conditions);
$sql = "SELECT * FROM events WHERE {$where_sql} ORDER BY {$order_by}";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Lấy danh sách địa điểm unique
$locations_sql = "SELECT DISTINCT location FROM events ORDER BY location";
$locations_result = $conn->query($locations_sql);

include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h2>Tất cả các sự kiện nổi bật tại Đà Nẵng</h2>
    </div>
</section>

<main class="container">
    <!-- SEARCH & FILTER -->
    <div class="search-filter-section">
        <form method="GET" action="events.php" class="search-filter-form">
            <!-- Tìm kiếm -->
            <div class="search-box">
                <input type="text" name="keyword" placeholder="🔍 Tìm kiếm sự kiện..." value="<?php echo htmlspecialchars($keyword); ?>" />
                <button type="submit" class="btn-search">Tìm kiếm</button>
            </div>
            
            <!-- Bộ lọc -->
            <div class="filter-row">
                <div class="filter-item">
                    <label>📅 Thời gian:</label>
                    <select name="time">
                        <option value="all" <?php echo $time_filter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                        <option value="ongoing" <?php echo $time_filter === 'ongoing' ? 'selected' : ''; ?>>Đang diễn ra</option>
                        <option value="upcoming" <?php echo $time_filter === 'upcoming' ? 'selected' : ''; ?>>Sắp diễn ra</option>
                        <option value="upcoming_7days" <?php echo $time_filter === 'upcoming_7days' ? 'selected' : ''; ?>>7 ngày tới</option>
                        <option value="this_month" <?php echo $time_filter === 'this_month' ? 'selected' : ''; ?>>Tháng này</option>
                        <option value="past" <?php echo $time_filter === 'past' ? 'selected' : ''; ?>>Đã kết thúc</option>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>💰 Giá vé:</label>
                    <select name="price">
                        <option value="all" <?php echo $price_filter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                        <option value="free" <?php echo $price_filter === 'free' ? 'selected' : ''; ?>>Miễn phí</option>
                        <option value="under_100k" <?php echo $price_filter === 'under_100k' ? 'selected' : ''; ?>>Dưới 100k</option>
                        <option value="100k_500k" <?php echo $price_filter === '100k_500k' ? 'selected' : ''; ?>>100k - 500k</option>
                        <option value="above_500k" <?php echo $price_filter === 'above_500k' ? 'selected' : ''; ?>>Trên 500k</option>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>📍 Địa điểm:</label>
                    <select name="location">
                        <option value="all">Tất cả</option>
                        <?php while ($loc = $locations_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($loc['location']); ?>" 
                                <?php echo $location_filter === $loc['location'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>📊 Sắp xếp:</label>
                    <select name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                        <option value="upcoming" <?php echo $sort === 'upcoming' ? 'selected' : ''; ?>>Sắp diễn ra</option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Giá thấp → cao</option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Giá cao → thấp</option>
                        <option value="tickets" <?php echo $sort === 'tickets' ? 'selected' : ''; ?>>Còn nhiều vé</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <a href="events.php" class="btn-reset">🔄 Xóa bộ lọc</a>
            </div>
        </form>
    </div>
    
    <!-- KẾT QUẢ -->
    <div class="search-results">
        <?php if (!empty($keyword) || $time_filter !== 'all' || $price_filter !== 'all' || $location_filter !== 'all'): ?>
            <p class="result-count">
                Tìm thấy <strong><?php echo $result->num_rows; ?></strong> sự kiện
                <?php if (!empty($keyword)): ?>
                    với từ khóa "<strong><?php echo htmlspecialchars($keyword); ?></strong>"
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    
    <!-- DANH SÁCH SỰ KIỆN -->
    <div class="event-list">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($event = $result->fetch_assoc()): ?>
                <div class="event-card">
                    <a href="event.php?id=<?php echo $event['id']; ?>">
                        <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" />
                        <div class="event-content">
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p class="event-date">📅 <?php echo date('d/m/Y', strtotime($event['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($event['end_date'])); ?></p>
                            <p class="event-location">📍 <?php echo htmlspecialchars($event['location']); ?></p>
                            <p class="event-price">
                                <?php if ($event['ticket_price'] > 0): ?>
                                    💰 <?php echo number_format($event['ticket_price'], 0, ',', '.'); ?> VNĐ
                                <?php else: ?>
                                    <span style="color: #28a745; font-weight: bold;">🎉 MIỄN PHÍ</span>
                                <?php endif; ?>
                            </p>
                            <p class="event-description">
                                <?php 
                                $desc = htmlspecialchars($event['description']);
                                echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                ?>
                            </p>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-results">
                <p>😞 Không tìm thấy sự kiện phù hợp</p>
                <a href="events.php" class="btn-primary">Xem tất cả sự kiện</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* Search & Filter Section */
.search-filter-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 30px 0;
}

.search-box {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-box input {
    flex: 1;
    padding: 12px 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
}

.search-box input:focus {
    outline: none;
    border-color: #0066cc;
}

.btn-search {
    padding: 12px 30px;
    background: #0066cc;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.btn-search:hover {
    background: #0052a3;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filter-item label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.filter-item select {
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 6px;
    background: white;
    cursor: pointer;
}

.filter-item select:focus {
    outline: none;
    border-color: #0066cc;
}

.filter-actions {
    text-align: center;
}

.btn-reset {
    display: inline-block;
    padding: 10px 20px;
    background: #6c757d;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: 0.3s;
}

.btn-reset:hover {
    background: #5a6268;
}

.search-results {
    margin: 20px 0;
}

.result-count {
    font-size: 16px;
    color: #666;
}

.no-results {
    text-align: center;
    padding: 60px 20px;
}

.no-results p {
    font-size: 20px;
    color: #999;
    margin-bottom: 20px;
}

.btn-primary {
    display: inline-block;
    padding: 12px 24px;
    background: #0066cc;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
}

.btn-primary:hover {
    background: #0052a3;
}

.event-price {
    font-size: 14px;
    font-weight: 600;
    color: #0066cc;
    margin: 8px 0;
}

/* Dark mode */
body.dark .search-filter-section {
    background: #2a2a2a;
}

body.dark .search-box input,
body.dark .filter-item select {
    background: #333;
    border-color: #444;
    color: #fff;
}

body.dark .filter-item label {
    color: #eee;
}

body.dark .no-results p {
    color: #aaa;
}

@media (max-width: 768px) {
    .search-box {
        flex-direction: column;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>