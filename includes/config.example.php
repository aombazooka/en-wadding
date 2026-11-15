<?php
// ===== Wedding site configuration (ตัวอย่าง) =====
// คัดลอกไฟล์นี้เป็น includes/config.php แล้วแก้ค่าให้ตรงกับเซิร์ฟเวอร์
// includes/config.php ถูก .gitignore ไว้ — ห้าม commit รหัสผ่านจริงขึ้น git

return [
    // Database
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'wedding_ne',
    'db_user' => 'root',
    'db_pass' => '',

    // รหัสผ่านเข้าหน้า admin.php — ตั้งให้ยาวและเดายาก
    'admin_password' => 'CHANGE_ME',

    // ขนาดรูปที่แขกแนบได้สูงสุด (MB) — หน้าเว็บย่อรูปให้ก่อนส่งอยู่แล้ว
    'max_photo_mb' => 10,

    // timezone ของงาน
    'timezone' => 'Asia/Bangkok',
];
