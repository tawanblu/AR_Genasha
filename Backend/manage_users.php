<?php
session_start();
require_once("../connect.php");
/** @var mysqli $conn */

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการระบบ!'); window.location.href='../index.php';</script>";
    exit();
}

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

// 2. จัดการข้อมูล (ADD/EDIT/DELETE)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $conn->real_escape_string($_POST['role']);

        $stmt = $conn->prepare("INSERT INTO accounts (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $password, $role);
        $stmt->execute() ? alertSuccess('เพิ่มผู้ใช้สำเร็จ', 'manage_users.php') : alertError($conn->error);
    } elseif ($_POST['action'] == 'edit') {
        $id_account = intval($_POST['id_account']);
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role']);

        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE accounts SET username=?, email=?, password=?, role=? WHERE id_account=?");
            $stmt->bind_param("ssssi", $username, $email, $password, $role, $id_account);
        } else {
            $stmt = $conn->prepare("UPDATE accounts SET username=?, email=?, role=? WHERE id_account=?");
            $stmt->bind_param("sssi", $username, $email, $role, $id_account);
        }

        if ($stmt->execute()) {
            if ($id_account == $_SESSION['id_account']) {
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
            }
            alertSuccess('แก้ไขข้อมูลสำเร็จ', 'manage_users.php');
        } else {
            alertError($conn->error);
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id == $_SESSION['id_account']) {
        alertError('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้!');
    }
    $conn->query("DELETE FROM accounts WHERE id_account = $id") ? alertSuccess('ลบผู้ใช้สำเร็จ', 'manage_users.php') : alertError($conn->error);
}

// 4. การค้นหา
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where = !empty($search) ? "WHERE username LIKE '%" . $conn->real_escape_string($search) . "%' OR email LIKE '%" . $conn->real_escape_string($search) . "%'" : "";
$result = $conn->query("SELECT * FROM accounts $where ORDER BY id_account DESC");
$adminNav = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Manage Users | AR Ganesha</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --gold: #c9a84c;
            --dark: #0e0e12;
            --panel: #16161e;
            --card: #1e1e2a;
            --border: rgba(201, 168, 76, .18);
            --txt: #e8e6f0;
            --muted: #a8a4c0;
            --red: #e05a5a;
            --blue: #4d9fff;
            --red-dim: rgba(224, 90, 90, .12);
            --blue-dim: rgba(77, 159, 255, .12);
            --sidebar-w: 260px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--txt)
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
            transition: transform .28s cubic-bezier(.4, 0, .2, 1);
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }

        .sidebar-logo {
            padding: 28px 24px 20px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo .logo-title {
            font-family: 'Cinzel', serif;
            font-size: 1.25rem;
            color: var(--gold);
            letter-spacing: .06em;
            line-height: 1;
        }

        .sidebar-logo .logo-sub {
            font-size: .7rem;
            color: var(--muted);
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 18px 12px;
            overflow-y: auto;
        }

        .nav-label {
            font-size: .62rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 6px 12px 8px;
            margin-top: 8px;
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
            position: relative;
        }

        .nav-link i {
            font-size: 1rem;
            min-width: 18px;
            text-align: center;
        }

        .nav-link:hover {
            color: var(--txt);
            background: rgba(255, 255, 255, .05);
        }

        .nav-link.active {
            color: var(--gold);
            background: rgba(201, 168, 76, .1);
            border: 1px solid var(--border);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            bottom: 20%;
            width: 3px;
            background: var(--gold);
            border-radius: 0 3px 3px 0;
        }

        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid var(--border);
        }

        .sidebar-footer .nav-link {
            color: var(--txt);
        }

        .sidebar-footer .nav-link:hover {
            color: var(--gold);
            background: rgba(201, 168, 76, .08);
        }

        .nav-link.logout {
            color: #e05a5a80;
        }

        .nav-link.logout:hover {
            color: var(--red);
            background: rgba(224, 90, 90, .08);
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
                padding: 24px 18px 50px !important
            }
        }

        .main {
            margin-left: var(--sidebar-w);
            padding: 36px 40px
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

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px
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

        .page-title {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            color: var(--txt)
        }

        .page-title span {
            color: var(--gold)
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px
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
            display: inline-flex;
            align-items: center;
            gap: 6px
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

        .data-table th {
            font-size: .72rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 14px 20px;
            border-bottom: 1px solid var(--border)
        }

        .data-table td {
            padding: 14px 20px;
            font-size: .87rem;
            border-bottom: 1px solid rgba(255, 255, 255, .04)
        }

        .btn-edit {
            background: var(--blue-dim);
            color: var(--blue);
            border: 1px solid rgba(77, 159, 255, .25);
            padding: 5px 12px;
            border-radius: 6px;
            font-size: .78rem;
            cursor: pointer;
            text-decoration: none
        }

        .btn-del {
            background: var(--red-dim);
            color: var(--red);
            border: 1px solid rgba(224, 90, 90, .25);
            padding: 5px 12px;
            border-radius: 6px;
            font-size: .78rem;
            cursor: pointer;
            text-decoration: none
        }

        .role-admin {
            background: rgba(224, 90, 90, 0.15);
            color: var(--red);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700
        }

        .role-user {
            background: rgba(77, 159, 255, 0.15);
            color: var(--blue);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700
        }

        .modal-content {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            color: var(--txt)
        }

        .form-control {
            background: rgba(255, 255, 255, .05);
            border: 1px solid var(--border);
            color: var(--txt);
            border-radius: 8px;
            padding: 9px 12px
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
        <div class="topbar">
            <div>
                <div class="page-title">Manage <span>Users</span></div>
                <div style="color:var(--muted);font-size:.8rem;margin-top:4px;"><?= date('l, d F Y') ?></div>
            </div>
            <div class="topbar-right">
                <div class="time-badge"><i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem;"></i> Live</div>
                <div class="user-pill">
                    <div class="avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
                    <span class="name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                </div>
            </div>
        </div>

        <div class="data-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th style="text-align:center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $modals = '';
                    while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><span class="id-badge"><?= $row['id_account'] ?></span></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td class="email-txt"><?= htmlspecialchars($row['email']) ?></td>
                            <td><span class="role-<?= $row['role'] ?>"><?= strtoupper($row['role']) ?></span></td>
                            <td style="text-align:center">
                                <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id_account'] ?>"><i class="bi bi-pencil-square"></i> แก้ไข</button>
                                <button class="btn-del" onclick="confirmDelete(<?= $row['id_account'] ?>)"><i class="bi bi-trash"></i> ลบ</button>
                            </td>
                        </tr>
                        <?php $modals .= '
                        <div class="modal fade" id="editModal' . $row['id_account'] . '" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <form method="POST" class="modal-content"><input type="hidden" name="action" value="edit"><input type="hidden" name="id_account" value="' . $row['id_account'] . '">
                                    <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body text-start">
                                        <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" value="' . htmlspecialchars($row['username']) . '" required></div>
                                        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="' . htmlspecialchars($row['email']) . '" required></div>
                                        <div class="mb-3"><label class="form-label">Role</label><select name="role" class="form-control"><option value="user" ' . ($row['role'] == 'user' ? 'selected' : '') . '>User</option><option value="admin" ' . ($row['role'] == 'admin' ? 'selected' : '') . '>Admin</option></select></div>
                                        <div class="mb-3"><label class="form-label">New Password (Optional)</label><input type="password" name="password" class="form-control"></div>
                                    </div>
                                    <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Changes</button></div>
                                </form>
                            </div>
                        </div>'; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn-add">Save User</button></div>
            </form>
        </div>
    </div>
    <?= $modals ?>
    <script>
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

        function confirmDelete(id) {
            Swal.fire({
                title: 'ยืนยันการลบผู้ใช้?',
                text: "ข้อมูลผู้ใช้จะถูกลบออกจากระบบอย่างถาวร!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e05a5a',
                cancelButtonColor: '#474761',
                confirmButtonText: 'ลบผู้ใช้',
                background: '#1e1e2a',
                color: '#e8e6f0'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = `?delete=${id}`;
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>