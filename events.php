<?php
require_once 'config/database.php';

$page_title = "Tất cả sự kiện";

// Lấy banners
$banners_sql = "SELECT * FROM banners WHERE is_active = 1 ORDER BY display_order ASC";
$banners_result = $conn->query($banners_sql);

// === XỬ LÝ TÌM KIẾM & LỌC ===
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$ward_filter = isset($_GET['ward']) ? $_GET['ward'] : 'all';
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'all';
$price_filter = isset($_GET['price']) ? $_GET['price'] : 'all';
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

// Lọc theo danh mục
if ($category_filter !== 'all') {
    $where_conditions[] = "EXISTS (SELECT 1 FROM event_categories WHERE event_categories.event_id = events.id AND event_categories.category_id = ?)";
    $params[] = $category_filter;
    $types .= "i";
}

// Lọc theo xã/phường
if ($ward_filter !== 'all') {
    $where_conditions[] = "EXISTS (SELECT 1 FROM event_wards WHERE event_wards.event_id = events.id AND event_wards.ward_id = ?)";
    $params[] = $ward_filter;
    $types .= "i";
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
    case 'all':
    default:
        // MẶC ĐỊNH: CHỈ HIỂN THỊ SỰ KIỆN CHƯA KẾT THÚC (end_date >= hôm nay)
        $where_conditions[] = "end_date >= ?";
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

// Lấy danh sách categories
$categories_sql = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_sql);

// Lấy danh sách xã/phường
$wards_sql = "SELECT * FROM wards ORDER BY type, display_order ASC";
$wards_result = $conn->query($wards_sql);

include 'includes/header.php';
?>

<!-- BANNER SLIDER -->
<?php if ($banners_result->num_rows > 0): ?>
<div class="banner-slider">
    <div class="slider-container">
        <?php 
        $banner_index = 0;
        while ($banner = $banners_result->fetch_assoc()): 
        ?>
            <div class="slide <?php echo $banner_index === 0 ? 'active' : ''; ?>">
                <?php if (!empty($banner['link'])): ?>
                    <a href="<?php echo htmlspecialchars($banner['link']); ?>">
                        <img src="<?php echo htmlspecialchars($banner['image']); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>">
                        <div class="slide-caption">
                            <h2><?php echo htmlspecialchars($banner['title']); ?></h2>
                        </div>
                    </a>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($banner['image']); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>">
                    <div class="slide-caption">
                        <h2><?php echo htmlspecialchars($banner['title']); ?></h2>
                    </div>
                <?php endif; ?>
            </div>
        <?php 
        $banner_index++;
        endwhile; 
        ?>
    </div>
    
    <?php if ($banners_result->num_rows > 1): ?>
    <button class="slider-btn prev" onclick="changeSlide(-1)">❮</button>
    <button class="slider-btn next" onclick="changeSlide(1)">❯</button>
    
    <div class="slider-dots">
        <?php for ($i = 0; $i < $banner_index; $i++): ?>
            <span class="dot <?php echo $i === 0 ? 'active' : ''; ?>" onclick="currentSlide(<?php echo $i; ?>)"></span>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- CATEGORIES SECTION -->
<section class="categories-section">
    <div class="container">
        <h2>📂 Danh mục sự kiện</h2>
        <div class="categories-grid">
            <a href="events.php" class="category-item <?php echo $category_filter === 'all' ? 'active' : ''; ?>">
                <span class="category-icon">🌟</span>
                <span class="category-name">Tất cả</span>
            </a>
            <?php while ($cat = $categories_result->fetch_assoc()): ?>
                <a href="events.php?category=<?php echo $cat['id']; ?>" 
                   class="category-item <?php echo $category_filter == $cat['id'] ? 'active' : ''; ?>">
                    <span class="category-icon"><?php echo $cat['icon']; ?></span>
                    <span class="category-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                </a>
            <?php endwhile; ?>
        </div>
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
                    <label>🏘️ Xã/Phường:</label>
                    <select name="ward" class="select2-filter-ward">
                        <option value="all">Tất cả</option>
                        <?php 
                        $current_type = '';
                        while ($ward = $wards_result->fetch_assoc()): 
                            if ($current_type !== $ward['type']) {
                                if ($current_type !== '') echo '</optgroup>';
                                echo '<optgroup label="' . $ward['type'] . '">';
                                $current_type = $ward['type'];
                            }
                        ?>
                            <option value="<?php echo $ward['id']; ?>" <?php echo $ward_filter == $ward['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ward['name']); ?>
                            </option>
                        <?php 
                        endwhile; 
                        if ($current_type !== '') echo '</optgroup>';
                        ?>
                    </select>
                </div>
                
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
                <button type="submit" class="btn-filter">🔍 Lọc kết quả</button>
                <a href="events.php" class="btn-reset">🔄 Xóa bộ lọc</a>
            </div>
        </form>
    </div>
    
    <!-- KẾT QUẢ -->
    <div class="search-results">
        <?php if (!empty($keyword) || $category_filter !== 'all' || $ward_filter !== 'all' || $time_filter !== 'all' || $price_filter !== 'all'): ?>
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
/* BANNER SLIDER */
.banner-slider {
    position: relative;
    width: 100%;
    max-width: 100%;
    height: 500px;
    overflow: hidden;
    margin-bottom: 0;
}

.slider-container {
    position: relative;
    height: 100%;
}

.slide {
    display: none;
    position: relative;
    width: 100%;
    height: 100%;
}

.slide.active {
    display: block;
    animation: fadeIn 0.5s;
}

@keyframes fadeIn {
    from { opacity: 0.8; }
    to { opacity: 1; }
}

.slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.slide-caption {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    padding: 40px 20px 20px;
    color: white;
}

.slide-caption h2 {
    font-size: 32px;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
}

.slider-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.5);
    color: white;
    border: none;
    padding: 16px 20px;
    font-size: 24px;
    cursor: pointer;
    transition: 0.3s;
    z-index: 10;
}

.slider-btn:hover {
    background: rgba(0,0,0,0.8);
}

.slider-btn.prev { left: 10px; }
.slider-btn.next { right: 10px; }

.slider-dots {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 10;
}

.dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255,255,255,0.5);
    cursor: pointer;
    transition: 0.3s;
}

.dot.active,
.dot:hover {
    background: white;
    transform: scale(1.2);
}

/* CATEGORIES SECTION */
.categories-section {
    background: #f8f9fa;
    padding: 40px 0;
    margin-bottom: 0;
}

.categories-section h2 {
    text-align: center;
    margin-bottom: 30px;
    color: #333;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
}

.category-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px;
    background: white;
    border-radius: 10px;
    text-decoration: none;
    color: #333;
    transition: 0.3s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    border: 2px solid transparent;
}

.category-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    color: #0066cc;
}

.category-item.active {
    border-color: #0066cc;
    background: #e6f2ff;
    color: #0066cc;
    box-shadow: 0 5px 15px rgba(0,102,204,0.2);
}

.category-icon {
    font-size: 36px;
}

.category-name {
    font-weight: 600;
    font-size: 14px;
}

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

/* Select2 override - match with regular selects */
.filter-item .select2-container {
    width: 100% !important;
}

.filter-item .select2-container .select2-selection--single {
    height: 42px;
    border: 2px solid #ddd;
    border-radius: 6px;
}

.filter-item .select2-container .select2-selection--single .select2-selection__rendered {
    line-height: 38px;
    padding-left: 10px;
}

.filter-item .select2-container .select2-selection--single .select2-selection__arrow {
    height: 38px;
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
    border: none;
    cursor: pointer;
}

.btn-reset:hover {
    background: #5a6268;
}

.btn-filter {
    display: inline-block;
    padding: 10px 24px;
    background: #0066cc;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    margin-right: 10px;
}

.btn-filter:hover {
    background: #0052a3;
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

/* Event card fixed heights */
.event-title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 8px;
    height: 50px;
    overflow: hidden;
    line-height: 25px;
    display: block;
}

.event-location {
    font-size: 14px;
    color: #777;
    margin-bottom: 10px;
    height: 20px;
    overflow: hidden;
    line-height: 20px;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.event-description {
    font-size: 14px;
    color: #555;
    margin-top: auto;
    height: 63px;
    overflow: hidden;
    line-height: 21px;
    display: block;
}

/* Dark mode */
body.dark .categories-section {
    background: #1a1a1a;
}

body.dark .categories-section h2 {
    color: #f0f0f0;
}

body.dark .category-item {
    background: #2a2a2a;
    color: #f0f0f0;
}

body.dark .category-item.active {
    background: #1a3a5a;
    border-color: #4d94ff;
    color: #4d94ff;
}

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
    .banner-slider {
        height: 300px;
    }
    
    .slide-caption h2 {
        font-size: 20px;
    }
    
    .categories-grid {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
    
    .search-box {
        flex-direction: column;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Banner Slider
let currentSlideIndex = 0;

function changeSlide(n) {
    showSlide(currentSlideIndex += n);
}

function currentSlide(n) {
    showSlide(currentSlideIndex = n);
}

function showSlide(n) {
    const slides = document.getElementsByClassName('slide');
    const dots = document.getElementsByClassName('dot');
    
    if (n >= slides.length) currentSlideIndex = 0;
    if (n < 0) currentSlideIndex = slides.length - 1;
    
    for (let i = 0; i < slides.length; i++) {
        slides[i].classList.remove('active');
    }
    
    for (let i = 0; i < dots.length; i++) {
        dots[i].classList.remove('active');
    }
    
    slides[currentSlideIndex].classList.add('active');
    if (dots[currentSlideIndex]) {
        dots[currentSlideIndex].classList.add('active');
    }
}

// Auto slide every 5 seconds
setInterval(() => {
    changeSlide(1);
}, 5000);
</script>

<!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Select2 cho bộ lọc xã/phường
    $('.select2-filter-ward').select2({
        placeholder: "Chọn xã/phường...",
        allowClear: true,
        language: {
            noResults: function() {
                return "Không tìm thấy kết quả";
            }
        }
    });
    
    // Không auto submit - user phải nhấn nút "Lọc"
});
</script>

<?php include 'includes/footer.php'; ?>