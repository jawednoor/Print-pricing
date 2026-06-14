<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Allow: GET, POST, OPTIONS');
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

function cleanNumber(mixed $value): float
{
    $normalized = str_replace(['٫', '٬', ','], ['.', '', '.'], trim((string) $value));
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function verifyStoredPassword(string $password, string $storedHash): bool
{
    if (password_verify($password, $storedHash)) {
        return true;
    }
    $parts = explode('$', $storedHash);
    if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256' || !ctype_digit($parts[1])) {
        return false;
    }
    $actual = hash_pbkdf2('sha256', $password, $parts[2], (int) $parts[1], 64, false);
    return hash_equals($parts[3], $actual);
}

function findUser(string $username): ?array
{
    $statement = database()->prepare('SELECT id, username, role, password_hash FROM users WHERE username = :username LIMIT 1');
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();
    return is_array($user) ? $user : null;
}

function loginKey(string $username): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', strtolower($username) . '|' . trim(explode(',', $ip)[0]));
}

function loginAttempt(string $key): ?array
{
    $statement = database()->prepare('SELECT attempt_count, first_attempt, locked_until FROM login_attempts WHERE attempt_key = :attempt_key');
    $statement->execute(['attempt_key' => $key]);
    $record = $statement->fetch();
    return is_array($record) ? $record : null;
}

function recordLoginFailure(string $key): void
{
    $now = time();
    $record = loginAttempt($key);
    if ($record === null || (int) $record['first_attempt'] < $now - 900) {
        $count = 1;
        $firstAttempt = $now;
    } else {
        $count = (int) $record['attempt_count'] + 1;
        $firstAttempt = (int) $record['first_attempt'];
    }
    $lockedUntil = $count >= 5 ? $now + 900 : 0;
    $statement = database()->prepare(
        'INSERT INTO login_attempts (attempt_key, attempt_count, first_attempt, locked_until)
         VALUES (:attempt_key, :attempt_count, :first_attempt, :locked_until)
         ON DUPLICATE KEY UPDATE attempt_count = VALUES(attempt_count), first_attempt = VALUES(first_attempt), locked_until = VALUES(locked_until)'
    );
    $statement->execute(['attempt_key' => $key, 'attempt_count' => $count, 'first_attempt' => $firstAttempt, 'locked_until' => $lockedUntil]);
}

function clearLoginFailures(string $key): void
{
    $statement = database()->prepare('DELETE FROM login_attempts WHERE attempt_key = :attempt_key');
    $statement->execute(['attempt_key' => $key]);
}

function settingsData(): array
{
    $result = [];
    foreach (database()->query('SELECT setting_key, setting_value FROM settings') as $row) {
        $result[(string) $row['setting_key']] = (float) $row['setting_value'];
    }
    return $result;
}

function pricingData(): array
{
    $rows = [];
    $statement = database()->query('SELECT threshold_value, percent_value FROM pricing_tiers ORDER BY sort_order, threshold_value');
    foreach ($statement as $row) {
        $value = (float) $row['threshold_value'];
        $percent = (float) $row['percent_value'];
        $rows[] = ['value' => $value, 'percent' => rtrim(rtrim(number_format($percent, 3, '.', ''), '0'), '.') . '%', 'decimal' => $percent / 100];
    }
    return $rows;
}

function papersData(): array
{
    $papers = [];
    $statement = database()->query(
        'SELECT paper_type, paper_name, available, price, sheet_size, sheets_per_pack,
                a3_count, a4_count, a5_count, a6_count, a7_count, card_count
         FROM papers ORDER BY sort_order, id'
    );
    foreach ($statement as $row) {
        $papers[] = [(string) $row['paper_type'], (string) $row['paper_name'], (bool) $row['available'], (string) (float) $row['price'], (string) $row['sheet_size'], (string) (int) $row['sheets_per_pack'], (string) (int) $row['a3_count'], (string) (int) $row['a4_count'], (string) (int) $row['a5_count'], (string) (int) $row['a6_count'], (string) (int) $row['a7_count'], (string) (int) $row['card_count']];
    }
    return ['papers' => $papers];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = requestPath();
$input = requestData();

try {
    if ($path === '/login' && $method === 'POST') {
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($username === '' || $password === '') {
            sendJSON(['success' => false, 'message' => 'أدخل اسم المستخدم وكلمة المرور'], 422);
        }
        $key = loginKey($username);
        $attempt = loginAttempt($key);
        if ((int) ($attempt['locked_until'] ?? 0) > time()) {
            sendJSON(['success' => false, 'message' => 'تم إيقاف المحاولات مؤقتًا. حاول بعد 15 دقيقة.'], 429);
        }
        $user = findUser($username);
        if ($user === null || !verifyStoredPassword($password, (string) $user['password_hash'])) {
            recordLoginFailure($key);
            usleep(300000);
            sendJSON(['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'], 401);
        }
        if (str_starts_with((string) $user['password_hash'], 'pbkdf2_sha256$')) {
            $statement = database()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $statement->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }
        clearLoginFailures($key);
        session_regenerate_id(true);
        $_SESSION['isLoggedIn'] = true;
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['role'] = (string) $user['role'];
        sendJSON(['success' => true, 'username' => $_SESSION['username'], 'role' => $_SESSION['role']]);
    }

    if ($path === '/session' && $method === 'GET') {
        sendJSON(['authenticated' => isLoggedIn(), 'username' => $_SESSION['username'] ?? null, 'role' => $_SESSION['role'] ?? null]);
    }

    if ($path === '/logout' && $method === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'secure' => (bool) $params['secure'], 'httponly' => true, 'samesite' => 'Strict']);
        }
        session_destroy();
        sendJSON(['success' => true]);
    }

    if ($path === '/users' && $method === 'GET') {
        requireAdmin();
        $users = database()->query("SELECT username, role FROM users ORDER BY CASE WHEN role = 'admin' THEN 0 ELSE 1 END, username")->fetchAll();
        sendJSON($users);
    }

    if ($path === '/save-users' && $method === 'POST') {
        requireAdmin();
        $connection = database();
        $connection->beginTransaction();
        $existingUsers = [];
        $existingRoles = [];
        foreach ($connection->query('SELECT username, role, password_hash FROM users') as $existing) {
            $existingKey = strtolower((string) $existing['username']);
            $existingUsers[$existingKey] = (string) $existing['password_hash'];
            $existingRoles[$existingKey] = (string) $existing['role'];
        }
        $normalized = [];
        $seen = [];
        foreach ($input as $user) {
            if (!is_array($user)) continue;
            $username = trim((string) ($user['username'] ?? ''));
            $key = strtolower($username);
            if ($username === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $password = (string) ($user['password'] ?? '');
            $hash = $existingUsers[$key] ?? '';
            if ($password !== '') {
                if (strlen($password) < 6) throw new InvalidArgumentException('كلمة المرور يجب ألا تقل عن 6 خانات');
                $hash = password_hash($password, PASSWORD_DEFAULT);
            }
            if ($hash === '') throw new InvalidArgumentException('أدخل كلمة مرور للمستخدم الجديد: ' . $username);
            $normalized[] = ['username' => $username, 'role' => ($existingRoles[$key] ?? ($user['role'] ?? 'user')) === 'admin' ? 'admin' : 'user', 'password_hash' => $hash];
        }
        if (!array_filter($normalized, static fn(array $user): bool => $user['role'] === 'admin')) {
            throw new InvalidArgumentException('لا يمكن حذف حساب المدير');
        }
        $connection->exec('DELETE FROM users');
        $statement = $connection->prepare('INSERT INTO users (username, role, password_hash) VALUES (:username, :role, :password_hash)');
        foreach ($normalized as $user) $statement->execute($user);
        $connection->commit();
        sendJSON(['success' => true]);
    }

    if ($path === '/values.json' && $method === 'GET') {
        requireLogin();
        sendJSON(pricingData());
    }
    if ($path === '/values.json' && $method === 'POST') {
        requireAdmin();
        $connection = database();
        $connection->beginTransaction();
        $connection->exec('DELETE FROM pricing_tiers');
        $statement = $connection->prepare('INSERT INTO pricing_tiers (threshold_value, percent_value, sort_order) VALUES (:threshold_value, :percent_value, :sort_order)');
        $order = 0;
        foreach ($input as $row) {
            if (!is_array($row)) continue;
            $statement->execute(['threshold_value' => cleanNumber($row['value'] ?? 0), 'percent_value' => cleanNumber(str_replace('%', '', (string) ($row['percent'] ?? ''))), 'sort_order' => $order++]);
        }
        $connection->commit();
        sendJSON(['success' => true]);
    }

    if ($path === '/papers.json' && $method === 'GET') {
        requireLogin();
        sendJSON(papersData());
    }
    if ($path === '/save-papers' && $method === 'POST') {
        requireAdmin();
        $paperRows = $input['papers'] ?? $input;
        if (!is_array($paperRows)) throw new InvalidArgumentException('بيانات الورق غير صحيحة');
        $connection = database();
        $connection->beginTransaction();
        $connection->exec('DELETE FROM papers');
        $statement = $connection->prepare(
            'INSERT INTO papers (paper_type, paper_name, available, price, sheet_size, sheets_per_pack, a3_count, a4_count, a5_count, a6_count, a7_count, card_count, sort_order)
             VALUES (:paper_type, :paper_name, :available, :price, :sheet_size, :sheets_per_pack, :a3_count, :a4_count, :a5_count, :a6_count, :a7_count, :card_count, :sort_order)'
        );
        $order = 0;
        foreach ($paperRows as $row) {
            if (!is_array($row) || trim((string) ($row[1] ?? '')) === '') continue;
            $statement->execute(['paper_type' => ($row[0] ?? '') === 'ظرف' ? 'ظرف' : 'ورق', 'paper_name' => trim((string) $row[1]), 'available' => filter_var($row[2] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, 'price' => cleanNumber($row[3] ?? 0), 'sheet_size' => trim((string) ($row[4] ?? '')), 'sheets_per_pack' => (int) cleanNumber($row[5] ?? 0), 'a3_count' => (int) cleanNumber($row[6] ?? 0), 'a4_count' => (int) cleanNumber($row[7] ?? 0), 'a5_count' => (int) cleanNumber($row[8] ?? 0), 'a6_count' => (int) cleanNumber($row[9] ?? 0), 'a7_count' => (int) cleanNumber($row[10] ?? 0), 'card_count' => (int) cleanNumber($row[11] ?? 0), 'sort_order' => $order++]);
        }
        $connection->commit();
        sendJSON(['success' => true, 'message' => 'تم حفظ بيانات الورق']);
    }

    if (($path === '/settings.json' || $path === '/settings') && $method === 'GET') {
        requireLogin();
        $settings = settingsData();
        if (isset($settings['letterPercent'])) $settings['letterPercent'] *= 100;
        sendJSON($settings);
    }
    if ($path === '/settings-display' && $method === 'GET') {
        requireAdmin();
        $settings = settingsData();
        foreach (['invoicePercent', 'letterPercent', 'vat'] as $field) {
            if (isset($settings[$field])) $settings[$field] *= 100;
        }
        sendJSON($settings);
    }
    if ($path === '/save-settings' && $method === 'POST') {
        requireAdmin();
        $connection = database();
        $connection->beginTransaction();
        $statement = $connection->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($input as $field => $value) {
            if (!is_string($field) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $field)) continue;
            $number = cleanNumber($value);
            if (in_array($field, ['invoicePercent', 'letterPercent', 'vat'], true)) $number /= 100;
            $statement->execute(['setting_key' => $field, 'setting_value' => $number]);
        }
        $connection->commit();
        sendJSON(['success' => true]);
    }

    if ($path === '/inner-paper-types' && $method === 'GET') {
        requireLogin();
        $statement = database()->query("SELECT paper_name FROM papers WHERE paper_type = 'ورق' AND available = 1 ORDER BY sort_order, paper_name");
        sendJSON(['types' => array_column($statement->fetchAll(), 'paper_name')]);
    }
    if ($path === '/inner-envelop-types' && $method === 'GET') {
        requireLogin();
        $statement = database()->query("SELECT paper_name FROM papers WHERE paper_type = 'ظرف' AND available = 1 ORDER BY sort_order, paper_name");
        sendJSON(['types' => array_column($statement->fetchAll(), 'paper_name')]);
    }
    if (($path === '/health' || $path === '/ping') && $method === 'GET') {
        database()->query('SELECT 1');
        sendJSON(['status' => 'ok', 'database' => 'connected', 'time' => date(DATE_ATOM)]);
    }
    sendJSON(['success' => false, 'message' => 'المسار غير موجود'], 404);
} catch (InvalidArgumentException $error) {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) $connection->rollBack();
    sendJSON(['success' => false, 'message' => $error->getMessage()], 422);
} catch (Throwable $error) {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) $connection->rollBack();
    error_log($error->__toString());
    sendJSON(['success' => false, 'message' => 'تعذر الاتصال بقاعدة البيانات أو تنفيذ العملية. راجع إعدادات MySQL.'], 500);
}
