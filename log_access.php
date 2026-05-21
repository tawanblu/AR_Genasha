<?php
require_once("connect.php");
/** @var mysqli $conn */

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $target_id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;

    // แยกคำสั่ง SQL เพื่อป้องกันบั๊กของ MySQLi เวลาบันทึกค่า NULL
    if ($target_id !== null) {
        $stmt = $conn->prepare("INSERT INTO access_logs (action_type, target_id) VALUES (?, ?)");
        $stmt->bind_param("si", $action, $target_id);
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
