<?php
session_start();
include '../config/db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
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
                    <h3 style="margin-top: 0; color: #333;"><i class="fa fa-plus-circle"></i> Tạo Mã Giảm Giá</h3>
                    <form id="create-voucher-form">
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
                        <button type="submit" class="btn-primary">Tạo Voucher</button>
                    </form>
                </div>

                <div class="list-section">
                    <h3 style="margin-top: 0; color: #333;">Danh sách Voucher đang có</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Mã Code</th>
                                <th>Loại - Mức giảm</th>
                                <th>Điều kiện</th>
                                <th>Hết hạn</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($vouchers->num_rows > 0): ?>
                            <?php while ($row = $vouchers->fetch_assoc()): ?>
                            <tr>
                                <td><b style="color: #00487a;"><?= htmlspecialchars($row['code']) ?></b></td>
                                <td>
                                    <?php if ($row['type'] == 'percent'): ?>
                                    <span
                                        style="color:#d70018; font-weight:bold;"><?= (float)$row['discount_amount'] ?>%</span>
                                    <br>
                                    <small style="color: #666;">(Tối đa
                                        <?= number_format($row['max_discount']) ?>đ)</small>
                                    <?php else: ?>
                                    <span
                                        style="color:#d70018; font-weight:bold;"><?= number_format($row['discount_amount']) ?>đ</span>
                                    <?php endif; ?>
                                </td>
                                <td>Đơn từ <?= number_format($row['min_order_value']) ?>đ</td>
                                <td><?= date('d/m/Y', strtotime($row['expiry_date'])) ?></td>
                                <td>
                                    <button class="btn-success"
                                        onclick="openAssignModal(<?= $row['id'] ?>, '<?= $row['code'] ?>')">
                                        <i class="fa fa-gift"></i> Gán mã
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #888; padding: 30px;">Chưa có mã giảm
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