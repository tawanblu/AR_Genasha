<?php
session_start();
require_once("../connect.php");
/** @var mysqli $conn */

if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการระบบ'); window.location.href='../index.php';</script>";
    exit();
}

$adminNav = basename($_SERVER['PHP_SELF']);

// ----------------- ADD DATA -----------------
if (isset($_POST['add'])) {
    try {
        $title   = $_POST['title_ganesha'];
        $content = $_POST['content_ganesha'];
        $img_tmp = $_FILES['img_ganesha']['tmp_name'];
        $img_name = $_FILES['img_ganesha']['name'];
        $ext     = pathinfo($img_name, PATHINFO_EXTENSION);
        $new_name = time() . "_" . rand(1000, 9999) . "." . $ext;
        $path    = "../image/" . $new_name;

        if (move_uploaded_file($img_tmp, $path)) {
            $stmt = $conn->prepare("INSERT INTO ganesha_info (title_ganesha, content_ganesha, img_ganesha) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $title, $content, $new_name);

            if ($stmt->execute()) {
                echo "<script>alert('เพิ่มข้อมูลสำเร็จ'); window.location.href='manage_ganeshainfo.php';</script>";
            } else {
                throw new Exception($stmt->error);
            }
        } else {
            throw new Exception("ไม่สามารถอัปโหลดไฟล์รูปภาพได้");
        }
    } catch (Exception $e) {
        $error_msg = addslashes($e->getMessage());
        echo "<script>alert('เกิดข้อผิดพลาด:\\n{$error_msg}'); history.back();</script>";
    }
    exit();
}

// ----------------- EDIT DATA -----------------
if (isset($_POST['edit'])) {
    try {
        $id = intval($_POST['info_id']);
        if ($id <= 0) throw new Exception("ID ข้อมูลไม่ถูกต้อง");

        $title = $_POST['title_ganesha'];
        $content = $_POST['content_ganesha'];
        $old_img = $_POST['old_img_ganesha'];

        // Check if new image is uploaded
        if (!empty($_FILES['img_ganesha']['name'])) {
            $img_tmp = $_FILES['img_ganesha']['tmp_name'];
            $img_name = $_FILES['img_ganesha']['name'];
            $ext = pathinfo($img_name, PATHINFO_EXTENSION);
            $new_name = time() . "_" . rand(1000, 9999) . "." . $ext;
            $path = "../image/" . $new_name;

            if (move_uploaded_file($img_tmp, $path)) {
                // Update with new image
                $stmt = $conn->prepare("UPDATE ganesha_info SET title_ganesha=?, content_ganesha=?, img_ganesha=? WHERE info_id=?");
                $stmt->bind_param("sssi", $title, $content, $new_name, $id);

                if ($stmt->execute()) {
                    // Delete old image only if DB update is successful
                    $f = "../image/" . $old_img;
                    if (file_exists($f) && !empty($old_img)) @unlink($f);
                    echo "<script>alert('แก้ไขข้อมูลสำเร็จ'); window.location.href='manage_ganeshainfo.php';</script>";
                } else {
                    throw new Exception($stmt->error);
                }
            } else {
                throw new Exception("ไม่สามารถอัปโหลดไฟล์รูปภาพใหม่ได้");
            }
        } else {
            // Update without changing image
            $stmt = $conn->prepare("UPDATE ganesha_info SET title_ganesha=?, content_ganesha=? WHERE info_id=?");
            $stmt->bind_param("ssi", $title, $content, $id);

            if ($stmt->execute()) {
                echo "<script>alert('แก้ไขข้อมูลสำเร็จ'); window.location.href='manage_ganeshainfo.php';</script>";
            } else {
                throw new Exception($stmt->error);
            }
        }
    } catch (Exception $e) {
        $error_msg = addslashes($e->getMessage());
        echo "<script>alert('เกิดข้อผิดพลาด:\\n{$error_msg}'); history.back();</script>";
    }
    exit();
}

// ----------------- DELETE DATA -----------------
if (isset($_GET['delete'])) {
    try {
        $id = intval($_GET['delete']);
        if ($id <= 0) throw new Exception("ID ข้อมูลไม่ถูกต้อง");

        // 1. ดึงชื่อไฟล์รูปภาพมาก่อน
        $s2 = $conn->prepare("SELECT img_ganesha FROM ganesha_info WHERE info_id=?");
        $s2->bind_param("i", $id);
        $s2->execute();
        $r2 = $s2->get_result();
        $row2 = $r2->fetch_assoc();

        // 2. สั่งลบข้อมูลใน Database
        $s3 = $conn->prepare("DELETE FROM ganesha_info WHERE info_id=?");
        $s3->bind_param("i", $id);

        if ($s3->execute()) {
            // 3. ถ้าลบ DB สำเร็จ ค่อยมาลบไฟล์รูปภาพ
            if ($row2 && !empty($row2['img_ganesha'])) {
                $f = "../image/" . $row2['img_ganesha'];
                if (file_exists($f)) @unlink($f);
            }
            echo "<script>alert('ลบข้อมูลสำเร็จ'); window.location.href='manage_ganeshainfo.php';</script>";
        } else {
            throw new Exception($s3->error);
        }
    } catch (Exception $e) {
        $error_msg = addslashes($e->getMessage());
        echo "<script>
                alert('ไม่สามารถลบข้อมูลได้!\\nสาเหตุ: {$error_msg}\\n\\n(อาจมีตารางสื่อ AR หรือตารางอื่นผูกกับข้อมูลนี้อยู่)'); 
                window.location.href='manage_ganeshainfo.php';
              </script>";
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Ganesha | AR Ganesha</title>
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

        .data-table tbody tr:last-child td {
            border-bottom: none
        }

        .data-table tbody tr:hover {
            background: rgba(255, 255, 255, .03)
        }

        .id-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: rgba(201, 168, 76, .12);
            border: 1px solid var(--border);
            font-size: .75rem;
            font-weight: 700;
            color: var(--gold)
        }

        .img-thumb {
            width: 72px;
            height: 48px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border)
        }

        /* สไตล์ปุ่มแก้ไข */
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

        .content-preview {
            color: var(--muted);
            font-size: .83rem;
            line-height: 1.5
        }

        /* สไตล์ Modal */
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

        textarea.form-control {
            resize: vertical
        }

        .mb-3 {
            margin-bottom: 1rem
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

        .btn-update {
            background: linear-gradient(135deg, var(--blue), #2b78d4);
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

        .btn-update:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(77, 159, 255, .3)
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

        /* Animations */
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
                <div class="page-title">Ganesha <span>Info</span></div>
                <div style="color:var(--muted);font-size:.8rem;margin-top:4px;"><?= date('l, d F Y') ?></div>
            </div>
            <div class="topbar-actions">
                <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg"></i> Add Ganesha
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
                        <th style="width:50px">ID</th>
                        <th style="width:90px">Image</th>
                        <th>Title</th>
                        <th>Content</th>
                        <th style="text-align:center;width:170px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $modals = ''; // ตัวแปรสำหรับเก็บ HTML ของ Edit Modal แต่ละแถว
                    $result = $conn->query("SELECT * FROM ganesha_info ORDER BY info_id DESC");
                    while ($row = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><span class="id-badge"><?= $row['info_id'] ?></span></td>
                            <td>
                                <img src="../image/<?= htmlspecialchars($row['img_ganesha'] ?? 'default.jpg') ?>"
                                    class="img-thumb" onerror="this.src='../image/default.jpg'">
                            </td>
                            <td style="font-weight:600"><?= htmlspecialchars($row['title_ganesha']) ?></td>
                            <td class="content-preview"><?= mb_substr(htmlspecialchars($row['content_ganesha']), 0, 110, 'UTF-8') ?>...</td>
                            <td style="text-align:center; display: flex; gap: 8px; justify-content: center; border-bottom: none;">
                                <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['info_id'] ?>">
                                    <i class="bi bi-pencil-square"></i> แก้ไข
                                </button>
                                <a href="?delete=<?= $row['info_id'] ?>" class="btn-del" onclick="return confirm('คุณต้องการลบข้อมูลองค์พระพิฆเนศนี้ใช่หรือไม่?')">
                                    <i class="bi bi-trash"></i> ลบ
                                </a>
                            </td>
                        </tr>

                        <?php
                        // สร้าง Edit Modal ของแต่ละแถวเก็บไว้ในตัวแปร
                        $modals .= '
                        <div class="modal fade" id="editModal' . $row['info_id'] . '" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" enctype="multipart/form-data" class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2" style="color:var(--blue)"></i>Edit Ganesha</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-start">
                                        <input type="hidden" name="info_id" value="' . $row['info_id'] . '">
                                        <input type="hidden" name="old_img_ganesha" value="' . htmlspecialchars($row['img_ganesha']) . '">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Title</label>
                                            <input type="text" name="title_ganesha" class="form-control" value="' . htmlspecialchars($row['title_ganesha']) . '" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Content</label>
                                            <textarea name="content_ganesha" class="form-control" rows="5" required>' . htmlspecialchars($row['content_ganesha']) . '</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Image (เว้นว่างไว้หากต้องการใช้รูปเดิม)</label>
                                            <div class="mb-2">
                                                <img src="../image/' . htmlspecialchars($row['img_ganesha'] ?? 'default.jpg') . '" onerror="this.src=\'../image/default.jpg\'" style="width:120px; border-radius:8px; border:1px solid var(--border)">
                                            </div>
                                            <input type="file" name="img_ganesha" class="form-control" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="edit" class="btn-update"><i class="bi bi-save"></i> Update</button>
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

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" enctype="multipart/form-data" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-bank2 me-2"></i>Add Ganesha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title_ganesha" class="form-control" placeholder="Enter title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content_ganesha" class="form-control" rows="5" placeholder="Enter content" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" name="img_ganesha" class="form-control" accept="image/*" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add" class="btn-save"><i class="bi bi-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <?= $modals ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>