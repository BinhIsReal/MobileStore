/**
 * orders.js - Admin Orders Page
 * Xử lý modal xem chi tiết đơn hàng
 */

function viewOrderDetail(orderId) {
    $('#modal-order-id').html('<i class="fa fa-spinner fa-spin"></i>');
    $('#orderDetailModal').fadeIn();
    $('#order-detail-content').html('<div style="text-align:center;"><i class="fa fa-spinner fa-spin fa-2x"></i> Đang tải dữ liệu...</div>');

    $.post('../api/admin_api.php', { action: 'get_order_detail', order_id: orderId }, function (res) {
        try {
            let data = typeof res === 'string' ? JSON.parse(res) : res;
            if (data.status === 'success') {
                let o = data.order;
                
                // Cập nhật lại ID hiển thị trên Title (Sử dụng order_code nếu có)
                $('#modal-order-id').text(o.order_code ? o.order_code : o.id);

                let items = data.items;
                let html = '';

                let payMethod = o.payment_method === 'banking' ? 'Chuyển khoản' : 'COD (Tiền mặt)';
                let payStatus = o.payment_status === 'paid'
                    ? '<span style="color:green;font-weight:bold;">Đã thanh toán</span>'
                    : '<span style="color:#e67e22;font-weight:bold;">Chưa thanh toán</span>';

                html += `
                <div class="order-detail-info-grid">
                    <div class="order-detail-info-box">
                        <h4 class="order-detail-info-title"><i class="fa fa-user"></i> NGƯỜI NHẬN</h4>
                        <p class="order-detail-info-line"><strong>${o.name}</strong></p>
                        <p class="order-detail-info-line"><i class="fa fa-phone"></i> ${o.phone}</p>
                        <p class="order-detail-info-line"><i class="fa fa-map-marker-alt"></i> ${o.address}</p>
                    </div>
                    <div class="order-detail-info-box">
                        <h4 class="order-detail-info-title"><i class="fa fa-file-invoice-dollar"></i> THANH TOÁN</h4>
                        <p class="order-detail-info-line">HT: <b>${payMethod}</b></p>
                        <p class="order-detail-info-line">Trạng thái: ${payStatus}</p>
                        <p class="order-detail-info-line">Ngày đặt: ${o.created_at}</p>
                    </div>
                </div>
                `;

                html += `
                <h4 class="order-detail-products-title"><i class="fa fa-box"></i> SẢN PHẨM</h4>
                <div class="order-detail-table-wrap">
                    <table class="order-detail-table">
                        <thead>
                            <tr class="order-detail-thead-row">
                                <th class="order-detail-th">Sản phẩm</th>
                                <th class="order-detail-th order-detail-th-center">SL</th>
                                <th class="order-detail-th order-detail-th-right">Giá</th>
                                <th class="order-detail-th order-detail-th-right">Tổng</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                let fmt = new Intl.NumberFormat('vi-VN');
                
                let total = 0;
                items.forEach(item => {
                    let imgUrl = item.image.startsWith('http') ? item.image : '../assets/img/' + item.image;
                    let lineTotal = parseFloat(item.price) * parseInt(item.quantity);
                    total += lineTotal;

                    html += `
                        <tr class="order-detail-tbody-row">
                            <td class="order-detail-td order-detail-product-cell">
                                <img src="${imgUrl}" class="order-detail-product-img" alt="">
                                <span class="order-detail-product-name">${item.name}</span>
                            </td>
                            <td class="order-detail-td order-detail-td-center">${item.quantity}</td>
                            <td class="order-detail-td order-detail-td-right">${fmt.format(item.price)} ₫</td>
                            <td class="order-detail-td order-detail-td-right order-detail-total-cell">${fmt.format(lineTotal)} ₫</td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                </div>
                `;

                let dis = parseFloat(o.discount_amount);
                let final = Math.max(0, parseFloat(o.total_price) - dis);
                html += `
                <div class="order-detail-summary">
                    <p class="order-detail-sum-row">Tạm tính: <b>${fmt.format(total)} ₫</b></p>
                `;
                if (dis > 0) {
                    html += `<p class="order-detail-sum-row order-detail-discount">Giảm giá: <b>- ${fmt.format(dis)} ₫</b></p>`;
                }
                html += `
                    <p class="order-detail-sum-final">Tổng thanh toán: <b class="order-detail-final-price">${fmt.format(final)} ₫</b></p>
                </div>
                `;

                $('#order-detail-content').html(html);
            } else {
                $('#order-detail-content').html('<div class="order-detail-error">' + data.message + '</div>');
            }
        } catch (e) {
            $('#order-detail-content').html('<div class="order-detail-error">Lỗi phân tích dữ liệu!</div>');
        }
    }).fail(function () {
        $('#order-detail-content').html('<div class="order-detail-error">Lỗi kết nối Server!</div>');
    });
}
