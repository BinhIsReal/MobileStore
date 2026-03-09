<?php
session_start();
// Hiển thị lỗi để debug nếu cần
ini_set('display_errors', 0);
error_reporting(E_ALL);

include '../config/db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// 1. ADD
if ($action == 'add') {
    $pid = intval($_POST['product_id']);
    $qty = intval($_POST['quantity'] ?? 1);

    if ($user_id > 0) {
        $check = $conn->query("SELECT quantity FROM cart WHERE user_id=$user_id AND product_id=$pid");
        if ($check->num_rows > 0) 
            $conn->query("UPDATE cart SET quantity=quantity+$qty WHERE user_id=$user_id AND product_id=$pid");
        else 
            $conn->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ($user_id, $pid, $qty)");
    } else {
        if (!isset($_SESSION['cart'][$pid])) $_SESSION['cart'][$pid] = 0;
        $_SESSION['cart'][$pid] += $qty;
    }
    echo json_encode(['status'=>'success']);
    exit;
}

// 2. COUNT
if ($action == 'count') {
    $c = 0;
    if ($user_id > 0) {
        $r = $conn->query("SELECT SUM(quantity) as t FROM cart WHERE user_id=$user_id")->fetch_assoc();
        $c = $r['t'] ?? 0;
    } else {
        if(isset($_SESSION['cart'])) $c = array_sum($_SESSION['cart']);
    }
    echo json_encode(['count' => $c]);
    exit;
}

// 3. DELETE
if ($action == 'delete') {
    $pid = intval($_POST['id']);
    if ($user_id > 0) $conn->query("DELETE FROM cart WHERE user_id=$user_id AND product_id=$pid");
    else unset($_SESSION['cart'][$pid]);
    echo json_encode(['status'=>'success']);
    exit;
}

// 4. DELETE LIST
if ($action == 'delete_list') {
    $ids = json_decode($_POST['ids'], true);
    if (!empty($ids)) {
        if ($user_id > 0) {
            $ids_str = implode(',', array_map('intval', $ids));
            $conn->query("DELETE FROM cart WHERE user_id=$user_id AND product_id IN ($ids_str)");
        } else {
            foreach($ids as $pid) if(isset($_SESSION['cart'][$pid])) unset($_SESSION['cart'][$pid]);
        }
    }
    echo json_encode(['status'=>'success']);
    exit;
}

// 5. DELETE ALL
if ($action == 'delete_all') {
    if ($user_id > 0) $conn->query("DELETE FROM cart WHERE user_id=$user_id");
    else unset($_SESSION['cart']);
    echo json_encode(['status'=>'success']);
    exit;
}

// 6. UPDATE QTY (QUAN TRỌNG: Trả về new_qty để JS cập nhật)
if ($action == 'update_qty') {
    $pid = intval($_POST['product_id']);
    $delta = intval($_POST['delta']);
    $new_qty = 0;
    
    if ($user_id > 0) {
        $curr = $conn->query("SELECT quantity FROM cart WHERE user_id=$user_id AND product_id=$pid")->fetch_assoc();
        if ($curr) {
            $new_qty = $curr['quantity'] + $delta;
            if ($new_qty >= 1) $conn->query("UPDATE cart SET quantity=$new_qty WHERE user_id=$user_id AND product_id=$pid");
            else $new_qty = 1;
        }
    } else {
        if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid] += $delta;
            if ($_SESSION['cart'][$pid] < 1) $_SESSION['cart'][$pid] = 1;
            $new_qty = $_SESSION['cart'][$pid];
        }
    }
    echo json_encode(['status'=>'success', 'new_qty' => $new_qty]);
    exit;
}

// 7. GET CART
if ($action == 'get_cart') {
    $data = [];
    if ($user_id > 0) {
        $sql = "SELECT p.id as product_id, p.name, p.image, p.price, c.quantity 
                FROM cart c JOIN products p ON c.product_id = p.id 
                WHERE c.user_id = $user_id";
        $res = $conn->query($sql);
        while($r = $res->fetch_assoc()) $data[] = $r;
    } else {
        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            $ids = implode(',', array_keys($_SESSION['cart']));
            if($ids){
                $sql = "SELECT id as product_id, name, image, price FROM products WHERE id IN ($ids)";
                $res = $conn->query($sql);
                while($r = $res->fetch_assoc()) {
                    $r['quantity'] = $_SESSION['cart'][$r['product_id']];
                    $data[] = $r;
                }
            }
        }
    }
    echo json_encode($data);
    exit;
}

// 8. CHECKOUT
if ($action == 'checkout') {
    if ($user_id == 0) { echo json_encode(['status'=>'error', 'message'=>'Vui lòng đăng nhập!']); exit; }
    
    $info = $_POST['info'];
    $items = json_decode($_POST['items'], true);
    
    if (empty($items)) { echo json_encode(['status'=>'error', 'message'=>'Giỏ hàng rỗng']); exit; }

    $total = 0;
    
    // --- BƯỚC 1: TÍNH TỔNG TIỀN (Server side) ---
    foreach($items as $it) {
        $pid = intval($it['product_id']);
        $qty = intval($it['quantity']);
        
        // Lấy cả giá gốc và giá giảm
        $p = $conn->query("SELECT price, sale_price FROM products WHERE id=$pid")->fetch_assoc();
        
        if($p) {
            // Ưu tiên giá sale nếu có
            $unit_price = ($p['sale_price'] > 0) ? $p['sale_price'] : $p['price'];
            $total += $unit_price * $qty;
        }
    }
    
    $payment_method = $_POST['payment_method'] ?? 'cod'; 
    $discount_amount = 0; 
    
    $sql = "INSERT INTO orders (user_id, name, phone, address, total_price, discount_amount, status, payment_method, payment_status, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, 'unpaid', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssdds", $user_id, $info['name'], $info['phone'], $info['address'], $total, $discount_amount, $payment_method);

    if ($stmt->execute()) {
        $oid = $conn->insert_id;
        $stmt_d = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        
        // --- BƯỚC 2: LƯU CHI TIẾT ĐƠN HÀNG ---
        foreach($items as $it) {
            $pid = intval($it['product_id']);
            $qty = intval($it['quantity']);
            
            $p = $conn->query("SELECT price, sale_price FROM products WHERE id=$pid")->fetch_assoc();
            if($p) {
                // Lưu đúng giá thực tế đã mua
                $unit_price = ($p['sale_price'] > 0) ? $p['sale_price'] : $p['price'];
                
                $stmt_d->bind_param("iiid", $oid, $pid, $qty, $unit_price);
                $stmt_d->execute();
                
                // Xóa sản phẩm khỏi giỏ hàng
                $conn->query("DELETE FROM cart WHERE user_id=$user_id AND product_id=$pid");
            }   
        }
        $stmt_d->close();
        
        echo json_encode([
            'status' => 'success',
            'order_id' => $oid,       
            'total_money' => $total  
        ]);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'DB Error: '.$stmt->error]);
    }
    exit;
}
?>