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

    // ตรวจสอบความยาวรหัสผ่าน
    if (strlen($password) < 6) {
        echo "<script>
                alert('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
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

</head>

<body class="login-body">

    <div class="login-container">

        <div class="col-md-6 login-image d-flex align-items-center"
            style="background-image: url('../image/picganesha1.jpg'); 
            background-size: cover; 
            background-position: center; 
            background-repeat: no-repeat; 
            min-height: 100vh;
            color: white; /* เปลี่ยนสี text เป็นสีขาว */
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

                <form method="post">

                    <label>Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Username" required>

                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Email" required>

                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>

                    <button type="submit" class="btn btn-register">
                        Sign Up
                    </button>

                </form>

            </div>

        </div>

    </div>

</body>

</html>