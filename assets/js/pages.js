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
                    <p><b>Tổng tiền:</b> <span style="color:red; font-weight:bold">${order.total_price_formatted}</span></p>
                    <p><b>Trạng thái:</b> <span class="badge-status bg-${order.status_code}">${order.status_text}</span></p>
                    <div id="btn-open-track-detail" class="btn-open-track-modal">
                        <i class="fa fa-info-circle"></i> XEM CHI TIẾT ĐƠN HÀNG
                    </div>
                `;
          resultBox.html(html).fadeIn();

          let payMethodStr = order.payment_method === 'banking' ? 'Chuyển khoản' : 'Thanh toán COD';
          let payStatusStr = order.payment_status === 'paid' ? '<span style="color:green;font-weight:bold">Đã thanh toán</span>' : '<span style="color:#f39c12;font-weight:bold">Chưa thanh toán</span>';

          let itemsHtml = '';
          let subTotal = 0;
          if (order.items && order.items.length > 0) {
              order.items.forEach(item => {
                  subTotal += (parseFloat(item.price) * parseInt(item.quantity));
                  itemsHtml += `
                     <div class="track-item-row">
                         <img src="${item.image}" alt="" class="track-item-img">
                         <div class="track-item-detail">
                              <div class="track-item-name">${item.name}</div>
                              <div class="track-item-meta">SL: ${item.quantity} x ${item.price_formatted}</div>
                         </div>
                         <div class="track-item-price">${item.total_formatted}</div>
                     </div>
                  `;
              });
          }

          let discount = parseFloat(order.discount_amount || 0);
          let finalTotal = Math.max(0, parseFloat(order.total_price) - discount);

          let modalBodyHtml = `
              <div class="track-info-grid">
                  <div class="track-info-box-sm">
                      <div class="track-info-title"><i class="fa fa-user"></i> Người nhận</div>
                      <div class="track-info-line"><b>${order.name}</b></div>
                      <div class="track-info-line"><i class="fa fa-phone"></i> ${order.phone}</div>
                      <div class="track-info-line"><i class="fa fa-map-marker-alt"></i> ${order.address}</div>
                  </div>
                  <div class="track-info-box-sm">
                      <div class="track-info-title"><i class="fa fa-credit-card"></i> Thanh toán</div>
                      <div class="track-info-line">PT: <b>${payMethodStr}</b></div>
                      <div class="track-info-line">Trạng thái: ${payStatusStr}</div>
                      <div class="track-info-line">Ngày đặt: ${order.created_at}</div>
                  </div>
              </div>
              <div class="track-items-list">
                  <div class="track-info-title" style="margin-bottom:10px;"><i class="fa fa-box"></i> Sản phẩm</div>
                  ${itemsHtml}
              </div>
              <div class="track-summary">
                  <div class="track-sum-row">
                      <span>Tạm tính:</span>
                      <span><b>${subTotal.toLocaleString('vi-VN')} ₫</b></span>
                  </div>
                  ${discount > 0 ? `<div class="track-sum-row" style="color:#27ae60"><span>Giảm giá:</span><span><b>- ${discount.toLocaleString('vi-VN')} ₫</b></span></div>` : ''}
                  <div class="track-sum-row">
                      <span>Phí vận chuyển:</span>
                      <span>Miễn phí</span>
                  </div>
                  <div class="track-sum-final">
                      <span>Tổng cộng:</span>
                      <span>${finalTotal.toLocaleString('vi-VN')} ₫</span>
                  </div>
              </div>
          `;

          if ($("#trackDetailModal").length === 0) {
              $("body").append(`
                  <div id="trackDetailModal" class="track-modal-overlay">
                      <div class="track-modal-content">
                          <div class="track-modal-header">
                              <h3 id="trackModalHeading">Chi tiết đơn hàng</h3>
                              <span class="track-modal-close" onclick="$('#trackDetailModal').fadeOut()">&times;</span>
                          </div>
                          <div id="trackModalBody" class="track-modal-body"></div>
                      </div>
                  </div>
              `);
              
              $(document).on("click", "#trackDetailModal", function(e) {
                  if(e.target.id === 'trackDetailModal') $(this).fadeOut();
              });
          }

          $("#trackModalHeading").text("Đơn hàng #" + order.id);
          $("#trackModalBody").html(modalBodyHtml);

          $("#btn-open-track-detail").off("click").on("click", function() {
              $("#trackDetailModal").css("display", "flex").hide().fadeIn();
          });
        } else {
          resultBox
            .html(
              `<p style="color:red; text-align:center;"><i class="fa fa-circle-xmark"></i> ${data.message}</p>`,
            )
            .fadeIn();
        }
      } catch (e) {
        alert("Lỗi hệ thống!");
      }
    },
  );
}
