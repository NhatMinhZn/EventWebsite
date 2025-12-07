<?php
/**
 * CẤU HÌNH VNPAY
 * 
 * Đăng ký tại: https://sandbox.vnpayment.vn/
 * Sau khi đăng ký, bạn sẽ nhận được:
 * - vnp_TmnCode (Mã website)
 * - vnp_HashSecret (Mã bí mật)
 */

// ========== CẤU HÌNH SANDBOX (TEST) ==========
define('VNP_TMN_CODE', 'YOUR_TMN_CODE');  // ⚠️ Thay bằng mã của bạn
define('VNP_HASH_SECRET', 'YOUR_HASH_SECRET');  // ⚠️ Thay bằng mã bí mật
define('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
define('VNP_RETURN_URL', 'http://localhost/EventWebsite/vnpay_return.php');

// ========== CẤU HÌNH PRODUCTION (THẬT) ==========
// Khi đã có tài khoản thật, uncomment và sửa:
// define('VNP_TMN_CODE', 'YOUR_REAL_TMN_CODE');
// define('VNP_HASH_SECRET', 'YOUR_REAL_HASH_SECRET');
// define('VNP_URL', 'https://vnpayment.vn/paymentv2/vpcpay.html');
// define('VNP_RETURN_URL', 'https://yourdomain.com/vnpay_return.php');

/**
 * HƯỚNG DẪN LẤY THÔNG TIN SANDBOX:
 * 
 * 1. Truy cập: https://sandbox.vnpayment.vn/devreg/
 * 2. Đăng ký tài khoản (miễn phí)
 * 3. Sau khi đăng ký, vào phần "Thông tin tài khoản"
 * 4. Copy:
 *    - Mã website (vnp_TmnCode)
 *    - Mã bí mật (vnp_HashSecret)
 * 5. Paste vào file này
 * 
 * THẺ TEST (dùng để thanh toán thử):
 * - Số thẻ: 9704198526191432198
 * - Tên chủ thẻ: NGUYEN VAN A
 * - Ngày phát hành: 07/15
 * - Mật khẩu OTP: 123456
 */
?>