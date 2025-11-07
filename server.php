<?php
// -----------------------------
// إعدادات PHP الأساسية
// -----------------------------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// معالجة طلبات OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// بدء الجلسة (Session)
session_start();

// قراءة المفتاح السري من ملف .env (أكثر أماناً)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    preg_match('/OTP_SECRET=(.+)/', $envContent, $matches);
    define('OTP_SECRET', trim($matches[1] ?? 'JBSWY3DPEHPK3PXP'));
} else {
    // قيمة افتراضية للتطوير فقط
    define('OTP_SECRET', 'JBSWY3DPEHPK3PXP');
}
define('OTP_WINDOW', 1); // السماح بفارق ±30 ثانية

// -----------------------------
// دوال مساعدة
// -----------------------------

// قراءة ملف JSON
function readJSON($filename) {
    $filePath = __DIR__ . '/' . $filename;
    if (!file_exists($filePath)) {
        return [];
    }
    $content = file_get_contents($filePath);
    return json_decode($content, true) ?? [];
}

// كتابة ملف JSON
function writeJSON($filename, $data) {
    $filePath = __DIR__ . '/' . $filename;
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $jsonData) !== false;
}

// دالة التحقق من OTP (Google Authenticator)
function verifyOTP($token, $secret) {
    // تحويل Base32 إلى binary
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper($secret);
    $binaryString = '';
    
    foreach (str_split($secret) as $char) {
        $binaryString .= str_pad(decbin(strpos($base32chars, $char)), 5, '0', STR_PAD_LEFT);
    }
    
    $key = '';
    foreach (str_split($binaryString, 8) as $chunk) {
        $key .= chr(bindec($chunk));
    }
    
    // حساب الوقت الحالي (كل 30 ثانية)
    $time = floor(time() / 30);
    
    // التحقق من النافذة الزمنية (الحالي و ±1)
    for ($i = -OTP_WINDOW; $i <= OTP_WINDOW; $i++) {
        $timestamp = pack('N*', 0) . pack('N*', $time + $i);
        $hash = hash_hmac('sha1', $timestamp, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $otp = (
            ((ord($hash[$offset+0]) & 0x7f) << 24 ) |
            ((ord($hash[$offset+1]) & 0xff) << 16 ) |
            ((ord($hash[$offset+2]) & 0xff) << 8 ) |
            (ord($hash[$offset+3]) & 0xff)
        ) % 1000000;
        
        $otp = str_pad($otp, 6, '0', STR_PAD_LEFT);
        
        if ($otp === $token) {
            return true;
        }
    }
    
    return false;
}

// إرسال JSON response
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// -----------------------------
// معالجة الطلبات (Routing)
// -----------------------------

$method = $_SERVER['REQUEST_METHOD'];
// استخدام query parameter 'action' لتحديد المسار
$path = isset($_GET['action']) ? '/' . $_GET['action'] : '/';

// الحصول على البيانات المرسلة
$inputData = json_decode(file_get_contents('php://input'), true) ?? [];

// -----------------------------
// تسجيل الدخول - POST /login
// -----------------------------
if ($path === '/login' && $method === 'POST') {
    $username = $inputData['username'] ?? '';
    $password = $inputData['password'] ?? '';
    
    // التحقق من اسم المستخدم
    if ($username !== 'admin') {
        sendJSON(['success' => false, 'message' => 'اسم المستخدم غير صحيح'], 401);
    }
    
    // تحميل بيانات المحاولات الفاشلة
    $failedData = readJSON('failed-logins.json');
    $now = time() * 1000; // milliseconds
    $userFail = $failedData[$username] ?? ['failedCount' => 0, 'lastFailed' => null, 'lockUntil' => null];
    
    // التحقق من الحظر
    if ($userFail['lockUntil'] && $now < $userFail['lockUntil']) {
        $remaining = ceil(($userFail['lockUntil'] - $now) / (60 * 1000));
        sendJSON(['success' => false, 'message' => "تم حظر الحساب مؤقتاً. حاول بعد $remaining دقيقة."], 403);
    }
    
    // التحقق من OTP
    if (verifyOTP($password, OTP_SECRET)) {
        $_SESSION['isLoggedIn'] = true;
        $_SESSION['username'] = $username;
        
        // إعادة تعيين المحاولات الفاشلة
        $failedData[$username] = ['failedCount' => 0, 'lastFailed' => null, 'lockUntil' => null];
        writeJSON('failed-logins.json', $failedData);
        
        sendJSON(['success' => true]);
    } else {
        // زيادة عدد المحاولات الفاشلة
        $failedCount = $userFail['failedCount'] ?? 0;
        $lastFailed = $userFail['lastFailed'];
        
        // إعادة العد إذا مر يوم
        if ($lastFailed && ($now - strtotime($lastFailed) * 1000 > 24 * 60 * 60 * 1000)) {
            $failedCount = 0;
        }
        
        $failedCount++;
        $lockUntil = null;
        
        if ($failedCount >= 3) {
            $lockUntil = $now + 12 * 60 * 60 * 1000; // 12 ساعة
        }
        
        $failedData[$username] = [
            'failedCount' => $failedCount,
            'lastFailed' => date('c'),
            'lockUntil' => $lockUntil
        ];
        writeJSON('failed-logins.json', $failedData);
        
        if ($lockUntil) {
            sendJSON(['success' => false, 'message' => 'تم حظر الحساب مؤقتاً لمدة 12 ساعة بسبب تكرار إدخال كلمة مرور خاطئة.'], 403);
        }
        
        $remaining = 3 - $failedCount;
        sendJSON(['success' => false, 'message' => "رمز التحقق غير صحيح. تبقى $remaining محاولة قبل الحظر."], 401);
    }
}

// -----------------------------
// حفظ المستخدمين - POST /save-users
// -----------------------------
if ($path === '/save-users' && $method === 'POST') {
    if (writeJSON('user-data.json', $inputData)) {
        sendJSON(['success' => true]);
    } else {
        sendJSON(['success' => false, 'error' => 'خطأ في حفظ المستخدمين'], 500);
    }
}

// -----------------------------
// قراءة/حفظ values.json
// -----------------------------
if ($path === '/values.json' && $method === 'GET') {
    $data = readJSON('values.json');
    sendJSON($data);
}

if ($path === '/values.json' && $method === 'POST') {
    if (writeJSON('values.json', $inputData)) {
        sendJSON(['success' => true]);
    } else {
        sendJSON(['error' => 'حدث خطأ أثناء الحفظ'], 500);
    }
}

// -----------------------------
// قراءة papers.json - GET /papers.json
// -----------------------------
if ($path === '/papers.json' && $method === 'GET') {
    $data = readJSON('papers.json');
    sendJSON($data);
}

// -----------------------------
// أنواع الورق الداخلي - GET /inner-paper-types
// -----------------------------
if ($path === '/inner-paper-types' && $method === 'GET') {
    $data = readJSON('papers.json');
    $papers = $data['papers'] ?? [];
    $types = [];
    
    foreach ($papers as $paper) {
        if ($paper[0] === 'ورق' && $paper[2] === true) {
            $types[] = $paper[1];
        }
    }
    
    sendJSON(['types' => $types]);
}

// -----------------------------
// أنواع الظروف الداخلية - GET /inner-envelop-types
// -----------------------------
if ($path === '/inner-envelop-types' && $method === 'GET') {
    $data = readJSON('papers.json');
    $papers = $data['papers'] ?? [];
    $types = [];
    
    foreach ($papers as $paper) {
        if ($paper[0] === 'ظرف' && $paper[2] === true) {
            $types[] = $paper[1];
        }
    }
    
    sendJSON(['types' => $types]);
}

// -----------------------------
// حفظ papers - POST /save-papers
// -----------------------------
if ($path === '/save-papers' && $method === 'POST') {
    if (writeJSON('papers.json', $inputData)) {
        sendJSON(['success' => true, 'message' => 'تم حفظ البيانات بنجاح']);
    } else {
        sendJSON(['success' => false, 'message' => 'خطأ في حفظ البيانات'], 500);
    }
}

// -----------------------------
// قراءة settings.json - GET /settings.json
// -----------------------------
if ($path === '/settings.json' && $method === 'GET') {
    $settings = readJSON('settings.json');
    sendJSON($settings);
}

// -----------------------------
// عرض الإعدادات للعرض - GET /settings-display
// -----------------------------
if ($path === '/settings-display' && $method === 'GET') {
    $settings = readJSON('settings.json');
    
    // تحويل القيم من عشرية إلى نسبة مئوية للعرض
    if (isset($settings['invoicePercent'])) {
        $settings['invoicePercent'] = $settings['invoicePercent'] * 100;
    }
    if (isset($settings['letterPercent'])) {
        $settings['letterPercent'] = $settings['letterPercent'] * 100;
    }
    if (isset($settings['vat'])) {
        $settings['vat'] = $settings['vat'] * 100;
    }
    
    sendJSON($settings);
}

// -----------------------------
// حفظ الإعدادات - POST /save-settings
// -----------------------------
if ($path === '/save-settings' && $method === 'POST') {
    $settings = $inputData;
    
    // تحويل النسب المئوية إلى قيم عشرية قبل الحفظ
    if (isset($settings['invoicePercent'])) {
        $settings['invoicePercent'] = floatval($settings['invoicePercent']) / 100;
    }
    if (isset($settings['letterPercent'])) {
        $settings['letterPercent'] = floatval($settings['letterPercent']) / 100;
    }
    if (isset($settings['vat'])) {
        $settings['vat'] = floatval($settings['vat']) / 100;
    }
    
    // التأكد من وجود جميع الحقول المطلوبة
    $requiredFields = [
        'slefanBigMatte', 'slefanBigGlossy', 'invoicePercent', 'letterPercent',
        'printPrice', 'cuttingPrice', 'breakingPrice', 'pocketWhitePrice',
        'pocketPrintedPrice', 'vat', 'invoicePaperPrice'
    ];
    
    foreach ($requiredFields as $field) {
        if (!isset($settings[$field]) || !is_numeric($settings[$field])) {
            $settings[$field] = 0;
        }
    }
    
    if (writeJSON('settings.json', $settings)) {
        sendJSON(['success' => true]);
    } else {
        sendJSON(['success' => false, 'message' => 'حدث خطأ أثناء الحفظ'], 500);
    }
}

// -----------------------------
// Health check - GET /health
// -----------------------------
if ($path === '/health' && $method === 'GET') {
    sendJSON([
        'status' => 'OK',
        'timestamp' => date('c'),
        'message' => 'السيرفر يعمل بشكل طبيعي'
    ]);
}

// -----------------------------
// Ping - GET /ping
// -----------------------------
if ($path === '/ping' && $method === 'GET') {
    sendJSON([
        'ping' => 'pong',
        'time' => date('c')
    ]);
}

// -----------------------------
// إذا لم يتطابق أي مسار
// -----------------------------
sendJSON(['error' => 'المسار غير موجود', 'path' => $path], 404);
?>
