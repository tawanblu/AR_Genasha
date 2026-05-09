<?php
session_start();

if (isset($_GET['redirect'])) {
    $_SESSION['redirect_to'] = $_GET['redirect'];
}
require_once "../connect.php";
/** @var mysqli $conn */


$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // รับค่าและตัดช่องว่าง
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // ค้นหาผู้ใช้จาก username
    $sql = "SELECT * FROM accounts WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        // ตรวจสอบรหัสผ่านที่แฮชไว้
        if (password_verify($password, $user['password'])) {

            // --- ส่วนที่คงไว้และปรับปรุง Session ให้ครอบคลุมทั้ง User และ Admin ---
            $_SESSION['id_account'] = $user['id_account']; // ใช้ id_account เป็นหลักเพื่อให้ตรงกับหน้า restaurants.php
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role']; // เก็บสิทธิ์ (user หรือ admin) ลง Session

            // ------------------------------------------------------------------
            // [ส่วนเดิมที่ห้ามตัด] อัปเดตเวลาเข้าสู่ระบบล่าสุด (login_date)
            // ------------------------------------------------------------------
            $update_sql = "UPDATE accounts SET login_date = CURRENT_TIMESTAMP WHERE id_account = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $user['id_account']);
            $update_stmt->execute();
            $update_stmt->close();
            // ------------------------------------------------------------------

            // --- [ส่วนที่เพิ่มการแยกหน้า] ห้าม User เข้า Dashboard ---
            if ($_SESSION['role'] === 'admin') {
                // ถ้าเป็น Admin ไปหน้า Dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                // ถ้าเป็น User ทั่วไป ให้ไปหน้า Home หรือหน้า Restaurants
                // (ถ้าไฟล์ restaurants.php อยู่ข้างนอกโฟลเดอร์ admin ให้ใช้ ../restaurants.php)
                $_SESSION['username'] = $username;

                // ตรวจสอบว่ามีการเก็บหน้าที่อยากให้กลับไป (redirect_to) ไว้ไหม
                if (isset($_SESSION['redirect_to'])) {
                    $redirect_url = $_SESSION['redirect_to'];
                    unset($_SESSION['redirect_to']); // ใช้เสร็จแล้วลบทิ้งทันที
                    header("Location: " . $redirect_url);
                } else {
                    // ถ้าเป็นการ Login ปกติ (ไม่ได้มาจากหน้า Review) ให้ไปหน้า Home
                    header("Location: ../index.php");
                }
                exit();
            }
            exit;
        } else {
            $message = "Username หรือ Password ไม่ถูกต้อง";
        }
    } else {
        $message = "Username หรือ Password ไม่ถูกต้อง";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login | AR Ganesha</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* สไตล์เดิมทั้งหมดห้ามตัดออก */
        body {
            min-height: 100vh;
            background: #ffffff;
        }

        .login-wrapper {
            min-height: 100vh;
        }

        /* Left Image */
        .login-image {
            background: url("../image/picganesha1.jpg") center / cover no-repeat;
            position: relative;
        }

        .login-image::after {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
        }

        .login-text {
            position: relative;
            z-index: 2;
            color: #fff;
            padding: 50px;
        }

        /* Right Form */
        .login-form {
            background: #fff;
            border: 1px solid #ccc;
            padding: 60px;
        }

        .login-form h2 {
            font-weight: 700;
            margin-bottom: 30px;
        }

        /* Mobile */
        @media (max-width:768px) {
            .login-image {
                display: none;
            }

            .login-form {
                padding: 40px 25px;
            }
        }
    </style>
</head>

<body>

    <div class="container-fluid">
        <div class="row login-wrapper">

            <div class="col-md-6 login-image d-flex align-items-center">
                <div class="login-text">
                    <h1 class="fw-bold">AR Ganesha</h1>
                    <p class="mt-3">
                        ประสบการณ์เสมือนจริงที่เชื่อมศิลปวัฒนธรรม<br>
                        เข้ากับเทคโนโลยี Augmented Reality
                    </p>
                </div>
            </div>

            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="login-form w-100" style="max-width:420px">
                    <p class="text-end small">
                        No account yet? <a href="register.php">Sign up</a>
                    </p>

                    <h2>Sign In</h2>

                    <?php if ($message != ""): ?>
                        <div class="alert alert-danger">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="post">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required autocomplete="username">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            เข้าสู่ระบบ
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</body>

</html>