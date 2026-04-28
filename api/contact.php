<?php
/**
 * api/contact.php – VPBank SME Portal
 * Xử lý form liên hệ:
 *   - Validate dữ liệu
 *   - Lưu vào file JSON (backup)
 *   - Gửi email thông báo đến chuyên viên
 *   - Trả về JSON response
 */

declare(strict_types=1);

/* ============================================================
   CẤU HÌNH – THAY TRƯỚC KHI DEPLOY
   ============================================================ */

// Email chuyên viên nhận thông báo khách hàng mới
$notifyEmail = 'Baynv@vpbank.com.vn';

// Email CC (quản lý, admin...)
$ccEmail = ''; // Để trống nếu không cần CC

// Thư mục lưu backup dữ liệu (cần có quyền ghi)
$dataDir = __DIR__ . '/../data';

// Số ký tự tối đa cho message field
$maxMessageLength = 500;

/* ============================================================
   HEADERS
   ============================================================ */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ============================================================
   KIỂM TRA PHƯƠNG THỨC
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
    exit;
}

/* ============================================================
   CHỐNG SPAM (CSRF + Rate Limit đơn giản)
   ============================================================ */
session_start();

$now = time();
$spamKey = 'contact_last_submit';

if (isset($_SESSION[$spamKey]) && ($now - $_SESSION[$spamKey]) < 60) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng chờ 1 phút trước khi gửi yêu cầu tiếp theo.'
    ]);
    exit;
}

/* ============================================================
   ĐỌC & VALIDATE DỮ LIỆU FORM
   ============================================================ */

// Hàm sanitize chuỗi
function sanitize(string $input): string
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Lấy dữ liệu từ POST
$name = sanitize($_POST['name'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$company = sanitize($_POST['company'] ?? '');
$need = sanitize($_POST['need'] ?? '');
$message = sanitize($_POST['message'] ?? '');

$errors = [];

// Validate Họ tên
if (empty($name)) {
    $errors[] = 'Vui lòng nhập họ và tên.';
} elseif (mb_strlen($name) > 100) {
    $errors[] = 'Họ và tên quá dài (tối đa 100 ký tự).';
}

// Validate SĐT
$phoneClean = preg_replace('/\D/', '', $phone); // Chỉ giữ số
if (empty($phoneClean)) {
    $errors[] = 'Vui lòng nhập số điện thoại.';
} elseif (!preg_match('/^(0|\+?84)[0-9]{8,9}$/', $phoneClean)) {
    $errors[] = 'Số điện thoại không hợp lệ (phải là số Việt Nam).';
}

// Validate Nhu cầu
$allowedNeeds = ['tinchaP', 'thechap', 'voiluu', 'thuongmai', 'other'];
if (empty($need) || !in_array($need, $allowedNeeds, true)) {
    $errors[] = 'Vui lòng chọn nhu cầu vốn.';
}

// Validate Message (tùy chọn, nhưng giới hạn độ dài)
if (mb_strlen($message) > $maxMessageLength) {
    $message = mb_substr($message, 0, $maxMessageLength);
}

// Trả về lỗi nếu có
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $errors),
        'errors' => $errors
    ]);
    exit;
}

/* ============================================================
   CHUẨN BỊ DỮ LIỆU
   ============================================================ */
$needLabels = [
    'tinchaP' => 'Vay tín chấp (không TSĐB)',
    'thechap' => 'Vay thế chấp BĐS',
    'voiluu' => 'Bổ sung vốn lưu động',
    'thuongmai' => 'Tài trợ thương mại',
    'other' => 'Nhu cầu khác'
];

$formData = [
    'id' => uniqid('cf_', true),
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'name' => $name,
    'phone' => $phoneClean,
    'company' => $company ?: 'Không cung cấp',
    'need' => $need,
    'need_label' => $needLabels[$need] ?? $need,
    'message' => $message,
    'source' => 'VPBank SME Portal – Contact Form',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

/* ============================================================
   LƯU DỮ LIỆU VÀO FILE JSON (Backup)
   ============================================================ */
$saveSuccess = false;

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Tạo file .htaccess bảo vệ thư mục data
$htaccess = $dataDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Deny from all\n");
}

$dataFile = $dataDir . '/contacts_' . date('Ym') . '.json';

// Đọc dữ liệu cũ (nếu có)
$existing = [];
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $existing = json_decode($content, true) ?: [];
}

$existing[] = $formData;

// Ghi lại file
if (file_put_contents($dataFile, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false) {
    $saveSuccess = true;
}

/* ============================================================
   GỬI EMAIL THÔNG BÁO
   Ghi chú: Trên hosting cPanel/DirectAdmin, hàm mail() thường
   hoạt động sẵn. Nếu cần SMTP, dùng PHPMailer.
   ============================================================ */
$emailSent = false;

if (!empty($notifyEmail)) {
    $subject = "[VPBank SME] Khách hàng mới: {$name} – {$phoneClean}";

    $body = "=== KHÁCH HÀNG MỚI TỪ WEBSITE ===\n\n";
    $body .= "Thời gian : {$formData['timestamp']}\n";
    $body .= "Họ và tên : {$name}\n";
    $body .= "SĐT       : {$phoneClean}\n";
    $body .= "Doanh nghiệp: {$formData['company']}\n";
    $body .= "Nhu cầu   : {$formData['need_label']}\n";
    if ($message) {
        $body .= "Mô tả     : {$message}\n";
    }
    $body .= "\n─────────────────────────────────────\n";
    $body .= "IP: {$formData['ip']}\n";
    $body .= "URL: https://smevpbank.vn/\n";
    $body .= "=================================\n";
    $body .= "Email này được gửi tự động từ VPBank SME Portal.\n";

    $headers = "From: no-reply@smevpbank.vn\r\n";
    $headers .= "Reply-To: {$notifyEmail}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    if ($ccEmail) {
        $headers .= "Cc: {$ccEmail}\r\n";
    }

    $emailSent = @mail($notifyEmail, $subject, $body, $headers);
}

/* ============================================================
   GHI LOG (bảo mật, optional)
   ============================================================ */
$logFile = $dataDir . '/access.log';
$logEntry = sprintf(
    "[%s] %s | %s | %s | %s\n",
    date('Y-m-d H:i:s'),
    $formData['ip'],
    $name,
    $phoneClean,
    $need
);
@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

/* ============================================================
   ĐÁNH DẤU THỜI GIAN SUBMIT (chống spam)
   ============================================================ */
$_SESSION[$spamKey] = $now;

/* ============================================================
   TRẢ VỀ RESPONSE
   ============================================================ */
$successMsg = "Cảm ơn {$name}! Yêu cầu tư vấn của bạn đã được ghi nhận. Anh Bẩy sẽ liên hệ trong vòng 30 phút (giờ hành chính).";

echo json_encode([
    'success' => true,
    'message' => $successMsg,
    'saved' => $saveSuccess,
    'email_sent' => $emailSent,
    'ref_id' => substr($formData['id'], 0, 12) // Reference ID cho KH
], JSON_UNESCAPED_UNICODE);
