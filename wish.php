<?php
// POST endpoint: save one wish (name + message + optional photo), respond with JSON.
declare(strict_types=1);

require __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function one_line(string $key, int $maxLen): string
{
    $v = trim((string) ($_POST[$key] ?? ''));
    $v = preg_replace('/\s+/u', ' ', $v) ?? '';
    return mb_substr($v, 0, $maxLen);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Method not allowed']);
}

// Request bigger than post_max_size → PHP drops the whole body
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond(413, ['ok' => false, 'error' => 'ไฟล์รูปใหญ่เกินไป']);
}

// Honeypot: real people never see/fill this field
if (!empty($_POST['website'])) {
    respond(200, ['ok' => true]);
}

$config    = app_config();
$name      = one_line('name', 120);
$invitedAs = one_line('invited_as', 120);
$message   = trim(str_replace("\r\n", "\n", (string) ($_POST['message'] ?? '')));
$message   = mb_substr(preg_replace("/\n{3,}/", "\n\n", $message) ?? '', 0, 1000);

$errors = [];
if ($name === '') {
    $errors['name'] = 'กรุณากรอกชื่อ';
}
if ($message === '') {
    $errors['message'] = 'กรุณาเขียนคำอวยพร';
}

// ---- Optional photo ----
$photo = $_FILES['photo'] ?? null;
$hasPhoto = $photo && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$photoExt = null;

if ($hasPhoto) {
    $maxBytes = (int) $config['max_photo_mb'] * 1024 * 1024;
    if (in_array($photo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $photo['size'] > $maxBytes) {
        $errors['photo'] = 'ไฟล์รูปใหญ่เกิน ' . $config['max_photo_mb'] . ' MB';
    } elseif ($photo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($photo['tmp_name'])) {
        $errors['photo'] = 'อัปโหลดรูปไม่สำเร็จ';
    } else {
        $info = @getimagesize($photo['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$info || !isset($allowed[$mime]) || $info['mime'] !== $mime || $info[0] > 8000 || $info[1] > 8000) {
            $errors['photo'] = 'รองรับเฉพาะไฟล์รูป JPG, PNG หรือ WEBP';
        } else {
            $photoExt = $allowed[$mime];
        }
    }
}

if ($errors) {
    respond(422, ['ok' => false, 'errors' => $errors]);
}

try {
    $pdo = db();
    $ip  = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    // Simple flood guard: max 10 wishes per IP per 10 minutes
    $q = $pdo->prepare('SELECT COUNT(*) FROM wishes WHERE ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)');
    $q->execute([$ip]);
    if ((int) $q->fetchColumn() >= 10) {
        respond(429, ['ok' => false, 'error' => 'ส่งบ่อยเกินไป กรุณาลองใหม่ภายหลัง']);
    }

    $fileName = null;
    if ($photoExt) {
        $dir = __DIR__ . '/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fileName = date('Ymd') . '-' . bin2hex(random_bytes(12));

        // Re-encode when GD is available (strips metadata); otherwise keep the validated file as-is
        if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring((string) file_get_contents($photo['tmp_name'])))) {
            $fileName .= '.jpg';
            imagejpeg($img, "$dir/$fileName", 85);
            imagedestroy($img);
        } else {
            $fileName .= '.' . $photoExt;
            if (!move_uploaded_file($photo['tmp_name'], "$dir/$fileName")) {
                throw new RuntimeException('move_uploaded_file failed');
            }
        }
    }

    $stmt = $pdo->prepare('INSERT INTO wishes (name, message, photo, invited_as, ip) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $message, $fileName, $invitedAs !== '' ? $invitedAs : null, $ip]);

    respond(200, ['ok' => true]);
} catch (Throwable $e) {
    error_log('[wish] ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง']);
}
