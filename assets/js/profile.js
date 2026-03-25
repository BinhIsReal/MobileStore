$(document).ready(function () {
  let originalData = {};

  // 1. SỰ KIỆN: Bấm nút CHỈNH SỬA
  $("#btn-edit").click(function () {
    // Lưu lại data gốc đề phòng user bấm Hủy
    originalData = {
      email: $("#pf-email").val(),
      phone: $("#pf-phone").val(),
      address: $("#pf-address").val(),
    };

    // Mở khóa các ô input (trừ username)
    $(".form-control:not(.readonly-always)").prop("disabled", false);
    $("#profile-form").addClass("editable");

    // Chuyển đổi trạng thái các nút
    $("#btn-edit").hide();
    $("#btn-change-pass").hide();
    $("#btn-cancel").show();
    $("#btn-save").show();

    $("#pf-email").focus();
  });

  // 2. SỰ KIỆN: Bấm nút HỦY BỎ
  $("#btn-cancel").click(function () {
    // Khôi phục dữ liệu gốc
    $("#pf-email").val(originalData.email);
    $("#pf-phone").val(originalData.phone);
    $("#pf-address").val(originalData.address);

    // Khóa lại input và giao diện
    $(".form-control:not(.readonly-always)").prop("disabled", true);
    $("#profile-form").removeClass("editable");

    // Chuyển đổi trạng thái nút về ban đầu
    $("#btn-cancel").hide();
    $("#btn-save").hide();
    $("#btn-edit").show();
    $("#btn-change-pass").show();
  });

  // 3. SỰ KIỆN: Bấm nút LƯU THAY ĐỔI
  $("#btn-save").click(function () {
    let data = {
      action: "update_info",
      email: $("#pf-email").val().trim(),
      phone: $("#pf-phone").val().trim(),
      address: $("#pf-address").val().trim(),
    };

    // Gửi AJAX
    $.post("api/profile_api.php", data, function (res) {
      try {
        let response = JSON.parse(res);

        let isSuccess = response.status === "success";
        let title = isSuccess ? "Thành công!" : "Cảnh báo!";
        let icon = isSuccess ? "fa-circle-check" : "fa-circle-xmark";
        let color = isSuccess ? "#28a745" : "#d70018";

        let toastHtml = `
          <div style="background: #fff; border-left: 5px solid ${color}; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 15px 20px; margin-bottom: 10px; border-radius: 4px; display: flex; align-items: center; gap: 15px; min-width: 280px; z-index: 9999; pointer-events: auto; animation: slideInLeft 0.3s ease, fadeOut linear 0.5s 2.5s forwards;">
              <i class="fa-solid ${icon}" style="color: ${color}; font-size: 24px;"></i>
              <div style="display: flex; flex-direction: column; text-align: left;">
                  <strong style="color: #333; font-size: 16px; margin-bottom: 4px;">${title}</strong>
                  <span style="color: #666; font-size: 14px;">${response.message}</span>
              </div>
          </div>
        `;

        // 3. Hiển thị (CSS animation sẽ tự động chạy hiệu ứng)
        let $toast = $(toastHtml);
        $("#toast-container").append($toast);

        // Tự động xóa element khỏi DOM sau khi animation hoàn tất (3 giây)
        setTimeout(() => {
          $toast.remove();
        }, 3000);

        // 4. Cập nhật giao diện nếu thành công
        if (isSuccess) {
          originalData = {
            email: data.email,
            phone: data.phone,
            address: data.address,
          };

          $(".form-control:not(.readonly-always)").prop("disabled", true);
          $("#profile-form").removeClass("editable");
          $("#btn-cancel").hide();
          $("#btn-save").hide();
          $("#btn-edit").show();
          $("#btn-change-pass").show();
        }
      } catch (e) {
        console.error("Lỗi parse JSON:", e);
      }
    });
  });
});
