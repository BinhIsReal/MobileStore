<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// 1. Tự động xóa các voucher đã hết hạn (Expiry date nhỏ hơn ngày hiện tại)
$conn->query("DELETE FROM vouchers WHERE expiry_date < CURDATE() AND expiry_date != '0000-00-00'");

// 2. Xử lý Xóa voucher thủ công khi click nút Xóa
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    if ($del_id <= 0) { header("Location: vouchers.php"); exit; }
    
    // FIXED: Dùng Prepared Statement lấy dữ liệu cũ
    $stmt_old = $conn->prepare("SELECT * FROM vouchers WHERE id = ?");
    $stmt_old->bind_param("i", $del_id);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();
    
    // FIXED: Prepared Statement xóa user_vouchers liên kết
    $stmt_uv = $conn->prepare("DELETE FROM user_vouchers WHERE voucher_id = ?");
    $stmt_uv->bind_param("i", $del_id);
    $stmt_uv->execute();
    $stmt_uv->close();
    
    // FIXED: Prepared Statement xóa voucher chính
    $stmt_del = $conn->prepare("DELETE FROM vouchers WHERE id = ?");
    $stmt_del->bind_param("i", $del_id);
    if ($stmt_del->execute()) {
        $stmt_del->close();
        include_once '../includes/admin_logger.php';
        $v_code = $old_data['code'] ?? "ID $del_id";
        logAdminAction($conn, 'Xóa Voucher', 'admin/vouchers.php', "Xóa mã giảm giá: $v_code", $old_data, null);
        
        header("Location: vouchers.php?msg=deleted");
        exit;
    } else {
        // SECURITY: Không expose lỗi DB, ghi log
        error_log("Delete voucher error: " . $stmt_del->error);
        header("Location: vouchers.php?msg=error");
        exit;
    }
}

// Lấy danh sách Voucher để hiển thị
$vouchers = $conn->query("SELECT * FROM vouchers ORDER BY created_at DESC");

// Lấy danh sách User để gán voucher
$users = $conn->query("SELECT id, username, email FROM users WHERE role != 'admin' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý Voucher - Admin</title>
    <link rel="stylesheet" href="../assets/css/vouchers.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="admin-wrapper">

        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="admin-content">
            <h2
                style="color: #00487a; margin-top: 0; border-bottom: 2px solid #00487a; padding-bottom: 10px; display: inline-block;">
                <i class="fa-solid fa-ticket-simple"></i> Quản lý Voucher
            </h2>

            <div class="voucher-container">
                <div class="form-section">
                    <h3 id="form-title" style="margin-top: 0; color: #333;"><i class="fa fa-plus-circle"></i> Tạo Mã
                        Giảm Giá</h3>
                    <form id="create-voucher-form">
                        <input type="hidden" name="action" id="voucher-action" value="create_voucher">
                        <input type="hidden" name="voucher_id" id="voucher-id" value="">

                        <div class="form-group">
                            <label>Mã Voucher (Code)</label>
                            <input type="text" name="code" class="form-control" placeholder="VD: TET2024" required
                                style="text-transform: uppercase;">
                        </div>
                        <div class="form-group">
                            <label>Loại giảm giá</label>
                            <select name="type" id="discount-type" class="form-control">
                                <option value="percent">Giảm theo phần trăm (%)</option>
                                <option value="fixed">Giảm số tiền trực tiếp (VNĐ)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Mức giảm</label>
                            <input type="number" name="discount_amount" class="form-control"
                                placeholder="VD: 10 (cho 10%) hoặc 50000 (cho 50k)" required>
                        </div>
                        <div class="form-group" id="max-discount-group">
                            <label>Giảm tối đa (VNĐ)</label>
                            <input type="number" name="max_discount" class="form-control"
                                placeholder="Chỉ dành cho % (VD: Tối đa 100.000đ)">
                        </div>
                        <div class="form-group">
                            <label>Đơn tối thiểu (VNĐ)</label>
                            <input type="number" name="min_order_value" class="form-control"
                                placeholder="Đơn từ bao nhiêu mới được dùng?" value="0">
                        </div>
                        <div class="form-group">
                            <label>Ngày hết hạn</label>
                            <input type="date" name="expiry_date" class="form-control" required>
                        </div>

                        <button type="submit" id="btn-submit-voucher" class="btn-primary">Tạo Voucher</button>
                        <button type="button" id="btn-cancel-edit" class="btn-secondary"
                            style="display: none; width: 100%; margin-top: 10px; padding: 10px; border: none; border-radius: 4px; cursor: pointer; background-color: #6c757d; color: white; font-weight: bold;"
                            onclick="cancelEdit()">Hủy Cập Nhật</button>
                    </form>
                </div>

                <div class="list-section">
                    <h3 style="margin-top: 0; color: #333;">Danh sách Voucher đang có</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã</th>
                                <th>Loại</th>
                                <th>Mức giảm</th>
                                <th>Tối đa</th>
                                <th>Đơn tối thiểu</th>
                                <th>Hết hạn</th>
                                <th width="15%">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($vouchers->num_rows > 0): ?>
                            <?php while ($row = $vouchers->fetch_assoc()): ?>
                            <tr>
                                <td><b class="voucher-code-text"><?= htmlspecialchars($row['code']) ?></b></td>
                                <td><?= $row['type'] == 'percent' ? 'Phần trăm' : 'Tiền mặt' ?></td>
                                <td>
                                    <?php if ($row['type'] == 'percent'): ?>
                                    <span class="voucher-discount-text"><?= (float)$row['discount_amount'] ?>%</span>
                                    <?php else: ?>
                                    <span class="voucher-discount-text"><?= number_format($row['discount_amount']) ?>đ</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($row['max_discount']) ?>đ</td>
                                <td><?= number_format($row['min_order_value']) ?>đ</td>
                                <td><?= date('d/m/Y', strtotime($row['expiry_date'])) ?></td>
                                <td class="voucher-action-td">
                                    <button class="btn-success btn-block-action"
                                        onclick="openAssignModal(<?= $row['id'] ?>, '<?= $row['code'] ?>')">
                                        <i class="fa-solid fa-users"></i> Gán
                                    </button>

                                    <button type="button" class="btn-primary btn-block-action"
                                        onclick="editVoucher(<?= $row['id'] ?>, '<?= htmlspecialchars($row['code']) ?>', '<?= $row['type'] ?>', <?= $row['discount_amount'] ?>, <?= $row['max_discount'] ?>, <?= $row['min_order_value'] ?>, '<?= $row['expiry_date'] ?>')">
                                        <i class="fa-solid fa-pen-to-square"></i> Sửa
                                    </button>

                                    <button type="button" class="btn-delete-voucher"
                                        onclick="confirmDelete(<?= $row['id'] ?>)">
                                        <i class="fa-solid fa-trash-can"></i> Xóa
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center-pad" style="color: #888;">Chưa có mã giảm
                                    giá nào được tạo.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="$('#assignModal').fadeOut()">&times;</span>
            <h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Gán Voucher: <span
                    id="assign-code-display" style="color: #d70018;"></span></h3>

            <form id="assign-voucher-form">
                <input type="hidden" name="voucher_id" id="assign-voucher-id">

                <div class="form-group">
                    <label>Số lượt sử dụng / người:</label>
                    <input type="number" name="usage_limit" class="form-control" value="1" min="1" required>
                </div>

                <div class="form-group"
                    style="background: #eef5ff; padding: 10px; border-radius: 5px; border: 1px solid #cce0ff;">
                    <label style="margin: 0; color: #00487a; cursor: pointer;">
                        <input type="checkbox" id="check-all-users"> <b>Gán cho TẤT CẢ Người dùng</b>
                    </label>
                </div>

                <div class="user-list" id="user-list-container">
                    <?php while ($u = $users->fetch_assoc()): ?>
                    <label>
                        <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" class="user-checkbox">
                        <b>ID: <?= $u['id'] ?></b> - <?= htmlspecialchars($u['username']) ?> <span
                            style="color:#888;">(<?= htmlspecialchars($u['email']) ?>)</span>
                    </label>
                    <?php endwhile; ?>
                </div>

                <button type="submit" class="btn-primary">Xác nhận gán Voucher</button>
            </form>
        </div>
    </div>
    <script src="../assets/js/admin_main.js"></script>
</body>

</html>