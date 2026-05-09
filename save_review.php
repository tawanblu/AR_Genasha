<?php
session_start();
require_once("connect.php");

// ตรวจสอบว่ามีการ Login และส่งข้อมูลมาจริงไหม
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['id_account'])) {
    $restaurant_id = $_POST['restaurant_id'];
    $id_account = $_SESSION['id_account'];
    $review_text = $_POST['review_text'];
    $rating_score = $_POST['rating_score'];

    // ใช้ Prepared Statement เพื่อความปลอดภัย
    $stmt = $conn->prepare("INSERT INTO restaurant_reviews (restaurant_id, id_account, review_text, rating_score) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $restaurant_id, $id_account, $review_text, $rating_score);

    if ($stmt->execute()) {
        echo "<script>alert('บันทึกรีวิวสำเร็จ!'); window.location='restaurants.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: restaurants.php");
}
?>