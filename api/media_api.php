<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================
// UPLOAD IMAGE
// ============================================================
if ($action === 'upload_image') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Upload thất bại']); exit;
    }

    $file = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['status' => 'error', 'message' => 'Chỉ cho phép JPG, PNG, WebP, GIF']); exit;
    }

    $upload_dir = dirname(__DIR__) . '/assets/img/banners/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'banner_' . time() . '_' . uniqid() . '.' . $ext;
    $dest     = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode([
            'status' => 'success',
            'url'    => 'assets/img/banners/' . $filename
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không thể lưu file']);
    }
    exit;
}

// Admin-only beyond this point
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
}

// ============================================================
// ADD BANNER
// ============================================================
if ($action === 'add_banner') {
    $section   = in_array($_POST['section'] ?? '', ['main_banner','right_banners']) ? $_POST['section'] : 'main_banner';
    $image_url = trim($_POST['image_url'] ?? '');
    $alt_text  = trim($_POST['alt_text'] ?? '');

    if (empty($image_url)) {
        echo json_encode(['status' => 'error', 'message' => 'URL ảnh không được trống']); exit;
    }

    // Right banners: max 3
    if ($section === 'right_banners') {
        $count = $conn->query("SELECT COUNT(*) AS n FROM site_banners WHERE section='right_banners'")->fetch_assoc()['n'];
        if ($count >= 3) {
            echo json_encode(['status' => 'error', 'message' => 'Chỉ tối đa 3 banner phải. Hãy xóa ô cũ trước.']); exit;
        }
    }

    $sort = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM site_banners WHERE section='$section'")->fetch_assoc()['n'];
    $stmt = $conn->prepare("INSERT INTO site_banners (section, image_url, alt_text, sort_order) VALUES (?,?,?,?)");
    $stmt->bind_param('sssi', $section, $image_url, $alt_text, $sort);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'id' => $conn->insert_id, 'message' => 'Đã thêm banner!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi DB: ' . $stmt->error]);
    }
    $stmt->close(); exit;
}

// ============================================================
// UPDATE BANNER
// ============================================================
if ($action === 'update_banner') {
    $id        = (int)($_POST['id'] ?? 0);
    $image_url = trim($_POST['image_url'] ?? '');
    $alt_text  = trim($_POST['alt_text'] ?? '');

    if ($id <= 0 || empty($image_url)) {
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']); exit;
    }

    $stmt = $conn->prepare("UPDATE site_banners SET image_url=?, alt_text=? WHERE id=?");
    $stmt->bind_param('ssi', $image_url, $alt_text, $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Đã cập nhật!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi DB']);
    }
    $stmt->close(); exit;
}

// ============================================================
// DELETE BANNER
// ============================================================
if ($action === 'delete_banner') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['status' => 'error', 'message' => 'ID không hợp lệ']); exit; }

    $stmt = $conn->prepare("DELETE FROM site_banners WHERE id=?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Đã xóa!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi DB']);
    }
    $stmt->close(); exit;
}

// ============================================================
// GET BANNERS (Public API for index.php)
// ============================================================
if ($action === 'get_banners') {
    $section = in_array($_GET['section'] ?? '', ['main_banner','right_banners']) ? $_GET['section'] : 'main_banner';
    $result  = $conn->query("SELECT * FROM site_banners WHERE section='$section' AND is_active=1 ORDER BY sort_order ASC");
    $banners = [];
    if ($result) { while ($row = $result->fetch_assoc()) $banners[] = $row; }
    echo json_encode(['status' => 'success', 'banners' => $banners]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
