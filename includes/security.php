<?php
/**
 * ============================================================
 * SECURITY HELPER — Good_Phone
 * Cung cấp: CSRF Token, Rate Limiter, Input Sanitizer
 * ============================================================
 */

// -----------------------------------------------
// CSRF TOKEN
// -----------------------------------------------

/**
 * Tạo hoặc lấy CSRF token hiện tại trong session.
 * Token sẽ được tái tạo sau mỗi 2 giờ để tăng bảo mật.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token']) || 
        empty($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 7200) 
    {
        $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Trả về thẻ <input hidden> chứa CSRF token
 * — Dùng trong mọi <form> POST của admin.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Xác thực CSRF token từ POST request.
 * Trả về false nếu token không hợp lệ hoặc thiếu.
 */
function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    
    // Fallback: Check header for AJAX requests
    if (empty($token) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Kiểm tra CSRF và tự động abort nếu thất bại.
 * Dùng cho API endpoint JSON.
 */
function csrf_verify_or_die(): void {
    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ (CSRF).']);
        exit;
    }
}

/**
 * Kiểm tra CSRF và redirect về trang lỗi nếu thất bại.
 * Dùng cho các trang PHP POST truyền thống (product_form, vouchers...).
 */
function csrf_verify_or_redirect(string $redirect_url = ''): void {
    if (!csrf_verify()) {
        if (!empty($redirect_url)) {
            header("Location: $redirect_url?msg=csrf_error");
        } else {
            http_response_code(403);
            die('Yêu cầu không hợp lệ. Vui lòng thử lại.');
        }
        exit;
    }
}

// -----------------------------------------------
// RATE LIMITER (dùng PHP Session)
// -----------------------------------------------

/**
 * Kiểm tra giới hạn số lần gọi theo key + khoảng thời gian.
 *
 * @param string $key        Định danh hành động (vd: 'login', 'register')
 * @param int    $max_attempts Số lần tối đa cho phép
 * @param int    $window_sec   Khoảng thời gian tính (giây)
 * @return bool  true = còn trong giới hạn | false = đã vượt
 */
function rate_limit_check(string $key, int $max_attempts = 5, int $window_sec = 300): bool {
    $now      = time();
    $rl_key   = 'rl_' . $key;

    if (!isset($_SESSION[$rl_key])) {
        $_SESSION[$rl_key] = ['count' => 0, 'start' => $now];
    }

    $rl = &$_SESSION[$rl_key];

    // Reset nếu đã hết cửa sổ thời gian
    if (($now - $rl['start']) > $window_sec) {
        $rl = ['count' => 0, 'start' => $now];
    }

    $rl['count']++;

    return $rl['count'] <= $max_attempts;
}

/**
 * Số giây còn lại trước khi rate limit được reset.
 */
function rate_limit_wait(string $key, int $window_sec = 300): int {
    $rl_key = 'rl_' . $key;
    if (empty($_SESSION[$rl_key]['start'])) return 0;
    $elapsed = time() - $_SESSION[$rl_key]['start'];
    return max(0, $window_sec - $elapsed);
}

/**
 * Reset rate limit counter — gọi sau khi login thành công.
 */
function rate_limit_reset(string $key): void {
    unset($_SESSION['rl_' . $key]);
}

// -----------------------------------------------
// INPUT SANITIZER
// -----------------------------------------------

/**
 * Làm sạch chuỗi đầu vào: trim + loại bỏ tag HTML.
 */
function sanitize_str(string $val): string {
    return strip_tags(trim($val));
}

/**
 * Trả về int an toàn, tối thiểu 0.
 */
function sanitize_int(mixed $val, int $min = 0): int {
    return max($min, (int)$val);
}
