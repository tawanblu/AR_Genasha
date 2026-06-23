<?php
session_start();
require_once("../connect.php");
/** @var mysqli $conn */

if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการระบบ'); window.location.href='../index.php';</script>";
    exit();
}

$adminNav = basename($_SERVER['PHP_SELF']);

function getCount($conn, $sql)
{
    $result = $conn->query($sql);
    if (!$result) {
        die("เกิดข้อผิดพลาด SQL: " . $conn->error . " | Query: " . $sql);
    }
    return $result->fetch_assoc()['total'] ?? 0;
}

function safeQuery($conn, $sql)
{
    $result = $conn->query($sql);
    if (!$result) return 0;
    $row = $result->fetch_assoc();
    return $row ? array_values($row)[0] : 0;
}

// ==========================================
// [ระบบฟิลเตอร์วันที่] ดึงค่าจากปุ่มค้นหาช่วงวัน
// ==========================================
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_restaurant_reviews = "";
$where_place_reviews = "";

if (!empty($start_date) && !empty($end_date)) {
    $s_date = $conn->real_escape_string($start_date) . " 00:00:00";
    $e_date = $conn->real_escape_string($end_date) . " 23:59:59";

    $where_restaurant_reviews = " WHERE created_at BETWEEN '$s_date' AND '$e_date' ";
    $where_place_reviews = " WHERE created_at BETWEEN '$s_date' AND '$e_date' ";
}

// --- ข้อมูลหลัก Dashboard เดิม ---
$userCount       = safeQuery($conn, "SELECT COUNT(*) FROM accounts");
$ganeshaCount    = safeQuery($conn, "SELECT COUNT(*) FROM ganesha_info");
$restaurantCount = safeQuery($conn, "SELECT COUNT(*) FROM restaurant");
$ARLocationCount = safeQuery($conn, "SELECT COUNT(*) FROM ar_media");
$placeCount      = safeQuery($conn, "SELECT COUNT(*) FROM nearby_place");
$reviewCount     = safeQuery($conn, "SELECT (SELECT COUNT(*) FROM restaurant_reviews $where_restaurant_reviews) + (SELECT COUNT(*) FROM nearby_place_reviews $where_place_reviews)");

// --- สถิติ AR ---
$countARStart = 0;
$countViewModel = 0;
$checkTable = $conn->query("SHOW TABLES LIKE 'access_logs'");
if ($checkTable && $checkTable->num_rows > 0) {
    $resAR = $conn->query("SELECT COUNT(*) AS total FROM access_logs WHERE action_type = 'ar_start'");
    if ($resAR) {
        $countARStart = (int)($resAR->fetch_assoc()['total'] ?? 0);
    }
    $resModel = $conn->query("SELECT COUNT(*) AS total FROM access_logs WHERE action_type = 'view_model'");
    if ($resModel) {
        $countViewModel = (int)($resModel->fetch_assoc()['total'] ?? 0);
    }
}

// ==========================================
// ข้อมูลเซกชัน Ganesha Model Views
// ==========================================
$labels_ar = [];
$dataViews_ar = [];

// ปรับ SQL ให้นับ log_id ที่ตรงกับ target_id ขององค์พระนั้นๆ อย่างแม่นยำ และกรองตามสถิติ view_model
$sqlRanking = "SELECT g.title_ganesha, COUNT(l.log_id) AS view_count
               FROM ganesha_info g
               LEFT JOIN access_logs l ON g.info_id = l.target_id AND l.action_type = 'view_model'
               GROUP BY g.info_id
               ORDER BY view_count DESC
               LIMIT 5";

$rankingResult = $conn->query($sqlRanking);
if ($rankingResult) {
    while ($row = $rankingResult->fetch_assoc()) {
        $labels_ar[] = $row['title_ganesha'];
        $dataViews_ar[] = (int)$row['view_count'];
    }
}
$json_labels_ar = json_encode($labels_ar, JSON_UNESCAPED_UNICODE);
$json_data_ar = json_encode($dataViews_ar);

// ==========================================
// ข้อมูลเซกชัน Monthly Review Trend
// ==========================================
$filter_year = !empty($start_date) ? date('Y', strtotime($start_date)) : date('Y');
$months_map = ['01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.', '04' => 'เม.ย.', '05' => 'พ.ค.', '06' => 'มิ.ย.', '07' => 'ก.ค.', '08' => 'ส.ค.', '09' => 'ก.ย.', '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'];
$chart_labels = [];
$chart_data = [];

foreach ($months_map as $m_num => $m_name) {
    if (!empty($start_date) && !empty($end_date)) {
        $q_chart = "SELECT 
            (SELECT COUNT(*) FROM nearby_place_reviews WHERE DATE_FORMAT(created_at,'%m')='$m_num' AND created_at BETWEEN '$s_date' AND '$e_date') + 
            (SELECT COUNT(*) FROM restaurant_reviews WHERE DATE_FORMAT(created_at,'%m')='$m_num' AND created_at BETWEEN '$s_date' AND '$e_date') AS total";
    } else {
        $q_chart = "SELECT 
            (SELECT COUNT(*) FROM nearby_place_reviews WHERE DATE_FORMAT(created_at,'%m')='$m_num' AND YEAR(created_at)=$filter_year) + 
            (SELECT COUNT(*) FROM restaurant_reviews WHERE DATE_FORMAT(created_at,'%m')='$m_num' AND YEAR(created_at)=$filter_year) AS total";
    }
    $row_c = $conn->query($q_chart)->fetch_assoc();
    $chart_labels[] = $m_name;
    $chart_data[] = (int)$row_c['total'];
}
$json_m_labels = json_encode($chart_labels, JSON_UNESCAPED_UNICODE);
$json_m_data = json_encode($chart_data);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>AR Ganesha | Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        .main {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 36px 40px;
        }

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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 14px;
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

        .ar-dashboard-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1.4fr;
            gap: 16px;
            margin-bottom: 32px;
        }

        .chart-box {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            position: relative;
            height: 280px;
        }

        .chart-box h5 {
            margin-bottom: 12px;
            color: var(--txt);
            font-size: 0.9rem;
        }

        .chart-container-inner {
            position: relative;
            flex: 1;
            min-height: 0;
            width: 100%;
        }

        /* --- กล่องฟิลเตอร์ตามภาพตัวอย่าง --- */
        .filter-panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 16px;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-label {
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 500;
            white-space: nowrap;
        }

        .filter-input {
            background: #16161e;
            border: 1px solid var(--border);
            color: #fff;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.82rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .filter-input:focus {
            border-color: var(--gold);
        }

        .btn-filter-submit {
            background: var(--gold);
            color: #000;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            transition: opacity 0.2s;
            cursor: pointer;
        }

        .btn-filter-submit:hover {
            opacity: 0.9;
        }

        .btn-filter-reset {
            background: rgba(255, 255, 255, 0.05);
            color: var(--muted);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-filter-reset:hover {
            background: rgba(224, 90, 90, 0.1);
            color: var(--red);
            border-color: rgba(224, 90, 90, 0.2);
        }

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

        .quick-card.c-blue {
            --accent: var(--blue);
        }

        .quick-card.c-red {
            --accent: var(--red);
        }

        .quick-card.c-purple {
            --accent: var(--purple);
        }

        .data-table-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 32px;
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
            text-align: left;
        }

        .data-table td {
            padding: 14px 20px;
            font-size: .87rem;
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            color: var(--txt);
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

        .divider {
            height: 1px;
            background: var(--border);
            margin: 32px 0;
        }

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
    </style>
</head>

<body>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-title">AR Ganesha</div>
            <div class="logo-sub">Admin Console</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Main</div>

            <a href="dashboard.php" class="nav-link <?= $adminNav === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard & Reports
            </a>
            <a href="manage_users.php" class="nav-link <?= $adminNav === 'manage_users.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Manage Users
            </a>

            <div class="nav-label">Content</div>

            <a href="manage_ganeshainfo.php" class="nav-link <?= $adminNav === 'manage_ganeshainfo.php' ? 'active' : '' ?>">
                <i class="bi bi-bank2"></i> Ganesha Info
            </a>
            <a href="manage_ar_media.php" class="nav-link <?= $adminNav === 'manage_ar_media.php' ? 'active' : '' ?>">
                <i class="bi bi-camera-fill"></i> AR Media
            </a>
            <a href="manage_restaurant.php" class="nav-link <?= $adminNav === 'manage_restaurant.php' ? 'active' : '' ?>">
                <i class="bi bi-shop"></i> Restaurants
            </a>
            <a href="manage_places.php" class="nav-link <?= $adminNav === 'manage_places.php' ? 'active' : '' ?>">
                <i class="bi bi-geo-alt-fill"></i> Places
            </a>
            <a href="manage_reviews.php" class="nav-link <?= $adminNav === 'manage_reviews.php' ? 'active' : '' ?>">
                <i class="bi bi-star-fill"></i> Reviews
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="../index.php" class="nav-link"><i class="bi bi-house-door-fill"></i> ไปหน้า Index</a>
            <a href="logout.php" class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar anim anim-1">
            <div>
                <div class="page-title">Over<span>view</span></div>
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
            <div class="stat-card c-purple anim anim-6">
                <div class="stat-icon-wrap"><i class="bi bi-star-fill"></i></div>
                <div class="stat-label">Total Reviews</div>
                <div class="stat-number"><?= $reviewCount ?></div>
                <i class="bi bi-star-fill bg-icon"></i>
            </div>
        </div>

        <div class="section-head anim anim-6">
            <div class="section-title"><i class="bi bi-phone-fill"></i> AR Usage & Rankings</div>
        </div>
        <div class="ar-dashboard-row anim anim-6">
            <div class="stat-card c-blue" style="height: 280px;">
                <div class="stat-icon-wrap"><i class="bi bi-phone"></i></div>
                <div class="stat-label">AR Usage</div>
                <div class="stat-number"><?= number_format($countARStart) ?></div>
                <i class="bi bi-phone bg-icon"></i>
            </div>
            <div class="chart-box">
                <h5><i class="bi bi-pie-chart-fill me-2"></i> สัดส่วนพฤติกรรม AR</h5>
                <div class="chart-container-inner">
                    <canvas id="arUsageChart"></canvas>
                </div>
            </div>
            <div class="chart-box">
                <h5><i class="bi bi-bar-chart-fill me-2"></i> องค์พระที่ถูกส่องมากที่สุด (Top 5)</h5>
                <div class="chart-container-inner">
                    <canvas id="arBarChart"></canvas>
                </div>
            </div>
        </div>

        <div class="section-head anim anim-6">
            <div class="section-title">
                <i class="bi bi-graph-up-arrow"></i>
                Monthly Review Trends & Rankings
                <?php if (!empty($start_date) && !empty($end_date)): ?>
                    <span style="color: var(--gold); font-size: 0.85rem;">(ช่วงวันที่: <?= htmlspecialchars($start_date) ?> ถึง <?= htmlspecialchars($end_date) ?>)</span>
                <?php else: ?>
                    <span>(ประจำปี <?= $filter_year ?>)</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="filter-panel anim anim-6">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <span class="filter-label"><i class="bi bi-calendar3 me-1" style="color: var(--gold);"></i> ค้นหาช่วงวันที่ข้อมูล:</span>
                </div>
                <div class="filter-group">
                    <label class="filter-label">เริ่มต้น</label>
                    <input type="date" name="start_date" class="filter-input" value="<?= htmlspecialchars($start_date) ?>" required>
                </div>
                <div class="filter-group">
                    <label class="filter-label">สิ้นสุด</label>
                    <input type="date" name="end_date" class="filter-input" value="<?= htmlspecialchars($end_date) ?>" required>
                </div>
                <button type="submit" class="btn-filter-submit">ค้นหาข้อมูล</button>
                <?php if (!empty($start_date) || !empty($end_date)): ?>
                    <a href="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>" class="btn-filter-reset">รีเซ็ต</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="chart-box anim anim-6" style="height: 340px; margin-bottom: 32px;">
            <div class="chart-container-inner">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>

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

        <div class="section-head anim anim-6">
            <div class="section-title"><i class="bi bi-trophy-fill"></i> Top Detailed Rankings</div>
        </div>
        <div class="row g-3 anim anim-6">
            <div class="col-md-6">
                <div class="data-table-wrap">
                    <div class="p-3 border-bottom border-secondary border-opacity-25" style="color:var(--gold); font-size:0.85rem; font-weight:600;">
                        <i class="bi bi-shop me-2"></i> Top 5 Restaurants (Most Reviews)
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Name</th>
                                <th>Reviews</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $r_rank = 1;
                            // [แก้ไขใหม่] เปลี่ยนมา ORDER BY avg_s DESC (คะแนนเฉลี่ยสูงสุด) และถ้าคะแนนเท่ากัน ให้ร้านที่มีรีวิวเยอะกว่าขึ้นก่อน (total DESC)
                            $sql_tr = "SELECT r.restaurant_name, COUNT(rev.review_id) AS total, IFNULL(AVG(rev.rating_score),0) AS avg_s 
                            FROM restaurant r 
                            LEFT JOIN restaurant_reviews rev ON r.restaurant_id=rev.restaurant_id $where_restaurant_reviews 
                            GROUP BY r.restaurant_id 
                            ORDER BY avg_s DESC, total DESC 
                            LIMIT 5";
                            $res_tr = $conn->query($sql_tr);
                            while ($row = $res_tr->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="id-badge"><?= $r_rank++ ?></span></td>
                                    <td><?= htmlspecialchars($row['restaurant_name']) ?></td>
                                    <td><?= $row['total'] ?></td>
                                    <td class="text-warning"><i class="bi bi-star-fill me-1"></i><?= number_format($row['avg_s'], 1) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-table-wrap">
                    <div class="p-3 border-bottom border-secondary border-opacity-25" style="color:var(--purple); font-size:0.85rem; font-weight:600;">
                        <i class="bi bi-geo-alt me-2"></i> Top 5 Places (Most Reviews)
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Name</th>
                                <th>Reviews</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $p_rank = 1;
                            // [แก้ไขใหม่] เปลี่ยนมา ORDER BY avg_s DESC (คะแนนเฉลี่ยสูงสุด) เช่นเดียวกันครับ
                            $sql_tp = "SELECT p.name, COUNT(rev.review_id) AS total, IFNULL(AVG(rev.rating_score),0) AS avg_s 
                            FROM nearby_place p 
                            LEFT JOIN nearby_place_reviews rev ON p.place_id=rev.place_id $where_place_reviews 
                            GROUP BY p.place_id 
                            ORDER BY avg_s DESC, total DESC 
                            LIMIT 5";
                            $res_tp = $conn->query($sql_tp);
                            while ($row = $res_tp->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="id-badge" style="background:rgba(155,114,207,.12); color:var(--purple); border-color:rgba(155,114,207,.2);"><?= $p_rank++ ?></span></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= $row['total'] ?></td>
                                    <td class="text-warning"><i class="bi bi-star-fill me-1"></i><?= number_format($row['avg_s'], 1) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="section-head anim anim-6">
            <div class="section-title"><i class="bi bi-clock-history"></i> Recent Users</div>
            <a href="manage_users.php" class="btn-view-all">View All →</a>
        </div>
        <div class="data-table-wrap anim anim-6">
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
                    while ($row = $users->fetch_assoc()): ?>
                        <tr>
                            <td><span class="id-badge"><?= $row['id_account'] ?></span></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td style="color:var(--muted);"><?= htmlspecialchars($row['email']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        Chart.defaults.color = '#7a7a96';
        Chart.defaults.font.family = "'DM Sans', sans-serif";

        // Doughnut: AR Usage
        new Chart(document.getElementById('arUsageChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['เข้าใช้ AR', 'ดูโมเดล 3D'],
                datasets: [{
                    data: [<?= $countARStart ?>, <?= $countViewModel ?>],
                    backgroundColor: ['#4d9fff', '#9b72cf'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 12
                        }
                    }
                }
            }
        });

        // Bar Chart: Top 5 Ganesha
        // ==========================================
        // แก้ไขสคริปต์กราฟแท่ง (arBarChart) ให้แท่งกลับมาโชว์ถูกต้อง
        // ==========================================
        <?php
        // เตรียมข้อมูลสำหรับกราฟแท่งแบบปลอดภัย 100% บนฝั่ง PHP
        $clean_labels = [];
        $clean_data = [];

        if (isset($labels_ar) && count($labels_ar) > 0) {
            $clean_labels = $labels_ar;
            $clean_data = $dataViews_ar;
        } else {
            // ถ้าไม่มีข้อมูลจริง ให้ใส่ค่าเริ่มต้นไว้เป็นแนวทางไม่ให้กราฟพัง
            $clean_labels = ["ไม่มีข้อมูล"];
            $clean_data = [0];
        }

        $js_labels = json_encode($clean_labels, JSON_UNESCAPED_UNICODE);
        $js_data   = json_encode($clean_data);
        ?>

        new Chart(document.getElementById('arBarChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= $js_labels ?>,
                datasets: [{
                    label: 'จำนวนครั้งที่ส่อง',
                    data: <?= $js_data ?>,
                    // กำหนดสีให้ 2 แท่งแตกต่างกัน (สีทอง และ สีม่วง)
                    backgroundColor: [
                        'rgba(201, 168, 76, 0.5)', // สีของแท่งที่ 1
                        'rgba(155, 114, 207, 0.5)' // สีของแท่งที่ 2
                    ],
                    borderColor: [
                        '#c9a84c', // ขอบสีของแท่งที่ 1
                        '#9b72cf' // ขอบสีของแท่งที่ 2
                    ],
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y', // <--- เปิดใช้งานกราฟแท่งแนวนอน
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { // เปลี่ยนมาตั้งค่าสเกลที่แกน X แทน
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#7a7a96'
                        }
                    },
                    y: { // เปลี่ยนแกน Y เป็นการแสดงชื่อองค์พระ
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#e8e6f0',
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Line Chart: Monthly Trend
        new Chart(document.getElementById('monthlyTrendChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= $json_m_labels ?>,
                datasets: [{
                    label: 'จำนวนรีวิวรวมรายเดือน',
                    data: <?= $json_m_data ?>,
                    borderColor: '#38c9a0',
                    backgroundColor: 'rgba(56, 201, 160, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: '#38c9a0'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255,255,255,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>