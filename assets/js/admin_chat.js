let currentChatUser = 0;
let currentChatUserName = "";
let isScrolledToBottom = false;

$(document).ready(function () {
  let targetUserId = parseInt($("#init-target-user-id").val()) || 0;

  // 1. Logic khởi tạo ban đầu (Khi ấn từ trang Quản lý KH sang)
  if (targetUserId > 0) {
    currentChatUser = targetUserId;
    $("#input-area").css("display", "flex");
    $("#chat-header-name").text("Khách hàng ID: #" + targetUserId);
    loadConversation(targetUserId);
  }

  loadUserList();

  // 2. Vòng lặp tự động cập nhật tin nhắn (mỗi 3s)
  setInterval(function () {
    loadUserList();
    if (currentChatUser > 0) {
      loadConversation(currentChatUser);
    }
  }, 3000);

  // 3. Sự kiện Gửi tin nhắn
  $("#admin-msg").keypress(function (e) {
    if (e.which == 13) sendReply();
  });

  $("#btn-send-reply").click(function () {
    sendReply();
  });

  // 4. Sự kiện Tìm kiếm User
  $("#search-user").on("keyup", function () {
    loadUserList();
  });
});

// --- CÁC HÀM XỬ LÝ CHÍNH ---

function loadUserList() {
  let keyword = $("#search-user").val().trim();

  $.post(
    "../api/chat_api.php",
    {
      action: "get_chat_users",
      search: keyword,
      active_user: currentChatUser, // Gửi ID đang chat để API ép user này nổi lên
    },
    function (data) {
      try {
        let users = typeof data === "object" ? data : JSON.parse(data);
        let html = "";

        if (!users || users.length === 0) {
          $("#user-list").html(
            '<div style="padding:15px; text-align:center; color:#999;">Không tìm thấy khách hàng.</div>',
          );
          return;
        }

        users.forEach((u) => {
          let activeClass = u.id == currentChatUser ? "active" : "";
          let noti =
            parseInt(u.unread) > 0
              ? `<span style="background:red; color:white; border-radius:50%; padding:2px 6px; font-size:11px; font-weight:bold; margin-left:auto;">${u.unread}</span>`
              : "";

          html += `<div class="user-item ${activeClass}" onclick="selectUser(${u.id}, '${u.username}')">
                                <i class="fa-solid fa-circle-user" style="font-size:30px; color:#aaa;"></i>
                                <div>
                                    <div style="font-weight:bold; color:#333;">${u.username}</div>
                                    <div style="font-size:12px; color:#888;">ID: #${u.id}</div>
                                </div>
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

function selectUser(uid, uname) {
  currentChatUser = uid;
  currentChatUserName = uname;
  isScrolledToBottom = false;

  // UI Update
  $("#chat-header-name").text(uname + " (ID: #" + uid + ")");
  $("#input-area").css("display", "flex");

  // Refresh danh sách ngay lập tức để đổi màu dòng vừa chọn
  loadUserList();

  // Đánh dấu đã đọc
  $.post(
    "../api/chat_api.php",
    {
      action: "mark_read",
      target_id: uid,
    },
    function () {
      if (typeof window.fetchAdminStats === "function") window.fetchAdminStats();
    },
  );

  // Tải nội dung chat
  $("#chat-window").html(
    '<div style="text-align:center; padding:20px; color:#666;"><i class="fa fa-spinner fa-spin"></i> Đang tải...</div>',
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
            '<div style="text-align:center; padding:20px; color:#999; font-style:italic;">Bạn chưa có tin nhắn nào với khách hàng này.</div>',
          );
          return;
        }

        msgs.forEach((m) => {
          let isMe = m.sender_id == 0;
          let align = isMe
            ? "align-self: flex-end;"
            : "align-self: flex-start;";
          let bubbleStyle = isMe
            ? "background:#00487a; color:white; border-radius: 15px 15px 2px 15px;"
            : "background:#e9ecef; color:#333; border-radius: 15px 15px 15px 2px; border:1px solid #ddd;";

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
        if (!isScrolledToBottom) {
          let d = document.getElementById("chat-window");
          if (d) d.scrollTop = d.scrollHeight;
          isScrolledToBottom = true;
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
      isScrolledToBottom = false; // Bắt buộc cuộn
      loadConversation(currentChatUser);
      loadUserList(); // Đôn user lên đầu
    },
  );
}
