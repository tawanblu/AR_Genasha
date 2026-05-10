<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("../connect.php");
/** @var mysqli $conn */


if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการระบบ'); window.location.href='../index.php';</script>";
    exit();
}

$adminNav = basename($_SERVER['PHP_SELF']);

$userCount       = $conn->query("SELECT COUNT(*) as total FROM accounts")->fetch_assoc()['total'];
$ganeshaCount    = $conn->query("SELECT COUNT(*) as total FROM ganesha_info")->fetch_assoc()['total'];
$restaurantCount = $conn->query("SELECT COUNT(*) as total FROM restaurant")->fetch_assoc()['total'];
$ARLocationCount = $conn->query("SELECT COUNT(*) as total FROM ar_media")->fetch_assoc()['total'];
$placeCount      = $conn->query("SELECT COUNT(*) as total FROM nearby_place")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>AR Ganesha — Admin</title>
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
            padding: 0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--txt);
            overflow-x: hidden;
        }

        /* ─── Sidebar ─────────────────────────────────────── */
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
        }

        /* top glow strip */
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

        .nav-link.logout {
            color: #e05a5a80;
        }

        .nav-link.logout:hover {
            color: var(--red);
            background: rgba(224, 90, 90, .08);
        }

        /* ─── Main Content ─────────────────────────────────── */
        .main {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 36px 40px;
        }

        /* ─── Top bar ──────────────────────────────────────── */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 36px;
        }

        .page-title {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            color: var(--txt);
            letter-spacing: .04em;
        }

        .page-title span {
            color: var(--gold);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--card);
            border: 1px solid var(--border);
            padding: 8px 16px 8px 10px;
            border-radius: 40px;
            font-size: .84rem;
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
            color: #000;
        }

        .user-pill .name {
            color: var(--gold);
            font-weight: 600;
        }

        .time-badge {
            background: var(--card);
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: 40px;
            font-size: .78rem;
            color: var(--muted);
        }

        /* ─── Stat Cards ───────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px 20px;
            position: relative;
            overflow: hidden;
            transition: transform .22s, box-shadow .22s;
            cursor: default;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, .4);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--accent);
            opacity: .6;
        }

        .stat-card .bg-icon {
            position: absolute;
            right: -6px;
            bottom: -10px;
            font-size: 5rem;
            opacity: .06;
            color: var(--accent);
            pointer-events: none;
        }

        .stat-label {
            font-size: .7rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1;
            color: var(--accent);
            font-family: 'Cinzel', serif;
        }

        .stat-icon-wrap {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: var(--accent);
            margin-bottom: 14px;
        }

        /* accent variants */
        .stat-card.c-gold {
            --accent: var(--gold);
        }

        .stat-card.c-teal {
            --accent: var(--teal);
        }

        .stat-card.c-blue {
            --accent: var(--blue);
        }

        .stat-card.c-red {
            --accent: var(--red);
        }

        .stat-card.c-purple {
            --accent: var(--purple);
        }

        /* ─── Section heading ──────────────────────────────── */
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .section-title {
            font-size: .95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--txt);
        }

        .section-title i {
            color: var(--gold);
        }

        .btn-view-all {
            font-size: .78rem;
            color: var(--gold);
            text-decoration: none;
            border: 1px solid var(--border);
            padding: 5px 14px;
            border-radius: 20px;
            transition: all .18s;
        }

        .btn-view-all:hover {
            background: rgba(201, 168, 76, .12);
            color: var(--gold-lt);
        }

        /* ─── Table ────────────────────────────────────────── */
        .data-table-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead tr {
            background: rgba(201, 168, 76, .07);
            border-bottom: 1px solid var(--border);
        }

        .data-table th {
            font-size: .72rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 14px 20px;
            font-weight: 600;
        }

        .data-table td {
            padding: 14px 20px;
            font-size: .87rem;
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            color: var(--txt);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr {
            transition: background .15s;
        }

        .data-table tbody tr:hover {
            background: rgba(255, 255, 255, .03);
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
            color: var(--gold);
        }

        .email-text {
            color: var(--muted);
            font-size: .82rem;
        }

        /* ─── Quick nav cards ──────────────────────────────── */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 32px;
        }

        .quick-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all .2s;
            position: relative;
            overflow: hidden;
        }

        .quick-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: var(--accent);
            opacity: 0;
            transition: opacity .2s;
        }

        .quick-card:hover {
            transform: translateX(4px);
            border-color: var(--accent);
        }

        .quick-card:hover::before {
            opacity: 1;
        }

        .quick-card .q-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, .05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--accent);
            flex-shrink: 0;
        }

        .quick-card .q-label {
            font-size: .82rem;
            font-weight: 600;
            color: var(--txt);
        }

        .quick-card .q-sub {
            font-size: .72rem;
            color: var(--muted);
            margin-top: 2px;
        }

        .quick-card.c-gold {
            --accent: var(--gold);
        }

        .quick-card.c-teal {
            --accent: var(--teal);
        }

        .quick-card.c-blue {
            --accent: var(--blue);
        }

        .quick-card.c-purple {
            --accent: var(--purple);
        }

        .quick-card.c-red {
            --accent: var(--red);
        }

        /* ─── Divider ──────────────────────────────────────── */
        .divider {
            height: 1px;
            background: var(--border);
            margin: 32px 0;
        }

        /* ─── Fade-in animation ────────────────────────────── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .anim {
            animation: fadeUp .45s ease both;
        }

        .anim-1 {
            animation-delay: .05s;
        }

        .anim-2 {
            animation-delay: .12s;
        }

        .anim-3 {
            animation-delay: .18s;
        }

        .anim-4 {
            animation-delay: .24s;
        }

        .anim-5 {
            animation-delay: .30s;
        }

        .anim-6 {
            animation-delay: .38s;
        }

        .anim-7 {
            animation-delay: .44s;
        }
    </style>
</head>

<body>

    <!-- ═══ SIDEBAR ═══════════════════════════════════════════ -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-title">AR Ganesha</div>
            <div class="logo-sub">Admin Console</div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Main</div>

            <a href="dashboard.php"
                class="nav-link <?= $adminNav === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <a href="manage_users.php"
                class="nav-link <?= $adminNav === 'manage_users.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Manage Users
            </a>

            <div class="nav-label">Content</div>

            <a href="manage_ganeshainfo.php"
                class="nav-link <?= $adminNav === 'manage_ganeshainfo.php' ? 'active' : '' ?>">
                <i class="bi bi-bank2"></i> Ganesha Info
            </a>
            <a href="manage_ar_media.php"
                class="nav-link <?= $adminNav === 'manage_ar_media.php' ? 'active' : '' ?>">
                <i class="bi bi-camera-fill"></i> AR Media
            </a>
            <a href="manage_restaurant.php"
                class="nav-link <?= $adminNav === 'manage_restaurant.php' ? 'active' : '' ?>">
                <i class="bi bi-shop"></i> Restaurants
            </a>
            <a href="manage_places.php"
                class="nav-link <?= $adminNav === 'manage_places.php' ? 'active' : '' ?>">
                <i class="bi bi-geo-alt-fill"></i> Places
            </a>
            <a href="manage_reviews.php"
                class="nav-link <?= $adminNav === 'manage_reviews.php' ? 'active' : '' ?>">
                <i class="bi bi-star-fill"></i> Reviews
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" class="nav-link logout">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>


    <!-- ═══ MAIN ═══════════════════════════════════════════════ -->
    <main class="main">

        <!-- Top bar -->
        <div class="topbar anim anim-1">
            <div>
                <div class="page-title">Over<span>view</span></div>
                <div style="color:var(--muted);font-size:.8rem;margin-top:4px;">
                    <?= date('l, d F Y') ?>
                </div>
            </div>
            <div class="topbar-right">
                <div class="time-badge"><i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem;"></i> Live</div>
                <div class="user-pill">
                    <div class="avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                    <span class="name"><?= htmlspecialchars($_SESSION['username']) ?></span>
                </div>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="stats-grid">
            <div class="stat-card c-gold anim anim-1">
                <div class="stat-icon-wrap"><i class="bi bi-people-fill"></i></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-number"><?= $userCount ?></div>
                <i class="bi bi-people-fill bg-icon"></i>
            </div>
            <div class="stat-card c-teal anim anim-2">
                <div class="stat-icon-wrap"><i class="bi bi-bank2"></i></div>
                <div class="stat-label">Ganesha Info</div>
                <div class="stat-number"><?= $ganeshaCount ?></div>
                <i class="bi bi-bank2 bg-icon"></i>
            </div>
            <div class="stat-card c-red anim anim-3">
                <div class="stat-icon-wrap"><i class="bi bi-shop"></i></div>
                <div class="stat-label">Restaurants</div>
                <div class="stat-number"><?= $restaurantCount ?></div>
                <i class="bi bi-shop bg-icon"></i>
            </div>
            <div class="stat-card c-blue anim anim-4">
                <div class="stat-icon-wrap"><i class="bi bi-camera-fill"></i></div>
                <div class="stat-label">AR Media</div>
                <div class="stat-number"><?= $ARLocationCount ?></div>
                <i class="bi bi-camera-fill bg-icon"></i>
            </div>
            <div class="stat-card c-purple anim anim-5">
                <div class="stat-icon-wrap"><i class="bi bi-geo-alt-fill"></i></div>
                <div class="stat-label">Places</div>
                <div class="stat-number"><?= $placeCount ?></div>
                <i class="bi bi-geo-alt-fill bg-icon"></i>
            </div>
        </div>

        <!-- Quick nav -->
        <div class="section-head anim anim-6">
            <div class="section-title"><i class="bi bi-lightning-charge-fill"></i> Quick Access</div>
        </div>
        <div class="quick-grid anim anim-6">
            <a href="manage_ganeshainfo.php" class="quick-card c-gold">
                <div class="q-icon"><i class="bi bi-bank2"></i></div>
                <div>
                    <div class="q-label">Ganesha Info</div>
                    <div class="q-sub">Manage content</div>
                </div>
            </a>
            <a href="manage_ar_media.php" class="quick-card c-blue">
                <div class="q-icon"><i class="bi bi-camera-fill"></i></div>
                <div>
                    <div class="q-label">AR Media</div>
                    <div class="q-sub">Upload & pin</div>
                </div>
            </a>
            <a href="manage_restaurant.php" class="quick-card c-red">
                <div class="q-icon"><i class="bi bi-shop"></i></div>
                <div>
                    <div class="q-label">Restaurants</div>
                    <div class="q-sub">Add & edit</div>
                </div>
            </a>
            <a href="manage_places.php" class="quick-card c-purple">
                <div class="q-icon"><i class="bi bi-geo-alt-fill"></i></div>
                <div>
                    <div class="q-label">Nearby Places</div>
                    <div class="q-sub">Location data</div>
                </div>
            </a>
        </div>

        <div class="divider"></div>

        <!-- Recent users table -->
        <div class="section-head anim anim-7">
            <div class="section-title"><i class="bi bi-clock-history"></i> Recent Users</div>
            <a href="manage_users.php" class="btn-view-all">View All →</a>
        </div>

        <div class="data-table-wrap anim anim-7">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $conn->query("SELECT * FROM accounts ORDER BY id_account DESC LIMIT 5");
                    if ($users->num_rows > 0):
                        while ($row = $users->fetch_assoc()):
                    ?>
                            <tr>
                                <td><span class="id-badge"><?= $row['id_account'] ?></span></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td class="email-text"><?= htmlspecialchars($row['email']) ?></td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="3" style="text-align:center;color:var(--muted);padding:32px;">
                                No users found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>