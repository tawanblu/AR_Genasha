<?php
session_start();
require_once("../connect.php");
/** @var mysqli $conn */

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน (ต้องเป็น Admin เท่านั้น)
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>
            alert('คุณไม่มีสิทธิ์เข้าถึงหน้าจัดการระบบ!');
            window.location.href = '../index.php';
          </script>";
    exit();
}

$adminNav = basename($_SERVER['PHP_SELF']);

// 2. จัดการตัวกรองวันที่ (Date Filter)
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_review_date = "";
if (!empty($start_date) && !empty($end_date)) {
    // ป้องกัน SQL Injection พื้นฐาน
    $s_date = $conn->real_escape_string($start_date) . " 00:00:00";
    $e_date = $conn->real_escape_string($end_date) . " 23:59:59";
    $where_review_date = " WHERE created_at BETWEEN '$s_date' AND '$e_date' ";
}

// 3. ดึงข้อมูลสรุป (Stats) จากฐานข้อมูล
$totalGanesha     = $conn->query("SELECT * FROM ganesha_info")->num_rows;
$totalUsers       = $conn->query("SELECT * FROM accounts")->num_rows;
$totalRestaurants = $conn->query("SELECT * FROM restaurant")->num_rows;
$totalPlaces      = $conn->query("SELECT * FROM nearby_place")->num_rows;

$reviewPlaceCount = $conn->query("SELECT * FROM nearby_place_reviews $where_review_date")->num_rows;
$reviewRestCount  = $conn->query("SELECT * FROM restaurant_reviews $where_review_date")->num_rows;
$totalReviews     = $reviewPlaceCount + $reviewRestCount;
// --- สถิติ AR ---
$countARStart = (int)($conn->query("SELECT COUNT(*) AS total FROM access_logs WHERE action_type = 'ar_start'")->fetch_assoc()['total'] ?? 0);
$countViewModel = (int)($conn->query("SELECT COUNT(*) AS total FROM access_logs WHERE action_type = 'view_model'")->fetch_assoc()['total'] ?? 0);

// ดึงข้อมูล Top 5 องค์พระที่ถูกรับชมโมเดลมากที่สุด (กราฟแท่ง)
$labels_ar = [];
$dataViews_ar = [];
$sqlRanking = "SELECT g.title_ganesha, COUNT(l.log_id) AS view_count
               FROM access_logs l
               JOIN ganesha_info g ON l.target_id = g.info_id
               WHERE l.action_type = 'view_model'
               GROUP BY l.target_id
               ORDER BY view_count DESC
               LIMIT 5";
$rankingResult = $conn->query($sqlRanking);
if ($rankingResult) {
    while ($row = $rankingResult->fetch_assoc()) {
        $labels_ar[] = $row['title_ganesha'];
        $dataViews_ar[] = (int)$row['view_count'];
    }
}
if (empty($labels_ar)) {
    $labels_ar[] = 'ยังไม่มีข้อมูล';
    $dataViews_ar[] = 0;
}
$json_labels_ar = json_encode($labels_ar, JSON_UNESCAPED_UNICODE);
$json_data_ar = json_encode($dataViews_ar);

// 4. จัดเตรียมข้อมูลสำหรับกราฟรีวิวรายเดือน (Dynamic Chart)
$filter_year = !empty($start_date) ? date('Y', strtotime($start_date)) : date('Y');
$months_map = ['01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.', '04' => 'เม.ย.', '05' => 'พ.ค.', '06' => 'มิ.ย.', '07' => 'ก.ค.', '08' => 'ส.ค.', '09' => 'ก.ย.', '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'];
$chart_labels = [];
$chart_data = [];

foreach ($months_map as $month_num => $month_name) {
    // รวมยอดรีวิวจาก 2 ตาราง
    $query_chart = "
        SELECT 
            (SELECT COUNT(*) FROM nearby_place_reviews WHERE DATE_FORMAT(created_at, '%m') = '$month_num' AND YEAR(created_at) = $filter_year) +
            (SELECT COUNT(*) FROM restaurant_reviews WHERE DATE_FORMAT(created_at, '%m') = '$month_num' AND YEAR(created_at) = $filter_year) AS total_reviews
    ";
    $res_chart = $conn->query($query_chart);
    $row_chart = $res_chart->fetch_assoc();

    $chart_labels[] = $month_name;
    $chart_data[] = (int)($row_chart['total_reviews'] ?? 0);
}
$json_labels = json_encode($chart_labels, JSON_UNESCAPED_UNICODE);
$json_data = json_encode($chart_data);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Report — AR Ganesha Admin</title>
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
            --purple: #8b5cf6;
            --blue: #4d9fff;
            --teal: #38c9a0;
            --sidebar-w: 260px;
            --radius: 14px;
            --radius-sm: 8px
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
            align-items: center;
            margin-bottom: 28px;
            animation: fadeUp .4s ease both
        }

        .page-title {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: .04em
        }

        .page-title span {
            color: var(--gold)
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px
        }

        .btn-action {
            background: linear-gradient(135deg, var(--gold), #a07830);
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 40px;
            font-weight: 700;
            font-size: .82rem;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(201, 168, 76, .35);
            color: #000;
        }

        .btn-outline-custom {
            background: transparent;
            color: var(--gold);
            border: 1px solid var(--gold);
            padding: 8px 16px;
            border-radius: 40px;
            font-weight: 700;
            font-size: .82rem;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-outline-custom:hover {
            background: rgba(201, 168, 76, 0.1);
        }

        .time-badge {
            background: var(--card);
            border: 1px solid var(--border);
            padding: 6px 14px;
            border-radius: 40px;
            font-size: .78rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--card);
            border: 1px solid var(--border);
            padding: 5px 14px 5px 6px;
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

        /* Filter Box */
        .filter-box {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .filter-box input[type="date"] {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-dim);
            color: var(--txt);
            padding: 6px 12px;
            border-radius: 6px;
            font-family: 'DM Sans', sans-serif;
            color-scheme: dark;
        }

        .filter-box input[type="date"]:focus {
            outline: none;
            border-color: var(--gold);
        }

        .stats-grid-container {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .card-stats {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .card-stats::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: radial-gradient(circle, rgba(201, 168, 76, .05) 0%, transparent 70%);
            border-radius: 50%
        }

        .chart-box {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 28px
        }

        .report-section {
            margin-bottom: 32px;
        }

        .section-heading {
            font-family: 'Cinzel', serif;
            font-size: 1.05rem;
            color: var(--gold);
            letter-spacing: .06em;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-dim);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-heading .num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--gold-dim);
            border: 1px solid var(--border);
            font-size: .75rem;
            color: var(--gold);
            font-family: 'DM Sans', sans-serif;
        }

        .stats-grid-ar {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .charts-ar-row {
            display: grid;
            grid-template-columns: 5fr 7fr;
            gap: 20px;
        }

        @media (max-width: 991px) {
            .charts-ar-row {
                grid-template-columns: 1fr;
            }
        }

        .tabs-container {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-dim);
            padding-bottom: 12px;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: transparent;
            border: 1px solid transparent;
            color: var(--muted);
            padding: 8px 16px;
            border-radius: 40px;
            font-size: .84rem;
            font-weight: 500;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .tab-btn:hover {
            color: var(--txt);
            background: rgba(255, 255, 255, .03)
        }

        .tab-btn.active {
            color: var(--gold);
            background: var(--gold-dim);
            border-color: var(--border)
        }

        .tab-pane-custom {
            display: none;
            animation: fadeIn .3s ease both
        }

        .tab-pane-custom.active {
            display: block
        }

        .table-responsive-custom {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: auto;
            max-height: 500px;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: .88rem;
            text-align: left
        }

        .table-custom th {
            background: #151520;
            color: var(--gold);
            font-family: 'Cinzel', serif;
            font-size: .8rem;
            letter-spacing: .04em;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-custom td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-dim);
            color: var(--txt-2);
            vertical-align: middle
        }

        .table-custom tr:last-child td {
            border-bottom: none
        }

        .table-custom tr:hover td {
            background: rgba(255, 255, 255, .01);
            color: var(--txt)
        }

        .img-thumb-custom {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid var(--border-dim)
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

        @keyframes fadeIn {
            from {
                opacity: 0
            }

            to {
                opacity: 1
            }
        }

        @media print {

            .no-print,
            .sidebar,
            .mobile-toggle,
            .tabs-container,
            .filter-box {
                display: none !important
            }

            .main {
                margin-left: 0 !important;
                padding: 0 !important
            }

            .tab-pane-custom {
                display: block !important;
                margin-bottom: 40px;
                page-break-inside: avoid
            }
        }

        @media(max-width:1200px) {
            .stats-grid-container {
                grid-template-columns: repeat(3, 1fr);
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px
            }

            .topbar-right {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap
            }
        }

        @media(max-width:768px) {
            .stats-grid-container {
                grid-template-columns: repeat(1, 1fr);
            }

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

    <button type="button" class="mobile-toggle no-print" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="sb-overlay no-print" id="sbOverlay"></div>

    <aside class="sidebar no-print" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-title">AR Ganesha</div>
            <div class="logo-sub">Admin Console</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Main</div>
            <a href="dashboard.php" class="nav-link <?= $adminNav === 'dashboard.php' ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            <a href="manage_users.php" class="nav-link <?= $adminNav === 'manage_users.php' ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> Manage Users</a>
            <a href="report_ganesha.php" class="nav-link <?= $adminNav === 'report_ganesha.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Ganesha Report
            </a>
            <div class="nav-label">Content</div>
            <a href="manage_ganeshainfo.php" class="nav-link"><i class="bi bi-bank2"></i> Ganesha Info</a>
            <a href="manage_ar_media.php" class="nav-link"><i class="bi bi-camera-fill"></i> AR Media</a>
            <a href="manage_restaurant.php" class="nav-link"><i class="bi bi-shop"></i> Restaurants</a>
            <a href="manage_places.php" class="nav-link"><i class="bi bi-geo-alt-fill"></i> Places</a>
            <a href="manage_reviews.php" class="nav-link"><i class="bi bi-star-fill"></i> Reviews</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar no-print">
            <div>
                <div class="page-title">System <span>Report</span></div>
                <div style="color:var(--muted);font-size:.8rem;margin-top:4px;"><?= date('l, d F Y') ?></div>
            </div>

            <div class="topbar-right">
                <button class="btn-outline-custom" onclick="exportTableToCSV('Ganesha_Report_Export.csv')"><i class="bi bi-file-earmark-excel"></i> Export CSV</button>
                <button class="btn-action" onclick="window.print()"><i class="bi bi-printer"></i> Export PDF</button>
                <div class="time-badge"><i class="bi bi-circle-fill text-success" style="font-size:.5rem;"></i> Active</div>
                <div class="user-pill">
                    <div class="avatar"><?= isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'A' ?></div>
                    <span class="name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                </div>
            </div>
        </div>

        <!-- 1. ภาพรวมเนื้อหาในระบบ -->
        <section class="report-section">
            <h2 class="section-heading"><span class="num">1</span> ภาพรวมเนื้อหาในระบบ</h2>
            <div class="stats-grid-container">
                <div class="card-stats">
                    <p class="text-secondary mb-1" style="font-size:.75rem;text-transform:uppercase;">พระพิฆเนศ</p>
                    <h3 class="fw-bold m-0" style="color: #ffffff; font-size: 1.35rem;"><span style="color:var(--gold);"><?= number_format($totalGanesha) ?></span> รายการ</h3>
                </div>
                <div class="card-stats">
                    <p class="text-secondary mb-1" style="font-size:.75rem;text-transform:uppercase;">ร้านอาหารแนะนำ</p>
                    <h3 class="fw-bold m-0" style="color: #ffffff; font-size: 1.35rem;"><span style="color:var(--gold);"><?= number_format($totalRestaurants) ?></span> ร้าน</h3>
                </div>
                <div class="card-stats">
                    <p class="text-secondary mb-1" style="font-size:.75rem;text-transform:uppercase;">สถานที่ยอดนิยม</p>
                    <h3 class="fw-bold m-0" style="color: #ffffff; font-size: 1.35rem;"><span style="color:var(--gold);"><?= number_format($totalPlaces) ?></span> แห่ง</h3>
                </div>
                <div class="card-stats">
                    <p class="text-secondary mb-1" style="font-size:.75rem;text-transform:uppercase;">รีวิวผู้ใช้งานรวม <?= $where_review_date ? '(ตามช่วงเวลา)' : '' ?></p>
                    <h3 class="fw-bold m-0" style="color: #ffffff; font-size: 1.35rem;"><span style="color:var(--gold);"><?= number_format($totalReviews) ?></span> รีวิว</h3>
                </div>
                <div class="card-stats">
                    <p class="text-secondary mb-1" style="font-size:.75rem;text-transform:uppercase;">ผู้ใช้ระบบรวม</p>
                    <h3 class="fw-bold m-0" style="color: #ffffff; font-size: 1.25rem;"><span style="color:var(--gold);"><?= number_format($totalUsers) ?> คน</span></h3>
                </div>
            </div>
        </section>

        <!-- 2. สถิติการใช้งาน AR -->
        <section class="report-section no-print">
            <h2 class="section-heading"><span class="num">2</span> สถิติการใช้งาน AR</h2>
            <div class="stats-grid-ar">
                <div class="card-stats" style="border-color: var(--blue);">
                    <p class="text-secondary mb-1" style="font-size:.75rem;text-transform:uppercase;color:var(--blue) !important;">เริ่มใช้ AR</p>
                    <h3 class="fw-bold m-0" style="color: #ffffff; font-size: 1.35rem;"><span style="color:var(--blue);"><?= number_format($countARStart) ?></span> ครั้ง</h3>
                </div>
                <div class="card-stats" style="border-color: var(--purple);">
                    <p class="text-secondary mb-1" style="font-size:.75rem;text-transform:uppercase;color:var(--purple) !important;">รับชมโมเดล 3D</p>
                    <h3 class="fw-bold m-0" style="color: #ffffff; font-size: 1.35rem;"><span style="color:var(--purple);"><?= number_format($countViewModel) ?></span> ครั้ง</h3>
                </div>
            </div>
            <div class="charts-ar-row">
                <div class="chart-box" style="margin-bottom: 0;">
                    <h5 class="mb-4" style="font-size: 1rem; color: #ffffff;"><i class="bi bi-pie-chart-fill me-2" style="color:var(--blue);"></i> สัดส่วนการใช้ AR vs โมเดล</h5>
                    <div style="height: 260px; position: relative; width: 100%; display: flex; justify-content: center;">
                        <canvas id="arPieChart"></canvas>
                    </div>
                </div>
                <div class="chart-box" style="margin-bottom: 0;">
                    <h5 class="mb-4" style="font-size: 1rem; color: #ffffff;"><i class="bi bi-bar-chart-line-fill me-2" style="color:var(--purple);"></i> องค์พระพิฆเนศที่ถูกรับชมมากที่สุด</h5>
                    <div style="height: 260px; position: relative; width: 100%;">
                        <canvas id="arBarChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. ตัวกรองวันที่ -->
        <section class="report-section no-print">
            <h2 class="section-heading"><span class="num">3</span> กรองข้อมูลตามวันที่</h2>
            <form method="GET" class="filter-box" style="margin-bottom: 0;">
                <div class="text-warning fw-bold"><i class="bi bi-funnel-fill"></i> ช่วงวันที่สำหรับรีวิวและตารางรายละเอียด:</div>
                <div>
                    <label style="font-size: 0.8rem; color: var(--muted);">ตั้งแต่วันที่:</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div>
                    <label style="font-size: 0.8rem; color: var(--muted);">ถึงวันที่:</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <button type="submit" class="btn btn-sm btn-primary" style="background: var(--gold); border: none; color: #000; font-weight: bold;"><i class="bi bi-search"></i> ค้นหา</button>
                <a href="report_ganesha.php" class="btn btn-sm btn-secondary" style="background: transparent; border: 1px solid var(--border-dim); color: var(--muted);">ล้างค่า</a>
            </form>
        </section>

        <!-- 4. กราฟรีวิวรายเดือน -->
        <section class="report-section">
            <h2 class="section-heading"><span class="num">4</span> ภาพรวมรีวิวรายเดือน ปี <?= $filter_year ?></h2>
            <div class="chart-box" style="margin-bottom: 0;">
                <div style="position: relative; width: 100%; height: 260px;">
                    <canvas id="ganeshaChart"></canvas>
                </div>
            </div>
        </section>

        <!-- 5. ตารางรายละเอียด -->
        <section class="report-section">
            <h2 class="section-heading"><span class="num">5</span> รายงานรายละเอียด</h2>

        <div class="tabs-container no-print">
            <button type="button" class="tab-btn active" data-tab="ganesha"><i class="bi bi-bank2"></i> 1. พระพิฆเนศ</button>
            <button type="button" class="tab-btn" data-tab="restaurant"><i class="bi bi-shop"></i> 2. ร้านอาหาร</button>
            <button type="button" class="tab-btn" data-tab="places"><i class="bi bi-geo-alt-fill"></i> 3. สถานที่ยอดนิยม</button>
            <button type="button" class="tab-btn" data-tab="reviews"><i class="bi bi-star-fill"></i> 4. ไทม์ไลน์รีวิว</button>
            <button type="button" class="tab-btn" data-tab="missing-ar"><i class="bi bi-exclamation-triangle-fill text-warning"></i> 5. ตรวจสอบสื่อ AR</button>
            <button type="button" class="tab-btn" data-tab="sentiment"><i class="bi bi-emoji-smile"></i> 6. สรุปความพึงพอใจ</button>
        </div>

        <div class="tab-pane-custom active" id="tab-ganesha">
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th style="width: 100px;">รูปภาพ</th>
                            <th>หัวข้อ (Title)</th>
                            <th>รายละเอียดโดยย่อ (Content)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("SELECT * FROM ganesha_info ORDER BY info_id DESC");
                        if ($res && $res->num_rows > 0):
                            while ($row = $res->fetch_assoc()):
                        ?>
                                <tr>
                                    <td>#<?= $row['info_id'] ?></td>
                                    <td><img src="../image/<?= htmlspecialchars($row['img_ganesha'] ?? 'default.jpg') ?>" class="img-thumb-custom" onerror="this.src='../image/default.jpg'"></td>
                                    <td class="fw-semibold text-white"><?= htmlspecialchars($row['title_ganesha']) ?></td>
                                    <td><?= mb_strimwidth(strip_tags($row['content_ganesha']), 0, 120, "...", "UTF-8") ?></td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">ไม่พบข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane-custom" id="tab-restaurant">
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 80px;">อันดับ</th>
                            <th>ชื่อร้านอาหาร</th>
                            <th>หมวดหมู่</th>
                            <th>คะแนนเฉลี่ย</th>
                            <th>จำนวนรีวิว</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res_rest = $conn->query("
                            SELECT r.*, AVG(rev.rating_score) as avg_rating, COUNT(rev.review_id) as total_rev
                            FROM restaurant r 
                            LEFT JOIN restaurant_reviews rev ON r.restaurant_id = rev.restaurant_id 
                            GROUP BY r.restaurant_id 
                            ORDER BY avg_rating DESC, total_rev DESC
                        ");
                        if ($res_rest && $res_rest->num_rows > 0):
                            $rank = 1;
                            while ($row = $res_rest->fetch_assoc()):
                                $rating_val = floatval($row['avg_rating'] ?? 0.0);
                        ?>
                                <tr>
                                    <td class="text-center fw-bold text-warning"><?= $rank++ ?></td>
                                    <td class="fw-semibold text-white"><?= htmlspecialchars($row['restaurant_name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['category'] ?? 'ทั่วไป') ?></span></td>
                                    <td class="fw-bold text-white"><i class="bi bi-star-fill text-warning me-1"></i> <?= number_format($rating_val, 1) ?></td>
                                    <td><?= number_format($row['total_rev']) ?> รีวิว</td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">ไม่พบข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane-custom" id="tab-places">
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 80px;">อันดับ</th>
                            <th>ชื่อสถานที่</th>
                            <th>หมวดหมู่</th>
                            <th style="color:var(--gold)">จำนวนคนรีวิว</th>
                            <th>คะแนนเฉลี่ย</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_place_popular = "SELECT p.*, COUNT(r.review_id) AS total_clicks, AVG(r.rating_score) AS avg_rating
                                              FROM nearby_place p 
                                              LEFT JOIN nearby_place_reviews r ON p.place_id = r.place_id 
                                              GROUP BY p.place_id 
                                              ORDER BY total_clicks DESC, p.place_id DESC";
                        $res_place = $conn->query($sql_place_popular);
                        if ($res_place && $res_place->num_rows > 0):
                            $p_rank = 1;
                            while ($row = $res_place->fetch_assoc()):
                        ?>
                                <tr>
                                    <td class="text-center fw-bold" style="color: #60a5fa;"><?= $p_rank++ ?></td>
                                    <td class="fw-semibold text-white"><?= htmlspecialchars($row['name']) ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($row['category'] ?? 'สถานที่') ?></span></td>
                                    <td class="fw-bold text-white"><i class="bi bi-chat-right-text-fill text-info me-2"></i><?= number_format($row['total_clicks']) ?> ครั้ง</td>
                                    <td><i class="bi bi-star-fill text-warning me-1"></i> <?= number_format(floatval($row['avg_rating']), 1) ?></td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">ไม่พบข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane-custom" id="tab-reviews">
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 140px;">ประเภท</th>
                            <th style="width: 100px;">ID อ้างอิง</th>
                            <th style="width: 120px;">คะแนน</th>
                            <th>ความคิดเห็น</th>
                            <th style="width: 180px;">วันที่</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $q_all_rev = $conn->query("
                            (SELECT 'สถานที่' as type, place_id as ref_id, rating_score, review_text, created_at FROM nearby_place_reviews $where_review_date)
                            UNION ALL
                            (SELECT 'ร้านอาหาร' as type, restaurant_id as ref_id, rating_score, review_text, created_at FROM restaurant_reviews $where_review_date)
                            ORDER BY created_at DESC LIMIT 50
                        ");
                        if ($q_all_rev && $q_all_rev->num_rows > 0) {
                            while ($row = $q_all_rev->fetch_assoc()) {
                                $rating = intval($row['rating_score'] ?? 5);
                                $stars = str_repeat('<i class="bi bi-star-fill text-warning"></i> ', $rating) . str_repeat('<i class="bi bi-star text-muted"></i> ', 5 - $rating);
                                $badge = ($row['type'] == 'สถานที่') ? "<span class='badge bg-info text-dark'><i class='bi bi-geo-alt-fill'></i> สถานที่</span>" : "<span class='badge bg-warning text-dark'><i class='bi bi-shop'></i> ร้านอาหาร</span>";
                                echo "<tr>
                                        <td>{$badge}</td>
                                        <td>#{$row['ref_id']}</td>
                                        <td>{$stars}</td>
                                        <td style='font-style: italic; color: var(--txt);'>\"" . htmlspecialchars($row['review_text'] ?? '') . "\"</td>
                                        <td class='text-muted'>" . date('d M Y H:i', strtotime($row['created_at'])) . "</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center py-4 text-muted'>ไม่พบประวัติการรีวิวในช่วงเวลานี้</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane-custom" id="tab-missing-ar">
            <div class="alert alert-info mb-3" style="background: rgba(77, 159, 255, 0.1); border: 1px solid var(--border); color: var(--blue);">
                <i class="bi bi-info-circle-fill me-2"></i> รายงานวิเคราะห์ความสมบูรณ์ของฐานข้อมูล (แสดงองค์พระพิฆเนศที่ยัง <b>ไม่มีสื่อ AR 3D Model หรือ Audio</b> แนบไว้)
            </div>
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Info ID</th>
                            <th>องค์พระพิฆเนศ</th>
                            <th>สถานะ 3D Model</th>
                            <th>สถานะ Audio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_missing_ar = "SELECT g.info_id, g.title_ganesha, 
                                           MAX(CASE WHEN a.media_type = 'model' OR a.file_path IS NOT NULL THEN 1 ELSE 0 END) as has_model,
                                           MAX(CASE WHEN a.audio_file IS NOT NULL THEN 1 ELSE 0 END) as has_audio
                                           FROM ganesha_info g 
                                           LEFT JOIN ar_media a ON g.info_id = a.info_id 
                                           GROUP BY g.info_id
                                           HAVING has_model = 0 OR has_audio = 0";
                        $res_missing = $conn->query($sql_missing_ar);
                        if ($res_missing && $res_missing->num_rows > 0):
                            while ($row = $res_missing->fetch_assoc()):
                                $mdl_status = $row['has_model'] ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> มีไฟล์</span>' : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> ขาดไฟล์</span>';
                                $aud_status = $row['has_audio'] ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> มีไฟล์</span>' : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> ขาดไฟล์</span>';
                        ?>
                                <tr>
                                    <td>#<?= $row['info_id'] ?></td>
                                    <td class="fw-semibold text-white"><?= htmlspecialchars($row['title_ganesha']) ?></td>
                                    <td><?= $mdl_status ?></td>
                                    <td><?= $aud_status ?></td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-success"><i class="bi bi-check-circle-fill"></i> ข้อมูลสื่อ AR ในระบบสมบูรณ์ครบทุกองค์แล้ว</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane-custom" id="tab-sentiment">
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 150px;">ระดับคะแนน</th>
                            <th>ดาวความพึงพอใจ</th>
                            <th>จำนวนรีวิวที่ได้รับ</th>
                            <th>คิดเป็นเปอร์เซ็นต์</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_sentiment = "SELECT rating_score, COUNT(*) as count FROM (
                                            SELECT rating_score FROM nearby_place_reviews $where_review_date
                                            UNION ALL
                                            SELECT rating_score FROM restaurant_reviews $where_review_date
                                          ) as all_reviews GROUP BY rating_score ORDER BY rating_score DESC";
                        $res_sentiment = $conn->query($sql_sentiment);
                        $total_sen_rev = 0;
                        $sen_data = [];
                        if ($res_sentiment) {
                            while ($r = $res_sentiment->fetch_assoc()) {
                                $sen_data[$r['rating_score']] = $r['count'];
                                $total_sen_rev += $r['count'];
                            }
                        }

                        if ($total_sen_rev > 0):
                            for ($i = 5; $i >= 1; $i--):
                                $count = $sen_data[$i] ?? 0;
                                $percent = ($count / $total_sen_rev) * 100;
                                $stars = str_repeat('<i class="bi bi-star-fill text-warning"></i> ', $i) . str_repeat('<i class="bi bi-star text-muted"></i> ', 5 - $i);
                        ?>
                                <tr>
                                    <td class="fw-bold text-white"><?= $i ?> ดาว</td>
                                    <td><?= $stars ?></td>
                                    <td><?= number_format($count) ?> รีวิว</td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <div style="flex:1; background:rgba(255,255,255,0.1); height:8px; border-radius:4px; overflow:hidden;">
                                                <div style="width:<?= $percent ?>%; background:var(--gold); height:100%;"></div>
                                            </div>
                                            <span style="font-size:0.75rem; color:var(--gold);"><?= number_format($percent, 1) ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endfor;
                        else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">ไม่พบข้อมูลคะแนนรีวิว</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </section>

    </main>

    <script>
        const chartLabels = <?= $json_labels ?>;
        const chartData = <?= $json_data ?>;

        const ganeshaChartEl = document.getElementById('ganeshaChart');
        if (ganeshaChartEl) {
        new Chart(ganeshaChartEl.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'ปริมาณการส่งรีวิวรวมรายเดือน (ปี <?= $filter_year ?>)',
                    data: chartData,
                    borderColor: '#c9a84c',
                    backgroundColor: 'rgba(201, 168, 76, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#e8e6f0'
                        }
                    }
                },
                scales: {
                    y: {
                        grid: {
                            color: 'rgba(255,255,255,0.05)'
                        },
                        ticks: {
                            color: '#7a7a96',
                            beginAtZero: true,
                            callback: function(value) {
                                if (value % 1 === 0) {
                                    return value;
                                }
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#7a7a96'
                        }
                    }
                }
            }
        });
        }

        const arPieCtx = document.getElementById('arPieChart');
        if (arPieCtx) {
            new Chart(arPieCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['เริ่มใช้ AR', 'รับชมโมเดล'],
                    datasets: [{
                        data: [<?= $countARStart ?>, <?= $countViewModel ?>],
                        backgroundColor: ['#4d9fff', '#8b5cf6'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#e8e6f0', padding: 14 }
                        }
                    }
                }
            });
        }

        const arBarCtx = document.getElementById('arBarChart');
        if (arBarCtx) {
            new Chart(arBarCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= $json_labels_ar ?>,
                    datasets: [{
                        label: 'จำนวนการรับชม (ครั้ง)',
                        data: <?= $json_data_ar ?>,
                        backgroundColor: '#8b5cf6',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#7a7a96', stepSize: 1 },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        },
                        x: {
                            ticks: { color: '#e8e6f0', maxRotation: 45, minRotation: 0 },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    </script>

    <script>
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
    </script>

    <script>
        function exportTableToCSV(filename) {
            // หา Tab ที่กำลังเปิดอยู่ (active)
            var activePane = document.querySelector('.tab-pane-custom.active');
            if (!activePane) return;

            var table = activePane.querySelector('table');
            if (!table) {
                alert('ไม่พบข้อมูลตารางให้ส่งออกในแท็บนี้');
                return;
            }

            var csv = [];
            var rows = table.querySelectorAll('tr');

            for (var i = 0; i < rows.length; i++) {
                var row = [],
                    cols = rows[i].querySelectorAll('td, th');

                for (var j = 0; j < cols.length; j++) {
                    // ลบข้อความที่เกิดจากไอคอน (ถ้ามี) แล้วดึงเฉพาะ Text
                    var data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                    // Escape เครื่องหมาย " 
                    data = data.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                csv.push(row.join(','));
            }

            // นำข้อมูลลงไฟล์ CSV (ใช้ BOM uFEFF เพื่อให้ Excel อ่านภาษาไทยได้ถูกต้อง)
            var csvFile = new Blob(["\uFEFF" + csv.join('\n')], {
                type: 'text/csv;charset=utf-8;'
            });
            var downloadLink = document.createElement('a');
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    </script>
    <script>
        function logAccess(action, id = null) {
            let formData = new FormData();
            formData.append('action', action);
            if (id) formData.append('id', id);
            fetch('log_access.php', {
                method: 'POST',
                body: formData
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>