<?php
// Cấu hình hiển thị lỗi để dễ kiểm tra nếu kết nối thất bại
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {

    $db_path = __DIR__ . '/university_exam_management.db';
    
    // Khởi tạo kết nối PDO SQLite
    $conn = new PDO("sqlite:" . $db_path);
    
    // Cấu hình chế độ báo lỗi
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Cấu hình trả về dữ liệu dạng mảng Associate (Key là tên cột)
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Kết nối CSDL thất bại: " . $e->getMessage());
}
?>