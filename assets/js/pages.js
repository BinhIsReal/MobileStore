// --- LOGIC TRA CỨU ĐƠN HÀNG ---
function trackOrder() {
  let orderId = $("#track-id").val().trim();
  let phone = $("#track-phone").val().trim();
  let btn = $("#btn-track");
  let resultBox = $("#tracking-result");

  if (!orderId || !phone) {
    alert("Vui lòng nhập Mã đơn hàng và Số điện thoại!");
    return;
  }

  let oldText = btn.text();
  btn.prop("disabled", true).text("Đang tra cứu...");
  resultBox.hide();

  $.post(
    "../api/order_api.php",
    {
      action: "guest_track_order",
      order_id: orderId,
      phone: phone,
    },
    function (res) {
      btn.prop("disabled", false).text(oldText);

      try {
        let data = typeof res === "object" ? res : JSON.parse(res);

        if (data.status === "success") {
          let order = data.data;
          resultBox.attr("data-id", order.id);

          let html = `
                    <h4 style="color:#00487a; margin-bottom:15px;">Kết quả tra cứu đơn #${order.id}</h4>
                    <p><b>Người nhận:</b> ${order.name}</p>
                    <p><b>Ngày đặt:</b> ${order.created_at}</p>
                    <p><b>Tổng tiền:</b> <span style="color:red; font-weight:bold">${order.total_price}</span></p>
                    <p><b>Trạng thái:</b> <span class="badge-status bg-${order.status_code}">${order.status_text}</span></p>
                    <div style="margin-top:15px; background:#eef5ff; padding:10px; border-radius:5px;">
                        <i class="fa fa-info-circle"></i> Click vào khung này để xem chi tiết đơn hàng.
                    </div>
                `;
          resultBox.html(html).fadeIn();
        } else {
          resultBox
            .html(
              `<p style="color:red; text-align:center;"><i class="fa fa-circle-xmark"></i> ${data.message}</p>`
            )
            .fadeIn();
        }
      } catch (e) {
        alert("Lỗi hệ thống!");
      }
    }
  );
}
