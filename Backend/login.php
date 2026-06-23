<?php
session_start();

// 1. [จุดที่เพิ่ม] เช็กว่ามีข้อความแจ้งเตือนผิดพลาดฝากมาใน Session ไหม ถ้ามีให้ดึงมาใช้แล้วลบทิ้ง
$message = "";
if (isset($_SESSION['login_error'])) {
    $message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if (isset($_GET['redirect'])) {
    $_SESSION['redirect_to'] = $_GET['redirect'];
}
require_once "../connect.php";
/** @var mysqli $conn */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM accounts WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            $_SESSION['id_account'] = $user['id_account'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['role']       = $user['role'];

            $update_sql = "UPDATE accounts SET login_date = CURRENT_TIMESTAMP WHERE id_account = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $user['id_account']);
            $update_stmt->execute();

            if (isset($_SESSION['redirect_to'])) {
                $redirect_url = $_SESSION['redirect_to'];
                unset($_SESSION['redirect_to']);
                header("Location: $redirect_url");
                exit();
            }

            // admin → dashboard | user → index
            if ($user['role'] === 'admin') {
                header("Location: dashboard.php");
            } else {
                header("Location: ../index.php");
            }
            exit();
        } else {
            // 2. [จุดที่แก้] ฝากข้อความรหัสผิดไว้ใน Session แล้ว Redirect กลับมาหน้าเดิมเพื่อล้างค่า POST
            $_SESSION['login_error'] = "รหัสผ่านไม่ถูกต้อง";
            header("Location: login.php");
            exit();
        }
    } else {
        // 3. [จุดที่แก้] ฝากข้อความไม่พบผู้ใช้ไว้ใน Session แล้ว Redirect กลับมาหน้าเดิมเพื่อล้างค่า POST
        $_SESSION['login_error'] = "ไม่พบผู้ใช้นี้ในระบบ";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login | AR Ganesha</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            min-height: 100vh;
            background: #ffffff;
        }

        .login-wrapper {
            min-height: 100vh;
        }

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

        .login-form {
            background: #fff;
            border: 1px solid #ccc;
            padding: 60px;
        }

        .login-form h2 {
            font-weight: 700;
            margin-bottom: 30px;
        }

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

    <div class="container-fluid p-0">
        <div class="row g-0 login-wrapper">

            <div class="col-md-6 login-image d-flex align-items-center">
                <div class="login-text w-100">
                    <h1 class="fw-bold">AR Ganesha</h1>
                    <p class="mt-3 fs-5">
                        ประสบการณ์เสมือนจริงที่เชื่อมศิลปวัฒนธรรม
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
                            <div style="position: relative;">
                                <input type="password" name="password" id="passwordInput" class="form-control" required autocomplete="current-password" style="padding-right: 45px;">
                                <span id="togglePassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; user-select: none; color: #aaa;">
                                    <i class="fas fa-eye" id="eyeIcon"></i>
                                </span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            เข้าสู่ระบบ
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            if (type === 'password') {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
                this.style.color = '#aaa';
            } else {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
                this.style.color = '#777';
            }
        });
    </script>
</body>

</html>