<?php
session_start();
include '../config/db.php';
include_once '../includes/security.php';
include_once '../includes/flash_sale_helper.php';
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(0);

if (!function_exists('generateOrderCode')) {
    function generateOrderCode(): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code  = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
}

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
    $vid = isset($_POST['variation_id']) && $_POST['variation_id'] !== '' ? (int)$_POST['variation_id'] : null;

    if ($pid <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không hợp lệ']);
        exit;
    }

    if ($user_id > 0) {
        if ($vid !== null) {
            $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=? AND variation_id=?");
            $stmt->bind_param("iii", $user_id, $pid, $vid);
        } else {
            $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=? AND variation_id IS NULL");
            $stmt->bind_param("ii", $user_id, $pid);
        }
        $stmt->execute();
        $check = $stmt->get_result();
        $stmt->close();

        if ($check->num_rows > 0) {
            if ($vid !== null) {
                $stmt = $conn->prepare("UPDATE cart SET quantity=quantity+? WHERE user_id=? AND product_id=? AND variation_id=?");
                $stmt->bind_param("iiii", $qty, $user_id, $pid, $vid);
            } else {
                $stmt = $conn->prepare("UPDATE cart SET quantity=quantity+? WHERE user_id=? AND product_id=? AND variation_id IS NULL");
                $stmt->bind_param("iii", $qty, $user_id, $pid);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, variation_id) VALUES (?,?,?,?)");
            $stmt->bind_param("iiii", $user_id, $pid, $qty, $vid);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        $key = $vid !== null ? $pid . '_' . $vid : $pid;
        if (!isset($_SESSION['cart'][$key])) $_SESSION['cart'][$key] = 0;
        $_SESSION['cart'][$key] += $qty;
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
    $vid = isset($_POST['vid']) && $_POST['vid'] !== '' ? (int)$_POST['vid'] : 0;
    
    if ($user_id > 0) {
        if ($vid > 0) {
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=? AND variation_id=?");
            $stmt->bind_param("iii", $user_id, $pid, $vid);
        } else {
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=? AND (variation_id IS NULL OR variation_id=0)");
            $stmt->bind_param("ii", $user_id, $pid);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        $key = $vid > 0 ? $pid . '_' . $vid : $pid;
        unset($_SESSION['cart'][$key]);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// -----------------------------------------------
// 4. DELETE LIST
// -----------------------------------------------
if ($action === 'delete_list') {
    $raw_items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($raw_items)) { echo json_encode(['status' => 'error']); exit; }

    if ($user_id > 0) {
        $stmt_var = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=? AND variation_id=?");
        $stmt_no_var = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=? AND (variation_id IS NULL OR variation_id=0)");
        
        foreach ($raw_items as $item) {
            $pid = (int)($item['id'] ?? 0);
            $vid = isset($item['vid']) && $item['vid'] !== '' ? (int)$item['vid'] : 0;
            if ($pid <= 0) continue;
            
            if ($vid > 0) {
                $stmt_var->bind_param("iii", $user_id, $pid, $vid);
                $stmt_var->execute();
            } else {
                $stmt_no_var->bind_param("ii", $user_id, $pid);
                $stmt_no_var->execute();
            }
        }
        if($stmt_var) $stmt_var->close();
        if($stmt_no_var) $stmt_no_var->close();
    } else {
        foreach ($raw_items as $item) {
            $pid = (int)($item['id'] ?? 0);
            $vid = isset($item['vid']) && $item['vid'] !== '' ? (int)$item['vid'] : 0;
            if ($pid <= 0) continue;
            $key = $vid > 0 ? $pid . '_' . $vid : $pid;
            unset($_SESSION['cart'][$key]);
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
    $vid   = isset($_POST['variation_id']) && $_POST['variation_id'] !== '' ? (int)$_POST['variation_id'] : 0;
    $delta = (int)($_POST['delta'] ?? 0);
    $new_qty = 0;

    if ($user_id > 0) {
        if ($vid > 0) {
            $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=? AND variation_id=?");
            $stmt->bind_param("iii", $user_id, $pid, $vid);
        } else {
            $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=? AND (variation_id IS NULL OR variation_id=0)");
            $stmt->bind_param("ii", $user_id, $pid);
        }
        $stmt->execute();
        $curr = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($curr) {
            $new_qty = $curr['quantity'] + $delta;
            if ($new_qty >= 1) {
                if ($vid > 0) {
                    $stmt = $conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=? AND variation_id=?");
                    $stmt->bind_param("iiii", $new_qty, $user_id, $pid, $vid);
                } else {
                    $stmt = $conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=? AND (variation_id IS NULL OR variation_id=0)");
                    $stmt->bind_param("iii", $new_qty, $user_id, $pid);
                }
                $stmt->execute();
                $stmt->close();
            } else {
                $new_qty = 1;
            }
        }
    } else {
        $key = $vid > 0 ? $pid . '_' . $vid : $pid;
        if (isset($_SESSION['cart'][$key])) {
            $new_qty = max(1, $_SESSION['cart'][$key] + $delta);
            unset($_SESSION['cart'][$key]);
            $_SESSION['cart'][$key] = $new_qty;
        }
    }
    echo json_encode(['status' => 'success', 'new_qty' => $new_qty]);
    exit;
}

// -----------------------------------------------
// 7.  VARIANT
// -----------------------------------------------
if ($action === 'update_variant') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $old_vid = (int)($_POST['old_vid'] ?? 0);
    $new_vid = (int)($_POST['new_vid'] ?? 0);

    if ($pid > 0 && $new_vid > 0) {
        if ($user_id > 0) {
            // Kiểm tra xem biến thể mới đã có trong giỏ hàng chưa
            $check = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=? AND variation_id=?");
            $check->bind_param("iii", $user_id, $pid, $new_vid);
            $check->execute();
            $curr = $check->get_result()->fetch_assoc();
            $check->close();

            if ($curr) {
                // Đã có, cộng dồn số lượng và xóa cái cũ
                // Lấy số lượng cái cũ
                if ($old_vid > 0) {
                    $get_old = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=? AND variation_id=?");
                    $get_old->bind_param("iii", $user_id, $pid, $old_vid);
                } else {
                    $get_old = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=? AND (variation_id IS NULL OR variation_id=0)");
                    $get_old->bind_param("ii", $user_id, $pid);
                }
                $get_old->execute();
                $old_qty_res = $get_old->get_result()->fetch_assoc();
                $old_qty = $old_qty_res ? $old_qty_res['quantity'] : 1;
                $get_old->close();

                $stmt = $conn->prepare("UPDATE cart SET quantity=quantity+? WHERE user_id=? AND product_id=? AND variation_id=?");
                $stmt->bind_param("iiii", $old_qty, $user_id, $pid, $new_vid);
                $stmt->execute();
                $stmt->close();

                if ($old_vid > 0) {
                    $del = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=? AND variation_id=?");
                    $del->bind_param("iii", $user_id, $pid, $old_vid);
                } else {
                    $del = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=? AND (variation_id IS NULL OR variation_id=0)");
                    $del->bind_param("ii", $user_id, $pid);
                }
                $del->execute();
                $del->close();
            } else {
                // Chưa có, chỉ cần update variation_id
                if ($old_vid > 0) {
                    $stmt = $conn->prepare("UPDATE cart SET variation_id=? WHERE user_id=? AND product_id=? AND variation_id=?");
                    $stmt->bind_param("iiii", $new_vid, $user_id, $pid, $old_vid);
                } else {
                    $stmt = $conn->prepare("UPDATE cart SET variation_id=? WHERE user_id=? AND product_id=? AND (variation_id IS NULL OR variation_id=0)");
                    $stmt->bind_param("iii", $new_vid, $user_id, $pid);
                }
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $old_key = $old_vid > 0 ? $pid . '_' . $old_vid : $pid;
            $new_key = $pid . '_' . $new_vid;
            if (isset($_SESSION['cart'][$old_key])) {
                $qty = $_SESSION['cart'][$old_key];
                if (isset($_SESSION['cart'][$new_key])) {
                    $qty += $_SESSION['cart'][$new_key];
                    unset($_SESSION['cart'][$new_key]);
                }
                unset($_SESSION['cart'][$old_key]);
                $_SESSION['cart'][$new_key] = $qty;
            }
        }
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// -----------------------------------------------
// 8. GET CART
// -----------------------------------------------
if ($action === 'get_cart') {
    $data = [];
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT p.id as product_id, p.name, p.image, c.quantity, c.variation_id, COALESCE(pv.price, p.price) as price, pv.attributes as var_attrs
                                FROM cart c JOIN products p ON c.product_id = p.id
                                LEFT JOIN product_variations pv ON c.variation_id = pv.id
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
    $allowed_methods  = ['cod', 'banking', 'vnpay'];
    $payment_method   = in_array($_POST['payment_method'] ?? '', $allowed_methods) ? $_POST['payment_method'] : 'cod';
    
    // Giữ nguyên là banking cho VNPay trong database theo yêu cầu
    $db_payment_method = ($payment_method === 'vnpay') ? 'banking' : $payment_method;
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
            // Luôn tính qua helper: ưu tiên Flash Sale > sale_price > price
            $price_info = get_effective_price($conn, $pid, $p['price'], $p['sale_price']);
            $unit_price     = $price_info['effective_price'];
            $total         += $unit_price * $qty;
            $variant_text   = trim($it['variant_text'] ?? '');
            $valid_items[]  = ['pid' => $pid, 'qty' => $qty, 'price' => $unit_price, 'variant_text' => $variant_text];
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
                $upd_v = $conn->prepare("UPDATE user_vouchers SET used_count = used_count + 1 WHERE user_id = ? AND voucher_id = ?");
                $upd_v->bind_param("ii", $user_id, $voucher_id);
                $upd_v->execute();
                $upd_v->close();
            }
        }
        $v_stmt->close();
    }

    // BƯỚC 3: Tạo đơn hàng
    $order_code = generateOrderCode();

    $sql  = "INSERT INTO orders (order_code, user_id, name, phone, address, total_price, discount_amount, status, payment_method, payment_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'unpaid', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisssdds", $order_code, $user_id, $buyer_name, $buyer_phone, $buyer_address, $total, $discount_amount, $db_payment_method);

    if ($stmt->execute()) {
        $oid   = $conn->insert_id;
        $stmt->close();

        $stmt_d = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price, variant_text) VALUES (?,?,?,?,?)");
        $stmt_del = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=?");

        foreach ($valid_items as $v) {
            $vt = $v['variant_text'] ?? '';
            $stmt_d->bind_param("iiids", $oid, $v['pid'], $v['qty'], $v['price'], $vt);
            $stmt_d->execute();

            if ($payment_method !== 'vnpay') {
                $stmt_del->bind_param("ii", $user_id, $v['pid']);
                $stmt_del->execute();
            }
        }
        $stmt_d->close();
        $stmt_del->close();

        // CHỈ XÁC NHẬN ĐƠN THÀNH CÔNG NẾU KHÔNG PHẢI VNPAY
        // Nếu là VNPay, các bước này sẽ được thực hiện tại vnpay_return.php sau khi thanh toán thành công
        if ($payment_method !== 'vnpay') {
            // Gửi notification Order Success
            $n_success_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'order_success', 'Đặt hàng thành công', ?, ?, 0, NOW())");
            if ($n_success_stmt) {
                $msg = "Đơn hàng #{$order_code} đã được đặt thành công. Cảm ơn bạn đã mua sắm!";
                $link = "/order_history.php";
                $n_success_stmt->bind_param("iss", $user_id, $msg, $link);
                $n_success_stmt->execute();
                $n_success_stmt->close();
            }

            // HOOK 1: AUTO REWARD VOUCHER
            if ($user_id > 0) {
                $final_paid = $total - $discount_amount;
                $reward_sql = "SELECT id, code FROM vouchers
                               WHERE is_reward_template = 1
                                 AND reward_min_order <= ?
                                 AND (expiry_date >= CURDATE() OR expiry_date = '0000-00-00')
                               ORDER BY reward_min_order DESC
                               LIMIT 1";
                $r_stmt = $conn->prepare($reward_sql);
                $r_stmt->bind_param("d", $final_paid);
                $r_stmt->execute();
                $reward_v = $r_stmt->get_result()->fetch_assoc();
                $r_stmt->close();

                if ($reward_v) {
                    $grant = $conn->prepare(
                        "INSERT INTO user_vouchers (user_id, voucher_id, usage_limit, is_new)
                         VALUES (?, ?, 1, 1)
                         ON DUPLICATE KEY UPDATE usage_limit = usage_limit + 1, is_new = 1"
                    );
                    $grant->bind_param("ii", $user_id, $reward_v['id']);
                    $grant->execute();
                    $grant->close();

                    $noti_msg = "🎁 Bạn nhận được voucher thưởng \"{$reward_v['code']}\" cho đơn hàng vừa hoàn thành!";
                    $n_stmt   = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'reward_voucher', 'Nhận Voucher Thưởng!', ?, '/my_vouchers.php')");
                    if ($n_stmt) {
                        $n_stmt->bind_param("is", $user_id, $noti_msg);
                        $n_stmt->execute();
                        $n_stmt->close();
                    }
                }
            }

            $pairs_stmt = $conn->prepare("
                SELECT od1.product_id AS a, od2.product_id AS b
                FROM order_details od1
                JOIN order_details od2 ON od1.order_id = od2.order_id AND od1.product_id < od2.product_id
                WHERE od1.order_id = ?
            ");
            $pairs_stmt->bind_param("i", $oid);
            $pairs_stmt->execute();
            $pairs_res = $pairs_stmt->get_result();
            $pairs_stmt->close();

            $ins_assoc = $conn->prepare("
                INSERT INTO product_associations (product_a, product_b, co_count)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE co_count = co_count + 1
            ");
            while ($pair = $pairs_res->fetch_assoc()) {
                $ins_assoc->bind_param("ii", $pair['a'], $pair['b']);
                $ins_assoc->execute();
            }
            $ins_assoc->close();
        }

        echo json_encode(['status' => 'success', 'order_id' => $oid, 'order_code' => $order_code, 'total_money' => $total]);
    } else {
        // SECURITY: Không expose chi tiết lỗi DB ra ngoài
        error_log("Checkout DB Error: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Đặt hàng thất bại, vui lòng thử lại.']);
    }
    exit;
}
?>