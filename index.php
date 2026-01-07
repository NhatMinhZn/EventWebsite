<?php
require_once 'config/database.php';
$page_title = "Cổng Thông Tin Sự Kiện";

// Lấy banners
$banners_sql = "SELECT * FROM banners WHERE is_active = 1 ORDER BY display_order ASC";
$banners_result = $conn->query($banners_sql);

// Lấy sự kiện nổi bật (featured) - CHỈ HIỂN THỊ SỰ KIỆN CHƯA KẾT THÚC
$today = date('Y-m-d');
$featured_sql = "SELECT * FROM events WHERE is_featured = 1 AND end_date >= ? ORDER BY created_at DESC LIMIT 6";
$featured_stmt = $conn->prepare($featured_sql);
$featured_stmt->bind_param("s", $today);
$featured_stmt->execute();
$featured_result = $featured_stmt->get_result();

// Lấy sự kiện mới nhất - CHỈ HIỂN THỊ SỰ KIỆN CHƯA KẾT THÚC
$latest_sql = "SELECT * FROM events WHERE end_date >= ? ORDER BY created_at DESC LIMIT 9";
$latest_stmt = $conn->prepare($latest_sql);
$latest_stmt->bind_param("s", $today);
$latest_stmt->execute();
$latest_result = $latest_stmt->get_result();

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
                    <?php
// Lấy ảnh thumbnail ngẫu nhiên từ event_images
$img_sql = "SELECT image_url FROM event_images WHERE event_id = ? ORDER BY RAND() LIMIT 1";
$img_stmt = $conn->prepare($img_sql);
$img_stmt->bind_param("i", $event['id']);
$img_stmt->execute();
$img_result = $img_stmt->get_result();
$thumbnail = $img_result->num_rows > 0 ? $img_result->fetch_assoc()['image_url'] : 'https://via.placeholder.com/300x200?text=No+Image';
?>
<img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" />

<!-- ====================================== -->
<!-- HOẶC NẾU MUỐN LẤY ẢNH ĐẦU TIÊN THAY VÌ NGẪU NHIÊN -->
<!-- (Thay RAND() thành display_order ASC) -->
<!-- ====================================== -->

<?php
$img_sql = "SELECT image_url FROM event_images WHERE event_id = ? ORDER BY display_order ASC LIMIT 1";
$img_stmt = $conn->prepare($img_sql);
$img_stmt->bind_param("i", $event['id']);
$img_stmt->execute();
$img_result = $img_stmt->get_result();
$thumbnail = $img_result->num_rows > 0 ? $img_result->fetch_assoc()['image_url'] : 'https://via.placeholder.com/300x200?text=No+Image';
?>
<img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" />
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

@media (max-width: 768px) {
    .banner-slider {
        height: 300px;
    }
    
    .slide-caption h2 {
        font-size: 20px;
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