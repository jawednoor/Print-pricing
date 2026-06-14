<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

const PUBLIC_DATA_FILES = ['values.json', 'papers.json', 'settings.json'];
const PRIVATE_DATA_FILES = ['user-data.json', 'failed-logins.json'];

function privateDataDirectory(): string
{
    $configured = trim((string) getenv('DATA_DIR'));
    $directory = $configured !== '' ? $configured : __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        sendJSON(['success' => false, 'message' => 'تعذر إنشاء مجلد البيانات'], 500);
    }
    return rtrim($directory, DIRECTORY_SEPARATOR);
}

function dataPath(string $filename): string
{
    if (in_array($filename, PRIVATE_DATA_FILES, true)) {
        return privateDataDirectory() . DIRECTORY_SEPARATOR . $filename;
    }
    if (in_array($filename, PUBLIC_DATA_FILES, true)) {
        return __DIR__ . DIRECTORY_SEPARATOR . $filename;
    }
    throw new InvalidArgumentException('Unsupported data file');
}

function readJSON(string $filename): array
{
    $path = dataPath($filename);
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function writeJSON(string $filename, array $data): bool
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }
    return file_put_contents(dataPath($filename), $encoded . PHP_EOL, LOCK_EX) !== false;
}

function sendJSON(array $data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestData(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        sendJSON(['success' => false, 'message' => 'بيانات الطلب غير صحيحة'], 400);
    }
    return $decoded;
}

function requestPath(): string
{
    if (isset($_GET['action']) && is_string($_GET['action'])) {
        return '/' . trim($_GET['action'], '/');
    }
    if (!empty($_SERVER['PATH_INFO'])) {
        return '/' . trim((string) $_SERVER['PATH_INFO'], '/');
    }
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $marker = '/server.php/';
    $position = strpos($uri, $marker);
    return $position === false ? '/' : '/' . trim(substr($uri, $position + strlen($marker)), '/');
}

function isLoggedIn(): bool
{
    return ($_SESSION['isLoggedIn'] ?? false) === true;
}

function requireLogin()
{
    if (!isLoggedIn()) {
        sendJSON(['success' => false, 'message' => 'يرجى تسجيل الدخول أولاً'], 401);
    }
}

function requireAdmin()
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        sendJSON(['success' => false, 'message' => 'هذه العملية متاحة للمدير فقط'], 403);
    }
}

function verifyPassword(string $password, string $storedHash): bool
{
    $parts = explode('$', $storedHash);
    if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256' || !ctype_digit($parts[1])) {
        return false;
    }
    $actual = hash_pbkdf2('sha256', $password, $parts[2], (int) $parts[1], 64, false);
    return hash_equals($parts[3], $actual);
}

function createPasswordHash(string $password): string
{
    $iterations = 210000;
    $salt = bin2hex(random_bytes(16));
    $hash = hash_pbkdf2('sha256', $password, $salt, $iterations, 64, false);
    return 'pbkdf2_sha256$' . $iterations . '$' . $salt . '$' . $hash;
}

function findUser(string $username): ?array
{
    foreach (readJSON('user-data.json') as $user) {
        if (is_array($user) && hash_equals((string) ($user['username'] ?? ''), $username)) {
            return $user;
        }
    }
    return null;
}

function loginKey(string $username): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', strtolower($username) . '|' . trim(explode(',', $ip)[0]));
}

function recordLoginFailure(string $key): int
{
    $records = readJSON('failed-logins.json');
    $now = time();
    $record = $records[$key] ?? ['count' => 0, 'firstAttempt' => $now, 'lockedUntil' => 0];
    if ((int) ($record['firstAttempt'] ?? 0) < $now - 900) {
        $record = ['count' => 0, 'firstAttempt' => $now, 'lockedUntil' => 0];
    }
    $record['count'] = (int) ($record['count'] ?? 0) + 1;
    if ($record['count'] >= 5) {
        $record['lockedUntil'] = $now + 900;
    }
    $records[$key] = $record;
    writeJSON('failed-logins.json', $records);
    return (int) $record['lockedUntil'];
}

function clearLoginFailures(string $key)
{
    $records = readJSON('failed-logins.json');
    unset($records[$key]);
    writeJSON('failed-logins.json', $records);
}

function cleanNumber($value): float
{
    $normalized = str_replace(['٫', '٬', ','], ['.', '', '.'], trim((string) $value));
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = requestPath();
$input = requestData();

if ($path === '/login' && $method === 'POST') {
    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    if ($username === '' || $password === '') {
        sendJSON(['success' => false, 'message' => 'أدخل اسم المستخدم وكلمة المرور'], 422);
    }

    $key = loginKey($username);
    $failures = readJSON('failed-logins.json');
    if ((int) ($failures[$key]['lockedUntil'] ?? 0) > time()) {
        sendJSON(['success' => false, 'message' => 'تم إيقاف المحاولات مؤقتًا. حاول بعد 15 دقيقة.'], 429);
    }

    $user = findUser($username);
    if ($user === null || !verifyPassword($password, (string) ($user['passwordHash'] ?? ''))) {
        recordLoginFailure($key);
        usleep(300000);
        sendJSON(['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'], 401);
    }

    clearLoginFailures($key);
    session_regenerate_id(true);
    $_SESSION['isLoggedIn'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = (string) ($user['role'] ?? 'user');
    sendJSON(['success' => true, 'username' => $username, 'role' => $_SESSION['role']]);
}

if ($path === '/session' && $method === 'GET') {
    sendJSON([
        'authenticated' => isLoggedIn(),
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
    ]);
}

if ($path === '/logout' && $method === 'POST') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], '', (bool) $params['secure'], true);
    }
    session_destroy();
    sendJSON(['success' => true]);
}

if ($path === '/users' && $method === 'GET') {
    requireAdmin();
    $result = [];
    foreach (readJSON('user-data.json') as $user) {
        $result[] = [
            'username' => (string) ($user['username'] ?? ''),
            'role' => (string) ($user['role'] ?? 'user'),
        ];
    }
    sendJSON($result);
}

if ($path === '/save-users' && $method === 'POST') {
    requireAdmin();
    $users = [];
    $seen = [];
    foreach ($input as $user) {
        if (!is_array($user)) continue;
        $username = trim((string) ($user['username'] ?? ''));
        if ($username === '' || isset($seen[strtolower($username)])) continue;
        $seen[strtolower($username)] = true;
        $existing = findUser($username);
        $hash = (string) ($existing['passwordHash'] ?? '');
        if (!empty($user['password'])) {
            if (strlen((string) $user['password']) < 6) {
                sendJSON(['success' => false, 'message' => 'كلمة المرور يجب ألا تقل عن 6 خانات'], 422);
            }
            $hash = createPasswordHash((string) $user['password']);
        }
        if ($hash === '') {
            sendJSON(['success' => false, 'message' => 'أدخل كلمة مرور للمستخدم الجديد: ' . $username], 422);
        }
        $users[] = [
            'username' => $username,
            'role' => $username === 'admin' ? 'admin' : 'user',
            'passwordHash' => $hash,
        ];
    }
    if (!array_filter($users, function ($user) { return $user['username'] === 'admin'; })) {
        sendJSON(['success' => false, 'message' => 'لا يمكن حذف حساب المدير'], 422);
    }
    sendJSON(writeJSON('user-data.json', $users)
        ? ['success' => true]
        : ['success' => false, 'message' => 'تعذر حفظ المستخدمين'], 200);
}

if ($path === '/values.json' && $method === 'GET') sendJSON(readJSON('values.json'));
if ($path === '/values.json' && $method === 'POST') {
    requireAdmin();
    $rows = [];
    foreach ($input as $row) {
        if (!is_array($row)) continue;
        $value = cleanNumber($row['value'] ?? 0);
        $percent = cleanNumber(str_replace('%', '', (string) ($row['percent'] ?? '')));
        $rows[] = ['value' => $value, 'percent' => $percent . '%', 'decimal' => $percent / 100];
    }
    sendJSON(writeJSON('values.json', $rows) ? ['success' => true] : ['success' => false], 200);
}
if ($path === '/papers.json' && $method === 'GET') sendJSON(readJSON('papers.json'));
if ($path === '/save-papers' && $method === 'POST') {
    requireAdmin();
    sendJSON(writeJSON('papers.json', $input) ? ['success' => true, 'message' => 'تم حفظ بيانات الورق'] : ['success' => false], 200);
}
if ($path === '/settings.json' && $method === 'GET') sendJSON(readJSON('settings.json'));
if ($path === '/settings-display' && $method === 'GET') {
    requireAdmin();
    $settings = readJSON('settings.json');
    foreach (['invoicePercent', 'letterPercent', 'vat'] as $field) {
        if (isset($settings[$field])) $settings[$field] = (float) $settings[$field] * 100;
    }
    sendJSON($settings);
}
if ($path === '/save-settings' && $method === 'POST') {
    requireAdmin();
    $settings = [];
    foreach ($input as $field => $value) $settings[$field] = cleanNumber($value);
    foreach (['invoicePercent', 'letterPercent', 'vat'] as $field) {
        if (isset($settings[$field])) $settings[$field] /= 100;
    }
    sendJSON(writeJSON('settings.json', $settings) ? ['success' => true] : ['success' => false], 200);
}
if ($path === '/inner-paper-types' && $method === 'GET') {
    $types = [];
    foreach ((readJSON('papers.json')['papers'] ?? []) as $paper) {
        if (($paper[0] ?? '') === 'ورق' && ($paper[2] ?? false) === true) $types[] = $paper[1] ?? '';
    }
    sendJSON(['types' => array_values(array_filter($types))]);
}
if ($path === '/inner-envelop-types' && $method === 'GET') {
    $types = [];
    foreach ((readJSON('papers.json')['papers'] ?? []) as $paper) {
        if (($paper[0] ?? '') === 'ظرف' && ($paper[2] ?? false) === true) $types[] = $paper[1] ?? '';
    }
    sendJSON(['types' => array_values(array_filter($types))]);
}
if (($path === '/health' || $path === '/ping') && $method === 'GET') {
    sendJSON(['status' => 'ok', 'time' => date(DATE_ATOM)]);
}

sendJSON(['success' => false, 'message' => 'المسار غير موجود'], 404);
