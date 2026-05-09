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

// ----------------- DELETE DATA -----------------
if (isset($_GET['delete_place'])) {
    $id = intval($_GET['delete_place']);
    $conn->query("DELETE FROM nearby_place_reviews WHERE review_id = $id");
    header("Location: manage_reviews.php?del=1");
    exit();
}
if (isset($_GET['delete_rest'])) {
    $id = intval($_GET['delete_rest']);
    $conn->query("DELETE FROM restaurant_reviews WHERE review_id = $id");
    header("Location: manage_reviews.php?del=1");
    exit();
}

$res_place = $conn->query("SELECT r.*, p.name AS target_name, a.username FROM nearby_place_reviews r JOIN nearby_place p ON r.place_id=p.place_id JOIN accounts a ON r.id_account=a.id_account ORDER BY r.created_at DESC");
$res_rest  = $conn->query("SELECT r.*, res.restaurant_name AS target_name, a.username FROM restaurant_reviews r JOIN restaurant res ON r.restaurant_id=res.restaurant_id JOIN accounts a ON r.id_account=a.id_account ORDER BY r.created_at DESC");

function renderStars($score)
{
    $html = '';
    $score = intval($score);
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $score
            ? '<i class="bi bi-star-fill" style="color:var(--gold);font-size:.78rem"></i>'
            : '<i class="bi bi-star" style="color:rgba(255,255,255,.15);font-size:.78rem"></i>';
    }
    return '<span style="display:flex;gap:2px;align-items:center">' . $html . '<span style="font-size:.72rem;color:var(--muted);margin-left:5px">' . $score . '/5</span></span>';
}

$adminNav = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รีวิว — AR Ganesha Admin</title>
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
            background: var(--card);
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

        .breadcrumb-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .72rem;
            color: var(--muted);
            margin-top: 7px
        }

        .breadcrumb-bar a {
            color: var(--muted);
            text-decoration: none;
            transition: color .14s
        }

        .breadcrumb-bar a:hover {
            color: var(--gold)
        }

        .breadcrumb-bar .sep {
            opacity: .4
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

        /* Tab nav */
        .tab-nav {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 4px;
            animation: fadeUp .4s .06s ease both;
            width: fit-content
        }

        .tab-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--muted);
            font-family: 'DM Sans', sans-serif;
            font-size: .82rem;
            font-weight: 500;
            cursor: pointer;
            transition: all .18s
        }

        .tab-btn:hover {
            color: var(--txt);
            background: rgba(255, 255, 255, .05)
        }

        .tab-btn.active {
            background: var(--gold-dim);
            color: var(--gold);
            font-weight: 600;
            border: 1px solid var(--border)
        }

        .tab-btn .tab-count {
            background: var(--surface-3);
            color: var(--txt-3);
            font-size: .6rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 700
        }

        .tab-btn.active .tab-count {
            background: rgba(201, 168, 76, .2);
            color: var(--gold)
        }

        /* Tab content */
        .tab-pane-custom {
            display: none
        }

        .tab-pane-custom.active {
            display: block;
            animation: fadeUp .3s ease both
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

        .user-cell {
            display: flex;
            align-items: center;
            gap: 8px
        }

        .user-ava {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold-dim), var(--surface-3));
            border: 1px solid var(--border-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .65rem;
            font-weight: 700;
            color: var(--gold);
            flex-shrink: 0
        }

        .target-badge {
            background: var(--surface-3);
            border: 1px solid var(--border-dim);
            color: var(--txt-2);
            font-size: .72rem;
            padding: 3px 10px;
            border-radius: 6px;
            white-space: nowrap
        }

        .target-badge.place {
            border-color: rgba(155, 114, 207, .3);
            color: var(--purple)
        }

        .target-badge.rest {
            border-color: rgba(224, 90, 90, .3);
            color: var(--red)
        }

        .review-quote {
            background: rgba(0, 0, 0, .2);
            border-left: 2px solid var(--border-dim);
            padding: 6px 10px;
            border-radius: 4px;
            font-style: italic;
            font-size: .78rem;
            color: var(--txt-3);
            max-width: 320px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            word-break: break-word;
        }

        .time-txt {
            font-size: .72rem;
            color: var(--txt-3);
            white-space: nowrap
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
            font-family: 'DM Sans', sans-serif;
            transition: all .14s
        }

        .btn-del:hover {
            background: rgba(224, 90, 90, .22);
            color: var(--red)
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

            .tab-nav {
                width: 100%
            }

            .tab-btn {
                flex: 1;
                justify-content: center
            }
        }
    </style>
</head>

<body>

    <?php if (isset($_GET['del'])): ?>
        <div class="toast-container-custom">
            <div class="toast-custom success"><i class="bi bi-trash-fill"></i><span>ลบรีวิวแล้ว</span></div>
        </div>
        <script>
            setTimeout(() => {
                document.querySelector('.toast-custom')?.remove()
            }, 3000)
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
        <?php
        $cnt_place = $res_place ? $res_place->num_rows : 0;
        $cnt_rest  = $res_rest  ? $res_rest->num_rows  : 0;
        ?>
        <div class="topbar">
            <div>
                <div class="page-title">Manage <span>Reviews</span></div>
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

        <div class="tab-nav">
            <button class="tab-btn active" data-tab="place">
                <i class="bi bi-geo-alt-fill"></i> รีวิวสถานที่ <span class="tab-count"><?= $cnt_place ?></span>
            </button>
            <button class="tab-btn" data-tab="rest">
                <i class="bi bi-shop"></i> รีวิวร้านอาหาร <span class="tab-count"><?= $cnt_rest ?></span>
            </button>
        </div>

        <div class="tab-pane-custom active" id="tab-place">
            <div class="data-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ผู้ใช้งาน</th>
                            <th>สถานที่</th>
                            <th>คะแนน</th>
                            <th>ข้อความ</th>
                            <th>วันที่</th>
                            <th style="text-align:center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cnt_place > 0): $res_place->data_seek(0);
                            while ($row = $res_place->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-ava"><?= strtoupper(substr($row['username'], 0, 1)) ?></div>
                                            <span style="font-weight:600;color:var(--txt)"><?= htmlspecialchars($row['username']) ?></span>
                                        </div>
                                    </td>
                                    <td><span class="target-badge place"><?= htmlspecialchars($row['target_name']) ?></span></td>
                                    <td><?= renderStars($row['rating_score']) ?></td>
                                    <td>
                                        <div class="review-quote"><?= htmlspecialchars($row['review_text']) ?></div>
                                    </td>
                                    <td class="time-txt"><?= date('d M Y', strtotime($row['created_at'])) ?><br><span style="opacity:.6"><?= date('H:i', strtotime($row['created_at'])) ?></span></td>
                                    <td style="text-align:center">
                                        <a href="?delete_place=<?= $row['review_id'] ?>" class="btn-del" onclick="return confirm('ลบรีวิวนี้?')"><i class="bi bi-trash"></i> ลบ</a>
                                    </td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state"><i class="bi bi-geo-alt"></i>
                                        <p>ยังไม่มีรีวิวสถานที่</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane-custom" id="tab-rest">
            <div class="data-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ผู้ใช้งาน</th>
                            <th>ร้านอาหาร</th>
                            <th>คะแนน</th>
                            <th>ข้อความ</th>
                            <th>วันที่</th>
                            <th style="text-align:center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cnt_rest > 0): $res_rest->data_seek(0);
                            while ($row = $res_rest->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-ava"><?= strtoupper(substr($row['username'], 0, 1)) ?></div>
                                            <span style="font-weight:600;color:var(--txt)"><?= htmlspecialchars($row['username']) ?></span>
                                        </div>
                                    </td>
                                    <td><span class="target-badge rest"><?= htmlspecialchars($row['target_name']) ?></span></td>
                                    <td><?= renderStars($row['rating_score']) ?></td>
                                    <td>
                                        <div class="review-quote"><?= htmlspecialchars($row['review_text']) ?></div>
                                    </td>
                                    <td class="time-txt"><?= date('d M Y', strtotime($row['created_at'])) ?><br><span style="opacity:.6"><?= date('H:i', strtotime($row['created_at'])) ?></span></td>
                                    <td style="text-align:center">
                                        <a href="?delete_rest=<?= $row['review_id'] ?>" class="btn-del" onclick="return confirm('ลบรีวิวนี้?')"><i class="bi bi-trash"></i> ลบ</a>
                                    </td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state"><i class="bi bi-shop"></i>
                                        <p>ยังไม่มีรีวิวร้านอาหาร</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Custom tab switching (no Bootstrap pills needed)
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-pane-custom').forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
            });
        });
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>