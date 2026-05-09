<?php
// ดึงค่าจาก Environment Variables (ถ้าไม่มีให้ใช้ค่าของ XAMPP แทน)
$hostname = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$database = getenv('DB_NAME') ?: 'ar_ganesha'; // ฐานข้อมูลในเครื่องตัวเอง
$port     = getenv('DB_PORT') ?: '3306';

$conn = mysqli_connect($hostname, $username, $password, $database, $port);

if (!$conn) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว : " . mysqli_connect_error());
} else {
    mysqli_set_charset($conn, "utf8");
}
