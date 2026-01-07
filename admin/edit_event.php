<?php
require_once '../config/database.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = "Sửa sự kiện";
$error = '';
$success = '';

$sql = "SELECT * FROM events WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('manage_events.php');
}

$event = $result->fetch_assoc();

// Lấy ảnh hiện tại
$images_sql = "SELECT * FROM event_images WHERE event_id = ? ORDER BY display_order ASC";
$images_stmt = $conn->prepare($images_sql);
$images_stmt->bind_param("i", $event_id);
$images_stmt->execute();
$current_images = $images_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Lấy wards và categories đã chọn
$selected_wards_sql = "SELECT ward_id FROM event_wards WHERE event_id = ?";
$selected_wards_stmt = $conn->prepare($selected_wards_sql);
$selected_wards_stmt->bind_param("i", $event_id);
$selected_wards_stmt->execute();
$selected_ward_ids = array_column($selected_wards_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'ward_id');

$selected_categories_sql = "SELECT category_id FROM event_categories WHERE event_id = ?";
$selected_categories_stmt = $conn->prepare($selected_categories_sql);
$selected_categories_stmt->bind_param("i", $event_id);
$selected_categories_stmt->execute();
$selected_category_ids = array_column($selected_categories_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'category_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $location = trim($_POST['location']);
    
    $selected_wards = isset($_POST['wards']) ? $_POST['wards'] : [];
    $selected_categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    $ticket_price = isset($_POST['ticket_price']) ? (float)$_POST['ticket_price'] : 0;
    $available_tickets = isset($_POST['available_tickets']) ? (int)$_POST['available_tickets'] : 0;
    
    // Xử lý ảnh mới
    $keep_images = isset($_POST['keep_images']) ? $_POST['keep_images'] : [];
    $new_image_urls = isset($_POST['image_urls']) ? array_filter($_POST['image_urls']) : [];
    $uploaded_images = [];
    
    // Upload file mới
    if (isset($_FILES['image_files'])) {
        foreach ($_FILES['image_files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['image_files']['error'][$key] === 0) {
                $file_name = $_FILES['image_files']['name'][$key];
                $file_size = $_FILES['image_files']['size'][$key];
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed) && $file_size <= 5 * 1024 * 1024) {
                    $new_filename = 'event_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $upload_path = '../uploads/events/';
                    
                    if (!file_exists($upload_path)) {
                        mkdir($upload_path, 0777, true);
                    }
                    
                    if (move_uploaded_file($tmp_name, $upload_path . $new_filename)) {
                        $uploaded_images[] = 'uploads/events/' . $new_filename;
                    }
                }
            }
        }
    }
    
    // Kiểm tra ngày
    if (empty($error)) {
        $today = date('Y-m-d');
        if ($end_date < $today) {
            $error = "❌ Không thể cập nhật sự kiện với ngày kết thúc đã qua!";
        } elseif ($start_date > $end_date) {
            $error = "❌ Ngày bắt đầu không thể sau ngày kết thúc!";
        }
    }
    
    if (empty($error)) {
        $conn->begin_transaction();
        try {
            // Cập nhật thông tin event
            $update_sql = "UPDATE events SET title = ?, description = ?, start_date = ?, end_date = ?, location = ?, ticket_price = ?, available_tickets = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssssdii", $title, $description, $start_date, $end_date, $location, $ticket_price, $available_tickets, $event_id);
            $update_stmt->execute();
            
            // Xóa ảnh cũ không được giữ lại
            foreach ($current_images as $img) {
                if (!in_array($img['id'], $keep_images)) {
                    $del_img_sql = "DELETE FROM event_images WHERE id = ?";
                    $del_img_stmt = $conn->prepare($del_img_sql);
                    $del_img_stmt->bind_param("i", $img['id']);
                    $del_img_stmt->execute();
                    
                    // Xóa file nếu là upload
                    if (strpos($img['image_url'], 'uploads/events/') === 0 && file_exists('../' . $img['image_url'])) {
                        unlink('../' . $img['image_url']);
                    }
                }
            }
            
            // Thêm ảnh mới (URL + upload)
            $all_new_images = array_merge($new_image_urls, $uploaded_images);
            $max_order_sql = "SELECT COALESCE(MAX(display_order), 0) as max_order FROM event_images WHERE event_id = ?";
            $max_order_stmt = $conn->prepare($max_order_sql);
            $max_order_stmt->bind_param("i", $event_id);
            $max_order_stmt->execute();
            $max_order = $max_order_stmt->get_result()->fetch_assoc()['max_order'];
            
            $img_stmt = $conn->prepare("INSERT INTO event_images (event_id, image_url, display_order) VALUES (?, ?, ?)");
            foreach ($all_new_images as $img_url) {
                $max_order++;
                $img_stmt->bind_param("isi", $event_id, $img_url, $max_order);
                $img_stmt->execute();
            }
            
            // Cập nhật wards
            $del_wards_sql = "DELETE FROM event_wards WHERE event_id = ?";
            $del_wards_stmt = $conn->prepare($del_wards_sql);
            $del_wards_stmt->bind_param("i", $event_id);
            $del_wards_stmt->execute();
            
            if (!empty($selected_wards)) {
                $ward_stmt = $conn->prepare("INSERT INTO event_wards (event_id, ward_id) VALUES (?, ?)");
                foreach ($selected_wards as $ward_id) {
                    $ward_stmt->bind_param("ii", $event_id, $ward_id);
                    $ward_stmt->execute();
                }
            }
            
            // Cập nhật categories
            $del_categories_sql = "DELETE FROM event_categories WHERE event_id = ?";
            $del_categories_stmt = $conn->prepare($del_categories_sql);
            $del_categories_stmt->bind_param("i", $event_id);
            $del_categories_stmt->execute();
            
            if (!empty($selected_categories)) {
                $cat_stmt = $conn->prepare("INSERT INTO event_categories (event_id, category_id) VALUES (?, ?)");
                foreach ($selected_categories as $category_id) {
                    $cat_stmt->bind_param("ii", $event_id, $category_id);
                    $cat_stmt->execute();
                }
            }
            
            $conn->commit();
            $success = "✅ Cập nhật sự kiện thành công!";
            header("refresh:1.5;url=manage_events.php");
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Có lỗi xảy ra: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
    .current-images {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 15px 0;
    }
    
    .current-image-item {
        position: relative;
        width: 150px;
        border: 2px solid #ddd;
        border-radius: 8px;
        padding: 5px;
    }
    
    .current-image-item img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: 5px;
    }
    
    .current-image-item label {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 5px;
        font-size: 13px;
    }
    
    .image-preview-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 15px 0;
    }
    
    .image-preview-item {
        position: relative;
        width: 150px;
        height: 100px;
        border: 2px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .image-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .image-preview-item .remove-img {
        position: absolute;
        top: 5px;
        right: 5px;
        background: red;
        color: white;
        border: none;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        cursor: pointer;
    }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <h2>Admin Panel</h2>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="manage_events.php" class="active">Quản lý sự kiện</a></li>
                    <li><a href="add_event.php">Thêm sự kiện mới</a></li>
                    <li><a href="approve_tickets.php">Duyệt vé</a></li>
                    <li><a href="../index.php" target="_blank">Xem website</a></li>
                    <li><a href="logout.php">Đăng xuất</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="admin-content">
            <h1>Sửa sự kiện: <?php echo htmlspecialchars($event['title']); ?></h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="admin-form" enctype="multipart/form-data">
                <label>Tiêu đề sự kiện <span class="required">*</span></label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($event['title']); ?>" required />
                
                <label>Mô tả <span class="required">*</span></label>
                <textarea name="description" rows="5" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                
                <div class="form-row">
                    <div class="form-col">
                        <label>Ngày bắt đầu <span class="required">*</span></label>
                        <input type="date" name="start_date" value="<?php echo $event['start_date']; ?>" required />
                    </div>
                    <div class="form-col">
                        <label>Ngày kết thúc <span class="required">*</span></label>
                        <input type="date" name="end_date" value="<?php echo $event['end_date']; ?>" required />
                    </div>
                </div>
                
                <label>Địa điểm <span class="required">*</span></label>
                <input type="text" name="location" value="<?php echo htmlspecialchars($event['location']); ?>" required />
                
                <label>Xã/Phường</label>
                <select name="wards[]" class="select2-wards" multiple="multiple">
                    <?php
                    $wards_sql = "SELECT * FROM wards ORDER BY type, display_order ASC";
                    $wards_result = $conn->query($wards_sql);
                    $current_type = '';
                    while ($ward = $wards_result->fetch_assoc()):
                        $is_selected = in_array($ward['id'], $selected_ward_ids) ? 'selected' : '';
                        
                        if ($current_type !== $ward['type']) {
                            if ($current_type !== '') echo '</optgroup>';
                            echo '<optgroup label="' . $ward['type'] . '">';
                            $current_type = $ward['type'];
                        }
                    ?>
                        <option value="<?php echo $ward['id']; ?>" <?php echo $is_selected; ?>>
                            <?php echo htmlspecialchars($ward['name']); ?>
                        </option>
                    <?php 
                    endwhile; 
                    if ($current_type !== '') echo '</optgroup>';
                    ?>
                </select>
                
                <label>Danh mục</label>
                <select name="categories[]" class="select2-categories" multiple="multiple">
                    <?php
                    $categories_sql = "SELECT * FROM categories ORDER BY name ASC";
                    $categories_result = $conn->query($categories_sql);
                    while ($category = $categories_result->fetch_assoc()):
                        $is_selected = in_array($category['id'], $selected_category_ids) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $is_selected; ?>>
                            <?php echo htmlspecialchars($category['icon']) . ' ' . htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <!-- ẢNH HIỆN TẠI -->
                <label>Ảnh hiện tại (Chọn ảnh muốn giữ lại)</label>
                <div class="current-images">
                    <?php foreach ($current_images as $img): ?>
                        <div class="current-image-item">
                            <img src="<?php echo strpos($img['image_url'], 'http') === 0 ? htmlspecialchars($img['image_url']) : '../' . htmlspecialchars($img['image_url']); ?>" alt="">
                            <label>
                                <input type="checkbox" name="keep_images[]" value="<?php echo $img['id']; ?>" checked>
                                Giữ lại
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- THÊM ẢNH MỚI -->
                <label>Thêm ảnh mới</label>
                <div id="urlImagesSection">
                    <h4>📷 Thêm qua URL</h4>
                    <div style="display:flex;gap:10px;">
                        <input type="text" id="tempUrlInput" placeholder="Dán URL ảnh..." style="flex:1;">
                        <button type="button" onclick="addImageUrl()">➕ Thêm</button>
                    </div>
                </div>
                
                <div id="fileImagesSection" style="margin-top:15px;">
                    <h4>📤 Upload file</h4>
                    <input type="file" name="image_files[]" accept="image/*" multiple>
                </div>
                
                <div id="imagePreviewContainer" class="image-preview-container"></div>
                
                <div class="form-row">
                    <div class="form-col">
                        <label>Giá vé (VNĐ)</label>
                        <input type="number" name="ticket_price" value="<?php echo $event['ticket_price']; ?>" min="0" step="1000" />
                    </div>
                    <div class="form-col">
                        <label>Số vé có sẵn</label>
                        <input type="number" name="available_tickets" value="<?php echo $event['available_tickets']; ?>" min="0" />
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Cập nhật</button>
                <a href="manage_events.php" class="btn btn-secondary">Hủy</a>
            </form>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $('.select2-wards, .select2-categories').select2({
            placeholder: "Chọn...",
            allowClear: true
        });
    });
    
    let imageIndex = 0;
    
    function addImageUrl() {
        const url = document.getElementById('tempUrlInput').value.trim();
        if (!url) {
            alert('Vui lòng nhập URL!');
            return;
        }
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'image_urls[]';
        input.value = url;
        input.id = 'img_' + imageIndex;
        document.querySelector('form').appendChild(input);
        
        const container = document.getElementById('imagePreviewContainer');
        const div = document.createElement('div');
        div.className = 'image-preview-item';
        div.innerHTML = `
            <img src="${url}" alt="Preview">
            <button type="button" class="remove-img" onclick="removeImage('img_${imageIndex}', this.parentElement)">✖</button>
        `;
        container.appendChild(div);
        
        imageIndex++;
        document.getElementById('tempUrlInput').value = '';
    }
    
    function removeImage(inputId, element) {
        document.getElementById(inputId).remove();
        element.remove();
    }
    </script>
</body>
</html>