$(document).ready(function () {
  let lastOrderCount = 0;

  function fetchAdminStats() {
    $.ajax({
      url: "../api/get_admin_stats.php",
      method: "GET",
      success: function (res) {
        updateBadge("#badge-orders", res.order_count);
        updateBadge("#badge-chat", res.chat_count);

        if (res.order_count > lastOrderCount && lastOrderCount !== 0) {
          if (typeof showToast === "function") {
            showToast({
              title: "Đơn hàng mới!",
              message: `Bạn có ${res.order_count} đơn hàng đang chờ xử lý.`,
              type: "warning",
            });
          }
        }
        lastOrderCount = res.order_count;
      },
    });
  }

  function updateBadge(selector, count) {
    let el = $(selector);
    let num = parseInt(count) || 0;

    if (num > 0) {
      el.text(num > 99 ? "99+" : num).css("display", "inline-block");
    } else {
      el.hide();
    }
  }
  fetchAdminStats();
  setInterval(fetchAdminStats, 5000);
});
function initDashboardCharts(
  revenueLabels,
  revenueData,
  statusLabels,
  statusData,
) {
  // Biểu đồ doanh thu 7 ngày
  const revCtx = document.getElementById("revenueChart");
  if (revCtx) {
    new Chart(revCtx, {
      type: "line",
      data: {
        labels: revenueLabels,
        datasets: [
          {
            label: "Doanh thu (VNĐ)",
            data: revenueData,
            borderColor: "#00487a",
            backgroundColor: "rgba(0, 72, 122, 0.1)",
            fill: true,
            tension: 0.4,
          },
        ],
      },
    });
  }

  // Biểu đồ trạng thái đơn hàng
  const statusCtx = document.getElementById("statusChart");
  if (statusCtx) {
    new Chart(statusCtx, {
      type: "doughnut",
      data: {
        labels: statusLabels,
        datasets: [
          {
            data: statusData,
            backgroundColor: [
              "#f39c12",
              "#3498db",
              "#27ae60",
              "#e74c3c",
              "#95a5a6",
            ],
          },
        ],
      },
    });
  }
}

function updateStatus(id, st) {
  const btn = event.target;
  btn.disabled = true;

  $.post(
    "../api/admin_api.php",
    {
      action: "update_status",
      order_id: id,
      status: st,
    },
    function (res) {
      btn.disabled = false;
      try {
        let data = typeof res === "object" ? res : JSON.parse(res);
        if (data.status === "success") {
          showToast({
            title: "Thành công",
            message: "Đơn hàng #" + id + " đã cập nhật",
            type: "success",
          });
        }
      } catch (e) {
        console.error(res);
      }
    },
  );
}

/* =========================================
  VOUCHER TICKETN
========================================= */
$("#discount-type").change(function () {
  if ($(this).val() == "fixed") {
    $("#max-discount-group").hide();
    $('input[name="max_discount"]').val("");
  } else {
    $("#max-discount-group").show();
  }
});

// Xử lý tạo Voucher
$("#create-voucher-form").submit(function (e) {
  e.preventDefault();
  let formData = $(this).serialize() + "&action=create_voucher";

  $.post("../api/voucher_api.php", formData, function (res) {
    try {
      let response = JSON.parse(res);
      if (response.status === "success") {
        Swal.fire("Thành công", response.message, "success").then(() =>
          location.reload(),
        );
      } else {
        Swal.fire("Lỗi", response.message, "error");
      }
    } catch (e) {
      console.error("Lỗi parse JSON:", e, res);
      Swal.fire(
        "Lỗi hệ thống",
        "Có lỗi xảy ra, vui lòng thử lại sau.",
        "error",
      );
    }
  });
});

// Mở Modal Gán Voucher bằng hiệu ứng Fade
function openAssignModal(id, code) {
  $("#assign-voucher-id").val(id);
  $("#assign-code-display").text(code);
  $("#assignModal").fadeIn();
}

// Đóng modal khi bấm ra ngoài nền đen
$(window).click(function (e) {
  if ($(e.target).is("#assignModal")) {
    $("#assignModal").fadeOut();
  }
});

// Chọn tất cả User
$("#check-all-users").change(function () {
  $(".user-checkbox").prop("checked", $(this).prop("checked"));
});

// Xử lý Gán Voucher
$("#assign-voucher-form").submit(function (e) {
  e.preventDefault();

  let assignAll = $("#check-all-users").is(":checked") ? 1 : 0;
  let formData =
    $(this).serialize() + "&action=assign_voucher&assign_all=" + assignAll;

  // Kiểm tra xem đã chọn user nào chưa nếu không bấm check all
  if (assignAll === 0 && $(".user-checkbox:checked").length === 0) {
    Swal.fire(
      "Lưu ý",
      "Vui lòng chọn ít nhất 1 người dùng để gán mã!",
      "warning",
    );
    return;
  }

  $.post("../api/voucher_api.php", formData, function (res) {
    try {
      let response = JSON.parse(res);
      if (response.status === "success") {
        $("#assignModal").fadeOut();
        Swal.fire("Thành công", response.message, "success");
        // Reset lại form check
        $("#assign-voucher-form")[0].reset();
      } else {
        Swal.fire("Lỗi", response.message, "error");
      }
    } catch (e) {
      console.error(e);
      Swal.fire("Lỗi hệ thống", "Không thể gán voucher lúc này.", "error");
    }
  });
});

function editVoucher(id, code, type, discount, max, min, expiry) {
  // 1. Đổi tiêu đề form và tên nút
  $("#form-title").html('<i class="fa fa-edit"></i> Cập Nhật Mã Giảm Giá');
  $("#btn-submit-voucher").text("Lưu Thay Đổi");
  $("#btn-cancel-edit").show(); // Hiện nút Hủy

  // 2. Chuyển Action thành Cập nhật và lưu ID
  $("#voucher-action").val("update_voucher");
  $("#voucher-id").val(id);

  // 3. Đổ dữ liệu hiện tại vào các ô input
  $('input[name="code"]')
    .val(code)
    .prop("readonly", true)
    .css("background", "#e9ecef"); // Không cho sửa mã code
  $("#discount-type").val(type);
  $('input[name="discount_amount"]').val(discount);
  $('input[name="max_discount"]').val(max);
  $('input[name="min_order_value"]').val(min);
  $('input[name="expiry_date"]').val(expiry);

  // 4. Cuộn mượt lên vị trí Form
  $("html, body").animate(
    { scrollTop: $(".form-section").offset().top - 20 },
    300,
  );
}

function cancelEdit() {
  // Reset toàn bộ Form về trạng thái Tạo mới ban đầu
  $("#create-voucher-form")[0].reset();
  $("#form-title").html('<i class="fa fa-plus-circle"></i> Tạo Mã Giảm Giá');
  $("#btn-submit-voucher").text("Tạo Voucher");
  $("#btn-cancel-edit").hide();

  $("#voucher-action").val("create_voucher");
  $("#voucher-id").val("");
  $('input[name="code"]').prop("readonly", false).css("background", "#fff");
}

// Xử lý Gửi Form AJAX (Chung cho cả Tạo Mới và Cập Nhật)
$("#create-voucher-form").submit(function (e) {
  e.preventDefault();
  let formData = $(this).serialize(); // Lấy tất cả input bao gồm cả action (ẩn)

  $.post("../api/voucher_api.php", formData, function (res) {
    try {
      let response = JSON.parse(res);
      if (response.status === "success") {
        Swal.fire("Thành công!", response.message, "success").then(() => {
          window.location.reload();
        });
      } else {
        Swal.fire("Lỗi!", response.message, "error");
      }
    } catch (error) {
      console.error("Lỗi:", error);
    }
  });
});

function confirmDelete(id) {
  Swal.fire({
    title: "Xác nhận xóa?",
    text: "Bạn có chắc chắn muốn xóa mã giảm giá này? Mọi người dùng đang sở hữu mã này sẽ bị mất quyền sử dụng.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d70018",
    cancelButtonColor: "#6c757d",
    confirmButtonText: '<i class="fa-solid fa-trash-can"></i> Xóa ngay',
    cancelButtonText: "Hủy bỏ",
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = `vouchers.php?delete_id=${id}`;
    }
  });
}
