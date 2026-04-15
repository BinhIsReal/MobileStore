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
    CREATE TABLE IF NOT EXISTS site_banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section ENUM('main_banner','right_banners') NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        alt_text VARCHAR(255) DEFAULT '',
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Load banners
$main_banners = [];
$right_banners = [];

$res = $conn->query("SELECT * FROM site_banners ORDER BY section, sort_order ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if ($row['section'] === 'main_banner') {
            $main_banners[] = $row;
        } else {
            $right_banners[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Media Manager - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/media_manager.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="admin-container">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="admin-content">
        <!-- Page Header -->
        <div class="mm-page-header">
            <div class="mm-page-title">
                <i class="fa-solid fa-images"></i>
                <div>
                    <h1>Media Manager</h1>
                    <p>Quản lý banner &amp; hình ảnh hiển thị trên trang chủ</p>
                </div>
            </div>
        </div>

        <!-- TAB NAV -->
        <div class="mm-tab-nav">
            <button class="mm-tab active" data-tab="main-banner">
                <i class="fa-solid fa-panorama"></i> Main Banner (Slideshow)
            </button>
            <button class="mm-tab" data-tab="right-banners">
                <i class="fa-solid fa-th-large"></i> Right Banners (3 ô phải)
            </button>
        </div>

        <!-- =============================== -->
        <!-- TAB 1: MAIN BANNER SLIDESHOW    -->
        <!-- =============================== -->
        <div class="mm-tab-content active" id="tab-main-banner">
            <div class="mm-section">
                <div class="mm-section-header">
                    <h2><i class="fa-solid fa-film"></i> Slide Banner Chính</h2>
                    <span class="mm-badge"><?= count($main_banners) ?> slides</span>
                </div>

                <!-- Live Preview -->
                <div class="mm-preview-wrap">
                    <div class="mm-preview-label"><i class="fa-solid fa-eye"></i> Xem trước slideshow</div>
                    <div class="mm-slideshow-preview" id="main-banner-preview">
                        <?php if (empty($main_banners)): ?>
                        <div class="mm-preview-empty">
                            <i class="fa-solid fa-image"></i>
                            <p>Chưa có banner. Thêm bên dưới.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($main_banners as $idx => $b): ?>
                        <div class="mm-slide <?= $idx === 0 ? 'active' : '' ?>">
                            <img src="<?= htmlspecialchars($b['image_url']) ?>"
                                 alt="<?= htmlspecialchars($b['alt_text']) ?>"
                                 onerror="this.src='../assets/img/no-image.png'">
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <!-- Dots -->
                        <?php if (!empty($main_banners)): ?>
                        <div class="mm-preview-dots" id="preview-dots">
                            <?php foreach ($main_banners as $idx => $b): ?>
                            <span class="mm-dot <?= $idx === 0 ? 'active' : '' ?>" data-idx="<?= $idx ?>"></span>
                            <?php endforeach; ?>
                        </div>
                        <button class="mm-slide-arrow mm-prev" id="mm-prev-btn"><i class="fa-solid fa-chevron-left"></i></button>
                        <button class="mm-slide-arrow mm-next" id="mm-next-btn"><i class="fa-solid fa-chevron-right"></i></button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Banner List -->
                <div class="mm-banner-list" id="main-banner-list">
                    <?php foreach ($main_banners as $b): ?>
                    <div class="mm-banner-item" data-id="<?= $b['id'] ?>">
                        <div class="mm-banner-preview-thumb">
                            <img src="<?= htmlspecialchars($b['image_url']) ?>"
                                 alt=""
                                 onerror="this.src='../assets/img/no-image.png'">
                        </div>
                        <div class="mm-banner-meta">
                            <input type="text" class="mm-input banner-url-input" value="<?= htmlspecialchars($b['image_url']) ?>"
                                   placeholder="URL ảnh hoặc đường dẫn..." data-id="<?= $b['id'] ?>">
                            <input type="text" class="mm-input banner-alt-input" value="<?= htmlspecialchars($b['alt_text']) ?>"
                                   placeholder="Mô tả ảnh (alt text)..." data-id="<?= $b['id'] ?>">
                        </div>
                        <div class="mm-banner-actions">
                            <label class="mm-file-label" title="Chọn ảnh từ máy tính">
                                <i class="fa-solid fa-folder-open"></i>
                                <input type="file" class="mm-file-input" accept="image/*" data-id="<?= $b['id'] ?>" data-section="main_banner">
                            </label>
                            <button class="mm-btn-save-item" data-id="<?= $b['id'] ?>" data-section="main_banner" title="Lưu">
                                <i class="fa-solid fa-floppy-disk"></i>
                            </button>
                            <button class="mm-btn-delete-item" data-id="<?= $b['id'] ?>" title="Xóa slide">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Add New Banner -->
                <div class="mm-add-banner-panel">
                    <h3><i class="fa-solid fa-plus-circle"></i> Thêm Slide Mới</h3>
                    <div class="mm-add-form">
                        <div class="mm-add-input-group">
                            <label>Nhập URL ảnh</label>
                            <input type="text" id="new-main-url" class="mm-input" placeholder="https://... hoặc chọn file bên dưới">
                        </div>
                        <div class="mm-add-input-group">
                            <label>Mô tả ảnh</label>
                            <input type="text" id="new-main-alt" class="mm-input" placeholder="VD: Banner giới thiệu iPhone 15">
                        </div>
                        <div class="mm-add-actions">
                            <label class="mm-file-btn-label" for="new-main-file">
                                <i class="fa-solid fa-folder-open"></i> Chọn từ máy
                            </label>
                            <input type="file" id="new-main-file" accept="image/*" style="display:none">
                            <button id="btn-add-main-banner" class="mm-btn-add">
                                <i class="fa-solid fa-plus"></i> Thêm Slide
                            </button>
                        </div>
                        <!-- New Preview -->
                        <div class="mm-new-preview" id="new-main-preview" style="display:none">
                            <img id="new-main-preview-img" src="" alt="">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================== -->
        <!-- TAB 2: RIGHT BANNERS            -->
        <!-- =============================== -->
        <div class="mm-tab-content" id="tab-right-banners">
            <div class="mm-section">
                <div class="mm-section-header">
                    <h2><i class="fa-solid fa-th-large"></i> Banner phải (3 ô)</h2>
                    <span class="mm-badge mm-badge-green"><?= count($right_banners) ?>/3 ô</span>
                </div>

                <!-- Right Banner Grid Preview -->
                <div class="mm-right-preview-wrap">
                    <div class="mm-preview-label"><i class="fa-solid fa-eye"></i> Xem trước bố cục</div>
                    <div class="mm-right-preview-grid" id="right-preview-grid">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <?php $b = $right_banners[$i] ?? null; ?>
                        <div class="mm-right-preview-cell">
                            <?php if ($b): ?>
                            <img src="<?= htmlspecialchars($b['image_url']) ?>"
                                 alt="<?= htmlspecialchars($b['alt_text']) ?>"
                                 id="right-preview-<?= $i ?>"
                                 onerror="this.src='../assets/img/no-image.png'">
                            <?php else: ?>
                            <div class="mm-right-empty-cell" id="right-preview-<?= $i ?>">
                                <i class="fa-solid fa-image"></i>
                                <span>Ô <?= $i + 1 ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Right Banner Edit Panel -->
                <div class="mm-right-edit-grid">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <?php $b = $right_banners[$i] ?? null; ?>
                    <div class="mm-right-edit-card">
                        <div class="mm-right-edit-header">
                            <i class="fa-solid fa-image"></i> Ô Banner <?= $i + 1 ?>
                            <?php if ($b): ?>
                            <span class="mm-badge-mini mm-active-badge">Đang hiển thị</span>
                            <?php else: ?>
                            <span class="mm-badge-mini mm-empty-badge">Trống</span>
                            <?php endif; ?>
                        </div>
                        <div class="mm-right-edit-body">
                            <div class="mm-right-thumb">
                                <?php if ($b): ?>
                                <img src="<?= htmlspecialchars($b['image_url']) ?>"
                                     alt=""
                                     id="right-thumb-<?= $i ?>"
                                     onerror="this.src='../assets/img/no-image.png'">
                                <?php else: ?>
                                <div class="mm-right-thumb-empty" id="right-thumb-<?= $i ?>">
                                    <i class="fa-solid fa-image"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mm-right-inputs">
                                <label>Link ảnh / URL</label>
                                <input type="text" class="mm-input right-url-input" id="right-url-<?= $i ?>"
                                       value="<?= $b ? htmlspecialchars($b['image_url']) : '' ?>"
                                       placeholder="https:// hoặc chọn file"
                                       data-idx="<?= $i ?>"
                                       data-id="<?= $b['id'] ?? '' ?>">
                                <label style="margin-top:8px;">Mô tả</label>
                                <input type="text" class="mm-input right-alt-input" id="right-alt-<?= $i ?>"
                                       value="<?= $b ? htmlspecialchars($b['alt_text']) : '' ?>"
                                       placeholder="Mô tả banner <?= $i + 1 ?>"
                                       data-idx="<?= $i ?>">
                            </div>
                            <div class="mm-right-card-actions">
                                <label class="mm-file-label" title="Chọn từ máy tính">
                                    <i class="fa-solid fa-folder-open"></i> Chọn ảnh
                                    <input type="file" class="mm-right-file-input" accept="image/*"
                                           data-idx="<?= $i ?>" data-id="<?= $b['id'] ?? '' ?>">
                                </label>
                                <button class="mm-btn-save-right" data-idx="<?= $i ?>"
                                        data-id="<?= $b['id'] ?? '' ?>">
                                    <i class="fa-solid fa-floppy-disk"></i> Lưu
                                </button>
                                <?php if ($b): ?>
                                <button class="mm-btn-delete-right" data-id="<?= $b['id'] ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div><!-- end admin-content -->
</div>

<!-- Upload Progress Toast -->
<div id="mm-upload-toast" class="mm-upload-toast" style="display:none">
    <i class="fa-solid fa-spinner fa-spin"></i> Đang tải ảnh lên...
</div>

<script>
// ============================================================
// TAB SWITCHING
// ============================================================
$('.mm-tab').on('click', function () {
    $('.mm-tab').removeClass('active');
    $('.mm-tab-content').removeClass('active');
    $(this).addClass('active');
    $('#tab-' + $(this).data('tab')).addClass('active');
});

// ============================================================
// SLIDESHOW PREVIEW (Main Banner)
// ============================================================
var slideIdx = 0;
var slides   = [];

function buildSlides() {
    slides = [];
    $('.mm-slide').each(function () { slides.push($(this)); });
    buildDots();
}

function buildDots() {
    var $dots = $('#preview-dots');
    $dots.html('');
    slides.forEach(function (_, idx) {
        $dots.append('<span class="mm-dot ' + (idx === 0 ? 'active' : '') + '" data-idx="' + idx + '"></span>');
    });
}

function goToSlide(n) {
    if (slides.length === 0) return;
    slideIdx = (n + slides.length) % slides.length;
    slides.forEach(function (s, i) { s.toggleClass('active', i === slideIdx); });
    $('#preview-dots .mm-dot').removeClass('active').eq(slideIdx).addClass('active');
}

$(document).on('click', '.mm-dot', function () { goToSlide($(this).data('idx')); });
$(document).on('click', '#mm-next-btn', function () { goToSlide(slideIdx + 1); });
$(document).on('click', '#mm-prev-btn', function () { goToSlide(slideIdx - 1); });

// Auto-play preview
setInterval(function () {
    if (slides.length > 1) goToSlide(slideIdx + 1);
}, 3000);

$(document).ready(function () { buildSlides(); });

// ============================================================
// IMAGE URL LIVE PREVIEW for banner items
// ============================================================
$(document).on('input', '.banner-url-input', function () {
    var url = $(this).val().trim();
    $(this).closest('.mm-banner-item').find('.mm-banner-preview-thumb img').attr('src', url);
});

$(document).on('input', '#new-main-url', function () {
    var url = $(this).val().trim();
    if (url) {
        $('#new-main-preview').show();
        $('#new-main-preview-img').attr('src', url);
    } else {
        $('#new-main-preview').hide();
    }
});

$(document).on('input', '.right-url-input', function () {
    var url = $(this).val().trim();
    var idx = $(this).data('idx');
    if (url) {
        $('#right-thumb-' + idx).replaceWith('<img src="' + url + '" alt="" id="right-thumb-' + idx + '" onerror="this.src=\'../assets/img/no-image.png\'">');
        $('#right-preview-' + idx).replaceWith('<img src="' + url + '" alt="" id="right-preview-' + idx + '" onerror="this.src=\'../assets/img/no-image.png\'">');
    }
});

// ============================================================
// FILE UPLOAD HELPERS
// ============================================================
function uploadFile(file, callback) {
    $('#mm-upload-toast').fadeIn(200);
    var fd = new FormData();
    fd.append('action', 'upload_image');
    fd.append('image', file);
    $.ajax({
        url: '../api/media_api.php',
        type: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        success: function (res) {
            $('#mm-upload-toast').fadeOut(200);
            try {
                var data = typeof res === 'object' ? res : JSON.parse(res);
                if (data.status === 'success') callback(data.url);
                else Swal.fire('Lỗi upload', data.message, 'error');
            } catch (e) { Swal.fire('Lỗi', 'Lỗi xử lý phản hồi', 'error'); }
        },
        error: function () { $('#mm-upload-toast').fadeOut(200); Swal.fire('Lỗi', 'Upload thất bại', 'error'); }
    });
}

// -- New main banner file --
$('#new-main-file').on('change', function () {
    if (!this.files[0]) return;
    uploadFile(this.files[0], function (url) {
        $('#new-main-url').val(url);
        $('#new-main-preview').show();
        $('#new-main-preview-img').attr('src', url);
    });
});

// -- Existing main banner file --
$(document).on('change', '.mm-file-input[data-section="main_banner"]', function () {
    var id  = $(this).data('id');
    var $el = $(this);
    if (!this.files[0]) return;
    uploadFile(this.files[0], function (url) {
        $('.banner-url-input[data-id="' + id + '"]').val(url);
        $el.closest('.mm-banner-item').find('.mm-banner-preview-thumb img').attr('src', url);
    });
});

// -- Right banner file --
$(document).on('change', '.mm-right-file-input', function () {
    var idx = $(this).data('idx');
    if (!this.files[0]) return;
    uploadFile(this.files[0], function (url) {
        $('#right-url-' + idx).val(url);
        var thumb = $('#right-thumb-' + idx);
        if (thumb.is('img')) { thumb.attr('src', url); }
        else { thumb.replaceWith('<img src="' + url + '" alt="" id="right-thumb-' + idx + '" onerror="this.src=\'../assets/img/no-image.png\'">'); }
        var prev = $('#right-preview-' + idx);
        if (prev.is('img')) { prev.attr('src', url); }
        else { prev.replaceWith('<img src="' + url + '" alt="" id="right-preview-' + idx + '" onerror="this.src=\'../assets/img/no-image.png\'">'); }
    });
});

// ============================================================
// ADD NEW MAIN BANNER SLIDE
// ============================================================
$('#btn-add-main-banner').on('click', function () {
    var url = $('#new-main-url').val().trim();
    var alt = $('#new-main-alt').val().trim();
    if (!url) { Swal.fire('Thiếu thông tin', 'Vui lòng nhập URL ảnh hoặc chọn file!', 'warning'); return; }

    $.post('../api/media_api.php', { action: 'add_banner', section: 'main_banner', image_url: url, alt_text: alt }, function (res) {
        try {
            var data = typeof res === 'object' ? res : JSON.parse(res);
            if (data.status === 'success') {
                var html = buildBannerItemHTML(data.id, url, alt, 'main_banner');
                $('#main-banner-list').append(html);
                // Add slide to preview
                var newSlide = $('<div class="mm-slide"><img src="' + url + '" alt="' + alt + '" onerror="this.src=\'../assets/img/no-image.png\'"></div>');
                $('#main-banner-preview').append(newSlide);
                slides.push(newSlide);
                buildDots();
                // Remove empty state
                $('#main-banner-preview .mm-preview-empty').remove();
                // Clear form
                $('#new-main-url').val('');
                $('#new-main-alt').val('');
                $('#new-main-preview').hide();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Đã thêm slide!', showConfirmButton: false, timer: 1800 });
            } else Swal.fire('Lỗi', data.message, 'error');
        } catch (e) { console.error(e); }
    });
});

function buildBannerItemHTML(id, url, alt, section) {
    return `<div class="mm-banner-item" data-id="${id}">
        <div class="mm-banner-preview-thumb"><img src="${url}" alt="" onerror="this.src='../assets/img/no-image.png'"></div>
        <div class="mm-banner-meta">
            <input type="text" class="mm-input banner-url-input" value="${url}" placeholder="URL ảnh..." data-id="${id}">
            <input type="text" class="mm-input banner-alt-input" value="${alt}" placeholder="Mô tả..." data-id="${id}">
        </div>
        <div class="mm-banner-actions">
            <label class="mm-file-label" title="Chọn ảnh từ máy tính">
                <i class="fa-solid fa-folder-open"></i>
                <input type="file" class="mm-file-input" accept="image/*" data-id="${id}" data-section="${section}">
            </label>
            <button class="mm-btn-save-item" data-id="${id}" data-section="${section}" title="Lưu"><i class="fa-solid fa-floppy-disk"></i></button>
            <button class="mm-btn-delete-item" data-id="${id}" title="Xóa"><i class="fa-solid fa-trash"></i></button>
        </div>
    </div>`;
}

// ============================================================
// SAVE EXISTING BANNER ITEM
// ============================================================
$(document).on('click', '.mm-btn-save-item', function () {
    var id      = $(this).data('id');
    var section = $(this).data('section');
    var url     = $('.banner-url-input[data-id="' + id + '"]').val().trim();
    var alt     = $('.banner-alt-input[data-id="' + id + '"]').val().trim();
    if (!url) { Swal.fire('Thiếu thông tin', 'URL ảnh không được trống!', 'warning'); return; }

    $.post('../api/media_api.php', { action: 'update_banner', id: id, image_url: url, alt_text: alt }, function (res) {
        try {
            var data = typeof res === 'object' ? res : JSON.parse(res);
            if (data.status === 'success') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Đã lưu!', showConfirmButton: false, timer: 1500 });
            } else Swal.fire('Lỗi', data.message, 'error');
        } catch (e) {}
    });
});

// ============================================================
// DELETE BANNER ITEM
// ============================================================
$(document).on('click', '.mm-btn-delete-item', function () {
    var id   = $(this).data('id');
    var $row = $(this).closest('.mm-banner-item');
    Swal.fire({
        title: 'Xóa slide?', text: 'Banner này sẽ bị xóa khỏi trang chủ.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Xóa', cancelButtonText: 'Hủy',
        confirmButtonColor: '#e74c3c'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.post('../api/media_api.php', { action: 'delete_banner', id: id }, function (res) {
            try {
                var data = typeof res === 'object' ? res : JSON.parse(res);
                if (data.status === 'success') {
                    $row.slideUp(300, function () { $(this).remove(); });
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Đã xóa!', showConfirmButton: false, timer: 1500 });
                } else Swal.fire('Lỗi', data.message, 'error');
            } catch (e) {}
        });
    });
});

// ============================================================
// SAVE RIGHT BANNER
// ============================================================
$(document).on('click', '.mm-btn-save-right', function () {
    var idx = $(this).data('idx');
    var id  = $(this).data('id');
    var url = $('#right-url-' + idx).val().trim();
    var alt = $('#right-alt-' + idx).val().trim();
    if (!url) { Swal.fire('Thiếu thông tin', 'URL ảnh không được trống!', 'warning'); return; }

    var postData = { image_url: url, alt_text: alt };
    if (id) {
        postData.action = 'update_banner';
        postData.id = id;
    } else {
        postData.action = 'add_banner';
        postData.section = 'right_banners';
    }

    $.post('../api/media_api.php', postData, function (res) {
        try {
            var data = typeof res === 'object' ? res : JSON.parse(res);
            if (data.status === 'success') {
                if (!id && data.id) {
                    $('#right-url-' + idx).data('id', data.id);
                    $('.mm-btn-save-right[data-idx="' + idx + '"]').data('id', data.id);
                }
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Đã lưu!', showConfirmButton: false, timer: 1500 });
            } else Swal.fire('Lỗi', data.message, 'error');
        } catch (e) { console.error(e); }
    });
});

// ============================================================
// DELETE RIGHT BANNER
// ============================================================
$(document).on('click', '.mm-btn-delete-right', function () {
    var id  = $(this).data('id');
    var $card = $(this).closest('.mm-right-edit-card');
    Swal.fire({
        title: 'Xóa banner?', text: 'Ô này sẽ trống sau khi xóa.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Xóa', cancelButtonText: 'Hủy',
        confirmButtonColor: '#e74c3c'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.post('../api/media_api.php', { action: 'delete_banner', id: id }, function (res) {
            try {
                var data = typeof res === 'object' ? res : JSON.parse(res);
                if (data.status === 'success') {
                    location.reload();
                } else Swal.fire('Lỗi', data.message, 'error');
            } catch (e) {}
        });
    });
});
</script>
</body>
</html>
