<?php
// Cấu hình hiển thị lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // SỬ DỤNG FILE KẾT NỐI TRUNG TÂM
    require_once __DIR__ . '/connect.php';
    
    // 1. TỰ ĐỘNG LẤY DANH SÁCH NGÀNH THỰC TẾ TRONG DB
    $stmt_major = $conn->query("SELECT DISTINCT major FROM Student WHERE major IS NOT NULL AND major != '' ORDER BY major ASC");
    $majors = $stmt_major->fetchAll(PDO::FETCH_COLUMN);

    // 2. TỰ ĐỘNG TRÍCH XUẤT KHÓA HỌC TỪ MSSV (Ví dụ sinh viên mang số 39... -> K39)
    // Đoạn này lấy 2 chữ số đại diện cho Khóa học từ mã sinh viên để giảng viên thấy sự đồng bộ ngầm dữ liệu
    $stmt_khoa = $conn->query("SELECT DISTINCT 'K' || SUBSTR(student_id, 4, 2) as khoa_hoc FROM Student WHERE student_id IS NOT NULL ORDER BY khoa_hoc DESC");
    $khoas = $stmt_khoa->fetchAll(PDO::FETCH_COLUMN);
    // Nếu DB của bạn trích xuất ra dạng khác, bạn có thể tự fix cứng array này thành: $khoas = ['K39', 'K40']; để đối phó nhanh.

    // 3. TỰ ĐỘNG LẤY CÁC NGÀY THI/ĐỢT THI THỰC TẾ TỪ BẢNG TIMESLOT
    $stmt_time = $conn->query("SELECT DISTINCT exam_date FROM Timeslot WHERE exam_date IS NOT NULL AND exam_date != '' ORDER BY exam_date ASC");
    $dates = $stmt_time->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $majors = [];
    $khoas = ['K39'];
    $dates = [];
    $error_msg = "Lỗi kết nối database: " . $e->getMessage();
}

// Giữ lại trạng thái bộ lọc cũ khi submit nếu cần
$old_khoa_hoc  = $_REQUEST['khoa_hoc'] ?? 'Tất cả';
$old_khoa_nganh = $_REQUEST['khoa_nganh'] ?? 'Tất cả';
$old_exam_date  = $_REQUEST['exam_date'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống xếp lịch thi - HUB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .container-custom { 
            max-width: 850px; 
            margin: 40px auto; 
            background: #ffffff; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); 
        }
        .banner-img { width: 100%; height: auto; display: block; margin-bottom: 35px; border-radius: 8px; }
        .system-title { color: #1e293b; font-size: 24px; letter-spacing: 0.5px; position: relative; padding-bottom: 12px; }
        .system-title::after { content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 60px; height: 3px; background-color: #0d6efd; border-radius: 2px; }
        
        .input-group-custom { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; transition: all 0.3s ease; }
        .input-group-custom:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); background-color: #ffffff; }
        .input-group-text-custom { background: transparent; border: none; color: #64748b; padding-left: 15px; }
        .form-select-custom { border: none; background-color: transparent; font-weight: 600; color: #334155; padding: 14px 10px; cursor: pointer; }
        .form-select-custom:focus { box-shadow: none; background-color: transparent; }
        
        .btn-submit-custom { 
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: #ffffff; border: none; padding: 14px 60px; 
            font-weight: 700; border-radius: 10px; font-size: 16px; letter-spacing: 0.5px;
            box-shadow: 0 4px 14px rgba(13, 110, 253, 0.3); transition: all 0.2s ease; 
        }
        .btn-submit-custom:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4); color: #ffffff; }

        #loadingOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.95);
            z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column;
        }
        .progress-wraper { width: 80%; max-width: 500px; text-align: center; }
    </style>
</head>
<body>

<div id="loadingOverlay">
    <div class="progress-wraper">
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
        <h4 class="fw-bold text-dark mb-2">ĐANG KÍCH HOẠT THUẬT TOÁN DI TRUYỀN GA</h4>
        <p class="text-muted mb-4">Hệ thống đang truyền tham số sang Python để phân tích dữ liệu thực tế và tiến hành xếp lịch tối ưu...</p>
        <div class="progress" style="height: 10px; border-radius: 5px;">
            <div id="loadingBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
        </div>
    </div>
</div>

<div class="container-custom">
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger mb-4"><strong>Lỗi đồng bộ dữ liệu:</strong> <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <img src="banner.png" alt="Banner HUB" class="banner-img">
    <h3 class="text-center fw-bold system-title mb-5">HỆ THỐNG XẾP LỊCH THI TỰ ĐỘNG</h3>

    <form action="process.php" method="POST" onsubmit="showLoading(event)">
        <div class="row g-4 justify-content-center">
            
            <div class="col-md-12">
                <label class="form-label fw-bold text-secondary">Khóa học:</label>
                <div class="input-group input-group-custom">
                    <span class="input-group-text input-group-text-custom"><i class="bi bi-mortarboard"></i></span>
                    <select name="khoa_hoc" class="form-select form-select-custom" required>
                        <option value="Tất cả" <?php echo $old_khoa_hoc === 'Tất cả' ? 'selected' : ''; ?>>-- Tất cả các Khóa --</option>
                        <?php foreach ($khoas as $khoa): ?>
                            <option value="<?php echo htmlspecialchars($khoa); ?>" <?php echo $old_khoa_hoc === $khoa ? 'selected' : ''; ?>>
                                Khóa <?php echo htmlspecialchars($khoa); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-md-12">
                <label class="form-label fw-bold text-secondary">Ngành học:</label>
                <div class="input-group input-group-custom">
                    <span class="input-group-text input-group-text-custom"><i class="bi bi-building"></i></span>
                    <select name="khoa_nganh" class="form-select form-select-custom" required>
                        <option value="Tất cả" <?php echo $old_khoa_nganh === 'Tất cả' ? 'selected' : ''; ?>>-- Tất cả các Ngành --</option>
                        <?php foreach ($majors as $major): ?>
                            <option value="<?php echo htmlspecialchars($major); ?>" <?php echo $old_khoa_nganh === $major ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($major); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-md-12">
                <label class="form-label fw-bold text-secondary">Đợt thi:</label>
                <div class="input-group input-group-custom">
                    <span class="input-group-text input-group-text-custom"><i class="bi bi-calendar3"></i></span>
                    <select name="exam_date" class="form-select form-select-custom" required>
                        <option value="all" <?php echo $old_exam_date === 'all' ? 'selected' : ''; ?>>-- Tất cả các đợt thi --</option>
                        <?php foreach ($dates as $date): 
                            $display_date = !empty($date) ? date("d/m/Y", strtotime($date)) : $date;
                        ?>
                            <option value="<?php echo htmlspecialchars($date); ?>" <?php echo $old_exam_date === $date ? 'selected' : ''; ?>>
                                Đợt thi ngày: <?php echo htmlspecialchars($display_date); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

        </div>

        <div class="text-center mt-5">
            <button type="submit" class="btn btn-submit-custom">
                <i></i> XẾP LỊCH
            </button>
        </div>
    </form>
</div>

<script>
function showLoading(event) {
    event.preventDefault();
    const form = event.target;
    document.getElementById('loadingOverlay').style.display = 'flex';
    
    let width = 0;
    const loadingBar = document.getElementById('loadingBar');
    const interval = setInterval(() => {
        if (width >= 100) {
            clearInterval(interval);
            form.submit(); 
        } else {
            width += 5;
            loadingBar.style.width = width + '%';
        }
    }, 80);
}
</script>
</body>
</html>