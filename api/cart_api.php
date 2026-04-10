<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
header('Content-Type: application/json');

// =============================================
// SECURITY: Không bao giờ expose lỗi ra ngoài
// =============================================
ini_set('display_errors', 0);
error_reporting(0);

$action  = $_POST['action'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

// SECURITY: Xác thực CSRF Token cho mọi action thay đổi trạng thái giỏ hàng
$state_changing_actions = ['add', 'delete', 'delete_list', 'delete_all', 'update_qty', 'checkout'];
if (in_array($action, $state_changing_actions, true)) {
    csrf_verify_or_die();
}

// -----------------------------------------------
// 1. ADD TO CART
// -----------------------------------------------
if ($action === 'add') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    if ($pid <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không hợp lệ']);
        exit;
    }

    if ($user_id > 0) {
        // FIXED: Dùng Prepared Statement thay vì ghép chuỗi trực tiếp
        $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=?");
        $stmt->bind_param("ii", $user_id, $pid);
        $stmt->execute();
        $check = $stmt->get_result();
        $stmt->close();

        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE cart SET quantity=quantity+? WHERE user_id=? AND product_id=?");
            $stmt->bind_param("iii", $qty, $user_id, $pid);
        } else {
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?,?,?)");
            $stmt->bind_param("iii", $user_id, $pid, $qty);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        if (!isset($_SESSION['cart'][$pid])) $_SESSION['cart'][$pid] = 0;
        $_SESSION['cart'][$pid] += $qty;
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// -----------------------------------------------
// 2. COUNT CART
// -----------------------------------------------
if ($action === 'count') {
    $c = 0;
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT SUM(quantity) as t FROM cart WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $c = (int)($r['t'] ?? 0);
    } else {
        $c = isset($_SESSION['cart']) ? (int)array_sum($_SESSION['cart']) : 0;
    }
    echo json_encode(['count' => $c]);
    exit;
}

// -----------------------------------------------
// 3. DELETE ONE ITEM
// -----------------------------------------------
if ($action === 'delete') {
    $pid = (int)($_POST['id'] ?? 0);
    if ($user_id > 0) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=?");
        $stmt->bind_param("ii", $user_id, $pid);
        $stmt->execute();
        $stmt->close();
    } else {
        unset($_SESSION['cart'][$pid]);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// -----------------------------------------------
// 4. DELETE LIST
// -----------------------------------------------
if ($action === 'delete_list') {
    $raw_ids = json_decode($_POST['ids'] ?? '[]', true);
    if (!is_array($raw_ids)) { echo json_encode(['status' => 'error']); exit; }

    // FIXED: ép kiểu int cho từng phần tử, loại bỏ giá trị 0/âm
    $ids = array_filter(array_map('intval', $raw_ids), fn($v) => $v > 0);

    if (!empty($ids)) {
        if ($user_id > 0) {
            // Dùng placeholder động thay vì implode trực tiếp
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types  = str_repeat('i', count($ids) + 1);
            $params = array_merge([$user_id], array_values($ids));
            $stmt   = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id IN ($placeholders)");
            // FIXED: dùng call_user_func_array thay vì spread operator để pass by reference
            $bind_args = [$types];
            foreach ($params as &$param) $bind_args[] = &$param;
            call_user_func_array([$stmt, 'bind_param'], $bind_args);
            $stmt->execute();
            $stmt->close();
        } else {
            foreach ($ids as $pid) unset($_SESSION['cart'][$pid]);
        }
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// -----------------------------------------------
// 5. DELETE ALL
// -----------------------------------------------
if ($action === 'delete_all') {
    if ($user_id > 0) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        unset($_SESSION['cart']);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// -----------------------------------------------
// 6. UPDATE QUANTITY
// -----------------------------------------------
if ($action === 'update_qty') {
    $pid   = (int)($_POST['product_id'] ?? 0);
    $delta = (int)($_POST['delta'] ?? 0);
    $new_qty = 0;

    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=?");
        $stmt->bind_param("ii", $user_id, $pid);
        $stmt->execute();
        $curr = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($curr) {
            $new_qty = $curr['quantity'] + $delta;
            if ($new_qty >= 1) {
                $stmt = $conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=?");
                $stmt->bind_param("iii", $new_qty, $user_id, $pid);
                $stmt->execute();
                $stmt->close();
            } else {
                $new_qty = 1;
            }
        }
    } else {
        if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid] = max(1, $_SESSION['cart'][$pid] + $delta);
            $new_qty = $_SESSION['cart'][$pid];
        }
    }
    echo json_encode(['status' => 'success', 'new_qty' => $new_qty]);
    exit;
}

// -----------------------------------------------
// 7. GET CART
// -----------------------------------------------
if ($action === 'get_cart') {
    $data = [];
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT p.id as product_id, p.name, p.image, p.price, c.quantity
                                FROM cart c JOIN products p ON c.product_id = p.id
                                WHERE c.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $data[] = $r;
        $stmt->close();
    } else {
        if (!empty($_SESSION['cart'])) {
            // FIXED: array_keys của session đã được ép int khi lưu, nhưng phòng ngừa thêm
            $ids = array_filter(array_map('intval', array_keys($_SESSION['cart'])), fn($v) => $v > 0);
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $types  = str_repeat('i', count($ids));
                $vals   = array_values($ids);
                $stmt   = $conn->prepare("SELECT id as product_id, name, image, price FROM products WHERE id IN ($placeholders)");
                // FIXED: call_user_func_array thay vì spread operator
                $bind_args = [$types];
                foreach ($vals as &$val) $bind_args[] = &$val;
                call_user_func_array([$stmt, 'bind_param'], $bind_args);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $r['quantity'] = $_SESSION['cart'][$r['product_id']];
                    $data[] = $r;
                }
                $stmt->close();
            }
        }
    }
    echo json_encode($data);
    exit;
}

// -----------------------------------------------
// 8. CHECKOUT
// -----------------------------------------------
if ($action === 'checkout') {
    // SECURITY: Bắt buộc đăng nhập mới được checkout
    if ($user_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập!']);
        exit;
    }

    $info  = $_POST['info'] ?? [];
    $items = json_decode($_POST['items'] ?? '[]', true);

    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Giỏ hàng rỗng']);
        exit;
    }

    // SECURITY: Validate & Sanitize thông tin người dùng trước khi lưu
    $buyer_name    = trim(htmlspecialchars($info['name']    ?? '', ENT_QUOTES, 'UTF-8'));
    $buyer_phone   = trim(preg_replace('/[^0-9+\-]/', '', $info['phone']   ?? ''));
    $buyer_address = trim(htmlspecialchars($info['address'] ?? '', ENT_QUOTES, 'UTF-8'));

    if (empty($buyer_name) || empty($buyer_phone) || empty($buyer_address)) {
        echo json_encode(['status' => 'error', 'message' => 'Thông tin nhận hàng không đầy đủ']);
        exit;
    }

    // SECURITY: Whitelist payment method
    $allowed_methods  = ['cod', 'banking'];
    $payment_method   = in_array($_POST['payment_method'] ?? '', $allowed_methods) ? $_POST['payment_method'] : 'cod';
    // BƯỚC 1: Tính tổng tiền Server-side (an toàn, không tin giá từ client)
    $total = 0;
    $valid_items = [];
    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = max(1, (int)($it['quantity'] ?? 1));
        if ($pid <= 0) continue;

        $stmt = $conn->prepare("SELECT price, sale_price FROM products WHERE id=?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($p) {
            $unit_price     = ($p['sale_price'] > 0) ? (float)$p['sale_price'] : (float)$p['price'];
            $total         += $unit_price * $qty;
            $valid_items[]  = ['pid' => $pid, 'qty' => $qty, 'price' => $unit_price];
        }
    }

    if (empty($valid_items)) {
        echo json_encode(['status' => 'error', 'message' => 'Không có sản phẩm hợp lệ']);
        exit;
    }

    // BƯỚC 2: Tính toán Voucher
    $discount_amount = 0;
    $voucher_id = (int)($_POST['voucher_id'] ?? 0);
    
    if ($voucher_id > 0) {
        // Kiểm tra Voucher xem có còn hợp lệ đối với User không
        $v_sql = "SELECT v.* FROM user_vouchers uv 
                  JOIN vouchers v ON uv.voucher_id = v.id 
                  WHERE uv.user_id = ? AND uv.voucher_id = ? 
                  AND (v.expiry_date >= CURDATE() OR v.expiry_date = '0000-00-00')
                  AND uv.usage_limit > uv.used_count";
                  
        $v_stmt = $conn->prepare($v_sql);
        $v_stmt->bind_param("ii", $user_id, $voucher_id);
        $v_stmt->execute();
        $v_res = $v_stmt->get_result();
        
        if ($v = $v_res->fetch_assoc()) {
            if ($total >= $v['min_order_value']) {
                if ($v['type'] == 'percent') {
                     $cal_discount = $total * ($v['discount_amount'] / 100);
                     if ($v['max_discount'] > 0 && $cal_discount > $v['max_discount']) {
                         $cal_discount = $v['max_discount'];
                     }
                     $discount_amount = $cal_discount;
                } else {
                     $discount_amount = $v['discount_amount'];
                }
                
                if ($discount_amount > $total) $discount_amount = $total;
                
                // Trừ lượt dùng của user đó
                $conn->query("UPDATE user_vouchers SET used_count = used_count + 1 WHERE user_id = $user_id AND voucher_id = $voucher_id");
            }
        }
        $v_stmt->close();
    }

    // BƯỚC 3: Tạo đơn hàng
    $sql  = "INSERT INTO orders (user_id, name, phone, address, total_price, discount_amount, status, payment_method, payment_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, 'unpaid', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssdds", $user_id, $buyer_name, $buyer_phone, $buyer_address, $total, $discount_amount, $payment_method);

    if ($stmt->execute()) {
        $oid   = $conn->insert_id;
        $stmt->close();

        $stmt_d = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?,?,?,?)");
        $stmt_del = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=?");

        foreach ($valid_items as $v) {
            $stmt_d->bind_param("iiid", $oid, $v['pid'], $v['qty'], $v['price']);
            $stmt_d->execute();

            $stmt_del->bind_param("ii", $user_id, $v['pid']);
            $stmt_del->execute();
        }
        $stmt_d->close();
        $stmt_del->close();

        echo json_encode(['status' => 'success', 'order_id' => $oid, 'total_money' => $total]);
    } else {
        // SECURITY: Không expose chi tiết lỗi DB ra ngoài
        error_log("Checkout DB Error: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Đặt hàng thất bại, vui lòng thử lại.']);
    }
    exit;
}
?>