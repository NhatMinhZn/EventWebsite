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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $location = trim($_POST['location']);
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
            $success = "Cập nhật sự kiện thành công!";
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
    
    <script>
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