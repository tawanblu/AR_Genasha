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

$place_categories = ["วัด", "คาเฟ่", "ร้านอาหาร", "ธรรมชาติ", "พิพิธภัณฑ์", "อื่นๆ"];

// ─── ฟังก์ชันช่วยเหลือสำหรับแสดง SweetAlert2 ───
function alertSuccess($msg, $redirect)
{
    echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><style>body{background:#0e0e12;}</style></head><body><script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({icon: 'success', title: 'สำเร็จ!', text: '$msg', confirmButtonColor: '#c9a84c'}).then(() => { window.location.href='$redirect'; });
        });
    </script></body></html>";
    exit();
}
function alertError($msg)
{
    echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><style>body{background:#0e0e12;}</style></head><body><script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({icon: 'error', title: 'ข้อผิดพลาด!', text: '$msg', confirmButtonColor: '#e05a5a'}).then(() => { history.back(); });
        });
    </script></body></html>";
    exit();
}

// ----------------- ADD DATA (MULTIPLE IMAGES) -----------------
if (isset($_POST['add_place'])) {
    $name       = $_POST['name'];
    $category   = isset($_POST['category']) ? implode(", ", $_POST['category']) : "";
    $detail     = $_POST['detail'];
    $map_url    = $_POST['map_url'];
    $phone      = $_POST['phone_number'];
    $open_time  = $_POST['open_time'];
    $close_time = $_POST['close_time'];

    $stmt = $conn->prepare("INSERT INTO nearby_place (name, category, detail, map_url, phone_number, open_time, close_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $name, $category, $detail, $map_url, $phone, $open_time, $close_time);

    if ($stmt->execute()) {
        $pid = $stmt->insert_id;

        // จัดการอัปโหลดหลายรูปภาพ พร้อมตรวจสอบนามสกุลไฟล์
        if (!empty($_FILES['place_imgs']['name'][0])) {
            $si = $conn->prepare("INSERT INTO nearby_place_image (place_id, file_path) VALUES (?, ?)");
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            foreach ($_FILES['place_imgs']['name'] as $key => $filename) {
                $tempname = $_FILES['place_imgs']['tmp_name'][$key];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                // ข้ามไฟล์ถ้านามสกุลไม่ถูกต้อง
                if (!in_array($ext, $allowed_ext)) continue;

                $new_filename = time() . "_" . rand(1000, 9999) . "_" . $key . "." . $ext;

                if (move_uploaded_file($tempname, "../Place/" . $new_filename)) {
                    $si->bind_param("is", $pid, $new_filename);
                    $si->execute();
                }
            }
        }
        alertSuccess('เพิ่มสถานที่และรูปภาพสำเร็จ', 'manage_places.php');
    } else {
        alertError('ไม่สามารถเพิ่มข้อมูลได้: ' . $conn->error);
    }
}

// ----------------- EDIT DATA (APPEND MULTIPLE IMAGES) -----------------
if (isset($_POST['edit_place'])) {
    $place_id   = intval($_POST['place_id']);
    $name       = $_POST['name'];
    $category   = isset($_POST['category']) ? implode(", ", $_POST['category']) : "";
    $detail     = $_POST['detail'];
    $map_url    = $_POST['map_url'];
    $phone      = $_POST['phone_number'];
    $open_time  = $_POST['open_time'];
    $close_time = $_POST['close_time'];

    $stmt = $conn->prepare("UPDATE nearby_place SET name=?, category=?, detail=?, map_url=?, phone_number=?, open_time=?, close_time=? WHERE place_id=?");
    $stmt->bind_param("sssssssi", $name, $category, $detail, $map_url, $phone, $open_time, $close_time, $place_id);

    if ($stmt->execute()) {
        if (!empty($_FILES['place_imgs']['name'][0])) {
            $si = $conn->prepare("INSERT INTO nearby_place_image (place_id, file_path) VALUES (?, ?)");
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            foreach ($_FILES['place_imgs']['name'] as $key => $filename) {
                $tempname = $_FILES['place_imgs']['tmp_name'][$key];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_ext)) continue;

                $new_filename = time() . "_" . rand(1000, 9999) . "_" . $key . "." . $ext;

                if (move_uploaded_file($tempname, "../Place/" . $new_filename)) {
                    $si->bind_param("is", $place_id, $new_filename);
                    $si->execute();
                }
            }
        }
        alertSuccess('แก้ไขข้อมูลสถานที่สำเร็จ', 'manage_places.php');
    } else {
        alertError('ไม่สามารถแก้ไขข้อมูลได้: ' . $conn->error);
    }
}

// ----------------- DELETE SINGLE IMAGE (AJAX-LIKE) -----------------
if (isset($_GET['del_img'])) {
    $img_id = intval($_GET['del_img']);
    $res = $conn->query("SELECT file_path FROM nearby_place_image WHERE image_id = $img_id");
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (file_exists("../Place/" . $row['file_path'])) {
            unlink("../Place/" . $row['file_path']);
        }
        $conn->query("DELETE FROM nearby_place_image WHERE image_id = $img_id");
    }
    alertSuccess('ลบรูปภาพออกแล้ว', 'manage_places.php');
}

// ----------------- DELETE DATA (ALL) -----------------
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT file_path FROM nearby_place_image WHERE place_id = $id");
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['file_path']) && file_exists("../Place/" . $row['file_path'])) {
            unlink("../Place/" . $row['file_path']);
        }
    }
    // Database cascade จะลบข้อมูลในตาราง nearby_place_image ให้อัตโนมัติ (ตามที่ตั้งค่าไว้ใน SQL)
    $conn->query("DELETE FROM nearby_place WHERE place_id = $id");
    alertSuccess('ลบสถานที่และข้อมูลทั้งหมดสำเร็จ', 'manage_places.php');
}

$result = $conn->query("
    SELECT p.*, (SELECT file_path FROM nearby_place_image WHERE place_id = p.place_id LIMIT 1) AS file_path
    FROM nearby_place p ORDER BY p.place_id DESC
");
$adminNav = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Manage Places | AR Ganesha</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --gold: #c9a84c;
            --gold-lt: #e8c97a;
            --dark: #0e0e12;
            --panel: #16161e;
            --card: #1e1e2a;
            --border: rgba(201, 168, 76, .18);
            --txt: #e8e6f0;
            --muted: #a8a4c0;
            --red: #e05a5a;
            --teal: #38c9a0;
            --purple: #9b72cf;
            --sidebar-w: 260px;
            --blue: #4d9fff;
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
            overflow: hidden;
            transition: transform .28s ease;
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

        .sidebar-footer .nav-link {
            color: var(--txt)
        }

        .sidebar-footer .nav-link:hover {
            color: var(--gold);
            background: rgba(201, 168, 76, .08)
        }

        .nav-link.logout {
            color: #e05a5a80
        }

        .nav-link.logout:hover {
            color: var(--red);
            background: rgba(224, 90, 90, .08)
        }

        /* Mobile Responsive */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 14px;
            left: 14px;
            z-index: 1100;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            cursor: pointer;
            font-size: 1.1rem
        }

        .sb-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            z-index: 999;
            backdrop-filter: blur(2px)
        }

        @media(max-width:768px) {
            .sidebar {
                transform: translateX(-100%)
            }

            .sidebar.open {
                transform: translateX(0)
            }

            .sb-overlay.open {
                display: block
            }

            .mobile-toggle {
                display: flex
            }

            .main {
                margin-left: 0 !important;
                padding: 24px 18px 50px !important;
            }

            .topbar {
                margin-top: 40px !important;
            }
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

        .img-thumb {
            width: 72px;
            height: 48px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border)
        }

        .type-badge {
            background: rgba(155, 114, 207, .15);
            color: var(--purple);
            border: 1px solid rgba(155, 114, 207, .3);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 4px;
            margin-right: 4px;
        }

        .time-txt {
            color: var(--muted);
            font-size: .82rem
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

        .form-control {
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

        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            background: rgba(255, 255, 255, .07);
            box-shadow: 0 0 0 3px rgba(201, 168, 76, .12);
            color: var(--txt)
        }

        .form-control::placeholder {
            color: var(--muted)
        }

        .checkbox-group {
            background: rgba(255, 255, 255, .03);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px
        }

        .form-check-input {
            background-color: rgba(255, 255, 255, .1);
            border-color: var(--border);
            cursor: pointer
        }

        .form-check-input:checked {
            background-color: var(--gold);
            border-color: var(--gold)
        }

        .form-check-label {
            color: var(--txt);
            font-size: .84rem;
            cursor: pointer
        }

        .btn-save,
        .btn-update {
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

        .btn-save:hover,
        .btn-update:hover {
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

        .img-preview-box {
            position: relative;
            display: inline-block;
            margin-right: 8px;
            margin-bottom: 8px;
        }

        .img-preview-box img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .img-preview-box .btn-remove {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--red);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            text-decoration: none;
        }

        .img-preview-box .btn-remove:hover {
            background: #ff7676;
            color: white;
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
    <button type="button" class="mobile-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="sb-overlay" id="sbOverlay"></div>

    <aside class="sidebar" id="sidebar">
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
            <a href="../index.php" class="nav-link"><i class="bi bi-house-door-fill"></i> ไปหน้า Index</a>
            <a href="logout.php" class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar anim anim-1">
            <div>
                <div class="page-title">Manage <span>Places</span></div>
                <div style="color:var(--muted);font-size:.8rem;margin-top:4px;"><?= date('l, d F Y') ?></div>
            </div>
            <div class="topbar-actions">
                <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg"></i> เพิ่มสถานที่
                </button>
                <div class="topbar-right">
                    <div class="time-badge"><i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem;"></i> Live</div>
                    <div class="user-pill">
                        <div class="avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
                        <span class="name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="data-card anim anim-2">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:90px">รูป</th>
                        <th>ชื่อ</th>
                        <th>หมวดหมู่</th>
                        <th>เวลา</th>
                        <th style="text-align:center;width:170px">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $modals = ''; // ตัวแปรเก็บ HTML ของ Edit Modals ทั้งหมด
                    while ($row = $result->fetch_assoc()):
                        $pid = $row['place_id'];

                        // หั่นคำ category มาทำ badge
                        $pcat_raw = $row['category'] ?? '';
                        $pcats = $pcat_raw ? explode(", ", $pcat_raw) : ['ไม่ระบุ'];
                        $cat_html = '';
                        foreach ($pcats as $c) {
                            if (trim($c) != '') {
                                $cat_html .= '<span class="type-badge">' . htmlspecialchars(trim($c)) . '</span>';
                            }
                        }
                    ?>
                        <tr>
                            <td>
                                <img src="../Place/<?= htmlspecialchars($row['file_path'] ?? 'default.jpg') ?>"
                                    class="img-thumb" onerror="this.src='../Place/default.jpg'">
                            </td>
                            <td style="font-weight:600"><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= $cat_html ?></td>
                            <td class="time-txt"><?= substr($row['open_time'], 0, 5) ?> – <?= substr($row['close_time'], 0, 5) ?></td>
                            <td style="text-align:center; display: flex; gap: 8px; justify-content: center; border-bottom:none;">
                                <button type="button" class="btn-edit" data-bs-toggle="modal" data-bs-target="#editModal<?= $pid ?>">
                                    <i class="bi bi-pencil-square"></i> แก้ไข
                                </button>
                                <button class="btn-del" onclick="confirmDelete(<?= $pid ?>)">
                                    <i class="bi bi-trash"></i> ลบ
                                </button>
                            </td>
                        </tr>

                        <?php
                        // ------------------------------------------------------------------
                        // สร้างโครงสร้าง Modal แก้ไข
                        // ------------------------------------------------------------------
                        $current_cats = array_map('trim', explode(", ", $row['category'] ?? ''));
                        $checkboxes_html = '';
                        foreach ($place_categories as $i => $t) {
                            $checked = in_array($t, $current_cats) ? 'checked' : '';
                            $checkboxes_html .= '<div class="form-check form-check-inline">';
                            $checkboxes_html .= '<input class="form-check-input" type="checkbox" name="category[]" value="' . $t . '" id="edit_cat_' . $pid . '_' . $i . '" ' . $checked . '>';
                            $checkboxes_html .= '<label class="form-check-label" for="edit_cat_' . $pid . '_' . $i . '">' . $t . '</label>';
                            $checkboxes_html .= '</div>';
                        }

                        // ดึงรูปภาพทั้งหมดของสถานที่นี้มาแสดงใน Modal Edit
                        $img_res = $conn->query("SELECT * FROM nearby_place_image WHERE place_id = $pid");
                        $images_html = '<div class="mb-2 d-flex flex-wrap gap-2">';
                        $has_img = false;
                        if ($img_res->num_rows > 0) {
                            while ($img_row = $img_res->fetch_assoc()) {
                                $images_html .= '
                                <div class="img-preview-box">
                                    <img src="../Place/' . htmlspecialchars($img_row['file_path']) . '" onerror="this.src=\'../Place/default.jpg\'">
                                    <a href="javascript:void(0);" onclick="confirmDeleteImage(' . $img_row['image_id'] . ')" class="btn-remove" title="ลบรูปนี้"><i class="bi bi-x"></i></a>
                                </div>';
                                $has_img = true;
                            }
                        }
                        if (!$has_img) {
                            $images_html .= '<span style="color:var(--muted); font-size:0.8rem;">ยังไม่มีรูปภาพ</span>';
                        }
                        $images_html .= '</div>';

                        $modals .= '
                        <div class="modal fade" id="editModal' . $pid . '" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <form method="POST" enctype="multipart/form-data" class="modal-content">
                                    <input type="hidden" name="place_id" value="' . $pid . '">

                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2" style="color:var(--blue)"></i>แก้ไขสถานที่</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-start">
                                        <div class="mb-3">
                                            <label class="form-label">ชื่อสถานที่ *</label>
                                            <input type="text" name="name" class="form-control" value="' . htmlspecialchars($row['name']) . '" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">หมวดหมู่ (เลือกได้มากกว่า 1)</label>
                                            <div class="checkbox-group">' . $checkboxes_html . '</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">เบอร์ติดต่อ</label>
                                            <input type="text" name="phone_number" class="form-control" placeholder="08x-xxx-xxxx" value="' . htmlspecialchars($row['phone_number'] ?? '') . '">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">รายละเอียด</label>
                                            <textarea name="detail" class="form-control" rows="3">' . htmlspecialchars($row['detail'] ?? '') . '</textarea>
                                        </div>
                                        <div class="row g-2 mb-3">
                                            <div class="col">
                                                <label class="form-label">เวลาเปิด *</label>
                                                <input type="time" name="open_time" class="form-control" value="' . $row['open_time'] . '" required>
                                            </div>
                                            <div class="col">
                                                <label class="form-label">เวลาปิด *</label>
                                                <input type="time" name="close_time" class="form-control" value="' . $row['close_time'] . '" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">ลิงก์ Google Maps *</label>
                                            <input type="url" name="map_url" class="form-control" placeholder="https://maps.app.goo.gl/..." value="' . htmlspecialchars($row['map_url'] ?? '') . '" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-warning">รูปภาพปัจจุบัน (กดกากบาทสีแดงเพื่อลบ)</label>
                                            ' . $images_html . '
                                            
                                            <label class="form-label mt-2">เพิ่มรูปภาพใหม่ (เลือกได้หลายรูป)</label>
                                            <input type="file" name="place_imgs[]" id="edit_place_imgs_' . $pid . '" class="form-control" accept="image/png, image/jpeg, image/jpg, image/webp, image/gif" multiple onchange="previewImages(this, \'preview_edit_' . $pid . '\')">
                                            <div id="preview_edit_' . $pid . '" class="mt-2 d-flex flex-wrap gap-2"></div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">ยกเลิก</button>
                                        <button type="submit" name="edit_place" class="btn-update btn-submit-action"><i class="bi bi-save"></i> บันทึกการแก้ไข</button>
                                    </div>
                                </form>
                            </div>
                        </div>';
                        ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" enctype="multipart/form-data" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-geo-alt-fill me-2"></i>เพิ่มสถานที่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label">ชื่อสถานที่ *</label>
                        <input type="text" name="name" class="form-control" placeholder="ชื่อสถานที่" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมวดหมู่ (เลือกได้มากกว่า 1)</label>
                        <div class="checkbox-group">
                            <?php foreach ($place_categories as $i => $t): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="category[]" value="<?= $t ?>" id="add_cat_<?= $i ?>">
                                    <label class="form-check-label" for="add_cat_<?= $i ?>"><?= $t ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">เบอร์ติดต่อ</label>
                        <input type="text" name="phone_number" class="form-control" placeholder="08x-xxx-xxxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รายละเอียด</label>
                        <textarea name="detail" class="form-control" rows="3" placeholder="รายละเอียดสถานที่"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label">เวลาเปิด *</label>
                            <input type="time" name="open_time" class="form-control" required>
                        </div>
                        <div class="col">
                            <label class="form-label">เวลาปิด *</label>
                            <input type="time" name="close_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ลิงก์ Google Maps *</label>
                        <input type="url" name="map_url" class="form-control" placeholder="https://maps.app.goo.gl/..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รูปภาพ (เลือกได้หลายรูป) *</label>
                        <input type="file" name="place_imgs[]" id="add_place_imgs" class="form-control" accept="image/png, image/jpeg, image/jpg, image/webp, image/gif" multiple required onchange="previewImages(this, 'preview_add')">
                        <div id="preview_add" class="mt-2 d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="add_place" class="btn-save btn-submit-action"><i class="bi bi-save"></i> บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <?= $modals ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ─── ควบคุมการเปิดปิด Sidebar มือถือ ───
        const toggle = document.getElementById('sidebarToggle'),
            sidebar = document.getElementById('sidebar'),
            overlay = document.getElementById('sbOverlay');

        if (toggle && sidebar && overlay) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open')
            });
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('open')
            });
        }

        // ─── การพรีวิวรูปภาพใหม่แบบ AJAX-like (ที่เขียนไว้เดิม) ───
        function previewImages(input, containerId) {
            const previewContainer = document.getElementById(containerId);
            previewContainer.innerHTML = '';

            if (input.files) {
                Array.from(input.files).forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const box = document.createElement('div');
                        box.className = 'img-preview-box position-relative';
                        box.id = `new-file-${containerId}-${index}`;
                        box.innerHTML = `
                            <img src="${e.target.result}">
                            <span class="badge bg-primary position-absolute top-0 start-0 m-1" style="font-size:0.5rem">New</span>
                            <button type="button" class="btn-remove" onclick="removePreviewFile('${input.id}', '${containerId}', ${index})"><i class="bi bi-x"></i></button>
                        `;
                        previewContainer.appendChild(box);
                    }
                    reader.readAsDataURL(file);
                });
            }
        }

        function removePreviewFile(inputId, containerId, indexToRemove) {
            const input = document.getElementById(inputId);
            if (!input) return;

            const dt = new DataTransfer();
            const files = input.files;

            for (let i = 0; i < files.length; i++) {
                if (i !== indexToRemove) {
                    dt.items.add(files[i]);
                }
            }

            input.files = dt.files;
            previewImages(input, containerId);
        }

        // ─── การดักจับขนาดไฟล์ & Submit Spinner ───
        document.addEventListener('DOMContentLoaded', () => {
            const maxFileSizeMB = 5;
            const maxFileSizeBytes = maxFileSizeMB * 1024 * 1024;

            // ตรวจสอบขนาดของแต่ละไฟล์ที่อัปโหลดเข้ามาแบบ Multiple
            function validateMultipleImagesSize(event) {
                const fileInput = event.target;
                let hasOversizedFile = false;

                if (fileInput.files.length > 0) {
                    for (let i = 0; i < fileInput.files.length; i++) {
                        if (fileInput.files[i].size > maxFileSizeBytes) {
                            hasOversizedFile = true;
                            break;
                        }
                    }

                    if (hasOversizedFile) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'ขนาดไฟล์เกินกำหนด!',
                            text: `มีรูปภาพบางรูปที่ขนาดเกิน ${maxFileSizeMB} MB กรุณาเลือกไฟล์ใหม่ครับ`,
                            confirmButtonColor: '#e8c97a'
                        });
                        fileInput.value = ''; // เคลียร์ไฟล์ทั้งหมดทิ้ง

                        // เคลียร์พรีวิวออกด้วย
                        const containerId = fileInput.id === 'add_place_imgs' ? 'preview_add' : fileInput.id.replace('edit_place_imgs_', 'preview_edit_');
                        const previewContainer = document.getElementById(containerId);
                        if (previewContainer) previewContainer.innerHTML = '';
                    }
                }
            }

            document.querySelectorAll('input[type="file"][multiple]').forEach(input => {
                input.addEventListener('change', validateMultipleImagesSize);
            });

            // หมุน Spinner ตอนกดเซฟ
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    const btnSave = this.querySelector('.btn-submit-action');
                    if (btnSave) {
                        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> กำลังบันทึก...';
                        btnSave.style.pointerEvents = 'none';
                    }
                });
            });
        });

        // ─── ยืนยันการลบข้อมูล (สถานที่และรูปย่อย) ด้วย SweetAlert2 ───
        function confirmDelete(id) {
            Swal.fire({
                title: 'ยืนยันการลบสถานที่?',
                text: "ข้อมูลสถานที่และรูปภาพทั้งหมดจะถูกลบทิ้งอย่างถาวร!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e05a5a',
                cancelButtonColor: '#474761',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก',
                background: '#1e1e2a',
                color: '#e8e6f0'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?delete=${id}`;
                }
            });
        }

        function confirmDeleteImage(img_id) {
            Swal.fire({
                title: 'ลบรูปภาพนี้?',
                text: "รูปภาพจะถูกลบออกจากระบบทันที",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#e05a5a',
                cancelButtonColor: '#474761',
                confirmButtonText: 'ลบรูปภาพ',
                cancelButtonText: 'ยกเลิก',
                background: '#1e1e2a',
                color: '#e8e6f0'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?del_img=${img_id}`;
                }
            });
        }
    </script>
</body>

</html>