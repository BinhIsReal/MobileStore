<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
include_once '../includes/flash_sale_helper.php';
header('Content-Type: application/json');

$current_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$action = $_POST['action'] ?? '';

// =================================================================
// 1. XỬ LÝ ĐÁNH GIÁ (REVIEW)
// =================================================================
if ($action == 'submit_review') {
    csrf_verify_or_die();

    if ($current_user_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Phiên đăng nhập hết hạn hoặc chưa đăng nhập. Vui lòng F5 và đăng nhập lại!']);
        exit;
    }

    $pid = intval($_POST['product_id']);
    // Kiểm tra nếu sản phẩm ID không hợp lệ
    if ($pid <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không hợp lệ!']);
        exit;
    }

    $rating = intval($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');
    
    // Validate comment
    if (empty($comment)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập nội dung đánh giá!']);
        exit;
    }

    // Câu lệnh SQL
    $stmt = $conn->prepare("INSERT INTO product_reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiis", $pid, $current_user_id, $rating, $comment);

    if ($stmt->execute()) {
        $rid = $conn->insert_id;
        
        if (!empty($_FILES['images']['name'][0])) {
            $allowed_exts  = ['jpg','jpeg','png','webp','gif'];
            $allowed_mimes = ['image/jpeg','image/png','image/webp','image/gif'];
            if (!file_exists("../assets/img/reviews/")) mkdir("../assets/img/reviews/", 0755, true);

            foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
                if (!$tmp || !is_uploaded_file($tmp)) continue;
                if ($_FILES['images']['size'][$k] > 5 * 1024 * 1024) continue; // Max 5MB

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                $ext = strtolower(pathinfo($_FILES['images']['name'][$k], PATHINFO_EXTENSION));

                if (!in_array($mime, $allowed_mimes) || !in_array($ext, $allowed_exts)) continue;

                $safe_name = 'review_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($tmp, "../assets/img/reviews/" . $safe_name)) {
                    $stmt_img = $conn->prepare("INSERT INTO review_images (review_id, image_path) VALUES (?, ?)");
                    $stmt_img->bind_param("is", $rid, $safe_name);
                    $stmt_img->execute();
                    $stmt_img->close();
                }
            }
        }
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi Database: ' . $stmt->error]);
    }
    exit;
}

// --- Lấy danh sách đánh giá  ---
if ($action == 'get_reviews') {
    $pid = intval($_POST['product_id']);
    $filter = $_POST['star'] ?? 'all';
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    
    $where = "WHERE r.product_id = $pid";
    if (is_numeric($filter)) {
        $where .= " AND r.rating = " . intval($filter);
    } elseif ($filter == 'image') {
        $where .= " AND r.id IN (SELECT review_id FROM review_images)";
    }

    // 2. Đếm tổng số lượng 
    $total_sql = "SELECT COUNT(*) as total FROM product_reviews r $where";
    $total_res = $conn->query($total_sql)->fetch_assoc();
    $total_reviews = $total_res['total'];

    // 3. Lấy dữ liệu chi tiết (Kèm Limit/Offset)
    $sql = "SELECT r.*, u.username, u.id as uid_from_user,
            (SELECT COUNT(*) FROM review_likes WHERE review_id = r.id AND user_id = $current_user_id) as user_liked
            FROM product_reviews r 
            JOIN users u ON r.user_id = u.id 
            $where 
            ORDER BY r.created_at DESC 
            LIMIT $limit OFFSET $offset";
            
    $res = $conn->query($sql);
    
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $imgs = [];
        $r_img = $conn->query("SELECT image_path FROM review_images WHERE review_id = " . $row['id']);
        while ($i = $r_img->fetch_assoc()) $imgs[] = $i['image_path'];
        
        $data[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'rating' => $row['rating'],
            'comment' => $row['comment'],
            'date' => date('d/m/Y', strtotime($row['created_at'])),
            'images' => $imgs,
            'likes' => $row['likes']
        ];
    }

    echo json_encode([
        'reviews' => $data,
        'total' => $total_reviews,
        'has_more' => ($offset + $limit) < $total_reviews
    ]);
    exit;
}

// --- LOGIC THÍCH / BỎ THÍCH ĐÁNH GIÁ (TOGGLE) ---
if ($action == 'like_review') {
    csrf_verify_or_die();

    if ($current_user_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'require_login']);
        exit;
    }

    $review_id = intval($_POST['review_id']);

    // Kiểm tra xem user này đã like bài này chưa
    $stmt_check = $conn->prepare("SELECT * FROM review_likes WHERE user_id = ? AND review_id = ?");
    $stmt_check->bind_param("ii", $current_user_id, $review_id);
    $stmt_check->execute();
    $is_liked = $stmt_check->get_result()->num_rows > 0;
    $stmt_check->close();

    if ($is_liked) {
        $stmt_del = $conn->prepare("DELETE FROM review_likes WHERE user_id = ? AND review_id = ?");
        $stmt_del->bind_param("ii", $current_user_id, $review_id);
        $stmt_del->execute(); $stmt_del->close();
        $stmt_upd = $conn->prepare("UPDATE product_reviews SET likes = GREATEST(likes - 1, 0) WHERE id = ?");
        $stmt_upd->bind_param("i", $review_id);
        $stmt_upd->execute(); $stmt_upd->close();
        $liked_status = false;
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO review_likes (user_id, review_id) VALUES (?, ?)");
        $stmt_ins->bind_param("ii", $current_user_id, $review_id);
        $stmt_ins->execute(); $stmt_ins->close();
        $stmt_upd = $conn->prepare("UPDATE product_reviews SET likes = likes + 1 WHERE id = ?");
        $stmt_upd->bind_param("i", $review_id);
        $stmt_upd->execute(); $stmt_upd->close();
        $liked_status = true;
    }

    $stmt_cnt = $conn->prepare("SELECT likes FROM product_reviews WHERE id = ?");
    $stmt_cnt->bind_param("i", $review_id);
    $stmt_cnt->execute();
    $new_likes = $stmt_cnt->get_result()->fetch_assoc()['likes'];
    $stmt_cnt->close();

    echo json_encode([
        'status' => 'success', 
        'liked' => $liked_status, 
        'new_likes' => $new_likes
    ]);
    exit;
}

$sql = "SELECT p.*, ROUND(IFNULL(AVG(r.rating), 0), 1) AS avg_rating, COUNT(r.id) AS review_count
         FROM products p
         LEFT JOIN product_reviews r ON r.product_id = p.id
         WHERE 1=1";

// Lọc Brand
if (isset($_POST['brand']) && $_POST['brand'] != 'all') {
    $b = $conn->real_escape_string($_POST['brand']);
    // Tìm ID hãng theo tên (Vì FE gửi tên hãng)
    $sql .= " AND p.brand_id IN (SELECT id FROM brands WHERE name = '$b')";
}

// Lọc Giá
if (isset($_POST['min_price'])) {
    $min = floatval($_POST['min_price']);
    $max = floatval($_POST['max_price']);
    $sql .= " AND p.price BETWEEN $min AND $max";
}

$sql .= " GROUP BY p.id";

// Sắp xếp — whitelist để tránh ORDER BY injection
$sort = $_POST['sort'] ?? 'newest';
$allowed_sorts = [
    'asc'    => 'p.price ASC',
    'desc'   => 'p.price DESC',
    'newest' => 'p.id DESC',
];
$order_clause = $allowed_sorts[$sort] ?? 'p.id DESC';
$sql .= " ORDER BY $order_clause";

$sql .= " LIMIT 24"; 

$res = $conn->query($sql);
$products = [];
if ($res) {
    while ($row = $res->fetch_assoc()) $products[] = $row;
}

// Batch-inject giá Flash Sale 
if (!empty($products)) {
    $pids      = array_column($products, 'id');
    $flash_map = get_flash_prices_bulk($conn, $pids);

    foreach ($products as &$prod) {
        $pid = (int)$prod['id'];
        if (isset($flash_map[$pid])) {
            $prod['flash_price']          = $flash_map[$pid]['flash_price'];
            $prod['is_flash_sale']        = true;
            $prod['flash_discount_label'] = $flash_map[$pid]['discount_label'];
        } else {
            $prod['flash_price']          = null;
            $prod['is_flash_sale']        = false;
            $prod['flash_discount_label'] = '';
        }
    }
    unset($prod);
}

echo json_encode($products);
?>