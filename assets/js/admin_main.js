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
