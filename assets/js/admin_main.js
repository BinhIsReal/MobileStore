// =========================================
// SECURITY
// =========================================
$(document).ajaxSend(function (event, jqXHR, settings) {
  if (settings.type === "POST" || settings.type === "post") {
    const token = $('meta[name="csrf-token"]').attr("content");
    if (token) {
      jqXHR.setRequestHeader("X-CSRF-Token", token);
      if (typeof settings.data === "string") {
        settings.data += "&csrf_token=" + encodeURIComponent(token);
      }
    }
  }
});

$(document).ready(function () {
  let lastOrderCount = 0;

  window.fetchAdminStats = function () {
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
  };

  function updateBadge(selector, count) {
    let el = $(selector);
    let num = parseInt(count) || 0;

    let parentGroup = el.closest(".sb-group");
    let parentBadge = parentGroup.find(".badge-parent");

    if (num > 0) {
      let textNum = num > 99 ? "99+" : num;
      el.text(textNum).css("display", "inline-block");

      if (parentBadge.length && !parentGroup.hasClass("open")) {
        parentBadge.text(textNum).css("display", "inline-block");
      }
    } else {
      el.hide();
      if (parentBadge.length) parentBadge.hide();
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
  // Biểu đồ doanh thu theo ngày trong tháng
  const revCtx = document.getElementById("revenueChart");
  if (revCtx) {
    new Chart(revCtx, {
      type: "line",
      data: {
        labels: revenueLabels,
        datasets: [
          {
            label: "Doanh thu theo ngày (VNĐ)",
            data: revenueData,
            borderColor: "#00487a",
            backgroundColor: "rgba(0, 72, 122, 0.1)",
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 5,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return " " + Number(ctx.raw).toLocaleString("vi-VN") + "đ";
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              maxTicksLimit: 10,
              maxRotation: 45,
              minRotation: 0,
            },
          },
          y: {
            ticks: {
              callback: function (value) {
                if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                if (value >= 1000) return (value / 1000).toFixed(0) + "K";
                return value;
              },
            },
          },
        },
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

/* =========================================
   REVENUE DETAIL MODAL
========================================= */
(function () {
  let currentFilter  = "month";
  let revDetailChart = null;

  window.openRevenueModal = function () {
    currentFilter = "month";
    syncFilterInputVisibility();
    $(".rev-tab-btn").removeClass("active");
    $(".rev-tab-btn[data-filter='month']").addClass("active");
    $("#revenueFilterModal").fadeIn(200);
    loadRevenueDetail();
  };

  window.closeRevenueModal = function () {
    $("#revenueFilterModal").fadeOut(200);
  };

  // Đóng khi click ra nền
  $(document).on("click", "#revenueFilterModal", function (e) {
    if ($(e.target).is("#revenueFilterModal")) {
      closeRevenueModal();
    }
  });

  window.setRevFilter = function (filter) {
    currentFilter = filter;
    $(".rev-tab-btn").removeClass("active");
    $(".rev-tab-btn[data-filter='" + filter + "']").addClass("active");
    syncFilterInputVisibility();
  };

  function syncFilterInputVisibility() {
    $("#rev-pick-day, #rev-pick-week, #rev-pick-month, #rev-pick-year").hide();
    const inputMap = {
      day:   "#rev-pick-day",
      week:  "#rev-pick-week",
      month: "#rev-pick-month",
      year:  "#rev-pick-year",
    };
    $(inputMap[currentFilter]).show();
  }

  function getDateVal() {
    if (currentFilter === "year") {
      const y = $("#rev-pick-year").val() || new Date().getFullYear();
      return y + "-01-01";
    }
    if (currentFilter === "week") {
      // input[type=week] trả "2026-W20" → chuyển thành YYYY-MM-DD của thứ Hai
      const raw = $("#rev-pick-week").val(); // "2026-W20"
      if (!raw) return new Date().toISOString().slice(0, 10);
      const [year, week] = raw.split("-W").map(Number);
      const jan4  = new Date(year, 0, 4);
      const dayOfWeek = (jan4.getDay() || 7);
      const monday = new Date(jan4);
      monday.setDate(jan4.getDate() - dayOfWeek + 1 + (week - 1) * 7);
      return monday.toISOString().slice(0, 10);
    }
    if (currentFilter === "month") {
      const raw = $("#rev-pick-month").val(); // "2026-05"
      return raw ? raw + "-01" : new Date().toISOString().slice(0, 10);
    }
    return $("#rev-pick-day").val() || new Date().toISOString().slice(0, 10);
  }

  window.loadRevenueDetail = function () {
    const dateVal = getDateVal();
    $("#revTableLoading").show();
    $("#revDetailTable").hide();
    $("#revTotalRevenue, #revTotalOrders, #revAvgOrder").text("--");

    $.ajax({
      url: "../api/revenue_detail_api.php",
      method: "GET",
      data: { filter: currentFilter, date_val: dateVal },
      success: function (res) {
        try {
          const data = typeof res === "object" ? res : JSON.parse(res);
          if (data.status !== "success") return;

          // Summary
          const avg = data.total_orders > 0 ? data.total_revenue / data.total_orders : 0;
          $("#revTotalRevenue").text(formatVND(data.total_revenue));
          $("#revTotalOrders").text(data.total_orders + " đơn");
          $("#revAvgOrder").text(formatVND(avg));

          // Chart
          renderRevChart(data.breakdown);

          // Table
          renderRevTable(data.orders);
        } catch (e) {
          console.error("Revenue modal parse error:", e);
        }
      },
      complete: function () {
        $("#revTableLoading").hide();
        $("#revDetailTable").show();
      },
    });
  };

  function renderRevChart(breakdown) {
    const ctx = document.getElementById("revDetailChart");
    if (!ctx) return;
    if (revDetailChart) {
      revDetailChart.destroy();
      revDetailChart = null;
    }
    const labels = breakdown.map(function (r) {
      return r.day_date ? r.day_date.slice(5).replace("-", "/") : "";
    });
    const values = breakdown.map(function (r) { return parseFloat(r.day_revenue) || 0; });

    revDetailChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: labels,
        datasets: [{
          label: "Doanh thu (VNĐ)",
          data: values,
          backgroundColor: "rgba(0, 72, 122, 0.75)",
          borderRadius: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return " " + formatVND(ctx.raw); },
            },
          },
        },
        scales: {
          x: { ticks: { maxTicksLimit: 12, maxRotation: 45 } },
          y: {
            ticks: {
              callback: function (v) {
                if (v >= 1000000) return (v / 1000000).toFixed(1) + "M";
                if (v >= 1000)    return (v / 1000).toFixed(0) + "K";
                return v;
              },
            },
          },
        },
      },
    });
  }

  function renderRevTable(orders) {
    const paymentLabel = { banking: "Chuyển khoản", cod: "Tiền mặt", vnpay: "VNPay" };
    const badgeClass   = { banking: "badge-bank",   cod: "badge-cod",  vnpay: "badge-vnpay" };

    let html = "";
    if (!orders || orders.length === 0) {
      html = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">Không có dữ liệu</td></tr>';
    } else {
      orders.forEach(function (o) {
        const pm     = o.payment_method || "cod";
        const label  = paymentLabel[pm] || pm;
        const badge  = badgeClass[pm]  || "badge-cod";
        const dt     = o.created_at ? o.created_at.slice(0, 16).replace("T", " ") : "";
        html += `<tr>
          <td>#${o.order_code || o.id}</td>
          <td>${dt}</td>
          <td>${escHtml(o.name || "")}</td>
          <td><span class="${badge}">${label}</span></td>
          <td style="font-weight:700; color:#d70018;">${formatVND(o.final_price)}</td>
        </tr>`;
      });
    }
    $("#revDetailTbody").html(html);
  }

  function formatVND(num) {
    return Number(num || 0).toLocaleString("vi-VN") + "đ";
  }

  function escHtml(str) {
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  }
}());

function updateStatus(id, st) {
  const btn = event.target;
  btn.disabled = true;

  $(btn)
    .removeClass("bg-pending bg-shipping bg-completed bg-cancelled")
    .addClass("bg-" + st);

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
$(document).on("change", "#discount-type", function () {
  if ($(this).val() == "fixed") {
    $("#max-discount-group").hide();
    $('input[name="max_discount"]').val("");
  } else {
    $("#max-discount-group").show();
  }
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
$(document).on("change", "#check-all-users", function () {
  $(".user-checkbox").prop("checked", $(this).prop("checked"));
});

// Xử lý Gán Voucher
$(document).on("submit", "#assign-voucher-form", function (e) {
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
      let response = typeof res === "object" ? res : JSON.parse(res);
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
  $("#form-title").html('<i class="fa fa-edit"></i> Cập Nhật Mã Giảm Giá');
  $("#btn-submit-voucher").text("Lưu Thay Đổi");
  $("#btn-cancel-edit").show();
  $("#voucher-action").val("update_voucher");
  $("#voucher-id").val(id);

  $('input[name="code"]')
    .val(code)
    .prop("readonly", true)
    .css("background", "#e9ecef");
  $("#discount-type").val(type);
  $('input[name="discount_amount"]').val(discount);
  $('input[name="max_discount"]').val(max);
  $('input[name="min_order_value"]').val(min);
  $('input[name="expiry_date"]').val(expiry);

  $("html, body").animate(
    { scrollTop: $(".form-section").offset().top - 20 },
    300,
  );
}

function cancelEdit() {
  $("#create-voucher-form")[0].reset();
  $("#form-title").html('<i class="fa fa-plus-circle"></i> Tạo Mã Giảm Giá');
  $("#btn-submit-voucher").text("Tạo Voucher");
  $("#btn-cancel-edit").hide();

  $("#voucher-action").val("create_voucher");
  $("#voucher-id").val("");
  $('input[name="code"]').prop("readonly", false).css("background", "#fff");
}

// Xử lý Gửi Form AJAX (Chung cho cả Tạo Mới và Cập Nhật)
$(document).on("submit", "#create-voucher-form", function (e) {
  e.preventDefault();
  let formData = $(this).serialize();

  $.post("../api/voucher_api.php", formData, function (res) {
    try {
      let response = typeof res === "object" ? res : JSON.parse(res);
      if (response.status === "success") {
        Swal.fire("Thành công!", response.message, "success").then(() => {
          window.location.reload();
        });
      } else {
        Swal.fire("Lỗi!", response.message, "error");
      }
    } catch (error) {
      console.error("Lỗi:", error);
      Swal.fire(
        "Lỗi hệ thống",
        "Có lỗi xảy ra trong quá trình xử lý.",
        "error",
      );
    }
  }).fail(function () {
    Swal.fire("Lỗi kết nối", "Không thể kết nối với server.", "error");
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

// =========================================
//   ACTIVITY LOGS & BADGES
// =========================================
function formatJSON(jsonStr) {
  if (!jsonStr) return null;
  try {
    let obj = JSON.parse(jsonStr);
    let html =
      '<table style="width:100%; border-collapse: collapse; font-size:13px; text-align:left;">';

    const keyMap = {
      name: "Tên",
      price: "Giá",
      sale_price: "Giá KM",
      category_id: "Mã Danh mục",
      brand_id: "Mã Hãng",
      status: "Trạng thái",
      code: "Mã Voucher",
      discount_amount: "Mức giảm",
      type: "Loại",
      usage_limit: "Lượt dùng",
      colors: "Màu sắc",
      description: "Mô tả",
      image: "Ảnh đại diện",
      specs: "Thông số kỹ thuật",
    };

    for (let key in obj) {
      let val = obj[key];
      if (val === null || val === "") continue; // Bỏ qua dữ liệu rỗng

      if (typeof val === "object") {
        val = JSON.stringify(val);
      }

      let keyName = keyMap[key] || key;

      // Format kiểu hiển thị cho một số trường cụ thể
      if (
        key === "price" ||
        key === "sale_price" ||
        (key === "discount_amount" && obj.type === "fixed")
      ) {
        if (!isNaN(val) && val > 0)
          val = Number(val).toLocaleString("vi-VN") + "đ";
      }
      if (key === "type") {
        val =
          val === "percent"
            ? "Phần trăm (%)"
            : val === "fixed"
              ? "Tiền mặt (VNĐ)"
              : val;
      }
      if (key === "status") {
        const statusMap = {
          pending: "Chờ xử lý",
          shipping: "Đang giao",
          completed: "Hoàn thành",
          cancelled: "Đã hủy",
        };
        if (statusMap[val]) val = statusMap[val];
      }

      html += `<tr>
                        <td style="padding:6px; border-bottom:1px dashed #ddd; width:120px; font-weight:bold; color:#555;">${keyName}</td>
                        <td style="padding:6px; border-bottom:1px dashed #ddd; color:#000; word-break: break-word;">${val}</td>
                    </tr>`;
    }
    html += "</table>";
    return html;
  } catch (e) {
    return `<div style="padding:10px;">${jsonStr}</div>`;
  }
}

function viewLogDetail(log) {
  $("#modal-log-id").text(log.id);
  $("#modal-log-admin").html(
    `<b style="color:#00487a;"><i class="fa fa-user-shield"></i> ${log.admin_name}</b>`,
  );
  $("#modal-log-time").text(log.created_at);
  let actionClass = log.action.split(" ")[0];
  $("#modal-log-action").html(
    `<span class="badge ${actionClass}">${log.action}</span>`,
  );
  $("#modal-log-file").html(
    `<b style="color:#2c3e50;">${log.display_page}</b> <small style="color:#aaa;">(${log.page_name})</small>`,
  );
  $("#modal-log-desc").text(log.description);

  let oldData = formatJSON(log.old_data);
  let newData = formatJSON(log.new_data);

  if (oldData) {
    $("#col-old-data").show();
    $("#modal-log-old").html(oldData);
  } else {
    $("#col-old-data").hide();
  }

  if (newData) {
    $("#col-new-data").show();
    $("#modal-log-new").html(newData);
  } else {
    $("#col-new-data").hide();
  }

  $("#logDetailModal").fadeIn();
}

// =========================================
// SIDEBAR DROPDOWN LOGIC
// =========================================
$(document).ready(function () {
  $(".sb-group-title")
    .off("click")
    .on("click", function () {
      let parent = $(this).closest(".sb-group");
      parent.toggleClass("open");

      let parentBadge = parent.find(".badge-parent");
      let childBadge = parent.find(".sb-group-content .nav-badge").first();

      if (parent.hasClass("open")) {
        parentBadge.hide();
      } else if (childBadge.length && childBadge.is(":visible")) {
        parentBadge.text(childBadge.text()).css("display", "inline-block");
      }

      parent.find(".sb-group-content").stop(true, true).slideToggle(200);
    });

  $(".sb-group-content .sb-link.active").each(function () {
    let parent = $(this).closest(".sb-group");
    parent.addClass("open");
    parent.find(".sb-group-content").show();
  });

  $(document)
    .off("click", "#toggleSidebar")
    .on("click", "#toggleSidebar", function (e) {
      e.preventDefault();
      $("body").toggleClass("sidebar-collapsed");
    });

  let urlParams = new URLSearchParams(window.location.search);
  let msg = urlParams.get("msg");
  if (msg) {
    const msgMap = {
      add_success: {
        title: "Th\u00eam m\u1edbi th\u00e0nh c\u00f4ng!",
        type: "success",
      },
      del_success: { title: "X\u00f3a th\u00e0nh c\u00f4ng!", type: "success" },
      update_success: {
        title: "C\u1eadp nh\u1eadt th\u00e0nh c\u00f4ng!",
        type: "success",
      },
      deleted: {
        title: "\u0110\u00e3 x\u00f3a th\u00e0nh c\u00f4ng!",
        type: "success",
      },
      error: { title: "Thao t\u00e1c th\u1ea5t b\u1ea1i!", type: "error" },
    };
    const cfg = msgMap[msg];
    if (cfg) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: cfg.type,
        title: cfg.title,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    }
    window.history.replaceState(null, null, window.location.pathname);
  }
});
