<?php
if(isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $stmt = $conn->query("SELECT email, phone, address FROM users WHERE id = $uid");
    $current_user = $stmt->fetch_assoc();
}
?>

<div class="form-group">
    <label>Email:</label>
    <input type="email" name="checkout_email" value="<?= $current_user['email'] ?? '' ?>" required>
</div>

<div class="form-group">
    <label>Số điện thoại:</label>
    <input type="text" name="checkout_phone" value="<?= $current_user['phone'] ?? '' ?>" required>
</div>

<div class="form-group">
    <label>Địa chỉ nhận hàng:</label>
    <textarea name="checkout_address" required><?= $current_user['address'] ?? '' ?></textarea>
</div>