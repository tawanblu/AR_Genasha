<?php
require_once("connect.php");
/** @var mysqli $conn */

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    // เปลี่ยนชื่อตัวแปรจาก $target_id เป็น $info_id เพื่อความไม่งง
    $info_id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;

    // แยกคำสั่ง SQL เพื่อป้องกันบั๊กของ MySQLi เวลาบันทึกค่า NULL
    if ($info_id !== null) {
        // แก้ชื่อคอลัมน์ใน SQL ให้เป็น info_id
        $stmt = $conn->prepare("INSERT INTO access_logs (action_type, info_id) VALUES (?, ?)");
        $stmt->bind_param("si", $action, $info_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO access_logs (action_type) VALUES (?)");
        $stmt->bind_param("s", $action);
    }

    if ($stmt->execute()) {
        echo "Success";
    } else {
        echo "Error: " . $conn->error;
    }
}
