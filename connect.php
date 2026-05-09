<?php
// ถ้าuserพยายามเข้าconnect.php โดยตรง ให้เด้งกลับไปหน้าindex.php
// if ($open_connect != 1) {
//     die(header('Location: index.php'));
// }

$hostname = 'localhost';
$username = 'root';
$password = '';
$database = 'ar_ganesha';
$port = 'NULL';
$socket = 'NULL';
$conn = mysqli_connect($hostname, $username, $password, $database);

// เช็คการเชื่อมต่อฐานข้อมูล
if (!$conn) {

    die("การเชื่อมต่อฐานข้อมูลล้มเหลว : " . mysqli_connect_error());
} else {
    mysqli_set_charset($conn, "utf8");
}
