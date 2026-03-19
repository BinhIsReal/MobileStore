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
        alert(response.message);

        if (response.status === "success") {
          // Cập nhật lại biến lưu tạm thành dữ liệu mới nhất
          originalData = {
            email: data.email,
            phone: data.phone,
            address: data.address,
          };

          // Khóa lại giao diện (giống thao tác Hủy)
          $(".form-control:not(.readonly-always)").prop("disabled", true);
          $("#profile-form").removeClass("editable");
          $("#btn-cancel").hide();
          $("#btn-save").hide();
          $("#btn-edit").show();
          $("#btn-change-pass").show();
        }
      } catch (e) {
        console.error("Lỗi parse JSON:", e);
        alert("Có lỗi xảy ra khi lưu thông tin!");
      }
    });
  });
});
