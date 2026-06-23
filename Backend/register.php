<?php
require_once "../connect.php";
/** @var mysqli $conn */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    // ตรวจสอบค่าว่าง
    if (empty($username) || empty($email) || empty($password)) {
        echo "<script>
                alert('กรุณากรอกข้อมูลให้ครบถ้วน');
                window.history.back();
              </script>";
        exit();
    }

    // ตรวจสอบรูปแบบอีเมล
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
                alert('รูปแบบอีเมลไม่ถูกต้อง');
                window.history.back();
              </script>";
        exit();
    }

    // ตรวจสอบความแข็งแกร่งของรหัสผ่าน (Server-side Backup เผื่อกรณี JS ไม่ทำงาน)
    $uppercase = preg_match('@[A-Z]@', $password);
    $number    = preg_match('@[0-9]@', $password);
    $specialChars = preg_match('@[^\w]@', $password);

    if (!$uppercase || !$number || !$specialChars || strlen($password) < 6) {
        echo "<script>
                alert('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร รวมถึงต้องมีตัวพิมพ์ใหญ่ (A-Z), ตัวเลข (0-9) และอักขระพิเศษ อย่างน้อย 1 ตัว');
                window.history.back();
              </script>";
        exit();
    }

    // ตรวจสอบ username / email ซ้ำ
    $check = $conn->prepare("SELECT id_account FROM accounts WHERE username=? OR email=?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>
                alert('Username หรือ Email นี้ถูกใช้แล้ว');
                window.history.back();
              </script>";
        exit();
    }

    // เข้ารหัส password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // บันทึกข้อมูล
    $sql = "INSERT INTO accounts (username, email, password, login_date) 
            VALUES (?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $email, $hash);
    $stmt->execute();

    echo "<script>
            alert('สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ');
            window.location.href='login.php';
          </script>";

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register | AR Ganesha</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="login-body">

    <div class="login-container">

        <div class="col-md-6 login-image d-flex align-items-center"
            style="background-image: url('../image/picganesha1.jpg'); 
            background-size: cover; 
            background-position: center; 
            background-repeat: no-repeat; 
            min-height: 100vh;
            color: white;
            position: relative;">

            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1;"></div>

            <div class="login-text" style="position: relative; z-index: 2; padding: 40px;">
                <h1 class="fw-bold">AR Ganesha</h1>
                <p class="mt-3">
                    ประสบการณ์เสมือนจริงที่เชื่อมศิลปวัฒนธรรม
                    เข้ากับเทคโนโลยี Augmented Reality
                </p>
            </div>
        </div>

        <div class="login-right">

            <div class="login-form-box">

                <div class="top-link">
                    Already have an account?
                    <a href="login.php">Sign In</a>
                </div>

                <h2>Register AR Ganesha</h2>

                <form method="post" id="registerForm">

                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Email" required>

                    <label>Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Username" required>

                    <label>Password</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Password" required style="padding-right: 45px;">
                        <span id="togglePassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; user-select: none; color: #aaa;">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </span>
                    </div>

                    <small id="passwordError" style="color: #aaa; display: block; margin-top: 5px; font-size: 0.85rem; transition: color 0.3s;">
                        * รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร, มีตัวพิมพ์ใหญ่, ตัวเลข และอักขระพิเศษอย่างน้อย 1 ตัว
                    </small>

                    <button type="submit" class="btn btn-register" style="margin-top: 15px;">
                        Sign Up
                    </button>

                </form>

            </div>

        </div>

    </div>

    <script>
        // ระบบเปิด/ปิดตา
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

        // ระบบตรวจสอบความปลอดภัยของรหัสผ่านแบบ Real-time
        const registerForm = document.getElementById('registerForm');
        const passwordError = document.getElementById('passwordError');

        // ฟังก์ชันเช็คเงื่อนไขรหัสผ่าน
        function isValidPassword(password) {
            const hasUpperCase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecialChar = /[^\w]/.test(password);
            return password.length >= 6 && hasUpperCase && hasNumber && hasSpecialChar;
        }

        // ตรวจสอบทันทีที่ผู้ใช้กำลังพิมพ์ (Real-time)
        passwordInput.addEventListener('input', function() {
            const passwordVal = this.value;
            passwordInput.style.borderColor = ''; // คืนค่ากรอบเดิมเสมอเวลาพิมพ์ใหม่

            if (passwordVal === '') {
                // ถ้าลบจนว่างเปล่า ให้กลับไปเป็นสีเทา
                passwordError.style.color = '#aaa';
            } else if (!isValidPassword(passwordVal)) {
                // ถ้าพิมพ์แล้วแต่ยังผิดเงื่อนไข ให้ตัวหนังสือเป็นสีแดง
                passwordError.style.color = '#e05a5a';
            } else {
                // ถ้าเงื่อนไขครบถ้วน เปลี่ยนเป็นสีเขียว
                passwordError.style.color = '#38c9a0';
            }
        });

        // ดักจับตอนกดปุ่ม Sign Up ขั้นสุดท้าย
        registerForm.addEventListener('submit', function(e) {
            const passwordVal = passwordInput.value;

            if (!isValidPassword(passwordVal)) {
                e.preventDefault(); // ยกเลิกการส่งข้อมูล
                passwordError.style.color = '#e05a5a'; // ข้อความแดง
                passwordInput.style.borderColor = '#e05a5a'; // กรอบแดง
            }
        });
    </script>
</body>

</html>