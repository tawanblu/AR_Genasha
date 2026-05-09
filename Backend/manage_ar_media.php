<?php
session_start();
require_once("../connect.php");
/** @var mysqli $conn */

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน (ต้องเป็น Admin เท่านั้น) 🔐
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>
            alert('คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการระบบ!');
            window.location.href = '../index.php';
          </script>";
    exit();
}

// ----------------- ADD AR MEDIA -----------------
if (isset($_POST['add_media'])) {
    $media_type       = $_POST['media_type'];
    $info_id          = intval($_POST['info_id']);

    $db_path       = NULL;
    $db_audio_path = NULL;

    try {
        // 1. จัดการไฟล์โมเดล (ถ้ามีการอัปโหลด)
        if (!empty($_FILES["file_upload"]["name"])) {
            $filename = $_FILES["file_upload"]["name"];
            $tempname = $_FILES["file_upload"]["tmp_name"];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $new_filename = time() . "_model_" . rand(1000, 9999) . "." . $ext;
            $target_dir = "../model/";

            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

            if (move_uploaded_file($tempname, $target_dir . $new_filename)) {
                $db_path = "model/" . $new_filename;
            } else {
                throw new Exception("ไม่สามารถอัปโหลดไฟล์โมเดลได้");
            }
        }

        // 2. จัดการไฟล์เสียง (ถ้ามีการอัปโหลด)
        if (!empty($_FILES["audio_upload"]["name"])) {
            $filename = $_FILES["audio_upload"]["name"];
            $tempname = $_FILES["audio_upload"]["tmp_name"];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $new_filename = time() . "_audio_" . rand(1000, 9999) . "." . $ext;
            $target_dir = "../audio/";

            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

            if (move_uploaded_file($tempname, $target_dir . $new_filename)) {
                $db_audio_path = "audio/" . $new_filename;
            } else {
                throw new Exception("ไม่สามารถอัปโหลดไฟล์เสียงได้");
            }
        }

        // 3. บันทึกลงฐานข้อมูล
        $sql  = "INSERT INTO AR_Media (info_id, file_path, audio_file, media_type) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $info_id, $db_path, $db_audio_path, $media_type);

        if ($stmt->execute()) {
            echo "<script>alert('บันทึกสื่อ AR สำเร็จ'); window.location.href='manage_ar_media.php';</script>";
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        $error_msg = addslashes($e->getMessage());
        echo "<script>alert('เกิดข้อผิดพลาด:\\n{$error_msg}'); history.back();</script>";
    }
    exit();
}

// ----------------- EDIT AR MEDIA -----------------
if (isset($_POST['edit_media'])) {
    $media_id   = intval($_POST['media_id']);
    $media_type = $_POST['media_type'];
    $info_id    = intval($_POST['info_id']);

    try {
        if ($media_id <= 0) throw new Exception("ID ข้อมูลไม่ถูกต้อง");

        // ดึงข้อมูลเดิมมาตรวจสอบ
        $res = $conn->query("SELECT file_path, audio_file FROM AR_Media WHERE media_id = $media_id");
        if (!$res) throw new Exception("Query Failed: " . $conn->error);

        $current_data = $res->fetch_assoc();
        $db_path = $current_data['file_path'];
        $db_audio_path = $current_data['audio_file'];

        // 1. จัดการไฟล์โมเดลใหม่
        if (!empty($_FILES["edit_file_upload"]["name"])) {
            $filename = $_FILES["edit_file_upload"]["name"];
            $tempname = $_FILES["edit_file_upload"]["tmp_name"];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $new_filename = time() . "_model_" . rand(1000, 9999) . "." . $ext;
            $target_dir = "../model/";

            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

            if (move_uploaded_file($tempname, $target_dir . $new_filename)) {
                if (!empty($current_data['file_path']) && file_exists("../" . $current_data['file_path'])) {
                    @unlink("../" . $current_data['file_path']);
                }
                $db_path = "model/" . $new_filename;
            }
        }

        // 2. จัดการไฟล์เสียงใหม่
        if (!empty($_FILES["edit_audio_upload"]["name"])) {
            $filename = $_FILES["edit_audio_upload"]["name"];
            $tempname = $_FILES["edit_audio_upload"]["tmp_name"];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $new_filename = time() . "_audio_" . rand(1000, 9999) . "." . $ext;
            $target_dir = "../audio/";

            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

            if (move_uploaded_file($tempname, $target_dir . $new_filename)) {
                if (!empty($current_data['audio_file']) && file_exists("../" . $current_data['audio_file'])) {
                    @unlink("../" . $current_data['audio_file']);
                }
                $db_audio_path = "audio/" . $new_filename;
            }
        }

        // 3. อัปเดตข้อมูลในฐานข้อมูล
        $sql  = "UPDATE AR_Media SET info_id=?, file_path=?, audio_file=?, media_type=? WHERE media_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssi", $info_id, $db_path, $db_audio_path, $media_type, $media_id);

        if ($stmt->execute()) {
            echo "<script>alert('แก้ไขข้อมูลสื่อ AR สำเร็จ'); window.location.href='manage_ar_media.php';</script>";
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        $error_msg = addslashes($e->getMessage());
        echo "<script>alert('เกิดข้อผิดพลาด:\\n{$error_msg}'); history.back();</script>";
    }
    exit();
}

// ----------------- DELETE AR MEDIA -----------------
if (isset($_GET['delete'])) {
    $id  = intval($_GET['delete']);

    if ($id <= 0) {
        echo "<script>alert('เกิดข้อผิดพลาด: ไม่พบ ID ของข้อมูลที่ต้องการลบ'); window.location.href='manage_ar_media.php';</script>";
        exit();
    }

    // ดึงชื่อไฟล์มาก่อน
    $res = $conn->query("SELECT file_path, audio_file FROM AR_Media WHERE media_id = $id");
    $data = $res ? $res->fetch_assoc() : null;

    // สั่งลบข้อมูลในฐานข้อมูล
    $sql = "DELETE FROM AR_Media WHERE media_id = $id";

    if ($conn->query($sql) === TRUE) {
        // ถ้าลบใน DB สำเร็จ ค่อยลบไฟล์ทิ้ง
        if ($data) {
            if (!empty($data['file_path']) && file_exists("../" . $data['file_path'])) {
                @unlink("../" . $data['file_path']);
            }
            if (!empty($data['audio_file']) && file_exists("../" . $data['audio_file'])) {
                @unlink("../" . $data['audio_file']);
            }
        }
        echo "<script>alert('ลบข้อมูลสื่อ AR สำเร็จ'); window.location.href='manage_ar_media.php';</script>";
    } else {
        $error_msg = addslashes($conn->error);
        echo "<script>alert('ไม่สามารถลบข้อมูลได้!\\nสาเหตุ: " . $error_msg . "'); window.location.href='manage_ar_media.php';</script>";
    }
    exit();
}

$result = $conn->query("SELECT * FROM AR_Media ORDER BY media_id DESC");
$adminNav = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Manage AR Media | AR Ganesha</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #c9a84c;
            --gold-lt: #e8c97a;
            --dark: #0e0e12;
            --panel: #16161e;
            --card: #1e1e2a;
            --border: rgba(201, 168, 76, .18);
            --txt: #e8e6f0;
            --muted: #7a7a96;
            --red: #e05a5a;
            --teal: #38c9a0;
            --blue: #4d9fff;
            --purple: #9b72cf;
            --sidebar-w: 260px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--txt);
            overflow-x: hidden
        }

        .sidebar {
            width: var(--sidebar-w);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--panel);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent)
        }

        .sidebar-logo {
            padding: 28px 24px 20px;
            border-bottom: 1px solid var(--border)
        }

        .sidebar-logo .logo-title {
            font-family: 'Cinzel', serif;
            font-size: 1.25rem;
            color: var(--gold);
            letter-spacing: .06em
        }

        .sidebar-logo .logo-sub {
            font-size: .7rem;
            color: var(--muted);
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-top: 4px
        }

        .sidebar-nav {
            flex: 1;
            padding: 18px 12px;
            overflow-y: auto
        }

        .nav-label {
            font-size: .62rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 6px 12px 8px;
            margin-top: 8px
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            color: var(--muted);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
            transition: all .2s;
            position: relative
        }

        .nav-link i {
            font-size: 1rem;
            min-width: 18px;
            text-align: center
        }

        .nav-link:hover {
            color: var(--txt);
            background: rgba(255, 255, 255, .05)
        }

        .nav-link.active {
            color: var(--gold);
            background: rgba(201, 168, 76, .1);
            border: 1px solid var(--border)
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            bottom: 20%;
            width: 3px;
            background: var(--gold);
            border-radius: 0 3px 3px 0
        }

        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid var(--border)
        }

        .nav-link.logout {
            color: #e05a5a80
        }

        .nav-link.logout:hover {
            color: var(--red);
            background: rgba(224, 90, 90, .08)
        }

        .main {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 36px 40px
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap
        }

        .page-title {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            color: var(--txt);
            letter-spacing: .04em
        }

        .page-title span {
            color: var(--gold)
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--card);
            border: 1px solid var(--border);
            padding: 8px 16px 8px 10px;
            border-radius: 40px;
            font-size: .84rem
        }

        .user-pill .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), #7a5a1a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            font-weight: 700;
            color: #000
        }

        .user-pill .name {
            color: var(--gold);
            font-weight: 600
        }

        .time-badge {
            background: var(--card);
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: 40px;
            font-size: .78rem;
            color: var(--muted)
        }

        .btn-add {
            background: linear-gradient(135deg, var(--gold), #a07830);
            color: #000;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: .85rem;
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 6px
        }

        .btn-add:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(201, 168, 76, .35)
        }

        .data-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden
        }

        .data-table {
            width: 100%;
            border-collapse: collapse
        }

        .data-table thead tr {
            background: rgba(201, 168, 76, .07);
            border-bottom: 1px solid var(--border)
        }

        .data-table th {
            font-size: .72rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 14px 18px;
            font-weight: 600
        }

        .data-table td {
            padding: 13px 18px;
            font-size: .87rem;
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            color: var(--txt);
            vertical-align: middle
        }

        .data-table tbody tr:hover {
            background: rgba(255, 255, 255, .03)
        }

        .model-placeholder {
            width: 72px;
            height: 48px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: rgba(77, 159, 255, .08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--blue);
            font-size: 1.4rem
        }

        .audio-placeholder {
            width: 72px;
            height: 48px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: rgba(56, 201, 160, .08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--teal);
            font-size: 1.4rem
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase
        }

        .badge-model {
            background: rgba(77, 159, 255, .15);
            color: var(--blue);
            border: 1px solid rgba(77, 159, 255, .3)
        }

        .badge-audio {
            background: rgba(56, 201, 160, .15);
            color: var(--teal);
            border: 1px solid rgba(56, 201, 160, .3)
        }

        .file-path-box {
            background: rgba(255, 255, 255, .05);
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: .75rem;
            color: var(--muted);
            border: 1px solid var(--border);
            display: block;
            margin-bottom: 4px;
            word-break: break-all;
        }

        .btn-action-group {
            display: flex;
            gap: 6px;
            justify-content: center;
        }

        .btn-edit {
            background: rgba(77, 159, 255, .12);
            color: var(--blue);
            border: 1px solid rgba(77, 159, 255, .25);
            padding: 5px 12px;
            border-radius: 6px;
            font-size: .78rem;
            cursor: pointer;
            text-decoration: none;
            transition: all .18s;
            display: inline-flex;
            align-items: center;
            gap: 4px
        }

        .btn-edit:hover {
            background: var(--blue);
            color: #fff
        }

        .btn-del {
            background: rgba(224, 90, 90, .12);
            color: var(--red);
            border: 1px solid rgba(224, 90, 90, .25);
            padding: 5px 12px;
            border-radius: 6px;
            font-size: .78rem;
            cursor: pointer;
            text-decoration: none;
            transition: all .18s;
            display: inline-flex;
            align-items: center;
            gap: 4px
        }

        .btn-del:hover {
            background: var(--red);
            color: #fff
        }

        .modal-content {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            color: var(--txt)
        }

        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 20px 24px
        }

        .modal-title {
            font-family: 'Cinzel', serif;
            font-size: 1.05rem;
            color: var(--gold)
        }

        .btn-close {
            filter: invert(1) brightness(.5)
        }

        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 16px 24px;
            background: rgba(0, 0, 0, .15)
        }

        .modal-body {
            padding: 24px
        }

        .form-label {
            font-size: .72rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
            display: block;
            font-weight: 600
        }

        .form-control,
        .form-select {
            background: rgba(255, 255, 255, .05);
            border: 1px solid var(--border);
            color: var(--txt);
            border-radius: 8px;
            padding: 9px 13px;
            font-size: .88rem;
            width: 100%;
            transition: border-color .18s;
            font-family: 'DM Sans', sans-serif
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--gold);
            background: rgba(255, 255, 255, .07);
            box-shadow: 0 0 0 3px rgba(201, 168, 76, .12);
            color: var(--txt)
        }

        .form-select option {
            background: var(--panel)
        }

        .btn-save {
            background: linear-gradient(135deg, var(--teal), #1e8a6c);
            color: #fff;
            border: none;
            padding: 9px 22px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 6px
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(56, 201, 160, .3)
        }

        .btn-cancel {
            background: rgba(255, 255, 255, .07);
            color: var(--muted);
            border: 1px solid var(--border);
            padding: 9px 22px;
            border-radius: 8px;
            cursor: pointer;
            transition: all .18s
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, .12);
            color: var(--txt)
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .anim {
            animation: fadeUp .4s ease both
        }

        .anim-1 {
            animation-delay: .05s
        }

        .anim-2 {
            animation-delay: .12s
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-title">AR Ganesha</div>
            <div class="logo-sub">Admin Console</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Main</div>
            <a href="dashboard.php" class="nav-link <?= $adminNav === 'dashboard.php' ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            <a href="manage_users.php" class="nav-link <?= $adminNav === 'manage_users.php' ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> Manage Users</a>
            <div class="nav-label">Content</div>
            <a href="manage_ganeshainfo.php" class="nav-link <?= $adminNav === 'manage_ganeshainfo.php' ? 'active' : '' ?>"><i class="bi bi-bank2"></i> Ganesha Info</a>
            <a href="manage_ar_media.php" class="nav-link <?= $adminNav === 'manage_ar_media.php' ? 'active' : '' ?>"><i class="bi bi-camera-fill"></i> AR Media</a>
            <a href="manage_restaurant.php" class="nav-link <?= $adminNav === 'manage_restaurant.php' ? 'active' : '' ?>"><i class="bi bi-shop"></i> Restaurants</a>
            <a href="manage_places.php" class="nav-link <?= $adminNav === 'manage_places.php' ? 'active' : '' ?>"><i class="bi bi-geo-alt-fill"></i> Places</a>
            <a href="manage_reviews.php" class="nav-link <?= $adminNav === 'manage_reviews.php' ? 'active' : '' ?>"><i class="bi bi-star-fill"></i> Reviews</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar anim anim-1">
            <div>
                <div class="page-title">AR <span>Media</span></div>
                <div style="color:var(--muted);font-size:.8rem;margin-top:4px;"><?= date('l, d F Y') ?></div>
            </div>
            <div class="topbar-actions">
                <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="bi bi-plus-lg"></i> เพิ่มสื่อใหม่
                </button>
                <div class="topbar-right">
                    <div class="time-badge"><i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem;"></i> Live</div>
                    <div class="user-pill">
                        <div class="avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                        <span class="name"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="data-card anim anim-2">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:90px">Preview</th>
                        <th style="width:120px">Type</th>
                        <th>Files Info</th>
                        <th style="text-align:center;width:160px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if ($row['media_type'] == 'model'): ?>
                                    <div class="model-placeholder"><i class="bi bi-box-seam"></i></div>
                                <?php else: ?>
                                    <div class="audio-placeholder"><i class="bi bi-music-note-beamed"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $cls = ($row['media_type'] == 'model') ? 'badge-model' : 'badge-audio';
                                $type_text = ($row['media_type'] == 'model') ? 'MODEL 3D' : 'AUDIO ONLY';
                                ?>
                                <span class="badge-pill <?= $cls ?>"><?= $type_text ?></span>
                            </td>
                            <td>
                                <?php if (!empty($row['file_path'])): ?>
                                    <span class="file-path-box"><i class="bi bi-box text-primary me-1"></i> <?= htmlspecialchars($row['file_path']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($row['audio_file'])): ?>
                                    <span class="file-path-box"><i class="bi bi-mic text-success me-1"></i> <?= htmlspecialchars($row['audio_file']) ?></span>
                                <?php endif; ?>
                                <div style="font-size:0.75rem; color:var(--muted); margin-top: 4px;">
                                    <strong>Info ID:</strong> <?= $row['info_id'] ?>
                                </div>
                            </td>
                            <td style="text-align:center">
                                <div class="btn-action-group">
                                    <button type="button" class="btn-edit"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal"
                                        data-id="<?= $row['media_id'] ?>"
                                        data-info="<?= $row['info_id'] ?>"
                                        data-type="<?= $row['media_type'] ?>">
                                        <i class="bi bi-pencil-square"></i> แก้ไข
                                    </button>

                                    <a href="?delete=<?= $row['media_id'] ?>" class="btn-del"
                                        onclick="return confirm('ต้องการลบไฟล์สื่อนี้ใช่หรือไม่? ไฟล์ที่เกี่ยวข้องจะถูกลบทิ้งทั้งหมด')">
                                        <i class="bi bi-trash"></i> ลบ
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cloud-upload-fill me-2"></i>เพิ่มสื่อ AR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label">ประเภทสื่อ *</label>
                        <select name="media_type" id="media_type" class="form-select" onchange="toggleUploadFields()" required>
                            <option value="model" selected>AR Model (.glb, .usdz)</option>
                            <option value="audio_only">Audio Only (ไฟล์เสียงอย่างเดียว)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เชื่อมโยงกับ Info ID *</label>
                        <input type="number" name="info_id" class="form-control" placeholder="ระบุ ID ขององค์พระพิฆเนศ" required>
                    </div>

                    <hr style="border-color: var(--border); margin: 20px 0;">

                    <div class="mb-3" id="model_upload_group">
                        <label class="form-label text-primary">เลือกไฟล์โมเดล 3D *</label>
                        <input type="file" name="file_upload" id="file_upload" class="form-control" accept=".glb,.usdz" required>
                    </div>

                    <div class="mb-3" id="audio_upload_group">
                        <label class="form-label text-success">
                            เลือกไฟล์เสียง <span id="audio_req_text" class="text-muted" style="font-size: 0.72rem; font-weight: normal; text-transform: none;">(เว้นว่างได้ถ้าไม่มี)</span>
                        </label>
                        <input type="file" name="audio_upload" id="audio_upload" class="form-control" accept="audio/mp3, audio/wav, audio/mpeg">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" name="add_media" class="btn-save">
                        <i class="bi bi-save"></i> บันทึกข้อมูลสื่อ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="media_id" id="edit_media_id">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>แก้ไขสื่อ AR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label">ประเภทสื่อ *</label>
                        <select name="media_type" id="edit_media_type" class="form-select" onchange="toggleEditFields()" required>
                            <option value="model">AR Model (.glb, .usdz)</option>
                            <option value="audio_only">Audio Only (ไฟล์เสียงอย่างเดียว)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เชื่อมโยงกับ Info ID *</label>
                        <input type="number" name="info_id" id="edit_info_id" class="form-control" placeholder="ระบุ ID ขององค์พระพิฆเนศ" required>
                    </div>

                    <div class="alert alert-warning" style="background: rgba(201, 168, 76, 0.1); border: 1px solid var(--border); color: var(--gold-lt); font-size: 0.8rem; padding: 10px; border-radius: 8px;">
                        <i class="bi bi-info-circle me-1"></i> หากไม่ต้องการเปลี่ยนไฟล์ ปล่อยช่องอัปโหลดว่างไว้ได้เลย
                    </div>

                    <div class="mb-3" id="edit_model_upload_group">
                        <label class="form-label text-primary">เปลี่ยนไฟล์โมเดล 3D ใหม่</label>
                        <input type="file" name="edit_file_upload" id="edit_file_upload" class="form-control" accept=".glb,.usdz">
                    </div>

                    <div class="mb-3" id="edit_audio_upload_group">
                        <label class="form-label text-success">เปลี่ยนไฟล์เสียงใหม่</label>
                        <input type="file" name="edit_audio_upload" id="edit_audio_upload" class="form-control" accept="audio/mp3, audio/wav, audio/mpeg">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" name="edit_media" class="btn-save">
                        <i class="bi bi-check-lg"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleUploadFields() {
            const mediaType = document.getElementById('media_type').value;
            const modelGroup = document.getElementById('model_upload_group');
            const fileUpload = document.getElementById('file_upload');
            const audioUpload = document.getElementById('audio_upload');
            const audioReqText = document.getElementById('audio_req_text');

            if (mediaType === 'audio_only') {
                modelGroup.style.display = 'none';
                fileUpload.removeAttribute('required');
                audioUpload.setAttribute('required', 'required');
                audioReqText.textContent = '* จำเป็นต้องใส่ไฟล์เสียง';
                audioReqText.className = 'text-danger';
            } else {
                modelGroup.style.display = 'block';
                fileUpload.setAttribute('required', 'required');
                audioUpload.removeAttribute('required');
                audioReqText.textContent = '(เว้นว่างได้ถ้าไม่มี)';
                audioReqText.className = 'text-muted';
            }
        }

        function toggleEditFields() {
            const mediaType = document.getElementById('edit_media_type').value;
            const modelGroup = document.getElementById('edit_model_upload_group');

            if (mediaType === 'audio_only') {
                modelGroup.style.display = 'none';
            } else {
                modelGroup.style.display = 'block';
            }
        }

        const editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const info = button.getAttribute('data-info');
                const type = button.getAttribute('data-type');

                document.getElementById('edit_media_id').value = id;
                document.getElementById('edit_info_id').value = info;
                document.getElementById('edit_media_type').value = type;

                toggleEditFields();
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleUploadFields();

            const maxFileSizeMB = 40;
            const maxFileSizeBytes = maxFileSizeMB * 1024 * 1024;

            function validateFileSize(event) {
                const fileInput = event.target;
                if (fileInput.files.length > 0) {
                    const fileSize = fileInput.files[0].size;

                    if (fileSize > maxFileSizeBytes) {
                        const actualSizeMB = (fileSize / (1024 * 1024)).toFixed(2);
                        alert(`⚠️ ไฟล์มีขนาดใหญ่เกินไป!\n\nขนาดไฟล์ของคุณ: ${actualSizeMB} MB\nจำกัดไม่เกิน: ${maxFileSizeMB} MB\n\nกรุณาเลือกไฟล์ใหม่ครับ`);
                        fileInput.value = '';
                    }
                }
            }

            const fileInputIds = ['file_upload', 'audio_upload', 'edit_file_upload', 'edit_audio_upload'];
            fileInputIds.forEach(id => {
                const inputElement = document.getElementById(id);
                if (inputElement) {
                    inputElement.addEventListener('change', validateFileSize);
                }
            });
        });
    </script>
</body>

</html>