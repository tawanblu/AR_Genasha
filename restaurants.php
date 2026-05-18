<?php
session_start();
require_once("connect.php");

// ตั้งค่าโซนเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');
$current_time = date('H:i:s');

// 1. ดึงข้อมูลร้านอาหารทั้งหมดมาก่อน
$sql = "SELECT * FROM restaurant ORDER BY restaurant_id DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Query Failed: " . $conn->error);
}
$all_restaurants = $result->fetch_all(MYSQLI_ASSOC);

// -------------------------------------------------------------------------
// 2. ดึงรูปภาพทั้งหมด และ "กรองเฉพาะรูปที่มีไฟล์อยู่จริงในโฟลเดอร์"
$sql_all_images = "SELECT restaurant_id, file_path FROM restaurant_image ORDER BY image_id ASC";
$res_all_images = $conn->query($sql_all_images);
$images_by_res = [];

if ($res_all_images) {
    while ($img = $res_all_images->fetch_assoc()) {
        $rid = $img['restaurant_id'];
        $filepath = $img['file_path'];

        if (file_exists("Restaurants/" . $filepath)) {
            if (!isset($images_by_res[$rid])) {
                $images_by_res[$rid] = [];
            }
            $images_by_res[$rid][] = $filepath;
        }
    }
}

// 3. นำรูปแรกที่ใช้งานได้ ไปตั้งเป็น "รูปหน้าปก (Cover)" ให้แต่ละร้าน
foreach ($all_restaurants as $key => $res) {
    $rid = $res['restaurant_id'];
    if (!empty($images_by_res[$rid])) {
        $all_restaurants[$key]['image'] = $images_by_res[$rid][0];
    } else {
        $all_restaurants[$key]['image'] = 'default.jpg';
    }
}
// -------------------------------------------------------------------------

// 4. ดึงรีวิวทั้งหมดและชื่อผู้ใช้
$sql_reviews = "
    SELECT r.*, COALESCE(a.username, 'ผู้ใช้ทั่วไป') AS username 
    FROM restaurant_reviews r 
    LEFT JOIN accounts a ON r.id_account = a.id_account 
    ORDER BY r.created_at DESC
";
$res_reviews = $conn->query($sql_reviews);

if (!$res_reviews) {
    $sql_reviews = "SELECT *, 'ผู้ใช้ทั่วไป' AS username FROM restaurant_reviews ORDER BY created_at DESC";
    $res_reviews = $conn->query($sql_reviews);
}

$all_reviews = ($res_reviews) ? $res_reviews->fetch_all(MYSQLI_ASSOC) : [];

// จัดกลุ่มรีวิวตาม restaurant_id
$reviews_by_res = [];
foreach ($all_reviews as $review) {
    $rid = $review['restaurant_id'];
    if (!isset($reviews_by_res[$rid])) {
        $reviews_by_res[$rid] = [];
    }
    $reviews_by_res[$rid][] = $review;
}

// กำหนดหมวดหมู่สำหรับทำปุ่ม Filter
$restaurant_categories = ['อาหารตามสั่ง', 'ก๋วยเตี๋ยว', 'คาเฟ่ / ของหวาน', 'ปิ้งย่าง / ชาบู', 'อาหารอีสาน', 'อาหารฟาสต์ฟู้ด', 'อื่นๆ'];

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Restaurant - AR Ganesha</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700;900&family=Sarabun:wght@300;400;600&family=Cinzel:wght@400;600&family=Raleway:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">

    <style>
        body {
            background-color: #000000;
            color: #333;
            font-family: 'Sarabun', sans-serif;
        }

        .nav-link {
            color: #fff !important;
        }


        .navbar-toggler {
            transition: all 0.3s ease;
        }

        .navbar-toggler:hover {
            transform: scale(1.05);
            box-shadow: 0 0 12px rgba(212, 175, 55, 0.4);
            border-color: #D4AF37;
        }

        .page-title {
            color: #D4AF37;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 0.2rem;
        }

        .filter-btn {
            border-radius: 50px;
            padding: 8px 24px;
            border: none;
            background: white;
            color: #000000;
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.3s;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .filter-btn:hover {
            background-color: #f0f0f0;
        }

        .filter-btn.active {
            background-color: #D4AF37;
            color: white;
        }

        .place-card {
            border-radius: 20px;
            overflow: hidden;
            border: none;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
            background: black;
            transition: transform 0.3s ease;
            border: 1px solid #222;
        }

        .place-card:hover {
            transform: translateY(-6px);
            border-color: #D4AF37;
        }

        .card-img-top {
            height: 220px;
            object-fit: cover;
            border-radius: 20px 20px 0 0;
        }

        .rating-select {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-start;
            gap: 5px;
        }

        .rating-select input {
            display: none;
        }

        .rating-select label {
            cursor: pointer;
            font-size: 1.25rem;
            color: #444;
            transition: color 0.2s;
        }

        .rating-select label:hover,
        .rating-select label:hover~label,
        .rating-select input:checked~label {
            color: #D4AF37;
        }

        .rating-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            color: #333;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .rating-badge i {
            color: #A67B5B;
        }

        .time-tag {
            font-weight: 700;
            font-size: 0.9rem;
        }

        .cat-chip {
            display: inline-block;
            background-color: #D4AF37;
            color: #000000;
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .detail-quote {
            background-color: #000000;
            border-left: 3px solid #D4AF37;
            padding: 10px 15px;
            border-radius: 0 8px 8px 0;
            font-style: italic;
            font-size: 0.85rem;
            color: #ffffff;
            margin-bottom: 20px;
        }

        .btn-view {
            background-color: #c9a03c;
            border: 1px solid #c9a03c;
            color: #000000;
            border-radius: 50px;
            width: 100%;
            font-weight: 700;
            padding: 10px;
            transition: 0.3s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view:hover {
            background-color: #e8c97a;
            color: black;
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.4);
        }

        .place-item {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .review-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .review-scroll::-webkit-scrollbar-track {
            background: #1a1a1a;
            border-radius: 10px;
        }

        .review-scroll::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 10px;
        }

        .review-scroll::-webkit-scrollbar-thumb:hover {
            background: #D4AF37;
        }

        #modalCarousel {
            background-color: #000;
            border-bottom: 2px solid #333;
        }

        .carousel-item img {
            height: 350px;
            object-fit: cover;
        }

        /* --- Footer Styles Moved to Head --- */
        .footer-wrap {
            background: #0a0a0a;
            color: #ccc;
            font-family: 'Raleway', sans-serif;
            font-weight: 300;
            padding: 0;
            border-top: 1px solid #2a2a2a;
        }

        .footer-top {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr;
            gap: 0;
            border-bottom: 1px solid #1e1e1e;
        }

        .footer-col {
            padding: 3rem 2.5rem;
            border-right: none;
        }

        .brand-name {
            font-family: 'Cinzel', serif;
            font-size: 1.6rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            background: linear-gradient(135deg, #c9a84c 0%, #f0d080 50%, #c9a84c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 0.4rem;
            line-height: 1;
        }

        .brand-tagline {
            font-size: 0.72rem;
            letter-spacing: 0.3em;
            color: #666;
            text-transform: uppercase;
            margin: 0 0 2rem;
        }

        .follow-label {
            font-size: 0.65rem;
            letter-spacing: 0.35em;
            color: #c9a84c;
            text-transform: uppercase;
            margin: 0 0 0.8rem;
        }

        .social-row {
            display: flex;
            gap: 0.6rem;
        }

        .social-link {
            width: 36px;
            height: 36px;
            border: 1px solid #2a2a2a;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .social-link:hover {
            border-color: #c9a84c;
            color: #c9a84c;
        }

        .col-title {
            font-family: 'Cinzel', serif;
            font-size: 1rem;
            letter-spacing: 0.35em;
            color: #c9a84c;
            text-transform: uppercase;
            margin: 0 0 1.6rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #1e1e1e;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.82rem;
            color: #888;
            line-height: 1.5;
        }

        .contact-item svg {
            width: 14px;
            height: 14px;
            color: #c9a84c;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .nav-link-ft {
            display: block;
            font-size: 0.82rem;
            color: #777;
            text-decoration: none;
            padding: 0.45rem 0;
            letter-spacing: 0.05em;
            transition: all 0.25s;
        }

        .nav-link-ft:hover {
            color: #c9a84c;
            padding-left: 0.5rem;
        }

        .footer-bottom {
            padding: 1.4rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .copyright {
            font-size: 0.72rem;
            color: #444;
            letter-spacing: 0.05em;
        }

        .gold-line {
            width: 40px;
            height: 1px;
            background: linear-gradient(90deg, transparent, #c9a84c, transparent);
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .footer-top {
                grid-template-columns: 1fr;
            }

            .footer-col {
                padding: 2rem 1.5rem;
                border-bottom: 1px solid #1e1e1e;
            }

            .footer-bottom {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }

            .page-title {
                font-size: 1.65rem;
            }

            .card-img-top {
                height: 190px;
            }

            .filter-btn {
                font-size: 0.85rem;
                padding: 7px 16px;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <div class="sidebar-logo">
                <div class="logo-title">AR Ganesha</div>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto text-center align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link text-warning" href="restaurants.php">Restaurant</a></li>
                    <li class="nav-item"><a class="nav-link" href="place.php">Place</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>

                    <?php if (isset($_SESSION['username'])): ?>
                        <li class="nav-item ms-lg-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <span class="text-warning me-3 small">
                                    <i class="fa-solid fa-user me-1"></i>
                                    <?= htmlspecialchars($_SESSION['username']) ?>
                                </span>
                                <a href="logout.php" class="btn btn-outline-danger btn-sm px-3 rounded-pill">Logout</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-3">
                            <a href="Backend/login.php" class="btn btn-warning btn-sm px-4 fw-bold rounded-pill text-dark">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero-banner p-0 m-0 rounded-0">
        <div class="banner-grid p-0 m-0 rounded-0">
            <img src="image/Restaurantbanner.png" alt="Banner Image" class="img-fluid w-100 rounded-0"
                style="height: 60vh; object-fit: cover; width: 100vw; display: block; border-radius: 0 !important;">
        </div>
    </section>

    <div style="margin-top: 70px;" class="container pb-5">

        <div class="mb-4 text-center">
            <h1 class="page-title">Restaurants</h1>
            <p style="color: #999;">แนะนำร้านอาหารน่าสนใจบริเวณมหาวิทยาลัย</p>
        </div>

        <div class="d-flex justify-content-lg-center justify-content-start gap-2 mb-5 overflow-auto pb-2 px-3 category-container" style="scrollbar-width: none; -webkit-overflow-scrolling: touch;">
            <?php foreach ($restaurant_categories as $cat): ?>
                <button class="filter-btn" onclick="filterType('<?= htmlspecialchars($cat) ?>', this)">
                    <?= htmlspecialchars($cat) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div id="no-data-message" class="text-center w-100 py-5" style="display: none;">
            <i class="fa-solid fa-utensils fs-1 mb-3" style="color: #555;"></i>
            <h4 class="text-white fw-bold">ยังไม่มีร้านอาหารในหมวดหมู่นี้</h4>
            <p style="color: #999;">ลองเลือกดูหมวดหมู่ที่น่าสนใจอื่นๆ ด้านบนแทนนะครับ</p>
        </div>

        <div class="row g-4" id="restaurant-grid">
            <?php foreach ($all_restaurants as $res):

                // ระบบคำนวณ เปิด/ปิด
                $open_time = $res['open_time'] ?? '00:00:00';
                $close_time = $res['close_time'] ?? '00:00:00';
                $is_open = false;

                if ($open_time != '00:00:00' || $close_time != '00:00:00') {
                    if ($open_time < $close_time) {
                        if ($current_time >= $open_time && $current_time <= $close_time) {
                            $is_open = true;
                        }
                    } else {
                        if ($current_time >= $open_time || $current_time <= $close_time) {
                            $is_open = true;
                        }
                    }
                }

                if ($is_open) {
                    $status_text = 'เปิดอยู่';
                    $status_color = '#28a745';
                    $status_icon = 'fa-regular fa-clock';
                } else {
                    $status_text = 'ปิดแล้ว';
                    $status_color = '#dc3545';
                    $status_icon = 'fa-regular fa-clock';
                }

                // คำนวณคะแนนเฉลี่ยจากรีวิว
                $restaurant_id = $res['restaurant_id'];
                $r_reviews = $reviews_by_res[$restaurant_id] ?? [];
                $review_count = count($r_reviews);
                $total_score = 0;

                if ($review_count > 0) {
                    foreach ($r_reviews as $r) {
                        $total_score += (int)$r['rating_score'];
                    }
                    $avg_rating = number_format($total_score / $review_count, 1);
                } else {
                    $avg_rating = "0.0";
                }

                // ใช้ JSON Encode แบบระบุ Flag เพื่อความปลอดภัยเวลาแทรกลง HTML Attributes
                $res_reviews_json = json_encode($r_reviews, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                $res_images_json = json_encode($images_by_res[$restaurant_id] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
            ?>
                <!-- ใส่ data-category ทั้งก้อนซ่อนไว้ให้ JS ค้นหา -->
                <div class="col-12 col-md-6 col-lg-4 place-item" data-category="<?= htmlspecialchars($res['category'] ?? 'ไม่ระบุ') ?>" style="display: none;">

                    <div class="card place-card h-100">
                        <div class="position-relative">
                            <img src="Restaurants/<?= htmlspecialchars($res['image']) ?>" class="card-img-top w-100" onerror="this.onerror=null; this.src='image/default.jpg';">
                            <div class="rating-badge"><i class="fa fa-star me-1"></i> <?= $avg_rating ?></div>
                        </div>

                        <div class="card-body d-flex flex-column p-4">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h5 class="fw-bold mb-0 text-truncate" style="color: #ffffff; max-width: 65%;" title="<?= htmlspecialchars($res['restaurant_name']) ?>">
                                    <?= htmlspecialchars($res['restaurant_name']) ?>
                                </h5>
                                <span class="time-tag" style="color: <?= $status_color ?>; flex-shrink: 0;">
                                    <i class="fa-solid <?= $status_icon ?>"></i> <?= $status_text ?>
                                </span>
                            </div>

                            <!-- หั่น String ด้วยลูกน้ำเพื่อทำป้าย Badge -->
                            <div class="d-flex flex-wrap gap-1 mb-3 mt-2">
                                <?php
                                $cats = explode(',', $res['category'] ?? 'ไม่ระบุ');
                                foreach ($cats as $c):
                                    if (trim($c) != ''):
                                ?>
                                        <span class="cat-chip"><i class="fa-solid fa-utensils me-1"></i> <?= htmlspecialchars(trim($c)) ?></span>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>

                            <div class="detail-quote mt-auto">
                                "<?= mb_strimwidth(htmlspecialchars($res['detail'] ?? 'ไม่มีรายละเอียด'), 0, 60, '...') ?>"
                            </div>

                            <button type="button" class="btn-view mt-3"
                                data-bs-toggle="modal"
                                data-bs-target="#resDetailModal"
                                data-id="<?= $res['restaurant_id'] ?>"
                                data-name="<?= htmlspecialchars($res['restaurant_name']) ?>"
                                data-category="<?= htmlspecialchars($res['category'] ?? 'ไม่ระบุ') ?>"
                                data-mapurl="<?= htmlspecialchars($res['map_url'] ?? '') ?>"
                                data-status-text="<?= $status_text ?>"
                                data-status-color="<?= $status_color ?>"
                                data-time="<?= substr($open_time, 0, 5) ?> - <?= substr($close_time, 0, 5) ?>"
                                data-rating="<?= $avg_rating ?>"
                                data-images='<?= $res_images_json ?>'
                                data-detail="<?= htmlspecialchars($res['detail'] ?? 'ไม่มีรายละเอียด') ?>"
                                data-reviews='<?= $res_reviews_json ?>'>
                                View Details & Review
                            </button>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5" id="loadMoreContainer" style="display: none;">
            <button id="loadMoreBtn" class="btn text-dark fw-bold px-4 py-2" style="background-color: #c9a03c; border-radius: 25px; transition: 0.3s;">
                โหลดร้านเพิ่ม
            </button>
        </div>

    </div>

    <!-- Modal Review -->
    <div class="modal fade" id="resDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="background-color: #111; border: 1px solid #333; border-radius: 20px; overflow: hidden;">

                <div class="modal-header border-0 pb-0 position-absolute w-100 z-3" style="top: 0;">
                    <button type="button" class="btn text-white bg-dark bg-opacity-75 m-3 rounded-circle d-flex align-items-center justify-content-center"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        style="width: 42px; height: 42px; border: 1px solid rgba(255, 255, 255, 0.25); transition: all 0.2s ease;"
                        onmouseover="this.style.color='#c9a84c'; this.style.borderColor='#c9a84c'; this.style.transform='scale(1.05)';"
                        onmouseout="this.style.color='#fff'; this.style.borderColor='rgba(255, 255, 255, 0.25)'; this.style.transform='scale(1)';">
                        <i class="fa-solid fa-xmark fs-5"></i>
                    </button>
                </div>

                <div class="modal-body p-0">

                    <div id="modalCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-indicators" id="modal-carousel-indicators"></div>
                        <div class="carousel-inner" id="modal-carousel-inner" style="height: 350px;"></div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#modalCarousel" data-bs-slide="prev" id="modal-carousel-prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#modalCarousel" data-bs-slide="next" id="modal-carousel-next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>

                    <div class="p-4 p-md-5 text-white">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 id="modal-name" class="fw-bold" style="color: #D4AF37;">ชื่อร้านอาหาร</h2>
                            <div class="rating-badge position-static mt-1"><i class="fa fa-star me-1"></i> <span id="modal-rating">0.0</span></div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-4 mt-3">
                            <span id="modal-category-container" class="d-flex flex-wrap gap-2"></span>
                            <span class="badge" style="background-color: #222; font-size: 0.9rem; padding: 10px 15px; border: 1px solid #444;">
                                <i class="fa-regular fa-clock text-warning me-2"></i><span id="modal-time">00:00 - 00:00</span>
                            </span>
                            <a href="#" id="modal-map-link" target="_blank" class="badge text-decoration-none" style="background-color: #D4AF37; color: #000; font-size: 0.9rem; padding: 10px 15px;">
                                <i class="fa-solid fa-map-location-dot me-2"></i>ดูตำแหน่งแผนที่
                            </a>
                        </div>

                        <h5 class="fw-bold mb-3 border-bottom border-secondary pb-2">รายละเอียดร้าน</h5>
                        <p id="modal-detail" style="color: #cccccc; line-height: 1.8; font-size: 1.05rem;"></p>

                        <h5 class="fw-bold mt-5 mb-3 border-bottom border-secondary pb-2" style="color: #D4AF37;">
                            <i class="fa-solid fa-comments me-2"></i>ความคิดเห็นก่อนหน้า
                        </h5>

                        <div id="modal-reviews-container" class="review-scroll pe-2 mb-4" style="max-height: 300px; overflow-y: auto;"></div>

                        <div class="p-4 rounded-4" style="background-color: #1a1a1a; border: 1px solid #333;">
                            <h5 class="fw-bold mb-3 text-white"><i class="fa-solid fa-pen-to-square me-2"></i>เขียนรีวิวของคุณ</h5>

                            <?php if (isset($_SESSION['username'])): ?>
                                <form action="save_review.php" method="POST" class="review-form">
                                    <input type="hidden" name="restaurant_id" id="modal-restaurant-id" value="">

                                    <div class="rating-select mb-3 d-flex justify-content-start">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating_score" value="<?= $i ?>"
                                                id="modal-star<?= $i ?>" class="btn-check" required>
                                            <label class="btn btn-sm btn-outline-secondary border-0" for="modal-star<?= $i ?>">
                                                <i class="fa-solid fa-star fs-4"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>

                                    <div class="mb-3">
                                        <textarea name="review_text"
                                            class="form-control bg-dark text-white border-secondary"
                                            placeholder="แบ่งปันประสบการณ์เกี่ยวกับร้านอาหารนี้..."
                                            rows="3"
                                            style="font-size: 0.95rem; border-radius: 8px;"
                                            required></textarea>
                                    </div>
                                    <button type="submit" class="btn w-100 fw-bold rounded-pill text-dark" style="background-color: #D4AF37; transition: 0.3s;">
                                        ส่งรีวิว
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="text-center p-3">
                                    <i class="fa-solid fa-lock text-secondary mb-2" style="font-size: 1.5rem;"></i><br>
                                    <span class="text-secondary small">กรุณาเข้าสู่ระบบเพื่อแสดงความคิดเห็น</span><br>
                                    <a href="Backend/login.php?redirect=../restaurants.php" class="btn btn-sm btn-outline-warning rounded-pill mt-3 px-4">
                                        เข้าสู่ระบบ
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-wrap" id="contact">
        <div class="footer-top">
            <div class="footer-col">
                <p class="brand-name">AR Ganesha</p>
                <p class="brand-tagline">Phetchaburi</p>
                <p class="follow-label">Follow us</p>
                <div class="social-row">
                    <a href="#" class="social-link"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                        </svg></a>
                    <a href="#" class="social-link"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                            <path d="M11.998 0C5.374 0 .002 5.372.002 11.996c0 2.117.555 4.1 1.523 5.818L.004 23.998l6.337-1.663A11.954 11.954 0 0012 23.993C18.626 23.993 24 18.622 24 12c0-6.624-5.374-12-12.002-12z" />
                        </svg></a>
                    <a href="#" class="social-link"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                            <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                        </svg></a>
                </div>
            </div>
            <div class="footer-col">
                <p class="col-title">Contact</p>
                <div class="contact-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                    <span>ganeshaAR@silpakorn.ac.th</span>
                </div>
                <div class="contact-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-top: 3px;">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <span style="color: #c9a84c; font-weight: 600; letter-spacing: 0.05em;">จุดสแกน AR</span>
                        <span style="line-height: 1.5; color: #888;">- มหาวิทยาลัยศิลปากร วิทยาเขตสารสนเทศเพชรบุรี</span>
                    </div>
                </div>
            </div>
            <div class="footer-col">
                <p class="col-title">Explore</p>
                <a href="index.php" class="nav-link-ft">Home</a>
                <a href="restaurants.php" class="nav-link-ft">Restaurant</a>
                <a href="place.php" class="nav-link-ft">Place</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span class="copyright">© 2024 AR Ganesha. All rights reserved.</span>
            <div class="gold-line"></div>
            <span class="copyright">Silpakorn University</span>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return (text || "").toString().replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }

        document.addEventListener("DOMContentLoaded", function() {
            const placeItems = document.querySelectorAll(".place-item");
            const loadMoreBtn = document.getElementById("loadMoreBtn");
            const loadMoreContainer = document.getElementById("loadMoreContainer");
            const noDataMessage = document.getElementById("no-data-message");

            let visibleLimit = 6;
            const increment = 6;
            let currentFilter = 'all';

            function updateDisplay() {
                let matchedItems = [];
                let displayedCount = 0;

                placeItems.forEach(item => {
                    const itemCat = item.getAttribute('data-category');
                    if (currentFilter === 'all' || itemCat.includes(currentFilter)) {
                        matchedItems.push(item);
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (matchedItems.length === 0) {
                    if (noDataMessage) noDataMessage.style.display = 'block';
                    if (loadMoreContainer) loadMoreContainer.style.display = 'none';
                } else {
                    if (noDataMessage) noDataMessage.style.display = 'none';
                    matchedItems.forEach((item, index) => {
                        if (index < visibleLimit) {
                            item.style.display = 'block';
                            displayedCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    if (loadMoreContainer) {
                        if (matchedItems.length <= displayedCount) {
                            loadMoreContainer.style.display = 'none';
                        } else {
                            loadMoreContainer.style.display = 'block';
                        }
                    }
                }
            }

            updateDisplay();

            if (loadMoreBtn) {
                loadMoreBtn.addEventListener("click", function() {
                    visibleLimit += increment;
                    updateDisplay();
                });
            }

            window.filterType = function(cat, btn) {
                currentFilter = cat;
                visibleLimit = 6;
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                updateDisplay();
            };

            const resDetailModal = document.getElementById('resDetailModal');
            if (resDetailModal) {
                resDetailModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const name = button.getAttribute('data-name');
                    const category = button.getAttribute('data-category');
                    const mapUrl = button.getAttribute('data-mapurl');
                    const time = button.getAttribute('data-time');
                    const statusText = button.getAttribute('data-status-text');
                    const statusColor = button.getAttribute('data-status-color');
                    const detail = button.getAttribute('data-detail');
                    const rating = button.getAttribute('data-rating');
                    const reviewsJson = button.getAttribute('data-reviews');
                    const imagesJson = button.getAttribute('data-images');

                    resDetailModal.querySelector('#modal-name').textContent = name;
                    resDetailModal.querySelector('#modal-time').innerHTML = `<span style="color: ${statusColor}; font-weight: bold;">${statusText}</span> (${time})`;
                    resDetailModal.querySelector('#modal-detail').textContent = detail;
                    resDetailModal.querySelector('#modal-rating').textContent = rating;

                    const modalCatContainer = resDetailModal.querySelector('#modal-category-container');
                    modalCatContainer.innerHTML = '';
                    category.split(',').forEach(c => {
                        if (c.trim() !== '') {
                            modalCatContainer.innerHTML += `<span class="badge" style="background-color: #222; font-size: 0.9rem; padding: 10px 15px; border: 1px solid #444;"><i class="fa-solid fa-utensils text-danger me-2"></i>${escapeHtml(c.trim())}</span>`;
                        }
                    });

                    const mapLink = resDetailModal.querySelector('#modal-map-link');
                    if (mapUrl && mapUrl !== '') {
                        mapLink.href = mapUrl;
                        mapLink.style.display = 'inline-block';
                    } else {
                        mapLink.style.display = 'none';
                    }

                    const modalResIdInput = resDetailModal.querySelector('#modal-restaurant-id');
                    if (modalResIdInput) {
                        modalResIdInput.value = id;
                    }

                    const carouselInner = resDetailModal.querySelector('#modal-carousel-inner');
                    const carouselIndicators = resDetailModal.querySelector('#modal-carousel-indicators');
                    const prevBtn = resDetailModal.querySelector('#modal-carousel-prev');
                    const nextBtn = resDetailModal.querySelector('#modal-carousel-next');

                    carouselInner.innerHTML = '';
                    carouselIndicators.innerHTML = '';

                    try {
                        const images = JSON.parse(imagesJson || '[]');
                        if (images.length === 0) {
                            carouselInner.innerHTML = `
                                <div class="carousel-item active h-100">
                                    <img src="image/default.jpg" class="d-block w-100 h-100" style="object-fit: cover;">
                                </div>
                            `;
                            prevBtn.style.display = 'none';
                            nextBtn.style.display = 'none';
                        } else {
                            images.forEach((imgPath, index) => {
                                const isActive = index === 0 ? 'active' : '';
                                carouselIndicators.innerHTML += `
                                    <button type="button" data-bs-target="#modalCarousel" data-bs-slide-to="${index}" class="${isActive}"></button>
                                `;
                                carouselInner.innerHTML += `
                                    <div class="carousel-item ${isActive} h-100">
                                        <img src="Restaurants/${escapeHtml(imgPath)}" onerror="this.src='image/default.jpg';" class="d-block w-100 h-100" style="object-fit: cover;">
                                    </div>
                                `;
                            });
                            if (images.length > 1) {
                                prevBtn.style.display = 'block';
                                nextBtn.style.display = 'block';
                            } else {
                                prevBtn.style.display = 'none';
                                nextBtn.style.display = 'none';
                            }
                        }
                    } catch (e) {
                        console.error('Error parsing images:', e);
                    }

                    const reviewsContainer = resDetailModal.querySelector('#modal-reviews-container');
                    reviewsContainer.innerHTML = '';

                    try {
                        const reviews = JSON.parse(reviewsJson || '[]');
                        if (reviews.length === 0) {
                            reviewsContainer.innerHTML = '<div class="text-center text-secondary py-3" style="background-color: #1a1a1a; border-radius: 10px; border: 1px dashed #333;"><i class="fa-regular fa-comment-dots fs-3 mb-2"></i><br>ยังไม่มีรีวิวสำหรับร้านอาหารนี้ มารีวิวเป็นคนแรกสิ!</div>';
                        } else {
                            reviews.forEach(review => {
                                let starsHtml = '';
                                const score = parseInt(review.rating_score) || 0;
                                for (let i = 1; i <= 5; i++) {
                                    if (i <= score) {
                                        starsHtml += '<i class="fa-solid fa-star text-warning small"></i>';
                                    } else {
                                        starsHtml += '<i class="fa-regular fa-star text-secondary small"></i>';
                                    }
                                }

                                const dateStrDb = review.created_at || '';
                                const safeDateStr = dateStrDb.replace(' ', 'T');
                                const dateObj = new Date(safeDateStr);

                                const dateStr = isNaN(dateObj) ? 'ไม่ระบุวันที่' : dateObj.toLocaleDateString('th-TH', {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric'
                                });

                                reviewsContainer.innerHTML += `
                                    <div class="card bg-dark border-secondary mb-3" style="border-radius: 12px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <i class="fa-solid fa-circle-user fs-5 me-2 text-secondary"></i>
                                                    <span class="text-white fw-bold" style="font-size: 0.95rem;">${escapeHtml(review.username)}</span>
                                                </div>
                                                <div>${starsHtml}</div>
                                            </div>
                                            <p class="text-light mb-1" style="font-size: 0.9rem; line-height: 1.6;">${escapeHtml(review.review_text)}</p>
                                            <div class="text-secondary text-end mt-2" style="font-size: 0.75rem;">
                                                <i class="fa-regular fa-calendar me-1"></i> ${dateStr}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                    } catch (e) {
                        console.error('Error parsing reviews:', e);
                        reviewsContainer.innerHTML = '<div class="text-center text-danger py-3">เกิดข้อผิดพลาดในการโหลดรีวิว</div>';
                    }
                });
            }
        });
    </script>
</body>

</html>