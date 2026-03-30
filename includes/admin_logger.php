<?php

function logAdminAction($conn, $action, $page_name, $description, $old_data = null, $new_data = null) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        return false;
    }
    
    $admin_id = $_SESSION['user_id'];
    $old_json = $old_data ? json_encode($old_data, JSON_UNESCAPED_UNICODE) : null;
    $new_json = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;
    
    $sql = "INSERT INTO admin_logs (admin_id, action, page_name, description, old_data, new_data, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("isssss", $admin_id, $action, $page_name, $description, $old_json, $new_json);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}
?>
