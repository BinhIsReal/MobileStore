$(document).ajaxSend(function(event, jqXHR, settings) {
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

$(document).ready(function() {
    $('#btn-register').click(function() {
        let u = $('#reg-user').val();
        let p = $('#reg-pass').val();
        let e = $('#reg-email').val(); 
        let t = $('#reg-phone').val(); 

        if (!u || !p || !e || !t) {
            $('#reg-msg').css({'display':'block', 'background':'#f8d7da', 'color':'#721c24'}).html('Vui lòng nhập đầy đủ thông tin!');
            return;
        }

        let btn = $(this);
        let originalText = btn.text();
        btn.prop('disabled', true).text('Đang xử lý...');

        $.post('api/auth_api.php', {
            action: 'register',
            username: u,
            password: p,
            email: e,
            phone: t
        }, function(data) {
            btn.prop('disabled', false).text(originalText);
            try {
                let res = typeof data === 'object' ? data : JSON.parse(data);
                if (res.status == 'success') {
                    // Xóa trắng form
                    $('#reg-user, #reg-pass, #reg-email, #reg-phone').val('');
                    $('#reg-msg').hide();
                    
                    // Hiển thị Popup Modal thông báo thành công
                    $('body').append(`
                        <div class="reg-modal-overlay" id="reg-success-modal">
                            <div class="reg-modal-box">
                                <div class="reg-modal-icon"><i class="fa-solid fa-circle-check"></i></div>
                                <div class="reg-modal-title">Đăng ký thành công!</div>
                                <div class="reg-modal-msg">Tài khoản của bạn đã được tạo thành công. Vui lòng đăng nhập để tiếp tục mua sắm và sử dụng dịch vụ.</div>
                                <a href="login.php" class="btn-reg-login">Đi tới Đăng nhập</a>
                            </div>
                        </div>
                    `);
                } else {
                    $('#reg-msg').css({'display':'block', 'background':'#f8d7da', 'color':'#721c24'}).html(res.message);
                }
            } catch (err) {
                console.error("Lỗi phản hồi:", data);
                $('#reg-msg').css({'display':'block', 'background':'#f8d7da', 'color':'#721c24'}).html('Lỗi xử lý dử liệu từ máy chủ!');
            }
        }).fail(function() {
            btn.prop('disabled', false).text(originalText);
            $('#reg-msg').css({'display':'block', 'background':'#f8d7da', 'color':'#721c24'}).html('Lỗi kết nối máy chủ!');
        });
    });
});
