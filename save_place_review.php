<?php
session_start();
require_once("connect.php"); // ต้องมั่นใจว่าชื่อไฟล์เชื่อมต่อ DB ถูกต้อง

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['id_account'])) {

    // รับค่าจากฟอร์ม
    $place_id = $_POST['place_id'];
    $id_account = $_SESSION['id_account'];
    $rating_score = $_POST['rating_score'];
    $review_text = $_POST['review_text'];

    // ป้องกัน SQL Injection
    $place_id = $conn->real_escape_string($place_id);
    $rating_score = $conn->real_escape_string($rating_score);
    $review_text = $conn->real_escape_string($review_text);

    // คำสั่ง SQL สำหรับบันทึกรีวิว
    $sql = "INSERT INTO nearby_place_reviews (place_id, id_account, rating_score, review_text, created_at) 
            VALUES ('$place_id', '$id_account', '$rating_score', '$review_text', NOW())";

    if ($conn->query($sql) === TRUE) {
        // บันทึกสำเร็จ กลับไปหน้าเดิม (place.php)
        echo "<script>
                alert('บันทึกรีวิวเรียบร้อยแล้ว');
                window.location.href = 'place.php';
              </script>";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
} else {
    // ถ้าไม่ได้ Login หรือไม่ได้ส่งค่ามา ให้เด้งกลับ
    header("Location: place.php");
    exit();
}
