<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo isset($page_title) ? $page_title : 'Cổng Thông Tin Sự Kiện'; ?></title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="icon" href="https://res.cloudinary.com/dwxas9epy/image/upload/v1750020656/Logo_1_pu9e8o.png" type="image/png" />
  <link href="https://fonts.googleapis.com/css2?family=Edu+NSW+ACT+Foundation&display=swap" rel="stylesheet">
</head>
<body>
  <header>
    <div class="container">
      <h1 class="logo">
        <a href="index.php">
          <img src="https://res.cloudinary.com/dwxas9epy/image/upload/v1750020656/Logo_1_pu9e8o.png" alt="Logo" class="logo-img" />
          Da Nang Event Portal
        </a>
      </h1>
      <nav>
        <ul>
          <li><a href="index.php">Trang chủ</a></li>
          <li><a href="events.php">Sự kiện</a></li>
          <li><a href="contact.php">Liên hệ</a></li>
          <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="calendar.php">Lịch của tôi</a></li>
            <li><a href="account.php">Tài khoản</a></li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
              <li><a href="admin/index.php">Quản trị</a></li>
            <?php endif; ?>
          <?php else: ?>
            <li><a href="login.php">Đăng nhập</a></li>
            <li><a href="register.php">Đăng ký</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </header>