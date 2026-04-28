<?php
/**
 * api/chat.php – VPBank SME Portal
 * Wrapper bảo mật gọi DeepSeek API – Đầy đủ kiến thức 8 Module sản phẩm
 */

declare(strict_types=1);

/* ============================================================
   CẤU HÌNH – THAY CÁC BIẾN NÀY TRƯỚC KHI DEPLOY
   ============================================================ */

$apiKey  = 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; // ← Thay bằng API Key thật của bạn
$apiUrl  = 'https://api.deepseek.com/chat/completions';
$model   = 'deepseek-chat';
$maxRequestsPerMinute = 20;
$maxTokens = 900;

// ── System Prompt đầy đủ kiến thức 8 Module sản phẩm VPBank SME ──────────────
$systemPrompt = <<<'PROMPT'
Bạn là trợ lý ảo thông minh hỗ trợ khách hàng doanh nghiệp SME tại VPBank, phụ trách bởi Chuyên viên Nguyễn Văn Bẩy.

THÔNG TIN LIÊN HỆ:
- Chuyên viên: Nguyễn Văn Bẩy – VPBank Chi nhánh Tây Ninh
- ĐT/Zalo: 0876.680.001
- Email: Baynv@vpbank.com.vn
- Địa chỉ: 30/4, P.1, TP. Tây Ninh | Giờ làm: T2–T6 8:00–17:00

=== KIẾN THỨC SẢN PHẨM VPBank SME (8 MODULE) ===

MODULE 1: QUY TRÌNH TÍN DỤNG LUỒNG A & NGUYÊN TẮC
- Quy trình: (1) Thu thập & đánh giá KH → (2) Định giá TSĐB → (3) Lập tờ trình → (4) Chấm XHTD → (5) Khởi tạo LOS → (6) Giải ngân.
- Giới hạn tín dụng: 1 KH ≤15% vốn tự có; 1 KH + người liên quan ≤25% vốn tự có.
- Không cho vay: ngành nghề pháp luật cấm, mua vàng miếng, trả nợ TCTD khác (trừ ngoại lệ).
- Mã ngành chuẩn 5 số theo QĐ 27/2018 Thủ tướng CP, tra cứu QĐ 767/2018 VPBank.

MODULE 2: SẢN PHẨM CÓ TSĐB

[Micro Plus]
- XHTD 1.1–7.1. Thành lập ≥1 năm (dưới 1 năm: người điều hành phải có ≥3 năm KN).
- Tuổi GĐ/TGĐ đồng thời chủ DN: ≤65 tuổi tại đáo hạn.
- Hạn mức: Micro 1 ≤10 tỷ | Micro 2 ≤15 tỷ | Micro 3 ≤20 tỷ VNĐ.
- TSĐB: BĐS, phương tiện vận tải (trừ xe chuyên dùng; ngoại trừ xe đầu kéo, sơ mi rơ moóc), GTG do VPBank phát hành.

[Vay mua Ô tô – Auto Loan]
- CPC Car: tối đa 10 tỷ | Fast Auto: tối đa 2.5 tỷ VNĐ.
- Thời hạn: Xe G7/Hàn Quốc mới ≤7 năm; Xe Vinfast & nước khác mới ≤6 năm; Xe đã qua sử dụng <9 chỗ ≤5 năm.
- Tuổi chủ DN: ≤70 tuổi tại đáo hạn.

MODULE 3: SẢN PHẨM KHÔNG TSĐB – BIL

Phân nhóm:
- Nhóm A (thông thường) | Nhóm A+ (xuất nhập khẩu) | Nhóm A++ (có hạn mức TSĐB tại VPBank).

Điều kiện nhóm A:
- XHTD 1.1–6.1 (ngoại lệ đến 7.1). Thành lập ≥3 năm (2 năm gần nhất hoạt động trong lĩnh vực CP).
- Cam kết doanh số ≥150% giải ngân. Không nợ xấu CIC 3 năm. Lợi nhuận >0 trong 2 năm. Thanh toán ngắn hạn ≥1.

Hạn mức BIL:
- Micro: tối đa 1.5 tỷ VNĐ.
- Small/Middle: tối đa 3 tỷ (A++) lên đến 5 tỷ VNĐ.

MODULE 4: THẺ VPBizCARD & QUẢN LÝ DÒNG TIỀN

Thẻ:
- Debit Business: giao dịch trong phạm vi số dư tài khoản.
- Credit Standard: HM ≥30 triệu/KH, ≥10 triệu/thẻ. Lãi 34%/năm (không TSĐB).
- Credit Platinum: HM ≥50 triệu/KH, ≥20 triệu/thẻ. Lãi 32%/năm.
- Fast Card: BCTC N-1 ≥500 triệu → HM tối đa 10% DT thuế (max 100 triệu).
- Card to Bizcard: Theo HM thẻ NH khác → tối đa 100% TB hạn mức (max 200 triệu).

Thanh toán:
- SoftPOS Tap to Phone: Android 10+ NFC → điện thoại thành máy POS thanh toán một chạm.
- Ecompay: tích hợp website/App bán hàng. Simplify: tạo website bán hàng miễn phí. Invoicing: gửi link thanh toán Zalo/Email/SMS.

NEOBiz & NEOBiz Plus:
- 6 lớp bảo mật chuẩn quốc tế. Chuyển tiền nội địa/quốc tế; Thanh toán lô; Nộp thuế; Chi lương Payroll.
- QUAN TRỌNG: Khách hàng vay không kích hoạt NEOBiz.
- Tiền gửi Online tối thiểu 10 triệu VNĐ, tự giao dịch trên NEOBiz.

MODULE 5: BẢO LÃNH NGÂN HÀNG
- Bảo lãnh dự thầu; Thực hiện HĐ; Hoàn trả tiền tạm ứng; Chờ thanh quyết toán; Bảo hành; Thanh toán; Bán hàng trả chậm/trả góp; Thuế tạm nhập tái xuất; Nhà ở hình thành trong tương lai.

MODULE 6: TÀI KHOẢN & TIỀN GỬI
- TKTT thông thường: không yêu cầu số dư tối thiểu (VNĐ).
- Tiền gửi kỳ hạn: 1 tuần–36 tháng, trả gốc/lãi linh hoạt.
- VP An Khang: cam kết kỳ hạn tối thiểu 1/2/3 tháng hưởng lãi kỳ hạn.
- Tiền gửi Tự chọn: linh hoạt lẻ ngày, 7–389 ngày.
- Tiền gửi Online (NEOBiz): tối thiểu 10 triệu VNĐ.

MODULE 7: HỒ SƠ TÍN DỤNG LUỒNG A

Hồ sơ pháp lý:
- ĐKDN phải là bản mới nhất, tra cứu đối chiếu Cổng thông tin quốc gia.
- QĐ/BB họp vay vốn phù hợp loại hình DN: TNHH 2TV = Hội đồng thành viên; CP = HĐQT/ĐHĐCĐ.
- CMND/CCCD phải còn hiệu lực.

Đánh giá CIC:
- Trình phủ quyết (KO): khi bản thân DN không thỏa tiêu chí CIC.
- Trình vượt tiêu chí: cho thành viên góp vốn chính, chủ tài sản nếu CIC có vấn đề có thể giải trình.

Hồ sơ tài chính:
- BCTC: dư nợ vay 31/12 khớp CIC. DT nội bộ >150% DT thuế → cần chứng từ.
- BIL: 3 hóa đơn/HĐ mỗi năm → chứng minh KN 2 năm.
- Ảnh cơ sở: BIL ≥10 ảnh; sản phẩm khác ≥5 ảnh (phải rõ địa chỉ, biển hiệu).

MODULE 8: TÀI TRỢ CHUỖI ĐẶC THÙ

[Chuỗi TACN – Thức ăn chăn nuôi]
- Anchor: Cargill, Deheus, Japfa, Tongwei, C.P. Group...
- Đại lý: KH hiện hữu ngoại lệ ≥6 tháng, thông thường ≥24 tháng; DT mua ≥150 triệu/tháng.
- CHỈ tài trợ kinh doanh thương mại TACN, KHÔNG tài trợ chăn nuôi tại trang trại.

[Chuỗi Ô tô]
- Anchor: KD ô tô ≥5 năm, có ≥10 đại lý hoạt động.
- Đại lý: XHTD KH SME ≥7.2. KD dòng xe tương tự ≥6 tháng.
- Tài trợ ≤95% giá trị lô xe (100% nếu bổ sung tiền ký quỹ/sổ tiết kiệm).

[Chuỗi FMCG – Hàng tiêu dùng nhanh]
- NPP: DT ≥30 tỷ/năm, lợi nhuận/VCSH >0 → Thấu chi không TSĐB ≤5 tỷ (DN), ≤3 tỷ (HKĐ).
- Đại lý (NPP giới thiệu): mua hàng ≥6 tháng, DT ≥30 triệu/tháng → Thấu chi ≤500 triệu/đại lý.
- Vận hành: Giải ngân qua App VPBank Online. SMS nhắc nợ ngày 10, thu nợ tự động ngày 15.

QUY TẮC TRẢ LỜI:
- Ngắn gọn, súc tích, không quá 300 từ/câu trả lời.
- Lịch sự, chuyên nghiệp, thân thiện – xưng hô "anh/chị". Trả lời Tiếng Việt.
- Dùng emoji phù hợp, không lạm dụng.
- Câu hỏi ngoài phạm vi: lịch sự hướng về tư vấn tài chính ngân hàng.
- Không cam kết phê duyệt, không tiết lộ thông tin nội bộ ngân hàng.
- Khi cần: hướng dẫn liên hệ 0876.680.001 hoặc Baynv@vpbank.com.vn.
PROMPT;


/* ============================================================
   HEADERS & CORS
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Chỉ chấp nhận POST.']);
    exit;
}

/* ============================================================
   RATE LIMITING (Session-based)
   ============================================================ */
session_start();

$now        = time();
$sessionKey = 'chat_rate_limit';

if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = ['count' => 0, 'window_start' => $now];
}
$rateData = &$_SESSION[$sessionKey];

if ($now - $rateData['window_start'] > 60) {
    $rateData = ['count' => 0, 'window_start' => $now];
}
$rateData['count']++;

if ($rateData['count'] > $maxRequestsPerMinute) {
    http_response_code(429);
    echo json_encode(['error' => 'Bạn đang gửi quá nhiều yêu cầu. Vui lòng thử lại sau 1 phút.']);
    exit;
}

/* ============================================================
   ĐỌC & VALIDATE INPUT
   ============================================================ */
$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Dữ liệu không hợp lệ (JSON parse error).']);
    exit;
}

$userMessage = trim($data['message'] ?? '');
if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tin nhắn không được để trống.']);
    exit;
}

if (mb_strlen($userMessage) > 1000) {
    $userMessage = mb_substr($userMessage, 0, 1000);
}
$userMessage = strip_tags($userMessage);

/* ============================================================
   CONVERSATION HISTORY (Session, giữ ngữ cảnh 10 tin nhắn)
   ============================================================ */
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

$_SESSION['chat_history'][] = ['role' => 'user', 'content' => $userMessage];

if (count($_SESSION['chat_history']) > 10) {
    $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -10);
}

/* ============================================================
   GỌI DEEPSEEK API QUA cURL
   ============================================================ */
$requestBody = json_encode([
    'model'       => $model,
    'messages'    => array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $_SESSION['chat_history']
    ),
    'max_tokens'  => $maxTokens,
    'temperature' => 0.7,
    'stream'      => false
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'User-Agent: VPBank-SME-Portal/1.0'
    ]
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('[VPBank Chat API] cURL Error: ' . $curlError);
    http_response_code(503);
    echo json_encode(['error' => 'Không thể kết nối đến dịch vụ AI. Vui lòng thử lại sau.']);
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode !== 200) {
    $apiError = $responseData['error']['message'] ?? 'Unknown API error';
    error_log("[VPBank Chat API] HTTP {$httpCode}: {$apiError}");

    $errMsg = match (true) {
        $httpCode === 401 => 'Lỗi xác thực API. Vui lòng liên hệ admin.',
        $httpCode === 429 => 'Hệ thống đang bận. Vui lòng thử lại sau 30 giây.',
        $httpCode >= 500  => 'Dịch vụ AI tạm thời gián đoạn. Vui lòng thử lại sau.',
        default           => 'Có lỗi xảy ra khi xử lý yêu cầu.'
    };

    http_response_code(502);
    echo json_encode(['error' => $errMsg]);
    exit;
}

$assistantReply = $responseData['choices'][0]['message']['content'] ?? null;

if (empty($assistantReply)) {
    http_response_code(502);
    echo json_encode(['error' => 'AI không trả về nội dung. Vui lòng thử lại.']);
    exit;
}

$_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $assistantReply];

echo json_encode([
    'reply'  => $assistantReply,
    'model'  => $model,
    'tokens' => $responseData['usage']['total_tokens'] ?? null
], JSON_UNESCAPED_UNICODE);
