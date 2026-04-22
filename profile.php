<?php
session_start();
include 'config/db.php';
include_once __DIR__ . '/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Thêm cơ chế bắt lỗi SQL để đảm bảo Database đã có đủ các cột
$sql = "SELECT username, email, phone, address, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Nếu in ra dòng chữ này, nghĩa là bạn chưa chạy lệnh ALTER TABLE ở Bước 1
    die("Lỗi Database: " . $conn->error . " <br>Vui lòng kiểm tra xem đã thêm cột email, phone, address vào bảng users chưa.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Nếu lấy được dữ liệu thì gán vào mảng $user, nếu không thì để mảng rỗng
$user = $result->num_rows > 0 ? $result->fetch_assoc() : [];

$join_date = !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'Không xác định';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ của tôi - TechMate</title>
    <!-- CSRF Meta Tag -->
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?= filemtime('assets/css/mobile.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="profile-wrapper">
        <div class="profile-header">
            <i class="fa-solid fa-circle-user profile-avatar"></i>
            <h2 class="profile-name"><?php echo htmlspecialchars($user['username'] ?? 'Khách'); ?></h2>
            <div class="profile-joined"><i class="fa fa-calendar-alt"></i> Tham gia từ: <?php echo $join_date; ?></div>
        </div>

        <div class="profile-body" id="profile-form">
            <div class="form-group">
                <label>Tên đăng nhập (ID)</label>
                <input type="text" class="form-control readonly-always"
                    value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
            </div>

            <div class="form-group">
                <label>Email liên hệ</label>
                <input type="email" id="pf-email" class="form-control"
                    value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled
                    placeholder="Chưa cập nhật email">
            </div>

            <div class="form-group">
                <label>Số điện thoại</label>
                <input type="text" id="pf-phone" class="form-control"
                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" disabled
                    placeholder="Chưa cập nhật số điện thoại">
            </div>

            <div class="form-group">
                <label>Địa chỉ nhận hàng mặc định</label>
                <textarea id="pf-address" class="form-control" disabled
                    placeholder="Chưa cập nhật địa chỉ giao hàng..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
            </div>

            <div class="action-buttons">
                <a href="change_password.php" class="btn btn-outline-danger" id="btn-change-pass">
                    <i class="fa fa-key"></i> Đổi mật khẩu
                </a>

                <div style="flex:1; display:flex; gap:10px;">
                    <button type="button" class="btn btn-primary" id="btn-edit">
                        <i class="fa fa-edit"></i> Chỉnh sửa
                    </button>
                    <button type="button" class="btn btn-secondary" id="btn-cancel" style="display: none;">
                        Hủy bỏ
                    </button>
                    <button type="button" class="btn btn-primary" id="btn-save" style="display: none;">
                        <i class="fa fa-save"></i> Lưu thay đổi
                    </button>
                </div>
            </div>

            <div style="text-align: center; margin-top: 25px;">
                <a href="index.php" style="color: #00487a; text-decoration: none; font-size: 14px; font-weight: 500;">
                    <i class="fa fa-arrow-left"></i> Quay lại cửa hàng
                </a>
            </div>
        </div>
    </div>
    <div id="toast-container"></div>

    <script src="assets/js/profile.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
</body>

</html>