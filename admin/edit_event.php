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

// Lấy danh sách xã/phường đã chọn cho event này
$selected_wards_sql = "SELECT ward_id FROM event_wards WHERE event_id = ?";
$selected_wards_stmt = $conn->prepare($selected_wards_sql);
$selected_wards_stmt->bind_param("i", $event_id);
$selected_wards_stmt->execute();
$selected_wards_result = $selected_wards_stmt->get_result();

$selected_ward_ids = [];
while ($row = $selected_wards_result->fetch_assoc()) {
    $selected_ward_ids[] = $row['ward_id'];
}

// Lấy danh sách categories đã chọn cho event này
$selected_categories_sql = "SELECT category_id FROM event_categories WHERE event_id = ?";
$selected_categories_stmt = $conn->prepare($selected_categories_sql);
$selected_categories_stmt->bind_param("i", $event_id);
$selected_categories_stmt->execute();
$selected_categories_result = $selected_categories_stmt->get_result();

$selected_category_ids = [];
while ($row = $selected_categories_result->fetch_assoc()) {
    $selected_category_ids[] = $row['category_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $location = trim($_POST['location']);
    
    // Xử lý xã/phường
    $selected_wards = isset($_POST['wards']) ? $_POST['wards'] : [];
    
    // Xử lý categories
    $selected_categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    $ticket_price = isset($_POST['ticket_price']) ? (float)$_POST['ticket_price'] : 0;
    $available_tickets = isset($_POST['available_tickets']) ? (int)$_POST['available_tickets'] : 0;
    
    $image = $event['image'];
    $upload_method = $_POST['upload_method'];
    
    if ($upload_method === 'url') {
        $new_url = trim($_POST['image_url']);
        if (!empty($new_url)) {
            $image = $new_url;
        }
    } elseif ($upload_method === 'file') {
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
            $file = $_FILES['image_file'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $error = "Chỉ chấp nhận file ảnh: JPG, PNG, GIF, WEBP";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = "Kích thước file tối đa 5MB!";
            } else {
                $new_filename = 'event_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = '../uploads/events/';
                
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }
                
                if (move_uploaded_file($file['tmp_name'], $upload_path . $new_filename)) {
                    if (strpos($event['image'], 'uploads/events/') === 0 && file_exists('../' . $event['image'])) {
                        unlink('../' . $event['image']);
                    }
                    $image = 'uploads/events/' . $new_filename;
                } else {
                    $error = "Lỗi khi upload file!";
                }
            }
        }
    }
    
    if (empty($error) && (empty($title) || empty($description) || empty($start_date) || empty($end_date) || empty($location) || empty($image))) {
        $error = "Vui lòng điền đầy đủ thông tin bắt buộc!";
    }
    
    if (empty($error)) {
        $update_sql = "UPDATE events SET title = ?, description = ?, start_date = ?, end_date = ?, location = ?, image = ?, ticket_price = ?, available_tickets = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssssdii", $title, $description, $start_date, $end_date, $location, $image, $ticket_price, $available_tickets, $event_id);
        
        if ($update_stmt->execute()) {
            // Xóa xã/phường cũ
            $del_wards_sql = "DELETE FROM event_wards WHERE event_id = ?";
            $del_wards_stmt = $conn->prepare($del_wards_sql);
            $del_wards_stmt->bind_param("i", $event_id);
            $del_wards_stmt->execute();
            
            // Thêm xã/phường mới
            if (!empty($selected_wards)) {
                $ward_stmt = $conn->prepare("INSERT INTO event_wards (event_id, ward_id) VALUES (?, ?)");
                foreach ($selected_wards as $ward_id) {
                    $ward_stmt->bind_param("ii", $event_id, $ward_id);
                    $ward_stmt->execute();
                }
            }
            
            // Xóa categories cũ
            $del_categories_sql = "DELETE FROM event_categories WHERE event_id = ?";
            $del_categories_stmt = $conn->prepare($del_categories_sql);
            $del_categories_stmt->bind_param("i", $event_id);
            $del_categories_stmt->execute();
            
            // Thêm categories mới
            if (!empty($selected_categories)) {
                $cat_stmt = $conn->prepare("INSERT INTO event_categories (event_id, category_id) VALUES (?, ?)");
                foreach ($selected_categories as $category_id) {
                    $cat_stmt->bind_param("ii", $event_id, $category_id);
                    $cat_stmt->execute();
                }
            }
            
            $selected_ward_ids = $selected_wards;
            $selected_category_ids = $selected_categories;
            $success = "Cập nhật sự kiện thành công!";
            header("refresh:1.5;url=manage_events.php");
            $event = array_merge($event, compact('title', 'description', 'start_date', 'end_date', 'location', 'image', 'ticket_price', 'available_tickets'));
        } else {
            $error = "Có lỗi xảy ra: " . $update_stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="css/admin.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
    /* Select2 customization */
    .select2-container {
        z-index: 9999 !important;
    }

    .select2-container--default .select2-selection--multiple {
        border: 2px solid #ddd;
        border-radius: 6px;
        min-height: 45px;
        padding: 5px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #0066cc;
        border-color: #0066cc;
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        margin: 3px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: white;
        margin-right: 5px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ffdddd;
    }

    .optional {
        color: #999;
        font-weight: normal;
        font-size: 13px;
    }
    
    .form-hint {
        color: #666;
        font-size: 13px;
        display: block;
        margin-top: 5px;
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
                
                <!-- Ô chọn Xã/Phường/Đặc khu -->
                <label>Xã/Phường/Đặc khu <span class="optional">(Tùy chọn - Chọn nhiều)</span></label>
                <select name="wards[]" id="wards" class="select2-wards" multiple="multiple" style="width: 100%;">
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
                <small class="form-hint">💡 Các xã/phường đã chọn: <?php echo count($selected_ward_ids); ?></small>
                
                <!-- Ô chọn Danh mục -->
                <label>Danh mục sự kiện <span class="optional">(Tùy chọn - Chọn nhiều)</span></label>
                <select name="categories[]" id="categories" class="select2-categories" multiple="multiple" style="width: 100%;">
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
                <small class="form-hint">🏷️ Các danh mục đã chọn: <?php echo count($selected_category_ids); ?></small>
                
                <div class="current-image-section">
                    <label>Ảnh hiện tại:</label>
                    <img src="<?php echo strpos($event['image'], 'http') === 0 ? htmlspecialchars($event['image']) : '../' . htmlspecialchars($event['image']); ?>" alt="Current" style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 2px solid #ddd;" />
                </div>
                
                <div class="upload-method-selector">
                    <label>Thay đổi ảnh:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="upload_method" value="keep_current" checked onchange="toggleUploadMethod()" />
                            ✅ Giữ ảnh hiện tại
                        </label>
                        <label>
                            <input type="radio" name="upload_method" value="url" onchange="toggleUploadMethod()" />
                            🔗 Đổi sang URL mới
                        </label>
                        <label>
                            <input type="radio" name="upload_method" value="file" onchange="toggleUploadMethod()" />
                            📤 Upload file mới
                        </label>
                    </div>
                </div>
                
                <div id="urlUpload" class="upload-section" style="display: none;">
                    <label>URL hình ảnh mới</label>
                    <input type="text" name="image_url" id="imageUrl" placeholder="https://example.com/image.jpg" />
                    <small style="color: #666;">Preview:</small>
                    <img id="imagePreview" src="" alt="Preview" style="max-width: 300px; max-height: 200px; margin-top: 10px; display: none; border-radius: 8px; border: 1px solid #ddd;" />
                </div>
                
                <div id="fileUpload" class="upload-section" style="display: none;">
                    <label>Chọn file ảnh mới</label>
                    <input type="file" name="image_file" id="imageFile" accept="image/*" onchange="previewFile()" />
                    <small style="color: #666;">Hỗ trợ: JPG, PNG, GIF, WEBP. Tối đa 5MB</small>
                    <img id="filePreview" src="" alt="Preview" style="max-width: 300px; max-height: 200px; margin-top: 10px; display: none; border-radius: 8px; border: 1px solid #ddd;" />
                </div>
                
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
    
    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    // Initialize Select2 for wards selection
    $(document).ready(function() {
        $('.select2-wards').select2({
            placeholder: "🔍 Tìm kiếm và chọn xã/phường/đặc khu...",
            allowClear: true,
            language: {
                noResults: function() {
                    return "Không tìm thấy kết quả";
                },
                searching: function() {
                    return "Đang tìm kiếm...";
                }
            }
        });
        
        // Initialize Select2 for categories selection
        $('.select2-categories').select2({
            placeholder: "🏷️ Chọn danh mục sự kiện...",
            allowClear: true,
            language: {
                noResults: function() {
                    return "Không tìm thấy kết quả";
                },
                searching: function() {
                    return "Đang tìm kiếm...";
                }
            }
        });
    });
    
    function toggleUploadMethod() {
        const method = document.querySelector('input[name="upload_method"]:checked').value;
        const urlSection = document.getElementById('urlUpload');
        const fileSection = document.getElementById('fileUpload');
        
        if (method === 'keep_current') {
            urlSection.style.display = 'none';
            fileSection.style.display = 'none';
        } else if (method === 'url') {
            urlSection.style.display = 'block';
            fileSection.style.display = 'none';
        } else if (method === 'file') {
            urlSection.style.display = 'none';
            fileSection.style.display = 'block';
        }
    }
    
    const imageUrlInput = document.getElementById('imageUrl');
    const imagePreview = document.getElementById('imagePreview');
    
    if (imageUrlInput && imagePreview) {
        imageUrlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url) {
                imagePreview.src = url;
                imagePreview.style.display = 'block';
                imagePreview.onerror = function() {
                    this.style.display = 'none';
                };
            } else {
                imagePreview.style.display = 'none';
            }
        });
    }
    
    function previewFile() {
        const file = document.getElementById('imageFile').files[0];
        const preview = document.getElementById('filePreview');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    }
    </script>
</body>
</html>