<?php
header('Content-Type: application/json; charset=utf-8');

// Nhận mã lớp học phần từ Trình duyệt truyền lên
$maLop = $_GET['ma_lop'] ?? '';
// DỮ LIỆU GIẢ LẬP ĐỂ TEST (Đã đồng bộ Key camelCase để khớp hoàn toàn với Javascript nhận dữ liệu)
$databaseMock = [
    'DAT701_252_1_D01' => [
        'tenHp' => 'Chuỗi khối (Blockchain)',
        'phongThi' => 'PM.302 (Trắc nghiệm máy)', // Đã thêm định dạng phòng để test badge
        'gioThi' => '09:30',
        'ngayThi' => '04/05/2026',
        'students' => [
            ['mssv' => '030239230215', 'ho_ten' => 'Nguyễn Văn A', 'lop_ql' => 'ĐH40_NH01'],
            ['mssv' => '030239230216', 'ho_ten' => 'Trần Thị B', 'lop_ql' => 'ĐH40_NH02'],
            ['mssv' => '030239230217', 'ho_ten' => 'Lê Hoàng C', 'lop_ql' => 'ĐH40_TCDN']
        ]
    ],
    'ITS332_252_1_D02' => [
        'tenHp' => 'Phát triển ứng dụng mã nguồn mở',
        'phongThi' => 'PM.201 (Trắc nghiệm máy)',
        'gioThi' => '07:00',
        'ngayThi' => '06/05/2026',
        'students' => [
            ['mssv' => '030239230216', 'ho_ten' => 'Trần Thị B', 'lop_ql' => 'ĐH40_NH02'],
            ['mssv' => '030239230218', 'ho_ten' => 'Phạm Văn D', 'lop_ql' => 'ĐH40_HTTT']
        ]
    ]
];

if (array_key_exists($maLop, $databaseMock)) {
    $data = $databaseMock[$maLop];
    echo json_encode([
        'status' => 'success',
        'tenHp' => $data['tenHp'],
        'phongThi' => $data['phongThi'],
        'gioThi' => $data['gioThi'],
        'ngayThi' => $data['ngayThi'],
        'tong_so' => count($data['students']),
        'students' => $data['students']
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Không tìm thấy dữ liệu hoặc mã lớp "' . $maLop . '" không khớp DB!'
    ], JSON_UNESCAPED_UNICODE);
}
?>