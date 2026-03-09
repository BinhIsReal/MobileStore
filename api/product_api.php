<?php
// BẮT BUỘC: session_start phải ở dòng đầu tiên, không có khoảng trắng phía trước
session_start();
include '../config/db.php';
header('Content-Type: application/json');

// Lấy ID từ Session PHP (Server) - Cái này đáng tin cậy nhất
$current_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$action = $_POST['action'] ?? '';

// =================================================================
// 1. XỬ LÝ ĐÁNH GIÁ (REVIEW)
// =================================================================
if ($action == 'submit_review') {
    // Debug: Nếu lỗi, hãy bật dòng dưới để xem nó nhận được gì
    // echo json_encode(['status'=>'error', 'message'=>"Debug ID: $current_user_id"]); exit;

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
        
        // Xử lý upload ảnh
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
                if ($tmp && is_uploaded_file($tmp)) {
                    $name = time() . "_" . basename($_FILES['images']['name'][$k]);
                    $target = "../assets/img/reviews/" . $name;
                    // Tạo thư mục nếu chưa có
                    if (!file_exists("../assets/img/reviews/")) mkdir("../assets/img/reviews/", 0777, true);
                    
                    if (move_uploaded_file($tmp, $target)) {
                        $conn->query("INSERT INTO review_images (review_id, image_path) VALUES ($rid, '$name')");
                    }
                }
            }
        }
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi Database: ' . $stmt->error]);
    }
    exit;
}

// --- Lấy danh sách đánh giá (Có Phân trang & Tối ưu Query) ---
if ($action == 'get_reviews') {
    $pid = intval($_POST['product_id']);
    $filter = $_POST['star'] ?? 'all';
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $limit = 5; // Số lượng đánh giá mỗi lần tải
    $offset = ($page - 1) * $limit;
    
    // 1. Xây dựng điều kiện WHERE
    $where = "WHERE r.product_id = $pid";
    if (is_numeric($filter)) {
        $where .= " AND r.rating = " . intval($filter);
    } elseif ($filter == 'image') {
        $where .= " AND r.id IN (SELECT review_id FROM review_images)";
    }

    // 2. Đếm tổng số lượng (Để biết còn trang sau không)
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
        // Lấy ảnh (Query phụ nhẹ nhàng hơn JOIN lớn nếu index tốt)
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

    // Trả về JSON kèm thông tin phân trang
    echo json_encode([
        'reviews' => $data,
        'total' => $total_reviews,
        'has_more' => ($offset + $limit) < $total_reviews
    ]);
    exit;
}

// --- LOGIC THÍCH / BỎ THÍCH ĐÁNH GIÁ (TOGGLE) ---
if ($action == 'like_review') {
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
        // ĐÃ LIKE -> BỎ LIKE (Unlike)
        $conn->query("DELETE FROM review_likes WHERE user_id = $current_user_id AND review_id = $review_id");
        $conn->query("UPDATE product_reviews SET likes = GREATEST(likes - 1, 0) WHERE id = $review_id");
        $liked_status = false;
    } else {
        // CHƯA LIKE -> THÊM LIKE
        $conn->query("INSERT INTO review_likes (user_id, review_id) VALUES ($current_user_id, $review_id)");
        $conn->query("UPDATE product_reviews SET likes = likes + 1 WHERE id = $review_id");
        $liked_status = true;
    }

    // Lấy tổng số like mới nhất để trả về giao diện
    $res_count = $conn->query("SELECT likes FROM product_reviews WHERE id = $review_id");
    $new_likes = $res_count->fetch_assoc()['likes'];

    echo json_encode([
        'status' => 'success', 
        'liked' => $liked_status, 
        'new_likes' => $new_likes
    ]);
    exit;
}

$sql = "SELECT * FROM products WHERE 1=1";

// Lọc Brand
if (isset($_POST['brand']) && $_POST['brand'] != 'all') {
    $b = $conn->real_escape_string($_POST['brand']);
    // Tìm ID hãng theo tên (Vì FE gửi tên hãng)
    $sql .= " AND brand_id IN (SELECT id FROM brands WHERE name = '$b')";
}

// Lọc Giá
if (isset($_POST['min_price'])) {
    $min = floatval($_POST['min_price']);
    $max = floatval($_POST['max_price']);
    $sql .= " AND price BETWEEN $min AND $max";
}

// Sắp xếp
$sort = $_POST['sort'] ?? 'newest';
if ($sort == 'asc') $sql .= " ORDER BY price ASC";
elseif ($sort == 'desc') $sql .= " ORDER BY price DESC";
else $sql .= " ORDER BY id DESC";

$sql .= " LIMIT 20"; // Giới hạn hiển thị

$res = $conn->query($sql);
$products = [];
if ($res) {
    while ($row = $res->fetch_assoc()) $products[] = $row;
}
echo json_encode($products);
?>