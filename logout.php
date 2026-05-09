<?php
// 1. เริ่มต้น session เพื่อให้รู้จักว่าใครจะ logout
session_start();

// 2. ล้างข้อมูล session ทั้งหมด
session_unset();

// 3. ทำลาย session ทิ้ง
session_destroy();

// 4. สั่งให้เด้งกลับไปหน้า login หรือหน้าแรก (index.php)
header("Location: index.php"); 
exit();
?>