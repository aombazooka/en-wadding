<?php
// PDO connection + auto-create database/table on first run.

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        if (!is_file(__DIR__ . '/config.php')) {
            throw new RuntimeException('ไม่พบไฟล์ includes/config.php บนเซิร์ฟเวอร์ — คัดลอก includes/config.example.php เป็น config.php แล้วใส่ค่าฐานข้อมูลและรหัสผ่าน');
        }
        $loaded = require __DIR__ . '/config.php';
        if (!is_array($loaded)) {
            throw new RuntimeException('includes/config.php ต้อง return [ ... ] เป็น array (ดูตัวอย่างใน config.example.php)');
        }
        $password = (string) ($loaded['admin_password'] ?? '');
        if ($password === '' || $password === 'CHANGE_ME') {
            throw new RuntimeException('ยังไม่ได้ตั้ง admin_password ใน includes/config.php');
        }
        $config = $loaded + [
            'db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => 'wedding_ne', 'db_user' => 'root', 'db_pass' => '',
            'max_photo_mb' => 10, 'timezone' => 'Asia/Bangkok',
        ];
        date_default_timezone_set((string) $config['timezone']) || date_default_timezone_set('Asia/Bangkok');
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $c = app_config();
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO(
            "mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'], $c['db_pass'], $options
        );
    } catch (PDOException $e) {
        // 1049 = unknown database → create it (works on local XAMPP; on shared hosting create the DB in the panel)
        if ((int) $e->errorInfo[1] !== 1049) {
            throw $e;
        }
        $tmp = new PDO("mysql:host={$c['db_host']};port={$c['db_port']};charset=utf8mb4", $c['db_user'], $c['db_pass'], $options);
        $tmp->exec("CREATE DATABASE IF NOT EXISTS `{$c['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO(
            "mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'], $c['db_pass'], $options
        );
    }

    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    return $pdo;
}
