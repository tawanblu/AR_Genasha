<?php
session_start();
require_once("../connect.php");
/** @var mysqli $conn */


// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน (Role-Based Access Control) 🔐
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>
            alert('คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการระบบ!');
            window.location.href = '../index.php';
          </script>";
    exit();
}

// 2. จัดการการเพิ่มผู้ใช้ (POST Request จาก Modal)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $conn->real_escape_string($_POST['role']); // รับค่า role

    $stmt = $conn->prepare("INSERT INTO accounts (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $password, $role);

    if ($stmt->execute()) {
        header("Location: manage_users.php");
        exit();
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// 2.1 จัดการการแก้ไขผู้ใช้ (EDIT USER) ✏️
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
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
        echo "<script>alert('แก้ไขข้อมูลสำเร็จ!'); window.location.href='manage_users.php';</script>";
        exit();
    }
}

// 3. จัดการการลบผู้ใช้ (GET Request)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // ป้องกันแอดมินลบตัวเอง
    if ($id == $_SESSION['id_account']) {
        echo "<script>alert('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้!'); window.location.href='manage_users.php';</script>";
        exit();
    }
    $conn->query("DELETE FROM accounts WHERE id_account = $id");
    header("Location: manage_users.php");
    exit();
}

// 4. ตั้งค่าหน้าเพจและการค้นหา
$limit  = 5;
$page   = isset($_GET['page']) ? intval($_GET['page']) : 1;
$start  = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where  = "";

if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $where = "WHERE username LIKE '%$s%' OR email LIKE '%$s%'";
}

$totalRow   = $conn->query("SELECT COUNT(*) as total FROM accounts $where")->fetch_assoc()['total'];
$totalPages = ceil($totalRow / $limit);
$result     = $conn->query("SELECT * FROM accounts $where ORDER BY id_account DESC LIMIT $start, $limit");
$adminNav   = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Manage Users | AR Ganesha</title>
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

        /* Sidebar Styles */
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

        /* Main Content Styles */
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

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px
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

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px
        }

        .search-group {
            display: flex;
            gap: 0;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border)
        }

        .search-group input {
            background: rgba(255, 255, 255, .05);
            border: none;
            color: var(--txt);
            padding: 9px 14px;
            font-size: .87rem;
            width: 300px;
            font-family: 'DM Sans', sans-serif
        }

        .search-group input:focus {
            outline: none;
            background: rgba(255, 255, 255, .07)
        }

        .btn-search {
            background: rgba(201, 168, 76, .15);
            color: var(--gold);
            border: none;
            border-left: 1px solid var(--border);
            padding: 9px 16px;
            cursor: pointer
        }

        .btn-add {
            background: linear-gradient(135deg, var(--gold), #a07830);
            color: #000;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: .85rem;
            text-decoration: none;
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

        .btn-edit {
            background: rgba(77, 159, 255, .12);
            color: var(--blue);
            border: 1px solid rgba(77, 159, 255, .25);
            padding: 5px 12px;
            border-radius: 6px;
            font-size: .78rem;
            cursor: pointer;
            transition: all .18s
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
            transition: all .18s
        }

        .btn-del:hover {
            background: var(--red);
            color: #fff
        }

        .role-admin {
            background: rgba(224, 90, 90, 0.15);
            color: var(--red);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .role-user {
            background: rgba(77, 159, 255, 0.15);
            color: var(--blue);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .modal-content {
            background-color: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            color: var(--txt)
        }

        .form-control {
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            color: var(--txt);
        }

        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.06);
            border-color: var(--gold);
            color: var(--txt);
            box-shadow: none;
        }

        .email-txt {
            color: var(--muted);
            font-size: .82rem
        }

        .anim {
            animation: fadeUp .4s ease both
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
        <div class="topbar anim">
            <div>
                <div class="page-title">Manage <span>Users</span></div>
                <div style="color:var(--muted);font-size:.8rem;margin-top:4px;"><?= date('l, d F Y') ?></div>
            </div>
            <div class="topbar-right">
                <div class="time-badge"><i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem;"></i> Live</div>
                <div class="user-pill">
                    <div class="avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                    <span class="name"><?= htmlspecialchars($_SESSION['username']) ?></span>
                </div>
            </div>
        </div>

        <div class="toolbar anim">
            <form method="GET" class="search-group">
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-search"><i class="bi bi-search"></i></button>
            </form>
            <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-lg"></i> Add User
            </button>
        </div>

        <div class="data-card anim">
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
                    if ($result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                    ?>
                            <tr>
                                <td><span class="id-badge"><?= $row['id_account'] ?></span></td>
                                <td style="font-weight:600"><?= htmlspecialchars($row['username']) ?></td>
                                <td class="email-txt"><?= htmlspecialchars($row['email']) ?></td>
                                <td><span class="role-<?= $row['role'] ?>"><?= strtoupper($row['role']) ?></span></td>
                                <td style="text-align:center">
                                    <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id_account'] ?>"><i class="bi bi-pencil-square"></i> แก้ไข</button>
                                    <a href="?delete=<?= $row['id_account'] ?>" class="btn-del" onclick="return confirm('ลบผู้ใช้นี้?')"><i class="bi bi-trash"></i> ลบ</a>
                                </td>
                            </tr>
                            <?php
                            // สร้าง Modal แก้ไข
                            $modals .= '
                            <div class="modal fade" id="editModal' . $row['id_account'] . '" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <form method="POST" class="modal-content">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id_account" value="' . $row['id_account'] . '">
                                        <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body text-start">
                                            <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" value="' . htmlspecialchars($row['username']) . '" required></div>
                                            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="' . htmlspecialchars($row['email']) . '" required></div>
                                            <div class="mb-3"><label class="form-label">Role</label>
                                                <select name="role" class="form-control">
                                                    <option value="user" ' . ($row['role'] == 'user' ? 'selected' : '') . '>User</option>
                                                    <option value="admin" ' . ($row['role'] == 'admin' ? 'selected' : '') . '>Admin</option>
                                                </select>
                                            </div>
                                            <div class="mb-3"><label class="form-label">New Password (เว้นว่างได้)</label><input type="password" name="password" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-add">Save</button></div>
                                    </form>
                                </div>
                            </div>';
                            ?>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-add">Save User</button></div>
            </form>
        </div>
    </div>

    <?= $modals ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>