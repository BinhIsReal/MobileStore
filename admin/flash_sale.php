<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Tạo bảng nếu chưa có
$conn->query("
    CREATE TABLE IF NOT EXISTS flash_sale_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT 'HOT SALE CUOI TUAN',
        end_time DATETIME NOT NULL,
        default_discount INT DEFAULT 20,
        is_active TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS flash_sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        discount_type ENUM('percent','fixed') DEFAULT 'percent',
        discount_value DECIMAL(10,2) DEFAULT 20.00,
        UNIQUE KEY uq_product (product_id)
    )
");

// Lấy danh sách sản phẩm đang bán từ DB (không dùng p.status vì cột không tồn tại)
$products_result = $conn->query(
    "SELECT p.id, p.name, p.price, p.sale_price, p.image, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.stock > 0
     ORDER BY p.id DESC"
);
$all_products = [];
if ($products_result) {
    while ($row = $products_result->fetch_assoc()) {
        $all_products[] = $row;
    }
}

// Lấy cấu hình Flash Sale hiện tại
$flash_config = null;
$result_cfg = $conn->query("SELECT * FROM flash_sale_config ORDER BY id DESC LIMIT 1");
if ($result_cfg && $result_cfg->num_rows > 0) {
    $flash_config = $result_cfg->fetch_assoc();
}

// Lấy các sản phẩm hiện tại trong Flash Sale
$current_items = [];
$result_items = $conn->query(
    "SELECT fsi.*, p.name, p.price, p.image
     FROM flash_sale_items fsi
     JOIN products p ON p.id = fsi.product_id
     ORDER BY fsi.id ASC"
);
if ($result_items) {
    while ($row = $result_items->fetch_assoc()) {
        $current_items[$row['product_id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Flash Sale Manager - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/flash_sale.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="admin-container">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="admin-content">
        <div class="fs-page-header">
            <div class="fs-page-title">
                <i class="fa-solid fa-bolt"></i>
                <div>
                    <h1>Flash Sale Manager</h1>
                    <p>Quản lý chương trình khuyến mãi Flash Sale & Countdown Timer</p>
                </div>
            </div>
            <div class="fs-header-actions">
                <button id="btn-ai-suggest" class="btn-ai-magic">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> AI Chọn 8 SP Hot
                </button>
                <button id="btn-random-suggest" class="btn-ai-magic" style="background:linear-gradient(135deg,#6f42c1,#9b59b6);">
                    <i class="fa-solid fa-shuffle"></i> Ngẫu nhiên 8 SP
                </button>
                <button id="btn-save-flash-sale" class="btn-save-flash">
                    <i class="fa-solid fa-floppy-disk"></i> Lưu Flash Sale
                </button>
            </div>
        </div>

        <!-- FLASH SALE CONFIG PANEL -->
        <div class="fs-config-grid">
            <div class="fs-config-card">
                <div class="fs-config-card-header">
                    <i class="fa-solid fa-pen-to-square"></i> Tiêu đề & Mô tả
                </div>
                <div class="fs-config-body">
                    <div class="fs-form-group">
                        <label>Tiêu đề Flash Sale</label>
                        <input type="text" id="fs-title" class="fs-input"
                               value="<?= htmlspecialchars($flash_config['title'] ?? '🔥 HOT SALE CUỐI TUẦN') ?>"
                               placeholder="VD: 🔥 HOT SALE CUỐI TUẦN">
                    </div>
                    <div class="fs-form-group">
                        <label>Giảm giá mặc định (%)</label>
                        <div class="fs-input-suffix">
                            <input type="number" id="fs-default-discount" class="fs-input" min="1" max="99"
                                   value="<?= $flash_config['default_discount'] ?? 20 ?>">
                            <span>%</span>
                        </div>
                        <small>Áp dụng cho sản phẩm chưa được tùy chỉnh</small>
                    </div>
                </div>
            </div>
            <div class="fs-config-card">
                <div class="fs-config-card-header">
                    <i class="fa-solid fa-clock"></i> Countdown Timer
                </div>
                <div class="fs-config-body">
                    <div class="fs-form-group">
                        <label>Thời gian kết thúc</label>
                        <input type="datetime-local" id="fs-end-time" class="fs-input"
                               value="<?= isset($flash_config['end_time']) ? date('Y-m-d\TH:i', strtotime($flash_config['end_time'])) : date('Y-m-d\TH:i', strtotime('+2 days')) ?>">
                    </div>
                    <div class="fs-countdown-preview" id="fs-countdown-preview">
                        <span id="cp-days">00</span><label>Ngày</label>
                        <span id="cp-hours">00</span><label>Giờ</label>
                        <span id="cp-mins">00</span><label>Phút</label>
                        <span id="cp-secs">00</span><label>Giây</label>
                    </div>
                </div>
            </div>
            <div class="fs-config-card">
                <div class="fs-config-card-header">
                    <i class="fa-solid fa-chart-bar"></i> Trạng thái
                </div>
                <div class="fs-config-body">
                    <div class="fs-status-row">
                        <span>Flash Sale</span>
                        <label class="fs-toggle">
                            <input type="checkbox" id="fs-active"
                                   <?= (!$flash_config || $flash_config['is_active']) ? 'checked' : '' ?>>
                            <span class="fs-slider"></span>
                        </label>
                    </div>
                    <div class="fs-selected-count">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <span id="count-selected"><?= count($current_items) ?></span> / 8 sản phẩm đã chọn
                    </div>
                    <div class="fs-note">
                        <i class="fa-solid fa-circle-info"></i>
                        Tối đa 8 sản phẩm trong Flash Sale
                    </div>
                </div>
            </div>
        </div>

        <!-- CURRENTLY SELECTED PRODUCTS -->
        <div class="fs-section">
            <div class="fs-section-header">
                <h2><i class="fa-solid fa-fire-flame-curved"></i> Sản phẩm trong Flash Sale</h2>
                <button class="btn-clear-all" id="btn-clear-selection">
                    <i class="fa-solid fa-trash"></i> Xóa tất cả
                </button>
            </div>
            <div id="fs-selected-grid" class="fs-selected-grid">
                <?php if (empty($current_items)): ?>
                <div class="fs-empty-state" id="fs-empty-msg">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <p>Chưa có sản phẩm. Dùng AI để chọn hoặc thêm thủ công bên dưới.</p>
                </div>
                <?php else: ?>
                <?php foreach ($current_items as $item): ?>
                <div class="fs-selected-card" data-id="<?= $item['product_id'] ?>">
                    <div class="fs-card-remove" onclick="removeSelectedProduct(<?= $item['product_id'] ?>)">
                        <i class="fa-solid fa-xmark"></i>
                    </div>
                    <?php $img_path = (strpos($item['image'], 'http') === 0) ? $item['image'] : '../assets/img/' . $item['image']; ?>
                    <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($item['name']) ?>"
                         onerror="this.src='../assets/img/no-image.png'">
                    <div class="fs-card-info">
                        <div class="fs-card-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="fs-card-price"><?= number_format($item['price']) ?>đ</div>
                    </div>
                    <div class="fs-discount-control">
                        <label>Giảm giá</label>
                        <div class="fs-discount-input-wrap">
                            <select class="fs-discount-type" data-id="<?= $item['product_id'] ?>">
                                <option value="percent" <?= ($item['discount_type'] ?? 'percent') === 'percent' ? 'selected' : '' ?>>%</option>
                                <option value="fixed" <?= ($item['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>VNĐ</option>
                            </select>
                            <input type="number" class="fs-discount-value" data-id="<?= $item['product_id'] ?>"
                                   value="<?= (float)($item['discount_value'] ?? 20) ?>" min="1">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- PRODUCT LIST TO PICK FROM -->
        <div class="fs-section">
            <div class="fs-section-header">
                <h2><i class="fa-solid fa-list"></i> Danh sách sản phẩm</h2>
                <div class="fs-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="fs-search-product" class="fs-search-input" placeholder="Tìm sản phẩm...">
                </div>
            </div>
            <div class="fs-product-table-wrap">
                <table class="admin-table" id="fs-product-table">
                    <thead>
                        <tr>
                            <th width="50">Chọn</th>
                            <th width="70">Ảnh</th>
                            <th>Tên sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Giá gốc</th>
                            <th width="200">Giảm giá tùy chỉnh</th>
                        </tr>
                    </thead>
                    <tbody id="fs-product-tbody">
                    <?php foreach ($all_products as $prod): ?>
                        <?php
                        $is_in_sale = isset($current_items[$prod['id']]);
                        $disc_type = $is_in_sale ? ($current_items[$prod['id']]['discount_type'] ?? 'percent') : 'percent';
                        $disc_val  = $is_in_sale ? ($current_items[$prod['id']]['discount_value'] ?? 20) : 20;
                        ?>
                        <tr class="fs-product-row <?= $is_in_sale ? 'row-selected' : '' ?>"
                            data-id="<?= $prod['id'] ?>"
                            data-name="<?= strtolower(htmlspecialchars($prod['name'])) ?>"
                            data-cat="<?= strtolower(htmlspecialchars($prod['category_name'] ?? '')) ?>">
                            <td class="text-center">
                                <input type="checkbox" class="fs-check-product"
                                       data-id="<?= $prod['id'] ?>"
                                       data-name="<?= htmlspecialchars($prod['name']) ?>"
                                       data-price="<?= $prod['price'] ?>"
                                       data-image="<?= htmlspecialchars($prod['image']) ?>"
                                       <?= $is_in_sale ? 'checked' : '' ?>>
                            </td>
                            <td>
                                <?php $img_path = (strpos($prod['image'], 'http') === 0) ? $prod['image'] : '../assets/img/' . $prod['image']; ?>
                                <img src="<?= htmlspecialchars($img_path) ?>" class="admin-table img"
                                     style="width:55px;height:55px;object-fit:contain;"
                                     alt=""
                                     onerror="this.src='../assets/img/no-image.png'">
                            </td>
                            <td><strong class="product-name-text"><?= htmlspecialchars($prod['name']) ?></strong></td>
                            <td><?= htmlspecialchars($prod['category_name'] ?? '-') ?></td>
                            <td class="price-tag"><?= number_format($prod['price']) ?>đ</td>
                            <td>
                                <div class="fs-inline-discount">
                                    <select class="fs-discount-type-row" data-id="<?= $prod['id'] ?>">
                                        <option value="percent" <?= $disc_type === 'percent' ? 'selected' : '' ?>>%</option>
                                        <option value="fixed" <?= $disc_type === 'fixed' ? 'selected' : '' ?>>VNĐ</option>
                                    </select>
                                    <input type="number" class="fs-discount-value-row" data-id="<?= $prod['id'] ?>"
                                           value="<?= (float)$disc_val ?>" min="1" placeholder="Mặc định: 20">
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div><!-- end admin-content -->
</div>

<script>
// ============================================================
// FLASH SALE MANAGER JS
// ============================================================
var allProducts = <?= json_encode($all_products) ?>;
var currentItems = <?= json_encode(array_values($current_items)) ?>;
var MAX_ITEMS = 8;

// -- Countdown preview --
function updateCountdownPreview() {
    var endVal = $('#fs-end-time').val();
    if (!endVal) return;
    var end = new Date(endVal).getTime();
    var now = new Date().getTime();
    var diff = end - now;
    if (diff <= 0) {
        $('#cp-days, #cp-hours, #cp-mins, #cp-secs').text('00');
        return;
    }
    var days  = Math.floor(diff / (1000*60*60*24));
    var hours = Math.floor((diff % (1000*60*60*24)) / (1000*60*60));
    var mins  = Math.floor((diff % (1000*60*60)) / (1000*60));
    var secs  = Math.floor((diff % (1000*60)) / 1000);
    $('#cp-days').text(String(days).padStart(2,'0'));
    $('#cp-hours').text(String(hours).padStart(2,'0'));
    $('#cp-mins').text(String(mins).padStart(2,'0'));
    $('#cp-secs').text(String(secs).padStart(2,'0'));
}
setInterval(updateCountdownPreview, 1000);
$('#fs-end-time').on('change', updateCountdownPreview);
updateCountdownPreview();

// -- Get checked IDs --
function getSelectedIds() {
    var ids = [];
    $('.fs-selected-card').each(function() { ids.push($(this).data('id')); });
    return ids;
}

function updateCount() {
    var n = $('.fs-selected-card').length;
    $('#count-selected').text(n);
    if (n === 0) {
        if ($('#fs-empty-msg').length === 0) {
            $('#fs-selected-grid').html('<div class="fs-empty-state" id="fs-empty-msg"><i class="fa-solid fa-wand-magic-sparkles"></i><p>Chưa có sản phẩm. Dùng AI để chọn hoặc thêm thủ công bên dưới.</p></div>');
        }
    } else {
        $('#fs-empty-msg').remove();
    }
}

// Xóa toàn bộ selection hiện tại (dùng chung cho AI & random suggest)
function clearSelection() {
    $('.fs-selected-card').each(function() {
        var pid = $(this).data('id');
        $('.fs-check-product[data-id="' + pid + '"]').prop('checked', false);
        $('.fs-product-row[data-id="' + pid + '"]').removeClass('row-selected');
    });
    $('#fs-selected-grid').html('');
    updateCount();
}

// -- Add product card to selected grid --
function addToSelectedGrid(prod, discType, discVal) {
    if ($('.fs-selected-card[data-id="' + prod.id + '"]').length) return;
    if ($('.fs-selected-card').length >= MAX_ITEMS) {
        Swal.fire('Giới hạn', 'Flash Sale chỉ cho phép tối đa 8 sản phẩm!', 'warning');
        return false;
    }
    $('#fs-empty-msg').remove();
    var imgSrc = prod.image && prod.image.startsWith('http') ? prod.image : '../assets/img/' + (prod.image || '');
    var html = `
        <div class="fs-selected-card" data-id="${prod.id}">
            <div class="fs-card-remove" onclick="removeSelectedProduct(${prod.id})"><i class="fa-solid fa-xmark"></i></div>
            <img src="${imgSrc}" alt="${prod.name}" onerror="this.src='../assets/img/no-image.png'">
            <div class="fs-card-info">
                <div class="fs-card-name">${prod.name}</div>
                <div class="fs-card-price">${Number(prod.price).toLocaleString('vi-VN')}đ</div>
            </div>
            <div class="fs-discount-control">
                <label>Giảm giá</label>
                <div class="fs-discount-input-wrap">
                    <select class="fs-discount-type" data-id="${prod.id}">
                        <option value="percent" ${discType==='percent'?'selected':''}>%</option>
                        <option value="fixed" ${discType==='fixed'?'selected':''}>VNĐ</option>
                    </select>
                    <input type="number" class="fs-discount-value" data-id="${prod.id}" value="${discVal||20}" min="1">
                </div>
            </div>
        </div>`;
    $('#fs-selected-grid').append(html);
    updateCount();
    return true;
}

function removeSelectedProduct(pid) {
    $('.fs-selected-card[data-id="' + pid + '"]').remove();
    $('.fs-check-product[data-id="' + pid + '"]').prop('checked', false);
    $('.fs-product-row[data-id="' + pid + '"]').removeClass('row-selected');
    updateCount();
}

// -- Checkbox change in product table --
$(document).on('change', '.fs-check-product', function() {
    var pid     = $(this).data('id');
    var pname   = $(this).data('name');
    var pprice  = $(this).data('price');
    var pimage  = $(this).data('image');
    var row     = $('.fs-product-row[data-id="' + pid + '"]');
    var discType = row.find('.fs-discount-type-row').val();
    var discVal  = row.find('.fs-discount-value-row').val() || 20;

    if ($(this).prop('checked')) {
        var ok = addToSelectedGrid({id: pid, name: pname, price: pprice, image: pimage}, discType, discVal);
        if (!ok) { $(this).prop('checked', false); return; }
        row.addClass('row-selected');
    } else {
        removeSelectedProduct(pid);
    }
});

// -- Sync discount value from table row to selected card --
$(document).on('change input', '.fs-discount-type-row, .fs-discount-value-row', function() {
    var pid = $(this).data('id');
    var row = $('.fs-product-row[data-id="' + pid + '"]');
    var discType = row.find('.fs-discount-type-row').val();
    var discVal  = row.find('.fs-discount-value-row').val();
    // Sync to card
    $('.fs-selected-card[data-id="' + pid + '"] .fs-discount-type').val(discType);
    $('.fs-selected-card[data-id="' + pid + '"] .fs-discount-value').val(discVal);
});

$(document).on('change input', '.fs-discount-type, .fs-discount-value', function() {
    var pid = $(this).data('id');
    var card = $('.fs-selected-card[data-id="' + pid + '"]');
    var discType = card.find('.fs-discount-type').val();
    var discVal  = card.find('.fs-discount-value').val();
    // Sync to row
    $('.fs-product-row[data-id="' + pid + '"] .fs-discount-type-row').val(discType);
    $('.fs-product-row[data-id="' + pid + '"] .fs-discount-value-row').val(discVal);
});

// -- Search filter --
$('#fs-search-product').on('input', function() {
    var q = $(this).val().trim().toLowerCase();
    $('#fs-product-tbody tr').each(function() {
        var name = $(this).data('name') || '';
        var cat  = $(this).data('cat') || '';
        $(this).toggle(!q || name.includes(q) || cat.includes(q));
    });
});

// -- Clear all selection --
$('#btn-clear-selection').on('click', function() {
    Swal.fire({
        title: 'Xóa toàn bộ?',
        text: 'Bạn có chắc muốn xóa hết sản phẩm khỏi Flash Sale?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    }).then(function(result) {
        if (result.isConfirmed) {
            $('.fs-selected-card').each(function() {
                var pid = $(this).data('id');
                $('.fs-check-product[data-id="' + pid + '"]').prop('checked', false);
                $('.fs-product-row[data-id="' + pid + '"]').removeClass('row-selected');
            });
            $('#fs-selected-grid').html('<div class="fs-empty-state" id="fs-empty-msg"><i class="fa-solid fa-wand-magic-sparkles"></i><p>Chưa có sản phẩm. Dùng AI để chọn hoặc thêm thủ công bên dưới.</p></div>');
            updateCount();
        }
    });
});

// -- AI Suggest: Chọn 8 sản phẩm bán chạy --
$('#btn-ai-suggest').on('click', function() {
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Đang phân tích...');

    $.post('../api/flash_sale_api.php', { action: 'ai_suggest' }, function(res) {
        btn.prop('disabled', false).html('<i class="fa-solid fa-wand-magic-sparkles"></i> AI Chọn 8 SP Hot');
        try {
            var data = typeof res === 'object' ? res : JSON.parse(res);
            if (data.status === 'success') {
                clearSelection();
                var defaultDisc = parseInt($('#fs-default-discount').val()) || 20;
                data.products.forEach(function(prod) {
                    addToSelectedGrid(prod, 'percent', defaultDisc);
                    $('.fs-check-product[data-id="' + prod.id + '"]').prop('checked', true);
                    $('.fs-product-row[data-id="' + prod.id + '"]').addClass('row-selected');
                });
                Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                    title: 'AI đã chọn ' + data.products.length + ' sản phẩm bán chạy!',
                    showConfirmButton: false, timer: 2000 });
            } else {
                Swal.fire('Lỗi', data.message || 'Không thể lấy gợi ý AI', 'error');
            }
        } catch(e) { Swal.fire('Lỗi', 'Lỗi phân tích dữ liệu', 'error'); }
    }).fail(function() { btn.prop('disabled', false).html('<i class="fa-solid fa-wand-magic-sparkles"></i> AI Chọn 8 SP Hot'); });
});

// -- Random Suggest: Chọn 8 sản phẩm ngẫu nhiên --
$('#btn-random-suggest').on('click', function() {
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Đang chọn...');

    $.post('../api/flash_sale_api.php', { action: 'random_suggest' }, function(res) {
        btn.prop('disabled', false).html('<i class="fa-solid fa-shuffle"></i> Ngẫu nhiên 8 SP');
        try {
            var data = typeof res === 'object' ? res : JSON.parse(res);
            if (data.status === 'success') {
                clearSelection();
                var defaultDisc = parseInt($('#fs-default-discount').val()) || 20;
                data.products.forEach(function(prod) {
                    addToSelectedGrid(prod, 'percent', defaultDisc);
                    $('.fs-check-product[data-id="' + prod.id + '"]').prop('checked', true);
                    $('.fs-product-row[data-id="' + prod.id + '"]').addClass('row-selected');
                });
                Swal.fire({ toast: true, position: 'top-end', icon: 'info',
                    title: 'Đã chọn ngẫu nhiên ' + data.products.length + ' sản phẩm!',
                    showConfirmButton: false, timer: 2000 });
            } else {
                Swal.fire('Lỗi', data.message || 'Không thể chọn ngẫu nhiên', 'error');
            }
        } catch(e) { Swal.fire('Lỗi', 'Lỗi phân tích dữ liệu', 'error'); }
    }).fail(function() { btn.prop('disabled', false).html('<i class="fa-solid fa-shuffle"></i> Ngẫu nhiên 8 SP'); });
});

// -- Lưu Flash Sale --
$('#btn-save-flash-sale').on('click', function() {
    var items = [];
    var valid = true;
    $('.fs-selected-card').each(function() {
        var pid       = $(this).data('id');
        var discType  = $(this).find('.fs-discount-type').val();
        var discVal   = parseFloat($(this).find('.fs-discount-value').val());
        if (!discVal || discVal <= 0) { valid = false; return false; }
        items.push({ product_id: pid, discount_type: discType, discount_value: discVal });
    });

    if (!valid) {
        Swal.fire('Lỗi', 'Vui lòng nhập giá trị giảm giá hợp lệ cho tất cả sản phẩm!', 'error');
        return;
    }

    var payload = {
        action: 'save_flash_sale',
        title: $('#fs-title').val(),
        end_time: $('#fs-end-time').val(),
        default_discount: $('#fs-default-discount').val(),
        is_active: $('#fs-active').is(':checked') ? 1 : 0,
        items: JSON.stringify(items)
    };

    $.post('../api/flash_sale_api.php', payload, function(res) {
        try {
            var data = typeof res === 'object' ? res : JSON.parse(res);
            if (data.status === 'success') {
                Swal.fire('Đã lưu!', 'Flash Sale đã được cập nhật thành công.', 'success');
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        } catch(e) { Swal.fire('Lỗi', 'Lỗi xử lý phản hồi', 'error'); }
    }).fail(function() { Swal.fire('Lỗi kết nối', 'Không thể kết nối server', 'error'); });
});

updateCount();
</script>
</body>
</html>
