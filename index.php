<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// เปิดใช้งานระบบ Session ของ PHP เพื่อให้เว็บจำค่าต่างๆ ของผู้ใช้ได้ เช่น การล็อกอิน หรือข้อมูลที่ต้องการเก็บไว้ระหว่างการใช้งาน
session_start();

// ปิดการแสดง error บนหน้าเว็บช่วยป้องกันการเปิดเผยข้อมูลสำคัญของระบบ แต่ทำให้การ debug ยากขึ้น
// ini_set('display_errors', 0);
// error_reporting(0);

require_once("connect.php"); // เชื่อมต่อฐานข้อมูลrequire_once จะหยุดการทำงานทันทีเมื่อไฟล์มีปัญหา และป้องกันการ include ซ้ำ

// ================= DB ================= ขอดึงข้อมูลทุกคอลัมน์ (*) จากตารางที่ชื่อ ganesha_info โดยเรียงลำดับข้อมูลตามคอลัมน์ info_id จากน้อยไปมาก (ASC)
$result = $conn->query("SELECT * FROM ganesha_info ORDER BY info_id ASC");
// เช็กว่าดึงข้อมูลสำเร็จไหม ถ้ามีอะไรผิดพลาด (เช่น พิมพ์ชื่อตารางผิด) ระบบจะใช้คำสั่ง die() เพื่อหยุดการทำงานทั้งหมด แล้วแสดงข้อความว่า Query Error ตามด้วยสาเหตุที่พัง
if (!$result) {
    die("Query Error: " . $conn->error);
}

$all_data = $result->fetch_all(MYSQLI_ASSOC);

// fallback เช็กว่าในฐานข้อมูลมีข้อมูลอยู่ไหม ถ้าไม่มีข้อมูลเลย ระบบจะใส่ข้อมูลสำรองแทนเพื่อไม่ให้หน้าเว็บว่างเปล่า
if (empty($all_data)) {
    $all_data = [
        [
            "title_ganesha" => "ระบบ AR องค์พระพิฆเนศ",
            "content_ganesha" => "ยังไม่มีข้อมูลจากฐานข้อมูล กรุณาเพิ่มข้อมูลในระบบ Admin",
            "img_ganesha" => "default.png"
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>AR Ganesha</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700;900&family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Swiper -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

</head>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>


<body class="home-page" text-white">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">

            <!-- Logo -->
            <div class="sidebar-logo">
                <div class="logo-title">AR Ganesha</div>
            </div>


            <!-- Hamburger -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false" aria-label="เปิดเมนู">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu -->
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto text-center align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="restaurants.php">Restaurant</a></li>
                    <li class="nav-item"><a class="nav-link" href="place.php">Place</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>

                    <?php if (isset($_SESSION['username'])): ?>
                        <li class="nav-item ms-lg-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <span class="text-warning me-3 small">
                                    <i class="fa-solid fa-user me-1"></i>
                                    <?= htmlspecialchars($_SESSION['username']) ?>
                                </span>
                                <a href="logout.php" class="btn btn-outline-danger btn-sm px-3 rounded-pill">
                                    Logout
                                </a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-3">
                            <a href="Backend/login.php" class="btn btn-warning btn-sm px-4 fw-bold rounded-pill text-dark">
                                Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>

    </nav>

    <!-- ข้อความนอก nav -->
    <section class="container mt-5 pt-5 text-left">

        <p class="fw-bold fs-3">
            สัมผัสศิลปวัฒนธรรมผ่านเทคโนโลยี AR
            <br>เปิดประสบการณ์ใหม่ในการเรียนรู้และการท่องเที่ยว<br>
        </p>
        <p class="text-light opacity-75 lh-lg">
            คุณสามารถสัมผัสโมเดล 3 มิติ บทสวด และประวัติความเป็นมา ได้จากสมาร์ตโฟนของคุณ <br>
            ที่ลานสักการะมหาวิทยาลัยศิลปากร วิทยาเขตสารสนเทศเพชรบุรี
        </p>

        <a href="location_ar.html" class="btn btn-ar btn-lg mt-4 px-5 fw-bold">
            เริ่มต้นใช้งาน AR
        </a>

    </section>

    <section class="contact-glass mt-3 mb-3">
        <div class="container">
            <div class="row g-3 align-items-stretch justify-content-center">

                <div class="col-md-6 col-lg-4">
                    <div class="contact-card h-100">
                        <div class="contact-icon-wrap">
                            <i class="bi bi-geo-alt-fill contact-icon"></i>
                        </div>
                        <h5 class="fw-bold mb-2">ลานสักการะองค์พระพิฆเนศ</h5>
                        <p class="mb-3 small">ม.ศิลปากร วิทยาเขตสารสนเทศเพชรบุรี</p>
                        <a href="https://maps.app.goo.gl/Tgdo7pfvGPQCt1JSA" class="contact-link">
                            Google Maps <i class="bi bi-arrow-up-right ms-1"></i>
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="contact-card h-100">
                        <div class="contact-icon-wrap">
                            <i class="bi bi-geo-alt-fill contact-icon"></i>
                        </div>
                        <h5 class="fw-bold mb-2">พระพิฆเนศองค์ใหญ่ วัดนายาง</h5>
                        <p class="mb-3 small">ต.นายาง อ.ชะอำ จ.เพชรบุรี</p>
                        <a href="https://maps.app.goo.gl/p1skGyeqYpC1qtNv5" target="_blank" class="contact-link">
                            Google Maps <i class="bi bi-arrow-up-right ms-1"></i>
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="contact-card h-100">
                        <div class="contact-icon-wrap">
                            <i class="bi bi-envelope-fill contact-icon"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Email</h5>
                        <p class="mb-3 small">ganeshaAR@silpakorn.ac.th</p>
                        <a href="mailto:..." class="contact-link">
                            ติดต่อสอบถาม <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>



    <!-- MODEL -->
    <section class="feature-section py-5 bg-black">
        <div class="container">
            <div class="row align-items-center">

                <!-- LEFT CONTENT -->
                <div class="col-lg-7 order-2 order-lg-1 text-white">

                    <h2 class="feature-title mb-3 fw-light">
                        เห็นอนาคตของสื่อวัฒนธรรม <br>
                        ผ่านเทคโนโลยี Cinematic AR
                        <dr>
                    </h2>

                    <div class="feature-description mt-4 mb-5">
                        <p class="fw-bold fs-5">ระบบ AR ของเราถูกพัฒนาด้วยแนวคิด <br>“เรียนรู้ง่าย โต้ตอบได้จริง และเข้าถึงได้ทุกคน”</p>
                    </div>

                    <h5 class="mb-4">สิ่งที่ผู้ใช้สามารถทำได้</h5>

                    <ul class="feature-list">
                        <li>หมุน / ซูม / ขยายโมเดล 3 มิติขององค์พระพิฆเนศ</li>
                        <li>ฟังประวัติและความหมายเชิงสัญลักษณ์ของเทวรูป</li>
                        <li>รับชมคติความเชื่อด้านการเสริมดวงตามความต้องการ</li>
                        <li>เรียนรู้อัตลักษณ์เด่นของงานศิลป์ที่ใช้ในการสร้าง</li>
                        <li>สแกนผ่านมือถือได้โดยไม่ต้องติดตั้งแอป</li>
                    </ul>

                </div>

                <!-- RIGHT MODEL -->
                <div class="col-lg-5 order-1 order-lg-2 text-center mb-5 mb-lg-0">

                    <div class="model-wrapper">

                        <img src="image/model.png" alt="Ganesha 3D Model" class="ganesha-3d-img">

                    </div>

                </div>

            </div>
        </div>
    </section>

    <!-- ABOUT -->
    <section class="intro-section py-5 bg-black">
        <div class="container position-relative">

            <div class="ganesha-prev"></div>
            <div class="ganesha-next"></div>

            <div class="swiper infoSlider">
                <div class="swiper-wrapper">
                    <!-- สไลด์เลื่อน ซ้ายขวา / fetch_all() ดึงข้อมูลทั้งหมดในครั้งเดียว-->
                    <?php foreach ($all_data as $row): ?>
                        <div class="swiper-slide">
                            <div class="row align-items-center">
                                <div class="col-lg-6 text-white text-start">
                                    <h6 class="gold-label">ABOUT</h6>
                                    <!-- htmlspecialchars() เป็นฟังก์ชันของ PHP ที่ใช้ในการป้องกันการโจมตีแบบ Cross-Site Scripting (XSS) โดยการแปลงตัวอักษรพิเศษในข้อความให้เป็นรูปแบบที่ปลอดภัย เช่น แปลง < เป็น &lt; และ > เป็น &gt; เพื่อไม่ให้โค้ด HTML หรือ JavaScript ที่เป็นอันตรายถูกแทรกเข้ามาในหน้าเว็บ -->
                                    <h3 class="fw-light mb-4"><?= htmlspecialchars($row['title_ganesha']) ?></h3>
                                    <p class="text-light opacity-75 lh-lg">
                                        <?= nl2br(htmlspecialchars($row['content_ganesha'])) ?>
                                    </p>
                                    <a href="view-model.php?id=<?= $row['info_id'] ?>"
                                        target="_blank"
                                        class="gold-btn mt-3"
                                        style="text-decoration:none; display:inline-block;">
                                        รับชม Model
                                    </a>
                                </div>
                                <div class="col-lg-6 text-center mt-5 mt-lg-0">
                                    <div class="image-wrapper">
                                        <img src="image/<?= htmlspecialchars($row['img_ganesha']) ?>"
                                            class="ganesha-img"
                                            alt="<?= htmlspecialchars($row['title_ganesha']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination mt-5"></div>
            </div>
        </div>
    </section>


    <section class="product-section py-5" id="product">
        <div class="container text-center mt-4">

            <h2 class="section-title display-6 fw-bold text-uppercase mb-3 mt-2 text-white">Product</h2>
            <p class="text-secondary mb-5 pb-3">เพื่อจัดสร้างองค์พระพิฆเนศ เนื่องในวาระครบรอบ 80 ปี มหาวิทยาลัยศิลปากร</p>

            <div class="row g-4 justify-content-center">

                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="product-card-premium h-100 d-flex flex-column text-start">
                        <div class="product-img-wrapper">
                            <span class="product-badge">Sold Out</span>
                            <img src="image/product01.png" alt="องค์พระพิฆเนศ ขนาดหน้าตัก 5.5 นิ้ว">
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="product-title">องค์พระพิฆเนศ ขนาดหน้าตัก 5.5 นิ้ว</h6>
                            <div class="product-price text-secondary fs-6"><del>สินค้าหมดแล้ว</del></div>
                        </div>
                        <div class="d-grid mt-auto">
                            <button type="button" class="btn btn-product-outline py-2" data-bs-toggle="modal" data-bs-target="#productModal1">
                                ดูรายละเอียด
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="product-card-premium h-100 d-flex flex-column text-start">
                        <div class="product-img-wrapper">
                            <img src="image/product02.png" alt="เหรียญหล่อ เนื้อสัมฤทธิ์ ขนาด 2.8 ซม.">
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="product-title">เหรียญหล่อ เนื้อสัมฤทธิ์ ขนาด 2.8 ซม.</h6>
                            <div class="product-price">380 ฿</div>
                        </div>
                        <div class="d-flex gap-2 mt-auto">
                            <button type="button" class="btn btn-product-outline flex-grow-1 py-2" data-bs-toggle="modal" data-bs-target="#productModal2">
                                รายละเอียด
                            </button>
                            <a href="https://www.facebook.com/ganesha80su/?locale=th_TH" target="_blank" class="btn btn-product-gold flex-grow-1 py-2">
                                ติดต่อสั่งซื้อ
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="product-card-premium h-100 d-flex flex-column text-start">
                        <div class="product-img-wrapper">
                            <img src="image/product03.png" alt="องค์พระพิฆเนศ ลอยองค์ เนื้อเงิน 1.5 ซม.">
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="product-title">องค์พระพิฆเนศ ลอยองค์ เนื้อเงิน 1.5 ซม.</h6>
                            <div class="product-price">1,680 ฿</div>
                        </div>
                        <div class="d-flex gap-2 mt-auto">
                            <button type="button" class="btn btn-product-outline flex-grow-1 py-2" data-bs-toggle="modal" data-bs-target="#productModal3">
                                รายละเอียด
                            </button>
                            <a href="https://www.facebook.com/ganesha80su/?locale=th_TH" target="_blank" class="btn btn-product-gold flex-grow-1 py-2">
                                ติดต่อสั่งซื้อ
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- footer -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Raleway:wght@300;400;500&display=swap');

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

        .footer-col:last-child {
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

        .map-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid #c9a84c;
            color: #c9a84c;
            font-family: 'Raleway', sans-serif;
            font-size: 0.75rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            padding: 0.55rem 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 2rem;
        }

        .map-btn:hover {
            background: #c9a84c;
            color: #0a0a0a;
        }

        .map-btn svg {
            width: 14px;
            height: 14px;
        }

        .follow-label {
            font-size: 0.65rem;
            letter-spacing: 0.35em;
            /* แก้สีตรงบรรทัดนี้ครับ ลองใช้สีทองหรือขาวสว่างๆ */
            color: #c9a84c;
            /* เปลี่ยนเป็นสีทองให้เข้ากับธีมเว็บ */
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

        .nav-link {
            display: block;
            font-size: 0.82rem;
            color: #777;
            text-decoration: none;
            padding: 0.45rem 0;
            letter-spacing: 0.05em;
            border-bottom: 1px solid transparent;
            transition: all 0.25s;
        }

        .nav-link:hover {
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

        @media (max-width: 700px) {
            .footer-top {
                grid-template-columns: 1fr;
            }

            .footer-col {
                border-right: none;
                border-bottom: 1px solid #1e1e1e;
                padding: 2rem 1.5rem;
            }

            .footer-bottom {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
        }
    </style>

    <footer class="footer-wrap" id="contact">
        <div class="footer-top">

            <div class="footer-col">
                <p class="brand-name">AR Ganesha</p>
                <p class="brand-tagline">Phetchaburi</p>

                <p class="follow-label">Follow us</p>
                <div class="social-row">
                    <a href="#" class="social-link">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                        </svg>
                    </a>
                    <a href="#" class="social-link">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                            <path d="M11.998 0C5.374 0 .002 5.372.002 11.996c0 2.117.555 4.1 1.523 5.818L.004 23.998l6.337-1.663A11.954 11.954 0 0012 23.993C18.626 23.993 24 18.622 24 12c0-6.624-5.374-12-12.002-12z" />
                        </svg>
                    </a>
                    <a href="#" class="social-link">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                            <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                        </svg>
                    </a>
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
                        <span style="line-height: 1.5; color: #888;">- วัดนายาง ต.นายาง อ.ชะอำ จ.เพชรบุรี</span>
                    </div>
                </div>

            </div>

            <div class="footer-col">
                <p class="col-title">Explore</p>
                <a href="#" class="nav-link">Home</a>
                <a href="#restaurant" class="nav-link">Restaurant</a>
                <a href="#place" class="nav-link">Place</a>
                <a href="#contact" class="nav-link">Contact us</a>
            </div>

        </div>

        <div class="footer-bottom">
            <span class="copyright">© 2024 AR Ganesha. All rights reserved.</span>
            <div class="gold-line"></div>
            <span class="copyright">Silpakorn University</span>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>


    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script>
        var infoSwiper = new Swiper(".infoSlider", {
            slidesPerView: 1,
            loop: true,
            navigation: {
                nextEl: ".ganesha-next",
                prevEl: ".ganesha-prev",
            },
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
        });
    </script>
    <div class="modal fade" id="productModal1" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-warning">
                <div class="modal-header border-secondary d-flex flex-column-reverse">
                    <div class="modal-title text-warning p-title-big">องค์พระพิฆเนศ 5.5 นิ้ว</div>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="img-wrapper"><img src="image/product01.png" alt="องค์พระพิฆเนศ 5.5 นิ้ว"></div>
                    <p class="small opacity-75">วาระครบรอบ 80 ปี มหาวิทยาลัยศิลปากร</p>
                    <hr class="border-secondary">
                    <p class="text-danger fw-bold mb-0">สถานะ: SOLD OUT</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="productModal2" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-warning">
                <div class="modal-header border-secondary d-flex flex-column-reverse">
                    <div class="modal-title text-warning p-title-big">เหรียญหล่อ เนื้อสัมฤทธิ์</div>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="img-wrapper"><img src="image/product02.png" alt="เหรียญหล่อ เนื้อสัมฤทธิ์"></div>
                    <p class="small text-center opacity-75">ผ่านพิธีพุทธาภิเษก ณ ลานสักการะ ม.ศิลปากร</p>
                    <hr class="border-secondary">
                    <div class="d-flex justify-content-between align-items-center px-2">
                        <span class="text-warning fw-bold fs-4">380 บาท</span>
                        <a href="https://www.facebook.com/ganesha80su/?locale=th_TH" target="_blank" class="btn btn-warning btn-sm fw-bold text-dark px-3">ติดต่อสั่งซื้อ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="productModal3" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-warning">
                <div class="modal-header border-secondary d-flex flex-column-reverse">
                    <div class="modal-title text-warning p-title-big">เนื้อเงิน 1.5 ซม.</div>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="img-wrapper"><img src="image/product03.png" alt="องค์พระพิฆเนศ เนื้อเงิน 1.5 ซม."></div>
                    <p class="small text-center opacity-75">เนื้อเงินแท้ รายละเอียดคมชัด พร้อมกล่องกำมะหยี่</p>
                    <hr class="border-secondary">
                    <div class="d-flex justify-content-between align-items-center px-2">
                        <span class="text-warning fw-bold fs-4">1,680 บาท</span>
                        <a href="https://www.facebook.com/ganesha80su/?locale=th_TH" target="_blank" class="btn btn-warning btn-sm fw-bold text-dark px-3">ติดต่อสั่งซื้อ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>