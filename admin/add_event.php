<?php
require_once '../config/database.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$page_title = "Thêm sự kiện mới";
$error = '';
$success = '';

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
    $created_by = $_SESSION['user_id'];
    
    // Xử lý nhiều ảnh
    $image_urls = isset($_POST['image_urls']) ? array_filter($_POST['image_urls']) : [];
    $uploaded_images = [];
    
    // Upload file
    if (isset($_FILES['image_files'])) {
        foreach ($_FILES['image_files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['image_files']['error'][$key] === 0) {
                $file_name = $_FILES['image_files']['name'][$key];
                $file_size = $_FILES['image_files']['size'][$key];
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    $error = "Chỉ chấp nhận file ảnh: JPG, PNG, GIF, WEBP";
                    break;
                } elseif ($file_size > 5 * 1024 * 1024) {
                    $error = "Kích thước file tối đa 5MB!";
                    break;
                } else {
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
    
    // Gộp ảnh URL và ảnh upload
    $all_images = array_merge($image_urls, $uploaded_images);
    
    if (empty($error) && (empty($title) || empty($description) || empty($start_date) || empty($end_date) || empty($location) || empty($all_images))) {
        $error = "Vui lòng điền đầy đủ thông tin và thêm ít nhất 1 ảnh!";
    }
    
    // Kiểm tra ngày
    if (empty($error)) {
        $today = date('Y-m-d');
        if ($end_date < $today) {
            $error = "❌ Không thể tạo sự kiện đã kết thúc!";
        } elseif ($start_date > $end_date) {
            $error = "❌ Ngày bắt đầu không thể sau ngày kết thúc!";
        }
    }
    
    if (empty($error)) {
        $conn->begin_transaction();
        try {
            // Thêm event (không cần cột image nữa)
            $sql = "INSERT INTO events (title, description, start_date, end_date, location, ticket_price, available_tickets, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssdii", $title, $description, $start_date, $end_date, $location, $ticket_price, $available_tickets, $created_by);
            $stmt->execute();
            
            $event_id = $conn->insert_id;
            
            // Thêm ảnh vào event_images
            $img_stmt = $conn->prepare("INSERT INTO event_images (event_id, image_url, is_thumbnail, display_order) VALUES (?, ?, ?, ?)");
            foreach ($all_images as $index => $img_url) {
                $is_thumbnail = ($index === 0) ? 1 : 0; // Ảnh đầu tiên là thumbnail
                $display_order = $index + 1;
                $img_stmt->bind_param("isii", $event_id, $img_url, $is_thumbnail, $display_order);
                $img_stmt->execute();
            }
            
            // Lưu xã/phường
            if (!empty($selected_wards)) {
                $ward_stmt = $conn->prepare("INSERT INTO event_wards (event_id, ward_id) VALUES (?, ?)");
                foreach ($selected_wards as $ward_id) {
                    $ward_stmt->bind_param("ii", $event_id, $ward_id);
                    $ward_stmt->execute();
                }
            }
            
            // Lưu categories
            if (!empty($selected_categories)) {
                $cat_stmt = $conn->prepare("INSERT INTO event_categories (event_id, category_id) VALUES (?, ?)");
                foreach ($selected_categories as $category_id) {
                    $cat_stmt->bind_param("ii", $event_id, $category_id);
                    $cat_stmt->execute();
                }
            }
            
            $conn->commit();
            $success = "✅ Thêm sự kiện thành công với " . count($all_images) . " ảnh!";
            header("refresh:2;url=manage_events.php");
            
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
        font-weight: bold;
    }
    
    .url-input-group {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .url-input-group input {
        flex: 1;
    }
    
    .url-input-group button {
        padding: 10px 20px;
        background: #0066cc;
        color: white;
        border: none;
        border-radius: 5px;
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
                    <li><a href="index.php">📊 Dashboard</a></li>
                    <li><a href="manage_events.php">📅 Quản lý sự kiện</a></li>
                    <li><a href="add_event.php" class="active">➕ Thêm sự kiện mới</a></li>
                    <li><a href="approve_tickets.php">🎫 Duyệt vé</a></li>
                    <li><a href="../index.php" target="_blank">🌐 Xem website</a></li>
                    <li><a href="logout.php">🚪 Đăng xuất</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="admin-content">
            <h1>Thêm sự kiện mới</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="admin-form" enctype="multipart/form-data">
                <label>Tiêu đề sự kiện <span class="required">*</span></label>
                <input type="text" name="title" required />
                
                <label>Mô tả <span class="required">*</span></label>
                <textarea name="description" rows="5" required></textarea>
                
                <div class="form-row">
                    <div class="form-col">
                        <label>Ngày bắt đầu <span class="required">*</span></label>
                        <input type="date" name="start_date" required />
                    </div>
                    <div class="form-col">
                        <label>Ngày kết thúc <span class="required">*</span></label>
                        <input type="date" name="end_date" required />
                    </div>
                </div>
                
                <label>Địa điểm <span class="required">*</span></label>
                <input type="text" name="location" required />
                
                <label>Xã/Phường <span class="optional">(Tùy chọn)</span></label>
                <select name="wards[]" class="select2-wards" multiple="multiple">
                    <?php
                    $wards_sql = "SELECT * FROM wards ORDER BY type, display_order ASC";
                    $wards_result = $conn->query($wards_sql);
                    $current_type = '';
                    while ($ward = $wards_result->fetch_assoc()):
                        if ($current_type !== $ward['type']) {
                            if ($current_type !== '') echo '</optgroup>';
                            echo '<optgroup label="' . $ward['type'] . '">';
                            $current_type = $ward['type'];
                        }
                    ?>
                        <option value="<?php echo $ward['id']; ?>"><?php echo htmlspecialchars($ward['name']); ?></option>
                    <?php 
                    endwhile; 
                    if ($current_type !== '') echo '</optgroup>';
                    ?>
                </select>
                
                <label>Danh mục <span class="optional">(Tùy chọn)</span></label>
                <select name="categories[]" class="select2-categories" multiple="multiple">
                    <?php
                    $categories_sql = "SELECT * FROM categories ORDER BY name ASC";
                    $categories_result = $conn->query($categories_sql);
                    while ($category = $categories_result->fetch_assoc()):
                    ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['icon']) . ' ' . htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <!-- PHẦN UPLOAD NHIỀU ẢNH -->
                <label>Ảnh sự kiện <span class="required">*</span> (Có thể thêm nhiều ảnh)</label>
                
                <div id="urlImagesSection">
                    <h4>📷 Thêm ảnh qua URL</h4>
                    <div class="url-input-group">
                        <input type="text" id="tempUrlInput" placeholder="Dán URL ảnh vào đây...">
                        <button type="button" onclick="addImageUrl()">➕ Thêm ảnh</button>
                    </div>
                </div>
                
                <div id="fileImagesSection">
                    <h4>📤 Hoặc upload file từ máy</h4>
                    <input type="file" name="image_files[]" accept="image/*" multiple>
                    <small style="color: #666;">Có thể chọn nhiều file cùng lúc. Tối đa 5MB/file.</small>
                </div>
                
                <div id="imagePreviewContainer" class="image-preview-container"></div>
                
                <div class="form-row">
                    <div class="form-col">
                        <label>Giá vé (VNĐ)</label>
                        <input type="number" name="ticket_price" value="0" min="0" step="1000" />
                    </div>
                    <div class="form-col">
                        <label>Số vé có sẵn</label>
                        <input type="number" name="available_tickets" value="0" min="0" />
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Thêm sự kiện</button>
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
            alert('Vui lòng nhập URL ảnh!');
            return;
        }
        
        // Tạo hidden input
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'image_urls[]';
        input.value = url;
        input.id = 'img_' + imageIndex;
        document.querySelector('form').appendChild(input);
        
        // Tạo preview
        const container = document.getElementById('imagePreviewContainer');
        const div = document.createElement('div');
        div.className = 'image-preview-item';
        div.innerHTML = `
            <img src="${url}" alt="Preview" onerror="this.src='https://via.placeholder.com/150x100?text=Invalid'">
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