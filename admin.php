<?php
// Admin: view wishes + photos, export CSV, delete entries.
declare(strict_types=1);

require __DIR__ . '/includes/db.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_name('wedding_admin');
session_start();

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// Show a readable setup problem instead of a blank HTTP 500
function setup_error(string $title, string $detail): void
{
    http_response_code(503);
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>ตั้งค่าระบบ</title></head>'
        . '<body style="font-family:system-ui,sans-serif;background:#F2E8D8;color:#3A2A21;margin:0;padding:12vh 20px">'
        . '<div style="max-width:440px;margin:auto;background:#FBF6EE;border:1px solid #DCCAB0;border-radius:12px;padding:28px 24px;line-height:1.7">'
        . '<h1 style="font-size:20px;margin:0 0 10px">' . h($title) . '</h1><p style="margin:0">' . h($detail) . '</p></div></body></html>';
    exit;
}

try {
    $config = app_config();
} catch (Throwable $e) {
    error_log('[admin] ' . $e->getMessage());
    setup_error('ระบบยังตั้งค่าไม่ครบ', $e->getMessage());
}
$uploadDir = __DIR__ . '/uploads';

function valid_photo_name(?string $f): bool
{
    return $f !== null && (bool) preg_match('/^\d{8}-[0-9a-f]{24}\.(jpg|png|webp)$/', $f);
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid request');
    }
}

// ---- Login / logout ----
$loginError = '';
if (($_POST['action'] ?? '') === 'login') {
    check_csrf();
    if (hash_equals((string) $config['admin_password'], (string) ($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    }
    usleep(800000);
    $loginError = 'รหัสผ่านไม่ถูกต้อง';
}
if (($_POST['action'] ?? '') === 'logout') {
    check_csrf();
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

$loggedIn = !empty($_SESSION['admin']);

if ($loggedIn) {
    try {
        $pdo = db();
    } catch (Throwable $e) {
        error_log('[admin] db: ' . $e->getMessage());
        setup_error('เชื่อมต่อฐานข้อมูลไม่ได้', 'ตรวจค่า db_host, db_name, db_user, db_pass ใน includes/config.php ให้ตรงกับฐานข้อมูลบนโฮสต์ และต้องสร้างฐานข้อมูลไว้ก่อน (ตาราง wishes ระบบสร้างให้เอง)');
    }

    // ---- Serve a guest photo (admin only) ----
    if (isset($_GET['photo'])) {
        $q = $pdo->prepare('SELECT name, photo FROM wishes WHERE id = ?');
        $q->execute([(int) $_GET['photo']]);
        $row  = $q->fetch();
        $path = $row && valid_photo_name($row['photo']) ? "$uploadDir/{$row['photo']}" : null;
        if (!$path || !is_file($path)) {
            http_response_code(404);
            exit('Not found');
        }
        $ext   = pathinfo($path, PATHINFO_EXTENSION);
        $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        header('Content-Type: ' . $types[$ext]);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        if (!empty($_GET['dl'])) {
            $safe = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $row['name']) ?: 'photo';
            header("Content-Disposition: attachment; filename=\"photo.$ext\"; filename*=UTF-8''" . rawurlencode("wish-{$safe}.{$ext}"));
        }
        readfile($path);
        exit;
    }

    // ---- Delete ----
    if (($_POST['action'] ?? '') === 'delete') {
        check_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $q  = $pdo->prepare('SELECT photo FROM wishes WHERE id = ?');
        $q->execute([$id]);
        $photo = $q->fetchColumn();
        $pdo->prepare('DELETE FROM wishes WHERE id = ?')->execute([$id]);
        if (is_string($photo) && valid_photo_name($photo) && is_file("$uploadDir/$photo")) {
            unlink("$uploadDir/$photo");
        }
        header('Location: admin.php' . (!empty($_POST['back']) ? '?' . $_POST['back'] : ''));
        exit;
    }

    // ---- CSV export ----
    if (($_GET['export'] ?? '') === 'csv') {
        $rows = $pdo->query('SELECT * FROM wishes ORDER BY created_at ASC')->fetchAll();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="wishes-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads Thai correctly
        fputcsv($out, ['เวลา', 'ชื่อ', 'คำอวยพร', 'มีรูป', 'ลิงก์เชิญถึง']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['created_at'], $r['name'], $r['message'], $r['photo'] ? 'มี' : '', $r['invited_as']]);
        }
        exit;
    }

    // ---- Stats ----
    $stats = $pdo->query('SELECT COUNT(*) AS total, COALESCE(SUM(photo IS NOT NULL), 0) AS with_photo FROM wishes')->fetch();

    // ---- List with filter/search ----
    $filters = ['all' => 'ทั้งหมด', 'photo' => 'มีรูป'];
    $filter  = isset($filters[$_GET['f'] ?? '']) ? $_GET['f'] : 'all';
    $search = trim((string) ($_GET['q'] ?? ''));
    $where  = [];
    $params = [];
    if ($filter === 'photo') {
        $where[] = 'photo IS NOT NULL';
    }
    if ($search !== '') {
        $where[] = '(name LIKE ? OR message LIKE ? OR invited_as LIKE ?)';
        $like    = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    $sql  = 'SELECT * FROM wishes' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $back = http_build_query(array_filter(['f' => $filter !== 'all' ? $filter : null, 'q' => $search ?: null]));
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Wishes Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;500&family=Taviraj:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root{--bg:#F2E8D8;--blush:#E9DCC6;--rose:#CDAE7E;--deep:#3F2A20;--ink:#3A2A21;--muted:#76604F;--line:#DCCAB0;--white:#FBF6EE;--gold:#A8854F}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--ink);font-family:'Taviraj',Georgia,serif;font-weight:300;line-height:1.6;font-size:14px}
  a{color:var(--deep)}
  .wrap{max-width:1100px;margin:0 auto;padding:28px 16px 60px}
  .kicker{font-family:'Cormorant Garamond',serif;letter-spacing:.32em;text-transform:uppercase;font-size:11px;color:var(--deep)}
  h1{font-weight:400;font-size:24px}
  header{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:22px}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:6px;border:1px solid var(--ink);background:var(--ink);color:#fff;
    font:inherit;font-size:13px;text-decoration:none;cursor:pointer}
  .btn.ghost{background:transparent;color:var(--ink);border-color:var(--rose)}
  .btn-row{display:flex;gap:8px;flex-wrap:wrap}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:22px;max-width:420px}
  .stat{background:var(--white);border:1px solid var(--line);border-radius:10px;padding:14px 16px}
  .stat b{display:block;font-family:'Cormorant Garamond',serif;font-weight:300;font-size:30px;line-height:1.2}
  .stat small{color:var(--muted);font-size:12px}
  .toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:14px}
  .tabs{display:flex;gap:6px}
  .tabs a{padding:7px 14px;border-radius:999px;border:1px solid var(--line);text-decoration:none;color:var(--ink);background:var(--white);font-size:13px}
  .tabs a.on{border-color:var(--ink);color:#fff;background:var(--ink)}
  form.search{flex:1;max-width:340px;min-width:200px}
  input{font:inherit;font-weight:300;color:var(--ink);padding:9px 14px;border:1px solid var(--line);border-radius:999px;background:var(--white);outline:none;width:100%}
  input:focus{border-color:var(--deep)}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;align-items:start}
  .card{background:var(--white);border:1px solid var(--line);border-radius:10px;overflow:hidden}
  .card .photo{display:block;background:var(--blush)}
  .card .photo img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover}
  .card .body{padding:14px 16px 12px}
  .card .name{font-weight:500;font-size:15px}
  .card .meta{font-size:12px;color:var(--muted)}
  .card .msg{margin-top:8px;white-space:pre-wrap;word-break:break-word}
  .card .actions{display:flex;gap:14px;justify-content:flex-end;padding:8px 16px 12px;border-top:1px solid var(--line);margin-top:12px}
  .link{background:none;border:none;color:var(--muted);cursor:pointer;font:inherit;font-size:12px;text-decoration:underline}
  .link:hover{color:var(--deep)}
  .empty{padding:48px;text-align:center;color:var(--muted);background:var(--white);border:1px solid var(--line);border-radius:10px}
  .login{max-width:360px;margin:12vh auto 0;background:var(--white);border:1px solid var(--line);border-radius:14px;padding:32px 24px;text-align:center}
  .login h1{margin:6px 0 18px}
  .login input{border-radius:6px;margin-bottom:12px}
  .login .btn{width:100%;justify-content:center}
  .err{color:#B4766F;font-size:13px;margin-bottom:10px}
  .kicker{color:var(--gold)}
  .stat.hi{background:var(--deep);border-color:var(--deep);color:#F3EADB}
  .stat.hi small{color:rgba(243,234,219,.7)}
</style>
</head>
<body>
<?php if (!$loggedIn): ?>
  <div class="wrap">
    <form class="login" method="post">
      <div class="kicker">Wishes</div>
      <h1>เข้าสู่ระบบผู้ดูแล</h1>
      <?php if ($loginError): ?><div class="err"><?= h($loginError) ?></div><?php endif; ?>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="password" name="password" placeholder="รหัสผ่าน" autocomplete="current-password" required autofocus>
      <button class="btn" type="submit">เข้าสู่ระบบ</button>
    </form>
  </div>
<?php else: ?>
  <div class="wrap">
    <header>
      <div>
        <div class="kicker">Wishes</div>
        <h1>คำอวยพรจากแขก</h1>
      </div>
      <div class="btn-row">
        <a class="btn" href="admin.php?export=csv">ดาวน์โหลด CSV</a>
        <a class="btn ghost" href="./" target="_blank">ดูหน้าการ์ด</a>
        <form method="post">
          <input type="hidden" name="action" value="logout">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <button class="btn ghost" type="submit">ออกจากระบบ</button>
        </form>
      </div>
    </header>

    <div class="stats">
      <div class="stat hi"><b><?= (int) $stats['total'] ?></b><small>คำอวยพรทั้งหมด</small></div>
      <div class="stat"><b><?= (int) $stats['with_photo'] ?></b><small>แนบรูปมาด้วย</small></div>
    </div>

    <div class="toolbar">
      <div class="tabs">
        <?php foreach ($filters as $key => $label): ?>
          <a class="<?= $filter === $key ? 'on' : '' ?>" href="?<?= h(http_build_query(array_filter(['f' => $key !== 'all' ? $key : null, 'q' => $search ?: null]))) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </div>
      <form class="search" method="get">
        <?php if ($filter !== 'all'): ?><input type="hidden" name="f" value="<?= h($filter) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= h($search) ?>" placeholder="ค้นหาชื่อ / ข้อความ">
      </form>
    </div>

    <?php if (!$rows): ?>
      <div class="empty">ยังไม่มีคำอวยพร</div>
    <?php else: ?>
    <div class="grid">
      <?php foreach ($rows as $r): ?>
      <article class="card">
        <?php if ($r['photo']): ?>
          <a class="photo" href="admin.php?photo=<?= (int) $r['id'] ?>" target="_blank" rel="noopener">
            <img src="admin.php?photo=<?= (int) $r['id'] ?>" alt="รูปจาก <?= h($r['name']) ?>" loading="lazy">
          </a>
        <?php endif; ?>
        <div class="body">
          <div class="name"><?= h($r['name']) ?></div>
          <div class="meta">
            <?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?>
            <?php if ($r['invited_as'] && $r['invited_as'] !== $r['name']): ?> · ลิงก์: <?= h($r['invited_as']) ?><?php endif; ?>
          </div>
          <p class="msg"><?= h($r['message']) ?></p>
        </div>
        <div class="actions">
          <?php if ($r['photo']): ?>
            <a class="link" href="admin.php?photo=<?= (int) $r['id'] ?>&amp;dl=1">ดาวน์โหลดรูป</a>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('ลบคำอวยพรของ <?= h(addslashes($r['name'])) ?> ? (ลบรูปด้วย และกู้คืนไม่ได้)')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="back" value="<?= h($back) ?>">
            <button class="link" type="submit">ลบ</button>
          </form>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</body>
</html>
