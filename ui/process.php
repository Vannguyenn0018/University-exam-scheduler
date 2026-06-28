<?php
// 1. Cấu hình hiển thị lỗi 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sử dụng file kết nối cơ sở dữ liệu SQLite 
require_once __DIR__ . '/connect.php';

// 2. Nhận các bộ lọc được gửi từ trang index.php sang
$nam_hoc       = $_REQUEST['nam_hoc'] ?? '2024-2025';
$hoc_ki        = $_REQUEST['hoc_ki'] ?? 'Học kì 2';
$khoa          = $_REQUEST['khoa'] ?? 'Ngân hàng'; 
$khoa_nganh    = $_REQUEST['khoa_nganh'] ?? 'Khoa Ngân hàng';
$hinh_thuc_thi = $_REQUEST['hinh_thuc_thi'] ?? 'Tất cả hình thức';
$thoi_gian     = $_REQUEST['thoi_gian'] ?? 'Cuối kì - Đợt 1';
$exam_date     = $_REQUEST['exam_date'] ?? 'all'; // Nhận biến lọc ngày cụ thể từ UI (định dạng YYYY-MM-DD)

// CHUẨN HÓA DỮ LIỆU để khớp với cột major lưu trong SQLite
$nganh_truy_van = str_replace('Khoa ', '', $khoa_nganh);

// Đồng bộ hóa từ viết tắt nếu trên UI giao diện hiển thị không khớp hoàn toàn với DB
if (strpos($nganh_truy_van, 'Hệ thống thông tin') !== false) {
    $nganh_truy_van = 'Hệ thống thông tin quản lý';
}

if ($nganh_truy_van === 'Tất cả các Khoa' || empty($nganh_truy_van)) {
    $nganh_truy_van = ''; 
}

// =========================================================================================
// CƠ CHẾ KẾT NỐI VÀ KÍCH HOẠT THUẬT TOÁN GA BACKEND (PYTHON) TỰ ĐỘNG
// =========================================================================================
$python_script = __DIR__ . '/ga_algorithm.py'; 

if (file_exists($python_script)) {
    $cmd_nam_hoc = escapeshellarg($nam_hoc);
    $cmd_hoc_ki = escapeshellarg($hoc_ki);
    $cmd_nganh = escapeshellarg($nganh_truy_van);
    $cmd_hinh_thuc = escapeshellarg($hinh_thuc_thi);
    $cmd_dot_thi = escapeshellarg($thoi_gian);

    $command = "python $python_script $cmd_nam_hoc $cmd_hoc_ki $cmd_nganh $cmd_hinh_thuc $cmd_dot_thi 2>&1";
    exec($command, $output, $return_var);

    if ($return_var !== 0) {
        echo "<div class='alert alert-warning m-3'><strong>Thông báo hệ thống:</strong> Thuật toán GA kích hoạt dòng lệnh trả về mã cảnh báo. (Vui lòng đảm bảo Python đã được cài đặt và thiết lập biến môi trường).</div>";
    }
}
// =========================================================================================

$lich_thi_hoan_thinh = [];

try {
    // 4. TRUY VẤN LẤY DỮ LIỆU ĐÃ ĐỒNG BỘ THEO TÊN CỘT GỐC TRONG SQLITE
    $sql_schedule = "SELECT 
                        es.exam_id,
                        c.course_name,
                        t.exam_date,
                        t.start_time,
                        es.room_id,
                        er.building,
                        er.capacity
                     FROM ExamSchedule es
                     JOIN Exam e ON es.exam_id = e.exam_id
                     JOIN Course c ON e.course_id = c.course_id
                     JOIN Timeslot t ON es.timeslot_id = t.timeslot_id
                     JOIN ExamRoom er ON es.room_id = er.room_id
                     WHERE 1=1";
                     
    $main_params = [];
    
    //gán chặt điều kiện lọc ngày thi vào SQL 
    if ($exam_date !== 'all' && !empty($exam_date)) {
        $sql_schedule .= " AND t.exam_date = :exam_date";
        $main_params[':exam_date'] = $exam_date;
    }
                     
    $stmt = $conn->prepare($sql_schedule);
    $stmt->execute($main_params);
    $lich_thi_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Với mỗi lịch thi, lấy danh sách sinh viên thực tế thuộc ngành đã lọc ngồi phòng đó
    foreach ($lich_thi_raw as $row) {
        $exam_id = $row['exam_id'];
        $room_id = $row['room_id'];
        $building = strtoupper(trim($row['building'])); // Lấy tên tòa nhà (A hoặc C)
        
        // ==================== ĐỊNH DẠNG HÌNH THỨC HIỂN THỊ THEO TÒA NHÀ ====================
        // Tòa C mặc định là phòng máy -> Trắc nghiệm máy. Tòa A mặc định là phòng học -> Lý thuyết tự luận.
        if ($building === 'C') {
            $is_trac_nghiem = true;
            $phong_hien_thi = $room_id . " (Trắc nghiệm máy)";
        } else {
            $is_trac_nghiem = false;
            $phong_hien_thi = $room_id . " (Lý thuyết tự luận)";
        }

        // Kiểm tra điều kiện lọc hình thức thi được gửi từ giao diện bộ lọc  sang
        if ($hinh_thuc_thi === 'Lý thuyết tự luận' && $is_trac_nghiem) {
            continue; // Nếu chọn Tự luận nhưng phòng thuộc Tòa C (Máy) -> Bỏ qua không hiển thị
        }
        if ($hinh_thuc_thi === 'Trắc nghiệm máy' && !$is_trac_nghiem) {
            continue; // Nếu chọn Trắc nghiệm nhưng phòng thuộc Tòa A (Tự luận) -> Bỏ qua không hiển thị
        }
        // ===================================================================================
        
        if (!empty($nganh_truy_van)) {
            $sql_students = "SELECT s.student_id AS mssv, s.full_name AS ho_ten, s.major AS lop_ql
                             FROM StudentExamRoom ser
                             JOIN Student s ON ser.student_id = s.student_id
                             WHERE ser.exam_id = :exam_id AND ser.room_id = :room_id AND s.major LIKE :nganh";
            $params = [
                ':exam_id' => $exam_id, 
                ':room_id' => $room_id,
                ':nganh'   => '%' . $nganh_truy_van . '%'
            ];
        } else {
            $sql_students = "SELECT s.student_id AS mssv, s.full_name AS ho_ten, s.major AS lop_ql
                             FROM StudentExamRoom ser
                             JOIN Student s ON ser.student_id = s.student_id
                             WHERE ser.exam_id = :exam_id AND ser.room_id = :room_id";
            $params = [
                ':exam_id' => $exam_id, 
                ':room_id' => $room_id
            ];
        }
                     
        $stmt_stud = $conn->prepare($sql_students);
        $stmt_stud->execute($params);
        $students_in_room = $stmt_stud->fetchAll(PDO::FETCH_ASSOC);
        
        // Chỉ đẩy lịch thi này ra bảng hiển thị nếu phòng thi chứa sinh viên thỏa mãn điều kiện
        if (count($students_in_room) > 0) {
            $lich_thi_hoan_thinh[] = [
                "lhp"      => $row['exam_id'],
                "tenHp"    => $row['course_name'],
                "ngayThi"  => !empty($row['exam_date']) ? date("d/m/Y", strtotime($row['exam_date'])) : "Chưa xếp",
                "gioThi"   => !empty($row['start_time']) ? substr($row['start_time'], 0, 5) : "Chưa xếp",
                "phongThi" => $phong_hien_thi,
                "diaDiem"  => "Toà " . $row['building'],
                "students" => $students_in_room
            ];
        }
    }

} catch (Exception $e) {
    echo "<div class='alert alert-danger m-3'><strong>Lỗi CSDL SQLite:</strong> " . $e->getMessage() . "</div>";
}

// Cập nhật lại nhãn hiển thị tên ngành: Lấy trực tiếp từ biến $khoa_nganh ban đầu để giữ lại chữ "Khoa"
$khoa = ($khoa_nganh !== 'Tất cả các Khoa' && !empty($khoa_nganh)) ? $khoa_nganh : "Tất cả các ngành";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả xếp lịch thi - HUB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', sans-serif; padding: 30px 10px; }
        .container-custom { max-width: 1150px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); }
        .banner-img { width: 100%; height: auto; display: block; margin-bottom: 25px; border-radius: 6px; }
        
        .badge-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-bottom: 25px; }
        .badge-info-custom { font-size: 13px; padding: 8px 16px; background-color: #eef2f6; color: #334155; border-radius: 20px; font-weight: 500; }
        .badge-info-custom strong { color: #1a365d; }
        
        .search-container { max-width: 550px; margin: 0 auto 30px auto; text-align: center; }
        .search-label { font-size: 15px; font-weight: 600; color: #475569; margin-bottom: 8px; display: inline-block; }
        .search-group { background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 20px; padding: 2px 4px; transition: all 0.2s ease; display: flex; align-items: center; }
        .search-group:focus-within { border-color: #1a365d; box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.15); }
        .search-input { border: none; font-weight: 500; color: #334155; font-size: 15px; padding: 10px 16px; border-radius: 20px 0 0 20px; }
        .search-input:focus { box-shadow: none; background: transparent; }
        
        .search-btn { background: none; border: none; color: #64748b; padding: 0 16px; display: flex; align-items: center; border-radius: 0 20px 20px 0; transition: color 0.2s; cursor: pointer; }
        .search-btn:hover { color: #1a365d; }

        .table-title { color: #1a365d; font-weight: 700; margin-bottom: 20px; font-size: 19px; letter-spacing: 0.5px; text-transform: uppercase; }
        .table-responsive-custom { border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        
        .custom-thead { background-color: #1a365d !important; color: #ffffff !important; }
        .custom-thead th { font-weight: 700 !important; font-size: 14.5px; padding: 14px 8px; border: none; vertical-align: middle; letter-spacing: 0.5px; }
        
        .btn-xem-ds { font-size: 13px; font-weight: 600; padding: 5px 14px; border-radius: 6px; transition: all 0.2s; }
        
        .personal-result-box { display: none; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; margin-bottom: 35px; box-shadow: 0 10px 25px rgba(26, 54, 93, 0.08); animation: fadeIn 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
        .personal-box-header { border-bottom: 2px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 18px; }
        
        .empty-state-box { display: none; background-color: #fff1f2; border: 1px dashed #f43f5e; border-radius: 12px; padding: 30px 20px; margin-bottom: 35px; text-align: center; animation: fadeIn 0.3s ease-in-out; }
        
        .btn-view-all { font-size: 14px; font-weight: 600; color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; background: #eff6ff; border: 1px solid #bfdbfe; padding: 6px 16px; border-radius: 20px; margin-bottom: 15px; }
        .btn-view-all:hover { background: #dbeafe; color: #1d4ed8; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        .btn-excel { background-color: #ffffff; color: #334155; border: 1px solid #cbd5e1; padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 6px; transition: all 0.2s; }
        .btn-excel:hover { background-color: #f8fafc; border-color: #94a3b8; }
        .btn-back { color: #64748b; text-decoration: none; font-weight: 600; font-size: 14px; }
        .btn-back:hover { color: #1a365d; }
    </style>
</head>
<body>

<div class="container-custom">
    <img src="banner.png" alt="Banner HUB" class="banner-img">

    <div class="text-center">
        <div class="badge-container">
            <div class="badge-info-custom"><strong>Năm học:</strong> <?php echo htmlspecialchars($nam_hoc); ?></div>
            <div class="badge-info-custom"><strong>Học kì:</strong> <?php echo htmlspecialchars($hoc_ki); ?></div>
            <div class="badge-info-custom"><strong>Ngành:</strong> <?php echo htmlspecialchars($khoa); ?></div>
            <div class="badge-info-custom"><strong>Đợt thi:</strong> <?php echo htmlspecialchars($thoi_gian); ?></div>
            <div class="badge-info-custom"><strong>Ngày thi:</strong> <?php echo ($exam_date === 'all') ? 'Tất cả các ngày' : date('d/m/Y', strtotime($exam_date)); ?></div>
        </div>
    </div>

    <div class="search-container">
        <label for="mssvSearch" class="search-label"><i class="bi bi-search me-1"></i> Tra cứu lịch thi cá nhân:</label>
        <div class="input-group search-group">
            <input type="text" id="mssvSearch" class="form-control search-input" placeholder="Nhập Mã số sinh viên..." onkeypress="handleKeyPress(event)">
            <button onclick="executeSearch()" class="search-btn" title="Nhấn để tra cứu">
                <i class="bi bi-person-vcard-fill fs-4 text-primary"></i>
            </button>
        </div>
    </div>

    <div id="backToMainContainer" style="display: none;">
        <button onclick="resetSearchState()" class="btn-view-all">
            <i class="bi bi-arrow-left"></i> Quay lại xem lịch toàn trường
        </button>
    </div>

    <div id="personalResultSection" class="personal-result-box">
        <div class="personal-box-header">
            <h5 class="m-0 text-primary fw-bold">
                KẾT QUẢ TRA CỨU LỊCH THI: <span id="targetMssvLabel" class="ms-1 text-dark fw-bold"></span>
            </h5>
        </div>
        <div class="table-responsive table-responsive-custom">
            <table class="table table-bordered align-middle text-center m-0 bg-white">
                <thead class="custom-thead text-white">
                    <tr>
                        <th style="width: 18%;">Lớp học phần</th>
                        <th style="width: 32%;">Tên học phần</th>
                        <th style="width: 14%;">Ngày thi</th>
                        <th style="width: 12%;">Giờ thi</th>
                        <th style="width: 12%;">Phòng thi</th>
                        <th style="width: 12%;">Địa điểm</th>
                    </tr>
                </thead>
                <tbody id="personalResultBody"></tbody>
            </table>
        </div>
    </div>

    <div id="emptyStateSection" class="empty-state-box">
        <div class="text-danger mb-2"><i class="bi bi-exclamation-triangle-fill fs-3"></i></div>
        <h5 class="fw-bold text-danger mb-1">KHÔNG TÌM THẤY KẾT QUẢ</h5>
        <p class="text-muted m-0 small">Không tìm thấy lịch thi cho MSSV <strong id="wrongMssvLabel" class="text-dark"></strong> trong đợt này. Vui lòng kiểm tra lại!</p>
    </div>

    <div id="mainTableContainer">
        <h5 class="table-title text-center">Danh sách lịch thi</h5>
        
        <div class="table-responsive table-responsive-custom mb-4">
            <table id="lichThiTable" class="table table-striped align-middle text-center m-0">
                <thead class="custom-thead">
                    <tr>
                        <th style="width: 5%;">STT</th>
                        <th style="width: 15%;">Lớp học phần</th>
                        <th style="width: 25%;">Tên học phần</th>
                        <th style="width: 12%;">Ngày thi</th>
                        <th style="width: 10%;">Giờ thi</th>
                        <th style="width: 15%;">Phòng thi</th>
                        <th style="width: 13%;">Địa điểm</th>
                        <th style="width: 10%;">Hành động</th>
                    </tr>
                </thead>
                <tbody id="mainTableBody">
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
        <button onclick="exportToExcel()" class="btn btn-excel text-primary border-primary">
            <i class="bi bi-file-earmark-excel me-1"></i> XUẤT FILE EXCEL LỊCH THI
        </button>
        <a href="index.php" class="btn-back"><i class="bi bi-arrow-left me-1"></i> Quay lại thiết lập bộ lọc</a>
    </div>
</div>

<div class="modal fade" id="studentListModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header text-white" style="background-color: #1a365d; border-radius: 12px 12px 0 0;">
                <h6 class="modal-title fw-bold" id="modalTitle">DANH SÁCH SINH VIÊN THI</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="p-3 mb-3 bg-light rounded-3 border" style="font-size: 14px; line-height: 1.8;">
                    <div class="row g-2">
                        <div class="col-12">
                            Môn học: <strong id="modalMonHoc" class="text-primary fs-6"></strong>
                        </div>
                        <div class="col-12">
                            Phòng thi: <strong id="modalPhongThi"></strong>
                        </div>
                        <div class="col-12">
                            Giờ thi: <strong id="modalGioThi"></strong>
                        </div>
                        <div class="col-12">
                            Ngày thi: <strong id="modalNgayThi"></strong>
                        </div>
                        <div class="col-12 mt-2 border-top pt-2">
                            Tổng số thí sinh: <span class="badge bg-primary" id="modalTongSo" style="font-size: 13px;">0</span>
                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <table class="table table-bordered table-striped align-middle text-center m-0" style="font-size: 14px;">
                        <thead class="table-dark" style="position: sticky; top: 0; z-index: 10; font-weight: 700;">
                            <tr>
                                <th style="width: 8%;">STT</th>
                                <th style="width: 25%;">Mã số sinh viên</th>
                                <th style="width: 42%;" class="text-start ps-3">Họ và tên</th>
                                <th style="width: 25%;">Ngành</th>
                            </tr>
                        </thead>
                        <tbody id="modalTableBody"></tbody>
                    </table>
                </div>
            </div>
            
            <div class="modal-footer d-flex justify-content-between py-2">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Đóng cửa sổ</button>
                <button type="button" onclick="exportRoomExcel()" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-file-earmark-excel me-1"></i> Xuất danh sách phòng
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
// Nhận mảng JSON từ PHP một cách an toàn
var localDataArr = <?php echo json_encode($lich_thi_hoan_thinh, JSON_UNESCAPED_UNICODE); ?>;
var studentModalObj = new bootstrap.Modal(document.getElementById('studentListModal'));
var currentActiveLhp = ""; 

function renderMainTable() {
    var mainTableBody = document.getElementById("mainTableBody");
    mainTableBody.innerHTML = "";

    if(localDataArr.length === 0) {
        mainTableBody.innerHTML = `<tr><td colspan="8" class="text-muted py-4">Không có dữ liệu lịch thi nào phù hợp với bộ lọc trong CSDL.</td></tr>`;
        return;
    }

    localDataArr.forEach((row, index) => {
        var badgeClass = row.phongThi.includes("Trắc nghiệm") ? "bg-danger" : "bg-secondary";
        var htmlRow = `<tr>
            <td>${index + 1}</td>
            <td class="fw-semibold text-primary">${row.lhp}</td>
            <td class="text-start ps-2">${row.tenHp}</td>
            <td>${row.ngayThi}</td>
            <td>${row.gioThi}</td>
            <td><span class="badge ${badgeClass} px-2 py-1">${row.phongThi}</span></td>
            <td class="text-muted small">${row.diaDiem}</td>
            <td>
                <button type="button" class="btn btn-outline-primary btn-sm btn-xem-ds" data-malop="${row.lhp}">
                    <i class="bi bi-eye"></i> Xem danh sách
                </button>
            </td>
        </tr>`;
        mainTableBody.innerHTML += htmlRow;
    });
}

renderMainTable();

document.addEventListener('click', function(event) {
    const button = event.target.closest('.btn-xem-ds');
    if (!button) return; 

    event.preventDefault();
    const maLopHocPhan = button.getAttribute('data-malop');
    currentActiveLhp = maLopHocPhan;
    const targetData = localDataArr.find(item => item.lhp === maLopHocPhan);
    
    if (targetData) {
        document.getElementById('modalTitle').innerText = `DANH SÁCH SINH VIÊN THI - LỚP: ${targetData.lhp}`;
        document.getElementById('modalMonHoc').innerText = targetData.tenHp;
        document.getElementById('modalPhongThi').innerText = targetData.phongThi;
        document.getElementById('modalGioThi').innerText = targetData.gioThi;
        document.getElementById('modalNgayThi').innerText = targetData.ngayThi;
        document.getElementById('modalTongSo').innerText = targetData.students.length + " thí sinh";
        
        let htmlRows = '';
        targetData.students.forEach((student, index) => {
            htmlRows += `<tr>
                <td class="fw-bold text-muted">${index + 1}</td>
                <td class="font-monospace fw-semibold">${student.mssv}</td>
                <td class="text-start ps-3">${student.ho_ten}</td>
                <td><span class="badge bg-light text-dark border">${student.lop_ql}</span></td>
            </tr>`;
        });
        document.getElementById('modalTableBody').innerHTML = htmlRows;
        studentModalObj.show();
    } else {
        alert("Không tìm thấy dữ liệu lớp học phần này.");
    }
});

function executeSearch() {
    var input = document.getElementById("mssvSearch");
    var filter = input.value.trim().toLowerCase();
    if (filter === "") { resetSearchState(); return; }

    var personalResultBody = document.getElementById("personalResultBody");
    personalResultBody.innerHTML = ""; 
    var matchCount = 0;

    localDataArr.forEach(function(row) {
        var isFound = row.students.some(function(st) { return st.mssv.toLowerCase().indexOf(filter) > -1; });
        if (isFound) {
            var badgeClass = row.phongThi.includes("Trắc nghiệm") ? "bg-danger" : "bg-secondary";
            var newRow = `<tr>
                <td class="fw-semibold text-primary">${row.lhp}</td>
                <td class="text-start ps-2">${row.tenHp}</td>
                <td>${row.ngayThi}</td>
                <td>${row.gioThi}</td>
                <td><span class="badge ${badgeClass} px-2 py-1">${row.phongThi}</span></td>
                <td class="small text-muted">${row.diaDiem}</td>
            </tr>`;
            personalResultBody.innerHTML += newRow;
            matchCount++;
        }
    });

    document.getElementById("mainTableContainer").style.display = "none";
    document.getElementById("backToMainContainer").style.display = "block"; 

    if (matchCount > 0) {
        document.getElementById("targetMssvLabel").innerText = input.value.trim();
        document.getElementById("personalResultSection").style.display = "block";
        document.getElementById("emptyStateSection").style.display = "none"; 
    } else {
        document.getElementById("wrongMssvLabel").innerText = '"' + input.value.trim() + '"';
        document.getElementById("emptyStateSection").style.display = "block";
        document.getElementById("personalResultSection").style.display = "none";
    }
}

function resetSearchState() {
    document.getElementById("mssvSearch").value = "";
    document.getElementById("personalResultSection").style.display = "none";
    document.getElementById("emptyStateSection").style.display = "none";
    document.getElementById("backToMainContainer").style.display = "none";
    document.getElementById("mainTableContainer").style.display = "block";
}

function handleKeyPress(event) { if (event.key === "Enter") { executeSearch(); } }
function exportToExcel() {
    // 1. Kiểm tra xem vùng hiển thị lịch thi cá nhân có đang MỞ hay không
    var isPersonalMode = document.getElementById("personalResultSection").style.display === "block";
    
    if (isPersonalMode) {
        // NGƯỜI DÙNG ĐANG TRA CỨU CÁ NHÂN -> Xuất lịch thi của sinh viên đó
        var mssv = document.getElementById("targetMssvLabel").innerText.trim();
        var table = document.querySelector("#personalResultSection table");
        var workbook = XLSX.utils.table_to_book(table, {sheet: "Lịch thi cá nhân"});
        
        // Đặt tên file động theo MSSV của sinh viên để dễ quản lý
        XLSX.writeFile(workbook, `Lich_Thi_Sinh_Vien_${mssv}.xlsx`);
    } else {
        // NGƯỜI DÙNG ĐANG XEM TOÀN TRƯỜNG -> Xuất lịch thi tổng thể
        var table = document.getElementById("lichThiTable");
        var workbook = XLSX.utils.table_to_book(table, {sheet: "Lịch Thi Tổng Thể"});
        
        XLSX.writeFile(workbook, "Lich_Thi_Tong_The_HUB.xlsx");
    }
}

// Giữ nguyên hàm xuất danh sách phòng thi khi click xem chi tiết modal (nếu cần)
function exportRoomExcel() { 
    var table = document.querySelector("#studentListModal table"); 
    var workbook = XLSX.utils.table_to_book(table, {sheet: "Phòng Thi"}); 
    XLSX.writeFile(workbook, `Danh_sach_thi_lop_${currentActiveLhp}.xlsx`); 
}
</script>
</body>
</html>