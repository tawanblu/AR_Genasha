<?php
session_start();
require_once("../connect.php");
/** @var mysqli $conn */


// ตั้งค่าโซนเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');
$current_time = date('H:i:s');

if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการระบบ!'); window.location.href='../index.php';</script>";
    exit();
}

// กำหนดหมวดหมู่สำหรับทำปุ่ม Filter
$category_options = ['อาหารตามสั่ง', 'ก๋วยเตี๋ยว', 'คาเฟ่ / ของหวาน', 'ปิ้งย่าง / ชาบู', 'อาหารอีสาน', 'อาหารฟาสต์ฟู้ด', 'อื่นๆ'];

// ADD
if (isset($_POST['add_restaurant'])) {
    $name = $_POST['name'];

    // รับค่าหมวดหมู่ (ถ้าเลือกหลายอัน จะมาเป็น Array นำมาต่อกันด้วยลูกน้ำ)
    $category = isset($_POST['category']) ? implode(", ", $_POST['category']) : '';

    $detail = $_POST['detail'];
    $map_url = $_POST['map_url'];
    $open_time = $_POST['open_time'];
    $close_time = $_POST['close_time'];

    $stmt = $conn->prepare("INSERT INTO restaurant (restaurant_name,category,detail,map_url,open_time,close_time) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssssss", $name, $category, $detail, $map_url, $open_time, $close_time);

    if ($stmt->execute()) {
        $rid = $stmt->insert_id;
        if (!empty($_FILES['res_imgs']['name'][0])) {
            $si = $conn->prepare("INSERT INTO restaurant_image (restaurant_id,file_path) VALUES (?,?)");
            foreach ($_FILES['res_imgs']['name'] as $key => $filename) {
                $tempname = $_FILES['res_imgs']['tmp_name'][$key];
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $new_filename = time() . "_" . rand(1000, 9999) . "_" . $key . "." . $ext;
                if (move_uploaded_file($tempname, "../Restaurants/" . $new_filename)) {
                    $si->bind_param("is", $rid, $new_filename);
                    $si->execute();
                }
            }
        }
    }
    header("Location: manage_restaurant.php?ok=add");
    exit();
}

// EDIT
if (isset($_POST['edit_restaurant'])) {
    $rid = intval($_POST['restaurant_id']);
    $name = $_POST['name'];

    // รับค่าหมวดหมู่แบบ Array มาแปลงเป็น String
    $category = isset($_POST['category']) ? implode(", ", $_POST['category']) : '';

    $detail = $_POST['detail'];
    $map_url = $_POST['map_url'];
    $open_time = $_POST['open_time'];
    $close_time = $_POST['close_time'];

    $stmt = $conn->prepare("UPDATE Restaurant SET restaurant_name=?,category=?,detail=?,map_url=?,open_time=?,close_time=? WHERE restaurant_id=?");
    $stmt->bind_param("ssssssi", $name, $category, $detail, $map_url, $open_time, $close_time, $rid);
    $stmt->execute();

    if (!empty($_FILES['res_imgs']['name'][0])) {
        $si = $conn->prepare("INSERT INTO restaurant_image (restaurant_id,file_path) VALUES (?,?)");
        foreach ($_FILES['res_imgs']['name'] as $key => $filename) {
            $tempname = $_FILES['res_imgs']['tmp_name'][$key];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $new_filename = time() . "_" . rand(1000, 9999) . "_" . $key . "." . $ext;
            if (move_uploaded_file($tempname, "../Restaurants/" . $new_filename)) {
                $si->bind_param("is", $rid, $new_filename);
                $si->execute();
            }
        }
    }
    header("Location: manage_restaurant.php?ok=edit");
    exit();
}

// DELETE IMAGE
if (isset($_GET['del_img'])) {
    $iid = intval($_GET['del_img']);
    $r = $conn->query("SELECT file_path FROM restaurant_image WHERE image_id=$iid");
    if ($row = $r->fetch_assoc()) {
        $f = "../Restaurants/" . $row['file_path'];
        if (file_exists($f)) unlink($f);
    }
    $conn->query("DELETE FROM restaurant_image WHERE image_id=$iid");
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// DELETE RESTAURANT
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT file_path FROM restaurant_image WHERE restaurant_id=$id");
    while ($row = $res->fetch_assoc()) {
        if (file_exists("../Restaurants/" . $row['file_path'])) unlink("../Restaurants/" . $row['file_path']);
    }
    $conn->query("DELETE FROM Restaurant WHERE restaurant_id=$id");
    header("Location: manage_restaurant.php?ok=del");
    exit();
}

$result = $conn->query("SELECT r.*,(SELECT file_path FROM restaurant_image WHERE restaurant_id=r.restaurant_id LIMIT 1) AS file_path FROM Restaurant r ORDER BY r.restaurant_id DESC");
$total = $result->num_rows;
$adminNav = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ร้านอาหาร — AR Ganesha Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #c9a84c;
            --gold-lt: #e8c97a;
            --gold-dim: rgba(201, 168, 76, .12);
            --gold-glow: rgba(201, 168, 76, .22);
            --dark: #0e0e12;
            --panel: #16161e;
            --card: #1e1e2a;
            --border: rgba(201, 168, 76, .18);
            --border-dim: rgba(255, 255, 255, .06);
            --txt: #e8e6f0;
            --muted: #7a7a96;
            --txt-2: #a8a4c0;
            --txt-3: #7a7a96;
            --red: #e05a5a;
            --red-dim: rgba(224, 90, 90, .12);
            --teal: #38c9a0;
            --blue: #4d9fff;
            --blue-dim: rgba(77, 159, 255, .12);
            --purple: #9b72cf;
            --surface-1: var(--panel);
            --surface-2: var(--card);
            --surface-3: #252532;
            --sidebar-w: 260px;
            --radius: 14px;
            --radius-sm: 8px;
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
            transition: transform .28s cubic-bezier(.4, 0, .2, 1)
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
            letter-spacing: .06em;
            line-height: 1
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

        .mobile-toggle {
            display: none;
            position: fixed;
            top: 14px;
            left: 14px;
            z-index: 1100;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
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

        .main {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 36px 40px 60px;
            position: relative;
            z-index: 1
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            animation: fadeUp .4s ease both
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
            font-weight: 600;
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
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, var(--gold), #a07830);
            color: #000;
            font-weight: 700;
            font-size: .82rem;
            padding: 9px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all .2s;
            box-shadow: 0 6px 20px rgba(201, 168, 76, .25)
        }

        .btn-add:hover {
            background: linear-gradient(135deg, var(--gold-lt), var(--gold));
            color: #000;
            transform: translateY(-1px)
        }

        .data-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            animation: fadeUp .4s .10s ease both
        }

        .data-table {
            width: 100%;
            border-collapse: collapse
        }

        .data-table thead th {
            background: rgba(201, 168, 76, .07);
            font-size: .72rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 600;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap
        }

        .data-table tbody td {
            padding: 14px 20px;
            font-size: .87rem;
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            color: var(--txt);
            transition: background .12s;
            vertical-align: middle
        }

        .data-table tbody tr:last-child td {
            border-bottom: none
        }

        .data-table tbody tr:hover td {
            background: rgba(255, 255, 255, .025)
        }

        .img-thumb {
            width: 70px;
            height: 52px;
            object-fit: cover;
            border-radius: 7px;
            border: 1px solid var(--border-dim)
        }

        .time-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--surface-3);
            border: 1px solid var(--border-dim);
            color: var(--txt-3);
            font-size: .72rem;
            padding: 3px 9px;
            border-radius: 6px
        }

        .cat-chip {
            display: inline-flex;
            align-items: center;
            background: var(--gold-dim);
            border: 1px solid var(--border);
            color: var(--gold);
            font-size: .72rem;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .btn-edit {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--blue-dim);
            color: var(--blue);
            border: 1px solid rgba(77, 159, 255, .25);
            border-radius: 6px;
            padding: 5px 12px;
            font-size: .75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .14s;
            text-decoration: none
        }

        .btn-edit:hover {
            background: rgba(77, 159, 255, .25);
            color: var(--blue)
        }

        .btn-del {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--red-dim);
            color: var(--red);
            border: 1px solid rgba(224, 90, 90, .25);
            border-radius: 6px;
            padding: 5px 12px;
            font-size: .75rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .14s
        }

        .btn-del:hover {
            background: rgba(224, 90, 90, .22);
            color: var(--red)
        }

        .modal-content {
            background: var(--surface-2) !important;
            border: 1px solid var(--border) !important;
            border-radius: var(--radius) !important;
            color: var(--txt) !important
        }

        .modal-header {
            border-bottom: 1px solid var(--border-dim) !important;
            padding: 18px 22px !important
        }

        .modal-title {
            font-family: 'Cinzel', serif;
            font-size: 1rem;
            color: var(--gold)
        }

        .modal-body {
            padding: 20px 22px !important
        }

        .modal-footer {
            border-top: 1px solid var(--border-dim) !important;
            padding: 14px 22px !important;
            gap: 8px
        }

        .btn-close {
            filter: invert(1) !important;
            opacity: .5 !important
        }

        .btn-close:hover {
            opacity: 1 !important
        }

        .form-label {
            font-size: .78rem;
            color: var(--txt-2);
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: .04em
        }

        .form-control,
        .form-select {
            background: var(--surface-3) !important;
            border: 1px solid var(--border-dim) !important;
            color: var(--txt) !important;
            border-radius: var(--radius-sm) !important;
            font-size: .84rem !important;
            padding: 9px 12px !important;
            transition: border-color .16s !important;
            width: 100%
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--gold) !important;
            box-shadow: 0 0 0 3px var(--gold-dim) !important;
            background: var(--surface-3) !important;
            color: var(--txt) !important;
            outline: none !important
        }

        .img-preview-box {
            position: relative;
            display: inline-block
        }

        .img-preview-box img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-dim)
        }

        .img-preview-box .btn-remove {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--red);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none
        }

        .section-label {
            font-size: .7rem;
            color: var(--txt-3);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .1em;
            margin-bottom: 8px
        }

        .modal-btn-save {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, var(--gold), #b8932a);
            color: #000;
            font-weight: 700;
            font-size: .82rem;
            padding: 8px 18px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            box-shadow: 0 3px 12px var(--gold-glow);
            transition: all .16s
        }

        .modal-btn-save:hover {
            background: linear-gradient(135deg, var(--gold-lt), var(--gold));
            color: #000
        }

        .modal-btn-cancel {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, .05);
            color: var(--txt-3);
            border: 1px solid var(--border-dim);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: .82rem;
            transition: all .16s
        }

        .modal-btn-cancel:hover {
            background: rgba(255, 255, 255, .09);
            color: var(--txt)
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--txt-3)
        }

        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 12px;
            opacity: .3
        }

        .toast-container-custom {
            position: fixed;
            top: 20px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px
        }

        .toast-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--surface-2);
            border: 1px solid var(--border-dim);
            border-radius: var(--radius);
            padding: 13px 18px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .5);
            font-size: .83rem;
            color: var(--txt);
            animation: slideIn .3s ease both;
            min-width: 260px
        }

        .toast-custom.success {
            border-left: 3px solid var(--teal)
        }

        .toast-custom.success i {
            color: var(--teal)
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(16px)
            }

            to {
                opacity: 1;
                transform: translateX(0)
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(14px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
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
                margin-left: 0;
                padding: 24px 18px 50px
            }

            .topbar {
                margin-top: 50px
            }
        }
    </style>
</head>

<body>

    <?php if (isset($_GET['ok'])): $msgs = ['add' => 'เพิ่มร้านอาหารสำเร็จ', 'edit' => 'แก้ไขข้อมูลสำเร็จ', 'del' => 'ลบร้านอาหารแล้ว'];
        $m = $msgs[$_GET['ok']] ?? 'ดำเนินการสำเร็จ'; ?>
        <div class="toast-container-custom">
            <div class="toast-custom success"><i class="bi bi-check-circle-fill"></i><span><?= $m ?></span></div>
        </div>
        <script>
            setTimeout(() => {
                document.querySelector('.toast-custom')?.remove()
            }, 3500)
        </script>
    <?php endif; ?>

    <button type="button" class="mobile-toggle" id="sidebarToggle" aria-label="เมนู"><i class="bi bi-list"></i></button>
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
            <a href="logout.php" class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <div class="page-title">Manage <span>Restaurants</span></div>
                <div class="breadcrumb-bar"></div>
                <div style="color:var(--muted);font-size:.8rem;margin-top:6px;"><?= date('l, d F Y') ?></div>
            </div>
            <div class="topbar-actions">
                <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-circle-fill"></i> เพิ่มร้านอาหาร
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

        <div class="data-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>รูปภาพ</th>
                        <th>ชื่อร้าน</th>
                        <th>หมวดหมู่</th>
                        <th>รายละเอียด</th>
                        <th>เวลาเปิด–ปิด</th>
                        <th style="text-align:center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $modals = '';
                    if ($total > 0): while ($row = $result->fetch_assoc()):
                            $rid = $row['restaurant_id'];
                            $rname = htmlspecialchars($row['restaurant_name']);

                            // ดึงหมวดหมู่มาทำ Badge หลายอัน
                            $rcat_raw = $row['category'] ?? '';
                            $rcats = $rcat_raw ? explode(", ", $rcat_raw) : ['ไม่มีหมวดหมู่'];
                            $cat_html = '';
                            foreach ($rcats as $c) {
                                $cat_html .= '<span class="cat-chip me-1">' . htmlspecialchars(trim($c)) . '</span> ';
                            }

                            $img_res = $conn->query("SELECT * FROM restaurant_image WHERE restaurant_id=$rid");
                            $images_html = '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px">';
                            while ($img_row = $img_res->fetch_assoc()) {
                                $images_html .= '<div class="img-preview-box"><img src="../Restaurants/' . htmlspecialchars($img_row['file_path']) . '" onerror="this.src=\'../Restaurants/default.jpg\'"><a href="?del_img=' . $img_row['image_id'] . '" class="btn-remove" onclick="return confirm(\'ลบรูปภาพนี้?\')"><i class="bi bi-x"></i></a></div>';
                            }
                            $images_html .= '</div>';

                            // สร้าง Checkbox สำหรับหมวดหมู่ใน Modal แก้ไข
                            $current_cats = array_map('trim', explode(",", $row['category'] ?? ''));
                            $edit_cat_options = '<div class="d-flex flex-wrap gap-3 p-2 rounded" style="background: var(--surface-3); border: 1px solid var(--border-dim);">';
                            foreach ($category_options as $index => $cat) {
                                $checked = in_array($cat, $current_cats) ? 'checked' : '';
                                $edit_cat_options .= '
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="category[]" value="' . $cat . '" id="edit_cat_' . $rid . '_' . $index . '" ' . $checked . '>
                                    <label class="form-check-label text-light" for="edit_cat_' . $rid . '_' . $index . '">' . $cat . '</label>
                                </div>';
                            }
                            $edit_cat_options .= '</div>';
                    ?>
                            <tr>
                                <td>
                                    <img src="../Restaurants/<?= htmlspecialchars($row['file_path'] ?? 'default.jpg') ?>"
                                        class="img-thumb" onerror="this.src='../Restaurants/default.jpg'">
                                </td>
                                <td style="font-weight:600;color:var(--txt)"><?= $rname ?></td>
                                <td><?= $cat_html ?></td>
                                <td style="font-size:.78rem;color:var(--txt-3);max-width:200px"><?= mb_substr(htmlspecialchars($row['detail'] ?? ''), 0, 80, 'UTF-8') ?>...</td>
                                <td>
                                    <span class="time-chip"><i class="bi bi-clock me-1"></i><?= substr($row['open_time'], 0, 5) ?> – <?= substr($row['close_time'], 0, 5) ?></span>
                                </td>
                                <td style="text-align:center">
                                    <div style="display:flex;gap:6px;justify-content:center">
                                        <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#editModal<?= $rid ?>"><i class="bi bi-pencil-square"></i> แก้ไข</button>
                                        <a href="?delete=<?= $rid ?>" class="btn-del" onclick="return confirm('ลบร้าน &quot;<?= $rname ?>&quot;?')"><i class="bi bi-trash"></i> ลบ</a>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            $modals .= '
                    <div class="modal fade" id="editModal' . $rid . '" tabindex="-1">
                      <div class="modal-dialog modal-dialog-centered">
                        <form class="modal-content" method="POST" enctype="multipart/form-data">
                          <input type="hidden" name="restaurant_id" value="' . $rid . '">
                          <div class="modal-header"><h5 class="modal-title"><i class="bi bi-shop me-2"></i>แก้ไขร้านอาหาร</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                          <div class="modal-body">
                            <div class="mb-3"><label class="form-label">ชื่อร้าน <span style="color:var(--red)">*</span></label><input type="text" name="name" class="form-control" value="' . htmlspecialchars($row['restaurant_name']) . '" required></div>
                            <div class="mb-3">
                                <label class="form-label">หมวดหมู่ (เลือกได้มากกว่า 1) <span style="color:var(--red)">*</span></label>
                                ' . $edit_cat_options . '
                            </div>
                            <div class="mb-3"><label class="form-label">รายละเอียด</label><textarea name="detail" class="form-control" rows="3">' . htmlspecialchars($row['detail'] ?? '') . '</textarea></div>
                            <div class="row g-2 mb-3"><div class="col"><label class="form-label">เปิด</label><input type="time" name="open_time" class="form-control" value="' . $row['open_time'] . '" required></div><div class="col"><label class="form-label">ปิด</label><input type="time" name="close_time" class="form-control" value="' . $row['close_time'] . '" required></div></div>
                            <div class="mb-3">
                                <label class="form-label">ลิงก์ Google Maps <span style="color:var(--red)">*</span></label>
                                <input type="url" name="map_url" class="form-control" placeholder="https://maps.app.goo.gl/..." value="' . htmlspecialchars($row['map_url'] ?? '') . '" required>
                            </div>
                            <div class="mb-3">
                              <div class="section-label">รูปปัจจุบัน <small style="color:var(--txt-3);text-transform:none;font-weight:400">(กด × เพื่อลบ)</small></div>
                              ' . $images_html . '
                              <label class="form-label">เพิ่มรูปใหม่</label>
                              <input type="file" name="res_imgs[]" class="form-control" accept="image/*" multiple onchange="previewImages(this,\'preview_edit_' . $rid . '\')">
                              <div id="preview_edit_' . $rid . '" class="mt-2 d-flex flex-wrap gap-2"></div>
                            </div>
                          </div>
                          <div class="modal-footer"><button type="button" class="modal-btn-cancel" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" name="edit_restaurant" class="modal-btn-save"><i class="bi bi-save2"></i> บันทึก</button></div>
                        </form>
                      </div>
                    </div>';
                        endwhile;
                    else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state"><i class="bi bi-shop"></i>
                                    <p>ยังไม่มีร้านอาหาร</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i>เพิ่มร้านอาหาร</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">ชื่อร้าน <span style="color:var(--red)">*</span></label><input type="text" name="name" class="form-control" placeholder="ชื่อร้าน..." required></div>

                    <div class="mb-3">
                        <label class="form-label">หมวดหมู่ (เลือกได้มากกว่า 1) <span style="color:var(--red)">*</span></label>
                        <div class="d-flex flex-wrap gap-3 p-2 rounded" style="background: var(--surface-3); border: 1px solid var(--border-dim);">
                            <?php foreach ($category_options as $index => $cat): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="category[]" value="<?= $cat ?>" id="add_cat_<?= $index ?>">
                                    <label class="form-check-label text-light" for="add_cat_<?= $index ?>"><?= $cat ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3"><label class="form-label">รายละเอียด</label><textarea name="detail" class="form-control" rows="3" placeholder="รายละเอียดร้าน..."></textarea></div>
                    <div class="row g-2 mb-3">
                        <div class="col"><label class="form-label">เปิด</label><input type="time" name="open_time" class="form-control" required></div>
                        <div class="col"><label class="form-label">ปิด</label><input type="time" name="close_time" class="form-control" required></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ลิงก์ Google Maps <span style="color:var(--red)">*</span></label>
                        <input type="url" name="map_url" class="form-control" placeholder="https://maps.app.goo.gl/..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รูปภาพ (เลือกได้หลายรูป) <span style="color:var(--red)">*</span></label>
                        <input type="file" name="res_imgs[]" class="form-control" accept="image/*" multiple required onchange="previewImages(this,'preview_add')">
                        <div id="preview_add" class="mt-2 d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn-cancel" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="add_restaurant" class="modal-btn-save"><i class="bi bi-save2"></i> บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <?= $modals ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImages(input, cid) {
            const c = document.getElementById(cid);
            c.innerHTML = '';
            if (input.files) Array.from(input.files).forEach((file, i) => {
                const r = new FileReader();
                r.onload = e => {
                    const b = document.createElement('div');
                    b.className = 'img-preview-box';
                    b.id = 'pv-' + cid + '-' + i;
                    b.innerHTML = `<img src="${e.target.result}"><button type="button" class="btn-remove" onclick="document.getElementById('${b.id}').remove()"><i class="bi bi-x"></i></button>`;
                    c.appendChild(b);
                };
                r.readAsDataURL(file);
            });
        }

        const toggle = document.getElementById('sidebarToggle'),
            sidebar = document.getElementById('sidebar'),
            overlay = document.getElementById('sbOverlay');
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open')
        });
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('open')
        });
    </script>
</body>

</html>