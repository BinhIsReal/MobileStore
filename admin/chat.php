<?php 
session_start(); 
include '../config/db.php'; 

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
                <div class="chat-sidebar">
                    <div class="search-box">
                        <input type="text" id="search-user" placeholder="Tìm theo Tên hoặc ID...">
                    </div>
                    <div class="user-list" id="user-list">
                    </div>
                </div>

                <div class="chat-area">
                    <div class="chat-header" id="chat-header">
                        <i class="fa-solid fa-comments" style="color:#00487a;"></i>
                        <span id="chat-header-name">Chọn khách hàng để bắt đầu...</span>
                    </div>

                    <div id="chat-window" class="chat-messages">
                        <div class="no-select" style="text-align:center; color:#999; margin-top:50px;">Hãy chọn một cuộc
                            hội thoại bên trái.</div>
                    </div>

                    <div class="chat-input" id="input-area">
                        <input type="text" id="admin-msg" placeholder="Nhập tin nhắn hỗ trợ...">
                        <button id="btn-send-reply"><i class="fa fa-paper-plane"></i> Gửi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin_chat.js?v=<?php echo time(); ?>"></script>
</body>

</html>