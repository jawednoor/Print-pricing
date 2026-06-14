<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) === explode(':', $host)[0]) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
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

const DATA_FILES = [
    'user-data.json',
    'values.json',
    'papers.json',
    'settings.json',
    'failed-logins.json',
];

function dataDirectory(): string
{
    $configured = trim((string) getenv('DATA_DIR'));
    if ($configured === '') {
        return __DIR__;
    }

    if (!is_dir($configured) && !mkdir($configured, 0750, true) && !is_dir($configured)) {
        return __DIR__;
    }

    return rtrim($configured, DIRECTORY_SEPARATOR);
}

function dataPath(string $filename): string
{
    if (!in_array($filename, DATA_FILES, true)) {
        throw new InvalidArgumentException('Unsupported data file');
    }

    $persistentPath = dataDirectory() . DIRECTORY_SEPARATOR . $filename;
    $bundledPath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

    if (!file_exists($persistentPath) && file_exists($bundledPath)) {
        @copy($bundledPath, $persistentPath);
    }

    return $persistentPath;
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

    $path = dataPath($filename);
    $temp = $path . '.tmp';
    $written = file_put_contents($temp, $encoded . PHP_EOL, LOCK_EX);
    if ($written === false || !rename($temp, $path)) {
        @unlink($temp);
        return false;
    }

    // Keep direct JSON reads working when Render uses a persistent DATA_DIR.
    $bundledPath = __DIR__ . DIRECTORY_SEPARATOR . $filename;
    if ($path !== $bundledPath && is_writable(__DIR__)) {
        @copy($path, $bundledPath);
    }

    return true;
}

function sendJSON(array $data, int $status = 200): never
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

    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if ($pathInfo !== '') {
        return '/' . trim($pathInfo, '/');
    }

    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $marker = '/server.php/';
    $position = strpos($uriPath, $marker);
    if ($position !== false) {
        return '/' . trim(substr($uriPath, $position + strlen($marker)), '/');
    }

    return '/';
}

function isLoggedIn(): bool
{
    return ($_SESSION['isLoggedIn'] ?? false) === true;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        sendJSON(['success' => false, 'message' => 'يرجى تسجيل الدخول أولاً'], 401);
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        sendJSON(['success' => false, 'message' => 'هذه العملية متاحة للمدير فقط'], 403);
    }
}

function verifyPassword(string $password, string $storedHash): bool
{
    $parts = explode('$', $storedHash);
    if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256') {
        return false;
    }

    [$algorithm, $iterations, $salt, $expected] = $parts;
    if (!ctype_digit($iterations) || (int) $iterations < 100000) {
        return false;
    }

    $actual = hash_pbkdf2('sha256', $password, $salt, (int) $iterations, 64, false);
    return hash_equals($expected, $actual);
}

function createPasswordHash(string $password): string
{
    $iterations = 210000;
    $salt = bin2hex(random_bytes(16));
    $hash = hash_pbkdf2('sha256', $password, $salt, $iterations, 64, false);
    return "pbkdf2_sha256\$$iterations\$$salt\$$hash";
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
    return hash('sha256', strtolower($username) . '|' . explode(',', $ip)[0]);
}

function recordLoginFailure(string $key): int
{
    $records = readJSON('failed-logins.json');
    $now = time();
    $record = $records[$key] ?? ['count' => 0, 'firstAttempt' => $now, 'lockedUntil' => 0];

    if (($record['firstAttempt'] ?? 0) < $now - 900) {
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

function clearLoginFailures(string $key): void
{
    $records = readJSON('failed-logins.json');
    unset($records[$key]);
    writeJSON('failed-logins.json', $records);
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
    $lockedUntil = (int) ($failures[$key]['lockedUntil'] ?? 0);
    if ($lockedUntil > time()) {
        sendJSON(['success' => false, 'message' => 'تم إيقاف المحاولات مؤقتًا. حاول بعد قليل.'], 429);
    }

    $user = findUser($username);
    $valid = $user !== null && verifyPassword($password, (string) ($user['passwordHash'] ?? ''));
    if (!$valid) {
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

if ($path === '/save-users' && $method === 'POST') {
    requireAdmin();
    $users = [];
    foreach ($input as $user) {
        if (!is_array($user)) {
            continue;
        }
        $username = trim((string) ($user['username'] ?? ''));
        if ($username === '') {
            continue;
        }
        $existing = findUser($username);
        $passwordHash = (string) ($user['passwordHash'] ?? ($existing['passwordHash'] ?? ''));
        if (!empty($user['password'])) {
            $passwordHash = createPasswordHash((string) $user['password']);
        }
        if ($passwordHash === '') {
            continue;
        }
        $users[] = [
            'username' => $username,
            'role' => $username === 'admin' ? 'admin' : (string) ($user['role'] ?? 'user'),
            'passwordHash' => $passwordHash,
        ];
    }
    sendJSON(writeJSON('user-data.json', $users)
        ? ['success' => true]
        : ['success' => false, 'message' => 'تعذر حفظ المستخدمين'], 200);
}

if ($path === '/values.json' && $method === 'GET') {
    sendJSON(readJSON('values.json'));
}
if ($path === '/values.json' && $method === 'POST') {
    requireAdmin();
    sendJSON(writeJSON('values.json', $input) ? ['success' => true] : ['success' => false], 200);
}
if ($path === '/papers.json' && $method === 'GET') {
    sendJSON(readJSON('papers.json'));
}
if ($path === '/save-papers' && $method === 'POST') {
    requireAdmin();
    sendJSON(writeJSON('papers.json', $input) ? ['success' => true] : ['success' => false], 200);
}
if ($path === '/settings.json' && $method === 'GET') {
    sendJSON(readJSON('settings.json'));
}
if ($path === '/settings-display' && $method === 'GET') {
    requireAdmin();
    $settings = readJSON('settings.json');
    foreach (['invoicePercent', 'letterPercent', 'vat'] as $field) {
        if (isset($settings[$field])) {
            $settings[$field] = (float) $settings[$field] * 100;
        }
    }
    sendJSON($settings);
}
if ($path === '/save-settings' && $method === 'POST') {
    requireAdmin();
    $settings = $input;
    foreach (['invoicePercent', 'letterPercent', 'vat'] as $field) {
        if (isset($settings[$field])) {
            $settings[$field] = (float) $settings[$field] / 100;
        }
    }
    sendJSON(writeJSON('settings.json', $settings) ? ['success' => true] : ['success' => false], 200);
}
if ($path === '/inner-paper-types' && $method === 'GET') {
    $types = [];
    foreach ((readJSON('papers.json')['papers'] ?? []) as $paper) {
        if (($paper[0] ?? '') === 'ورق' && ($paper[2] ?? false) === true) {
            $types[] = $paper[1] ?? '';
        }
    }
    sendJSON(['types' => array_values(array_filter($types))]);
}
if ($path === '/inner-envelop-types' && $method === 'GET') {
    $types = [];
    foreach ((readJSON('papers.json')['papers'] ?? []) as $paper) {
        if (($paper[0] ?? '') === 'ظرف' && ($paper[2] ?? false) === true) {
            $types[] = $paper[1] ?? '';
        }
    }
    sendJSON(['types' => array_values(array_filter($types))]);
}
if (($path === '/health' || $path === '/ping') && $method === 'GET') {
    sendJSON(['status' => 'ok', 'time' => date(DATE_ATOM)]);
}

sendJSON(['success' => false, 'message' => 'المسار غير موجود'], 404);
