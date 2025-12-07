<?php
require_once 'config/database.php';
$page_title = "Cổng Thông Tin Sự Kiện";

// Lấy banners
$banners_sql = "SELECT * FROM banners WHERE is_active = 1 ORDER BY display_order ASC";
$banners_result = $conn->query($banners_sql);

// Lấy categories
$categories_sql = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_sql);

// Lấy sự kiện nổi bật (featured)
$featured_sql = "SELECT * FROM events WHERE is_featured = 1 ORDER BY created_at DESC LIMIT 6";
$featured_result = $conn->query($featured_sql);

// Lấy sự kiện mới nhất
$latest_sql = "SELECT * FROM events ORDER BY created_at DESC LIMIT 9";
$latest_result = $conn->query($latest_sql);

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

<!-- CATEGORIES -->
<section class="categories-section">
    <div class="container">
        <h2>📂 Danh mục sự kiện</h2>
        <div class="categories-grid">
            <a href="events.php" class="category-item">
                <span class="category-icon">🌟</span>
                <span class="category-name">Tất cả</span>
            </a>
            <?php while ($cat = $categories_result->fetch_assoc()): ?>
                <a href="events.php?category=<?php echo $cat['id']; ?>" class="category-item">
                    <span class="category-icon"><?php echo $cat['icon']; ?></span>
                    <span class="category-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<section class="about-section" id="about">
    <div class="about-box">
        <h2>Về chúng tôi</h2>
        <p>Danang Event Portal tự hào là nền tảng hàng đầu cung cấp thông tin các sự kiện hấp dẫn, đặc sắc tại Đà Nẵng.</p>
    </div>
</section>

<main class="container">
    <!-- SỰ KIỆN NỔI BẬT -->
    <?php if ($featured_result->num_rows > 0): ?>
    <h2>⭐ Sự kiện nổi bật</h2>
    <div class="event-list">
        <?php while ($event = $featured_result->fetch_assoc()): ?>
            <div class="event-card featured">
                <span class="featured-badge">⭐ NỔI BẬT</span>
                <a href="event.php?id=<?php echo $event['id']; ?>">
                    <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" />
                    <div class="event-content">
                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                        <p class="event-date">📅 <?php echo date('d/m/Y', strtotime($event['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($event['end_date'])); ?></p>
                        <p class="event-location">📍 <?php echo htmlspecialchars($event['location']); ?></p>
                        <?php if ($event['avg_rating'] > 0): ?>
                            <p class="event-rating">
                                ⭐ <?php echo number_format($event['avg_rating'], 1); ?> 
                                <small>(<?php echo $event['total_reviews']; ?> đánh giá)</small>
                            </p>
                        <?php endif; ?>
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
    </div>
    <?php endif; ?>
    
    <!-- SỰ KIỆN MỚI NHẤT -->
    <br><br>
    <h2>🆕 Sự kiện mới nhất</h2>
    <div class="event-list">
        <?php if ($latest_result->num_rows > 0): ?>
            <?php while ($event = $latest_result->fetch_assoc()): ?>
                <div class="event-card">
                    <a href="event.php?id=<?php echo $event['id']; ?>">
                        <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" />
                        <div class="event-content">
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p class="event-date">📅 <?php echo date('d/m/Y', strtotime($event['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($event['end_date'])); ?></p>
                            <p class="event-location">📍 <?php echo htmlspecialchars($event['location']); ?></p>
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
            <p>Chưa có sự kiện nào.</p>
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
    margin-bottom: 40px;
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

/* CATEGORIES */
.categories-section {
    background: #f8f9fa;
    padding: 40px 0;
    margin-bottom: 40px;
}

.categories-section h2 {
    text-align: center;
    margin-bottom: 30px;
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
}

.category-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    color: #0066cc;
}

.category-icon {
    font-size: 36px;
}

.category-name {
    font-weight: 600;
    font-size: 14px;
}

/* FEATURED EVENT */
.event-card.featured {
    position: relative;
    border: 2px solid #ffc107;
}

.featured-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ffc107;
    color: #333;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: bold;
    z-index: 5;
}

body.dark .categories-section {
    background: #1a1a1a;
}

body.dark .category-item {
    background: #2a2a2a;
    color: #f0f0f0;
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

<?php include 'includes/footer.php'; ?>