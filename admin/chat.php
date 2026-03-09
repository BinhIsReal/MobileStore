<?php 
session_start(); 
include '../config/db.php'; 

// Check quyền Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../login.php"); 
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <title>Hỗ trợ khách hàng</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="admin-content">
            <input type="hidden" id="init-target-user-id"
                value="<?= isset($_GET['user_id']) ? intval($_GET['user_id']) : 0 ?>">

            <h2>Hỗ trợ khách hàng</h2>
            <div class="chat-layout">
                <div class="user-list" id="user-list">
                </div>

                <div class="chat-area">
                    <div id="chat-window" class="chat-messages">
                        <div class="no-select">Chọn khách hàng để chat</div>
                    </div>

                    <div class="chat-input" id="input-area"
                        style="display:none; padding:15px; border-top:1px solid #eee; background:#fff;">
                        <input type="text" id="admin-msg" placeholder="Nhập tin nhắn..."
                            style="flex:1; padding:15px; border:1px solid #ddd; border-radius:8px;">
                        <button id="btn-send-reply"
                            style="padding:15px 30px; background:#00487a; color:white; border:none; border-radius:8px; cursor:pointer; margin-left:10px; font-weight:bold;">
                            <i class="fa fa-paper-plane"></i> Gửi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin_chat.js?v=<?php echo time(); ?>"></script>
</body>

</html>