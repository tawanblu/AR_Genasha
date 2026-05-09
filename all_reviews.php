<?php
session_start();
require_once("connect.php");

$restaurant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ดึงชื่อร้านอาหารมาแสดงเป็นหัวข้อ
$res_query = $conn->prepare("SELECT restaurant_name FROM Restaurant WHERE restaurant_id = ?");
$res_query->bind_param("i", $restaurant_id);
$res_query->execute();
$res_name = $res_query->get_result()->fetch_assoc()['restaurant_name'] ?? "ไม่พบชื่อร้าน";

// ดึงรีวิวทั้งหมดของร้านนี้
$sql = "
    SELECT r.*, a.username 
    FROM restaurant_reviews r 
    INNER JOIN accounts a ON r.id_account = a.id_account 
    WHERE r.restaurant_id = ? 
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$reviews = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รีวิวทั้งหมด - <?= htmlspecialchars($res_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #000; color: #fff; font-family: 'Sarabun', sans-serif; }
        .review-card { background: #111; border: 1px solid #222; border-radius: 15px; padding: 20px; margin-bottom: 15px; }
        .gold-text { color: #D4AF37; }
    </style>
</head>
<body>
    <div class="container mt-5 pt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="gold-text">รีวิวของร้าน: <?= htmlspecialchars($res_name) ?></h2>
            <a href="restaurants.php" class="btn btn-outline-light">กลับไปหน้าร้านอาหาร</a>
        </div>

        <?php if ($reviews->num_rows > 0): ?>
            <?php while($row = $reviews->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="d-flex justify-content-between">
                        <strong class="text-warning">
                            <i class="fa-solid fa-user-circle me-1"></i> <?= htmlspecialchars($row['username']) ?>
                        </strong>
                        <span class="text-white-50 small"><?= date('d/m/Y H:i', strtotime($row['review_date'])) ?></span>
                    </div>
                    <div class="my-2" style="color: #D4AF37;">
                        <?php 
                        for ($i = 1; $i <= 5; $i++) {
                            echo ($row['rating_score'] >= $i) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star text-secondary"></i>';
                        }
                        ?>
                    </div>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($row['review_text'])) ?></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <p class="text-secondary">ยังไม่มีใครมารีวิวร้านนี้เลย เป็นคนแรกที่รีวิวสิ!</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>