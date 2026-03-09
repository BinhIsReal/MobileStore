let currentChatUser = 0;
let currentChatUserScrollInit = false;

$(document).ready(function () {
  let targetUserId = parseInt($("#init-target-user-id").val()) || 0;

  // 1. Logic khởi tạo ban đầu
  if (targetUserId > 0) {
    // Tình huống: Admin click từ trang Quản lý Khách hàng sang
    $.post(
      "../api/chat_api.php",
      {
        action: "init_admin_chat",
        user_id: targetUserId,
      },
      function (res) {
        loadUserList(); // Load lại danh sách bên trái

        // Chờ HTML render xong thì tự động click chọn user đó
        setTimeout(() => {
          selectUser(targetUserId);
        }, 500);
      },
    );
  } else {
    // Tình huống: Admin vào thẳng trang Chat
    loadUserList();
  }

  // 2. Vòng lặp tự động cập nhật tin nhắn (mỗi 3s)
  setInterval(function () {
    loadUserList(); // Cập nhật danh sách (để lấy badge chưa đọc)
    if (currentChatUser > 0) {
      loadConversation(currentChatUser); // Cập nhật khung chat hiện tại
    }
  }, 3000);

  // 3. Sự kiện Gửi tin nhắn
  $("#admin-msg").keypress(function (e) {
    if (e.which == 13) sendReply();
  });

  $("#btn-send-reply").click(function () {
    sendReply();
  });
});

// --- CÁC HÀM XỬ LÝ CHÍNH ---

function loadUserList() {
  $.post(
    "../api/chat_api.php",
    {
      action: "get_chat_users",
    },
    function (data) {
      try {
        let users = typeof data === "object" ? data : JSON.parse(data);
        let html = "";

        if (!users || users.length === 0) {
          $("#user-list").html(
            '<div style="padding:15px; text-align:center; color:#999;">Chưa có khách hàng nào</div>',
          );
          return;
        }

        users.forEach((u) => {
          let activeClass = u.id == currentChatUser ? "active" : "";
          let noti =
            parseInt(u.unread) > 0
              ? `<span style="background:red; color:white; border-radius:50%; padding:2px 6px; font-size:11px; font-weight:bold; margin-left:auto;">${u.unread}</span>`
              : "";

          html += `<div class="user-item ${activeClass}" onclick="selectUser(${u.id})" style="cursor:pointer; display:flex; align-items:center; gap:10px; padding:10px; border-bottom:1px solid #eee;">
                            <i class="fa-solid fa-user-circle" style="font-size:30px; color:#ccc;"></i> 
                            <span style="font-weight:600; font-size:14px;">${u.username}</span> 
                            ${noti}
                         </div>`;
        });
        $("#user-list").html(html);
      } catch (e) {
        console.log("Lỗi parse JSON User List:", e);
      }
    },
  );
}

function selectUser(uid) {
  currentChatUser = uid;
  currentChatUserScrollInit = false; // Reset cờ cuộn màn hình

  // UI Update
  $("#input-area").css("display", "flex"); // Hiện khung gõ tin nhắn
  $(".user-item").removeClass("active");

  // Đánh dấu đã đọc
  $.post(
    "../api/chat_api.php",
    {
      action: "mark_read",
      target_id: uid,
    },
    function () {
      loadUserList(); // Cập nhật UI mất số đỏ
      if (typeof fetchAdminStats === "function") fetchAdminStats(); // Cập nhật chuông thông báo tổng (nếu có)
    },
  );

  // Tải nội dung chat
  $("#chat-window").html(
    '<div class="no-select" style="text-align:center; padding:20px; color:#666;"><i class="fa fa-spinner fa-spin"></i> Đang tải...</div>',
  );
  loadConversation(uid);
}

function loadConversation(uid) {
  $.post(
    "../api/chat_api.php",
    {
      action: "get_conversation",
      user_id: uid,
    },
    function (data) {
      try {
        let msgs = typeof data === "object" ? data : JSON.parse(data);
        let html = "";

        if (!msgs || msgs.length === 0) {
          $("#chat-window").html(
            '<div class="no-select" style="text-align:center; padding:20px; color:#999; font-style:italic;">Hãy gửi lời chào đến khách hàng!</div>',
          );
          return;
        }

        msgs.forEach((m) => {
          let isMe = m.sender_id == 0; // 0 là Admin
          let align = isMe
            ? "align-self: flex-end;"
            : "align-self: flex-start;";
          let bubbleStyle = isMe
            ? "background:#00487a; color:white; border-radius: 15px 15px 2px 15px;"
            : "background:#f1f1f1; color:#333; border-radius: 15px 15px 15px 2px; border:1px solid #ddd;";

          let time = new Date(m.created_at).toLocaleTimeString("vi-VN", {
            hour: "2-digit",
            minute: "2-digit",
          });

          let statusIcon = "";
          if (isMe) {
            let iconClass = m.is_read == 1 ? "fa-check-double" : "fa-check";
            let iconColor = m.is_read == 1 ? "#4CAF50" : "#ccc";
            statusIcon = `<i class="fa-solid ${iconClass}" style="font-size:10px; margin-left:5px; color:${iconColor};"></i>`;
          }

          html += `
                <div style="max-width:70%; ${align} display:flex; flex-direction:column; margin-bottom:12px;">
                    <div style="padding:10px 15px; font-size:14px; line-height:1.5; ${bubbleStyle}">
                        ${m.message}
                    </div>
                    <div style="font-size:11px; color:#999; margin-top:4px; ${isMe ? "text-align:right;" : "text-align:left;"}">
                        ${time} ${statusIcon}
                    </div>
                </div>`;
        });

        $("#chat-window").html(html);

        // Cuộn xuống dòng tin nhắn mới nhất
        if (!currentChatUserScrollInit) {
          let d = document.getElementById("chat-window");
          if (d) d.scrollTop = d.scrollHeight;
          currentChatUserScrollInit = true;
        }
      } catch (e) {
        console.log("Lỗi parse JSON Conversation:", e);
      }
    },
  );
}

function sendReply() {
  let msg = $("#admin-msg").val().trim();
  if (!msg || currentChatUser == 0) return;

  $("#admin-msg").val(""); // Clear input

  $.post(
    "../api/chat_api.php",
    {
      action: "send_msg",
      message: msg,
      receiver_id: currentChatUser,
    },
    function (data) {
      currentChatUserScrollInit = false; // Reset cờ để cuộn xuống đáy
      loadConversation(currentChatUser);
    },
  );
}
