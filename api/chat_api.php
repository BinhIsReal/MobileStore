<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

include '../config/db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$tab = $_POST['tab'] ?? 'bot';

// --- XÁC ĐỊNH ID NGƯỜI DÙNG ---
$real_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

if ($real_user_id > 0) {
    // Đã đăng nhập -> Dùng ID thật
    $current_chat_id = $real_user_id;
} else {
    $crc = crc32(session_id()); 
    $crc = sprintf('%u', $crc); 
    $guest_id = -1 * ($crc % 2000000000); 
    
    if ($guest_id == 0) $guest_id = -1;
    
    $current_chat_id = $guest_id;
}

// Hàm lấy giá tiền
function extractPrice($str) {
    $str = mb_strtolower($str, 'UTF-8');
    preg_match('/(\d+([.,]\d+)?)\s*(tr|triệu|k|nghìn)?/', $str, $matches);
    if (!empty($matches[1])) {
        $num = floatval(str_replace(',', '.', $matches[1]));
        $unit = $matches[3] ?? '';
        if ($unit == 'tr' || $unit == 'triệu') return $num * 1000000;
        if ($unit == 'k' || $unit == 'nghìn') return $num * 1000;
        if ($num < 1000) return $num * 1000000;
        return $num;
    }
    return 0;
}

// ---------------------------------------------------------
// 1. GỬI TIN NHẮN
// ---------------------------------------------------------
if ($action == 'send_message') {
    $msg = trim($_POST['message'] ?? '');

    // CHECK QUYỀN: Chat Shop bắt buộc đăng nhập
    if ($tab == 'shop' && $real_user_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập để chat với nhân viên!']);
        exit;
    }

    if (!empty($msg)) {
        // Chat Bot -> ID 9999 | Chat Shop -> ID 0
        $receiver_id = ($tab == 'bot') ? 9999 : 0;
        
        $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
        $stmt->bind_param("iis", $current_chat_id, $receiver_id, $msg);
        
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $stmt->error]);
            exit;
        }

        // --- BOT TRẢ LỜI BẰNG GEMINI API ---
        if ($tab == 'bot') {
            $envPath = __DIR__ . '/../.env';
            $env = file_exists($envPath) ? parse_ini_file($envPath) : [];
            $apiKey = $env['GEMINI_API_KEY'] ?? '';
            
            $model = "gemini-2.5-flash"; 
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            // 1. TÍCH HỢP BỘ NHỚ NGỮ CẢNH (MEMORY)
            $history_messages = [];
            $history_sql = "SELECT message, role FROM chat_messages 
                            WHERE (sender_id = $current_chat_id AND receiver_id = 9999) 
                               OR (sender_id = 9999 AND receiver_id = $current_chat_id) 
                            ORDER BY created_at DESC LIMIT 10";
            $history_res = $conn->query($history_sql);
            if ($history_res && $history_res->num_rows > 0) {
                while($row = $history_res->fetch_assoc()) {
                    $history_messages[] = [
                        "role" => ($row['role'] == 'bot') ? 'model' : 'user',
                        "parts" => [["text" => $row['message']]]
                    ];
                }
            }
            $history_messages = array_reverse($history_messages); // Đảo ngược thành từ cũ đến mới

            // 2. TÍCH HỢP RAG — TÌM SẢN PHẨM THỰC TẾ TRONG MYSQL
            $msg_lower = mb_strtolower($msg, 'UTF-8');

            // --- 2a. LẤY TẤT CẢ THƯƠNG HIỆU & DANH MỤC TỪ DB ---
            $db_brand_map = [];
            $res_brands = $conn->query("SELECT id, name FROM brands");
            if ($res_brands) {
                while ($b = $res_brands->fetch_assoc()) {
                    $db_brand_map[$b['id']] = mb_strtolower(trim($b['name']), 'UTF-8');
                }
            }

            $db_cat_map = [];
            $res_cats = $conn->query("SELECT id, name FROM categories");
            if ($res_cats) {
                while ($c = $res_cats->fetch_assoc()) {
                    $db_cat_map[$c['id']] = mb_strtolower(trim($c['name']), 'UTF-8');
                }
            }

            // --- 2b. TRÍCH XUẤT Ý ĐỊNH ---
            $matched_brand_ids  = [];
            $matched_cat_ids    = [];
            $keyword_fragments  = [];
            $price_min = 0;
            $price_max = 0;

            // Khớp thương hiệu
            foreach ($db_brand_map as $bid => $bname) {
                if (mb_strpos($msg_lower, $bname, 0, 'UTF-8') !== false) {
                    $matched_brand_ids[] = intval($bid);
                }
            }

            // Khớp danh mục
            foreach ($db_cat_map as $cid => $cname) {
                if (mb_strpos($msg_lower, $cname, 0, 'UTF-8') !== false) {
                    $matched_cat_ids[] = intval($cid);
                }
            }

            // Lấy khoảng giá từ câu hỏi (VD: "dưới 10 triệu", "từ 5 đến 15 triệu")
            if (preg_match('/dưới\s*([\d,.]+)\s*(triệu|tr|k|nghìn)?/u', $msg_lower, $pm)) {
                $price_max = extractPrice($pm[0]);
            }
            if (preg_match('/trên\s*([\d,.]+)\s*(triệu|tr|k|nghìn)?/u', $msg_lower, $pm)) {
                $price_min = extractPrice($pm[0]);
            }
            if (preg_match('/từ\s*([\d,.]+)\s*(triệu|tr|k|nghìn)?\s*đến\s*([\d,.]+)\s*(triệu|tr|k|nghìn)?/u', $msg_lower, $pm)) {
                $price_min = extractPrice($pm[1] . ' ' . ($pm[2] ?? 'triệu'));
                $price_max = extractPrice($pm[3] . ' ' . ($pm[4] ?? 'triệu'));
            }

            // Từ khóa tên sản phẩm (model cụ thể, VD: "14 pro", "s24 ultra")
            $stop_words_rag = ['điện', 'thoại', 'máy', 'cái', 'tôi', 'mình', 'em', 'anh', 'chị', 'bạn',
                               'shop', 'ơi', 'à', 'ạ', 'giá', 'mua', 'bao', 'nhiêu', 'rẻ', 'tư', 'vấn',
                               'tìm', 'có', 'cần', 'xem', 'dưới', 'trên', 'từ', 'đến', 'triệu', 'nghìn', 'tr', 'k'];
            $words = preg_split('/\s+/u', trim($msg_lower));
            foreach ($words as $w) {
                $w = trim($w);
                if (mb_strlen($w, 'UTF-8') >= 2 && !in_array($w, $stop_words_rag)) {
                    $keyword_fragments[] = $conn->real_escape_string($w);
                }
            }

            // Kiểm tra có ý định mua hàng không
            $intent_keywords = ['giá', 'mua', 'bao nhiêu', 'tư vấn', 'tìm', 'gợi ý', 'rẻ', 'xem', 'có không',
                                'điện thoại', 'laptop', 'tai nghe', 'đồng hồ', 'smartwatch', 'phụ kiện', 'loa', 'chuột'];
            $has_intent = !empty($matched_brand_ids) || !empty($matched_cat_ids) || $price_max > 0 || $price_min > 0;
            if (!$has_intent) {
                foreach ($intent_keywords as $ik) {
                    if (mb_strpos($msg_lower, $ik, 0, 'UTF-8') !== false) { $has_intent = true; break; }
                }
            }

            // --- 2c. XÂY DỰNG QUERY ĐỘNG DỰA TRÊN Ý ĐỊNH ---
            $product_context = "";
            if ($has_intent) {
                $where_clauses = [];
                $bind_types    = "";
                $bind_values   = [];

                // Lọc theo brand
                if (!empty($matched_brand_ids)) {
                    $placeholders = implode(',', array_fill(0, count($matched_brand_ids), '?'));
                    $where_clauses[] = "p.brand_id IN ($placeholders)";
                    foreach ($matched_brand_ids as $bid) { $bind_types .= "i"; $bind_values[] = $bid; }
                }

                // Lọc theo category
                if (!empty($matched_cat_ids)) {
                    $placeholders = implode(',', array_fill(0, count($matched_cat_ids), '?'));
                    $where_clauses[] = "p.category_id IN ($placeholders)";
                    foreach ($matched_cat_ids as $cid) { $bind_types .= "i"; $bind_values[] = $cid; }
                }

                // Lọc theo khoảng giá
                if ($price_min > 0) {
                    $where_clauses[] = "p.price >= ?";
                    $bind_types .= "d"; $bind_values[] = $price_min;
                }
                if ($price_max > 0) {
                    $where_clauses[] = "p.price <= ?";
                    $bind_types .= "d"; $bind_values[] = $price_max;
                }

                // Lọc theo từ khóa tên sản phẩm (dùng OR LIKE cho từng fragment)
                if (!empty($keyword_fragments) && empty($matched_brand_ids) && empty($matched_cat_ids)) {
                    $name_clauses = [];
                    foreach ($keyword_fragments as $frag) {
                        $like_val = "%{$frag}%";
                        $name_clauses[] = "p.name LIKE ?";
                        $bind_types .= "s"; $bind_values[] = $like_val;
                    }
                    if (!empty($name_clauses)) {
                        $where_clauses[] = "(" . implode(" OR ", $name_clauses) . ")";
                    }
                }

                $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

                $query_sql = "SELECT p.id, p.name, p.price, p.stock, b.name AS brand_name, c.name AS cat_name
                              FROM products p
                              LEFT JOIN brands b ON p.brand_id = b.id
                              LEFT JOIN categories c ON p.category_id = c.id
                              {$where_sql}
                              ORDER BY p.stock DESC, p.price ASC
                              LIMIT 10";

                if (!empty($bind_values)) {
                    $search_stmt = $conn->prepare($query_sql);
                    $bind_refs = [&$bind_types];
                    foreach ($bind_values as $k => $v) { $bind_refs[] = &$bind_values[$k]; }
                    call_user_func_array([$search_stmt, 'bind_param'], $bind_refs);
                    $search_stmt->execute();
                    $search_res = $search_stmt->get_result();
                } else {
                    // Không có filter cụ thể → trả về sản phẩm nổi bật
                    $search_res = $conn->query("SELECT p.id, p.name, p.price, p.stock, b.name AS brand_name, c.name AS cat_name
                                               FROM products p
                                               LEFT JOIN brands b ON p.brand_id = b.id
                                               LEFT JOIN categories c ON p.category_id = c.id
                                               WHERE p.stock > 0
                                               ORDER BY p.id DESC LIMIT 8");
                }

                $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

                if ($search_res && $search_res->num_rows > 0) {
                    $product_context = "DANH SÁCH SẢN PHẨM THỰC TẾ TRONG KHO (chỉ tư vấn từ danh sách này):\n";
                    while ($p = $search_res->fetch_assoc()) {
                        $price_format = number_format($p['price'], 0, ',', '.') . 'đ';
                        $url_product  = $base_url . "/product_detail.php?id=" . $p['id'];
                        $stock_str    = ($p['stock'] > 0) ? "Còn hàng ({$p['stock']} SP)" : "Hết hàng";
                        $brand_str    = $p['brand_name'] ? "[{$p['brand_name']}]" : "";
                        $cat_str      = $p['cat_name'] ? "[{$p['cat_name']}]" : "";
                        $product_context .= "- {$brand_str}{$cat_str} {$p['name']} | Giá: {$price_format} | {$stock_str} | Link: {$url_product}\n";
                    }
                } else {
                    $product_context = "KHÔNG TÌM THẤY sản phẩm phù hợp trong kho. Không được tự bịa sản phẩm.";
                }

                if (!empty($bind_values) && isset($search_stmt)) { $search_stmt->close(); }
            }

            // =========================================================
            // 3. RAG ĐƠN HÀNG — TRA CỨU THỰC TẾ TRONG MYSQL
            // =========================================================
            $order_intent_keywords = [
                'đơn hàng', 'đơn của tôi', 'đơn của em', 'order', 'đặt rồi', 'đặt hàng',
                'đang giao', 'đang ở đâu', 'bao giờ tới', 'trạng thái', 'theo dõi',
                'lịch sử mua', 'đã mua', 'mua gì', 'hủy đơn', 'hoàn tiền', 'đổi trả',
                'thanh toán', 'chưa trả', 'đã thanh toán', 'mã đơn', 'order id',
                'thống kê', 'tổng số', 'bao nhiêu đơn', 'tổng tiền', 'tiêu bao nhiêu', 'mua bao nhiêu'
            ];

            $has_order_intent = false;
            foreach ($order_intent_keywords as $ok) {
                if (mb_strpos($msg_lower, $ok, 0, 'UTF-8') !== false) {
                    $has_order_intent = true;
                    break;
                }
            }

            // A. NHẬN DIỆN MÃ ĐƠN (ID dạng số hoặc order_code dạng chuỗi)
            $order_id_requested = '';
            if (preg_match('/#([A-Za-z0-9]{1,10})|(?:đơn|order)\s*(?:số|mã)?\s*([A-Za-z0-9]{1,10})/ui', $msg, $om)) {
                $order_id_requested = trim($om[1] ?: $om[2]);
                $has_order_intent = true;
            }

            // B. NHẬN DIỆN TRẠNG THÁI (STATUS FILTER)
            $has_status_intent = '';
            if (preg_match('/đang giao|đang vận chuyển/ui', $msg_lower)) $has_status_intent = 'shipping';
            if (preg_match('/đã nhận|thành công|hoàn thành/ui', $msg_lower)) $has_status_intent = 'completed';
            if (preg_match('/đã hủy|hủy/ui', $msg_lower)) $has_status_intent = 'cancelled';
            if (preg_match('/chờ xác nhận/ui', $msg_lower)) $has_status_intent = 'pending';

            // C. NHẬN DIỆN THỐNG KÊ CHI TIÊU
            $has_stats_intent = false;
            if (preg_match('/(thống kê|tổng số|bao nhiêu đơn|tổng tiền|tiêu bao nhiêu|mua bao nhiêu)/ui', $msg_lower)) {
                $has_stats_intent = true;
                $has_order_intent = true;
            }

            $order_context = "";
            if ($has_order_intent) {
                if ($real_user_id <= 0) {
                    $order_context = "THÔNG BÁO HỆ THỐNG: Khách hàng CHƯA ĐĂNG NHẬP nên không thể tra cứu lịch sử mua hàng, đơn hàng.\n"
                        . "Hãy nhắc khách hàng vui lòng đăng nhập vào tài khoản trên website để có thể xem thông tin đơn hàng cá nhân.";
                } else {
                    $status_map = [
                        'pending'   => 'Chờ xác nhận',
                        'shipping'  => 'Đang giao hàng',
                        'completed' => 'Đã hoàn thành',
                        'cancelled' => 'Đã hủy',
                    ];
                    $payment_map = [
                        'unpaid' => 'Chưa thanh toán',
                        'paid'   => 'Đã thanh toán',
                    ];
                    $payment_method_map = [
                        'cod'     => 'Thu tiền khi giao (COD)',
                        'banking' => 'Chuyển khoản ngân hàng',
                    ];

                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

                    // XỬ LÝ RAG - THỐNG KÊ
                    if ($has_stats_intent) {
                        $stat_stmt = $conn->prepare("SELECT COUNT(id) as total_orders, SUM(total_price - discount_amount) as total_spent FROM orders WHERE user_id = ? AND status = 'completed'");
                        $stat_stmt->bind_param("i", $real_user_id);
                        $stat_stmt->execute();
                        $stat_res = $stat_stmt->get_result()->fetch_assoc();
                        
                        $count = $stat_res['total_orders'] ?? 0;
                        $spent = number_format($stat_res['total_spent'] ?? 0, 0, ',', '.') . 'đ';
                        
                        $order_context .= "THỐNG KÊ CÁ NHÂN CỦA KHÁCH HÀNG:\n";
                        $order_context .= "- Khách đã mua tổng cộng: {$count} đơn hàng thành công.\n";
                        $order_context .= "- Tổng chi tiêu tài khoản: {$spent}.\n";
                        $stat_stmt->close();
                    }

                    // XỬ LÝ RAG - TRUY VẤN MỤC TIÊU
                    $ord_stmt = null;
                    if (!empty($order_id_requested)) {
                        $ord_stmt = $conn->prepare(
                            "SELECT o.id, o.order_code, o.status, o.total_price, o.discount_amount, o.payment_method, o.payment_status, o.created_at,
                                    GROUP_CONCAT(p.name ORDER BY od.id SEPARATOR ' | ') AS products,
                                    SUM(od.quantity) AS total_qty
                             FROM orders o
                             JOIN order_details od ON o.id = od.order_id
                             JOIN products p     ON od.product_id = p.id
                             WHERE (o.id = ? OR o.order_code = ?) AND o.user_id = ?
                             GROUP BY o.id"
                        );
                        $ord_id_int = intval($order_id_requested);
                        $ord_stmt->bind_param("isi", $ord_id_int, $order_id_requested, $real_user_id);
                    } else if (!empty($has_status_intent)) {
                        $ord_stmt = $conn->prepare(
                            "SELECT o.id, o.order_code, o.status, o.total_price, o.discount_amount, o.payment_method, o.payment_status, o.created_at,
                                    GROUP_CONCAT(p.name ORDER BY od.id SEPARATOR ' | ') AS products,
                                    SUM(od.quantity) AS total_qty
                             FROM orders o
                             JOIN order_details od ON o.id = od.order_id
                             JOIN products p     ON od.product_id = p.id
                             WHERE o.user_id = ? AND o.status = ?
                             GROUP BY o.id
                             ORDER BY o.created_at DESC
                             LIMIT 5"
                        );
                        $ord_stmt->bind_param("is", $real_user_id, $has_status_intent);
                    } else if (!empty($keyword_fragments)) {
                        $name_clauses = [];
                        $bind_values_order = [$real_user_id];
                        $bind_types_order = "i";

                        foreach ($keyword_fragments as $frag) {
                            $like_val = "%{$frag}%";
                            $name_clauses[] = "p.name LIKE ?";
                            $bind_types_order .= "s";
                            $bind_values_order[] = $like_val;
                        }

                        $product_condition = !empty($name_clauses) ? " AND (" . implode(" OR ", $name_clauses) . ")" : "";

                        $sql = "SELECT o.id, o.order_code, o.status, o.total_price, o.discount_amount, o.payment_method, o.payment_status, o.created_at,
                                        GROUP_CONCAT(p.name ORDER BY od.id SEPARATOR ' | ') AS products,
                                        SUM(od.quantity) AS total_qty
                                 FROM orders o
                                 JOIN order_details od ON o.id = od.order_id
                                 JOIN products p     ON od.product_id = p.id
                                 WHERE o.user_id = ? {$product_condition}
                                 GROUP BY o.id
                                 ORDER BY o.created_at DESC
                                 LIMIT 5";

                        $ord_stmt = $conn->prepare($sql);
                        $bind_refs = [&$bind_types_order];
                        foreach ($bind_values_order as $key => $val) {
                            $bind_refs[] = &$bind_values_order[$key];
                        }
                        call_user_func_array([$ord_stmt, 'bind_param'], $bind_refs);
                    } else if (!$has_stats_intent) {
                        $ord_stmt = $conn->prepare(
                            "SELECT o.id, o.order_code, o.status, o.total_price, o.discount_amount, o.payment_method, o.payment_status, o.created_at,
                                    GROUP_CONCAT(p.name ORDER BY od.id SEPARATOR ' | ') AS products,
                                    SUM(od.quantity) AS total_qty
                             FROM orders o
                             JOIN order_details od ON o.id = od.order_id
                             JOIN products p     ON od.product_id = p.id
                             WHERE o.user_id = ?
                             GROUP BY o.id
                             ORDER BY o.created_at DESC
                             LIMIT 5"
                        );
                        $ord_stmt->bind_param("i", $real_user_id);
                    }

                    if ($ord_stmt !== null) {
                        $ord_stmt->execute();
                        $ord_res = $ord_stmt->get_result();

                        if ($ord_res && $ord_res->num_rows > 0) {
                            if (empty($order_context)) $order_context = "ĐƠN HÀNG CỦA KHÁCH DỰA THEO YÊU CẦU: \n";
                            while ($o = $ord_res->fetch_assoc()) {
                                $status_vi  = $status_map[$o['status']]  ?? $o['status'];
                                $pay_status = $payment_map[$o['payment_status']] ?? $o['payment_status'];
                                $pay_method = $payment_method_map[$o['payment_method']] ?? $o['payment_method'];
                                $final_price = number_format(($o['total_price'] - $o['discount_amount']), 0, ',', '.') . 'đ';
                                $date_fmt   = date('d/m/Y H:i', strtotime($o['created_at']));
                                $detail_url = $base_url . "/order_detail.php?id=" . $o['id'];
                                $can_cancel = ($o['status'] === 'pending') ? "Có thể hủy" : "Không thể hủy";
                                $disp_code  = $o['order_code'] ?? $o['id'];
                                
                                $order_context .= "- Mã Đơn #{$disp_code} | Sản phẩm: {$o['products']} (x{$o['total_qty']}) | Tổng trả: {$final_price} | Trạng thái: {$status_vi} | Thanh toán: {$pay_method} - {$pay_status} | Đặt lúc: {$date_fmt} | {$can_cancel} | Chi tiết: {$detail_url}\n";
                            }
                        } else {
                            if (empty($has_stats_intent)) $order_context = "KHÔNG TÌM THẤY đơn hàng nào" . (!empty($order_id_requested) ? " với mã #{$order_id_requested}" : "") . " trong tài khoản này phù hợp với yêu cầu.";
                        }
                        $ord_stmt->close();
                    }
                }
            }

            // 4. SYSTEM PROMPT STRICT — CẤM HALLUCINATE
            $system_prompt = "Bạn là chuyên gia tư vấn công nghệ của MobileStore. Xưng hô lịch sự (Dạ/Em/Anh/Chị). Trả lời ngắn gọn, rõ ràng, xuống dòng hợp lý.\n"
                . "QUY TẮC BẮT BUỘC:\n"
                . "1. CHỈ được tư vấn sản phẩm có trong DANH SÁCH THỰC TẾ bên dưới. TUYỆT ĐỐI không được đề xuất hay đặt tên sản phẩm nào KHÔNG có trong danh sách.\n"
                . "2. Nếu không có sản phẩm phù hợp, hãy nói thật là kho chưa có và gợi ý khách để lại liên hệ.\n"
                . "3. Khi liệt kê sản phẩm hoặc đơn hàng, PHẢI dùng Markdown link [Tên](Link) để khách click xem.\n"
                . "4. Nếu sản phẩm ghi 'Hết hàng', không được gợi ý mua sản phẩm đó.\n"
                . "5. Về đơn hàng: CHỈ trả lời dựa trên DỮ LIỆU ĐƠN HÀNG bên dưới, không được tự bịa trạng thái hay thông tin đơn.\n"
                . "6. Nếu khách muốn hủy đơn ở trạng thái 'Có thể hủy', hướng dẫn vào link Chi tiết đơn hàng để thao tác.\n\n"
                . ($product_context ? $product_context . "\n" : "")
                . ($order_context   ? $order_context             : "");

            $data = [
                "system_instruction" => [
                    "parts" => [
                        [
                            "text" => $system_prompt
                        ]
                    ]
                ],
                "contents" => $history_messages
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $curl_err = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Xử lý lỗi & Timeout (Error Handling)
            $bot_reply = "Dạ hiện tại hệ thống AI đang bảo trì, anh/chị vui lòng để lại lời nhắn, nhân viên thật của Shop sẽ hỗ trợ anh/chị ngay ạ!";
            
            if ($response && !$curl_err && $http_code == 200) {
                $res_data = json_decode($response, true);
                
                if (isset($res_data['candidates'][0]['content']['parts'][0]['text'])) {
                    $raw_reply = trim($res_data['candidates'][0]['content']['parts'][0]['text']);
                    
                    // Gemini đôi khi trả về thẻ <br> dở dang, ta chuẩn hóa thành \n trước khi htmlspecialchars
                    $raw_reply = str_ireplace(['<br>', '<br/>', '<br />', '&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'], "\n", $raw_reply);
                    
                    // NÉN NHIỀU KHOẢNG TRẮNG XUỐNG DÒNG (THU GỌN KHOẢNG CÁCH)
                    $raw_reply = preg_replace("/\n+/", "\n", $raw_reply);
                    
                    $raw_reply = htmlspecialchars($raw_reply);
                    // Lọc Markdown (in đậm)
                    $raw_reply = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $raw_reply);
                    // Lọc Markdown đường dẫn link (RAG)
                    $raw_reply = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank" style="color:#0d47a1; font-weight:bold;">$1</a>', $raw_reply);
                    
                    $bot_reply = nl2br($raw_reply);
                }
            }

            // Lưu tin Bot trả lời
            $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, role, created_at) VALUES (9999, ?, ?, 'bot', NOW())");
            $stmt->bind_param("is", $current_chat_id, $bot_reply);
            $stmt->execute();
        }
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// ---------------------------------------------------------
// 2. LẤY TIN NHẮN
// ---------------------------------------------------------
if ($action == 'get_messages') {
    if ($tab == 'bot') {
        // Bot: Lấy theo ID hiện tại (User thật hoặc Khách tạm)
        $sql = "SELECT * FROM chat_messages 
                WHERE (sender_id = $current_chat_id AND receiver_id = 9999) 
                   OR (sender_id = 9999 AND receiver_id = $current_chat_id) 
                ORDER BY created_at ASC";
    } else {
        // Shop: Chỉ lấy khi đã đăng nhập
        if ($real_user_id == 0) { echo json_encode([]); exit; }
        
        $sql = "SELECT * FROM chat_messages 
                WHERE (sender_id = $real_user_id AND receiver_id = 0) 
                   OR (sender_id = 0 AND receiver_id = $real_user_id) 
                ORDER BY created_at ASC";
    }

    $result = $conn->query($sql);
    $msgs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $msgs[] = [
                'sender_id' => $row['sender_id'],
                'message' => $row['message'],
                'role' => $row['role'], 
                'time' => date('H:i', strtotime($row['created_at']))
            ];
        }
    }
    echo json_encode($msgs);
    exit;
}


if ($action == 'init_admin_chat') {
    $target_id = intval($_POST['user_id'] ?? 0);
    
    if ($target_id > 0) {
        // Kiểm tra xem đã có tin nhắn nào giữa Shop và User này chưa
        $check_sql = "SELECT id FROM chat_messages WHERE (sender_id = $target_id AND receiver_id = 0) OR (sender_id = 0 AND receiver_id = $target_id) LIMIT 1";
        $check_res = $conn->query($check_sql);
        
        if ($check_res->num_rows == 0) {
            // $system_msg = "MobileStore Xin kính chào quý khách.";
            $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, is_read) VALUES (0, ?, ?, 1)");
            $stmt->bind_param("is", $target_id, $system_msg);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}
// ---------------------------------------------------------
// 3. ADMIN 
// ---------------------------------------------------------
// 1. LẤY DANH SÁCH USER (Có tích hợp Tìm kiếm và Ép hiển thị User đang chọn)
if ($action == 'get_chat_users') {
    $search = $_POST['search'] ?? '';
    $active_user = intval($_POST['active_user'] ?? 0);
    $search_esc = $conn->real_escape_string($search);

    // Mặc định: Chỉ lấy những khách đã từng chat
    $where = "(u.id IN (SELECT sender_id FROM chat_messages) OR u.id IN (SELECT receiver_id FROM chat_messages))";

    // Nếu Admin vừa click từ trang Khách hàng sang (có active_user), ép lấy cả người đó
    if ($active_user > 0) {
        $where = "($where OR u.id = $active_user)";
    }

    // Nếu Admin đang gõ tìm kiếm
    if ($search !== '') {
        $where = "(u.id = '$search_esc' OR u.username LIKE '%$search_esc%')";
    }

    $sql = "SELECT u.id, u.username,
            (SELECT COUNT(id) FROM chat_messages WHERE sender_id = u.id AND is_read = 0 AND receiver_id = 0) as unread,
            (SELECT MAX(created_at) FROM chat_messages WHERE (sender_id = u.id AND receiver_id = 0) OR (sender_id = 0 AND receiver_id = u.id)) as last_time
            FROM users u
            WHERE u.role = 'user' AND $where
            ORDER BY unread DESC, last_time DESC, u.id DESC";
            
    $res = $conn->query($sql);
    $users = [];
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
    }
    echo json_encode($users);
    exit;
}

// 2. LẤY NỘI DUNG CUỘC TRÒ CHUYỆN
if ($action == 'get_conversation') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $sql = "SELECT * FROM chat_messages 
            WHERE (sender_id = $user_id AND receiver_id = 0) 
               OR (sender_id = 0 AND receiver_id = $user_id) 
            ORDER BY created_at ASC";
    $res = $conn->query($sql);
    $msgs = [];
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $msgs[] = $row;
        }
    }
    echo json_encode($msgs);
    exit;
}

// 3. ADMIN GỬI TIN NHẮN
if ($action == 'send_msg') {
    $message = $_POST['message'] ?? '';
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    
    if ($message != '' && $receiver_id > 0) {
        // sender_id = 0 mặc định là Admin, role = 'shop' để phân biệt giao diện
        $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, role, is_read) VALUES (0, ?, ?, 'shop', 0)");
        $stmt->bind_param("is", $receiver_id, $message);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// 4. CHECK NOTIFICATION CỦA ADMIN GỬI USER
if ($action == 'check_notification') {
    $unread = 0;
    if ($real_user_id > 0) {
        $res = $conn->query("SELECT COUNT(id) as c FROM chat_messages WHERE receiver_id = $real_user_id AND sender_id = 0 AND is_read = 0");
        $unread = $res->fetch_assoc()['c'] ?? 0;
    } else if ($current_chat_id < 0) {
        $res = $conn->query("SELECT COUNT(id) as c FROM chat_messages WHERE receiver_id = $current_chat_id AND sender_id = 0 AND is_read = 0");
        $unread = $res->fetch_assoc()['c'] ?? 0;
    }
    echo json_encode(['unread' => $unread]);
    exit;
}

// 5. ĐÁNH DẤU ĐÃ ĐỌC
if ($action == 'mark_read') {
    $target_id = intval($_POST['target_id'] ?? 0);
    if ($target_id > 0) {
        $conn->query("UPDATE chat_messages SET is_read = 1 WHERE sender_id = $target_id AND receiver_id = 0");
    }
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action == 'mark_read_user' && $real_user_id > 0) {
    $conn->query("UPDATE chat_messages SET is_read=1 WHERE receiver_id=$real_user_id AND sender_id=0");
    echo json_encode(['status'=>'success']); exit;
}


?>