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

$.ajaxPrefilter(function (options, originalOptions, jqXHR) {
  if (options.url && options.url.startsWith("api/")) {
    if (typeof BASE_URL !== "undefined") {
      options.url = BASE_URL + "/" + options.url;
    } else {
      options.url = "/" + options.url;
    }
  }
});

var currentUserId = 0;
var currentChatTab = "bot";
var chatInterval = null;
var isSending = false;
var reviewState = {
  page: 1,
  filter: "all",
  isLoading: false,
};

/* =================================================================
   UTILITY FUNCTIONS (TOAST, CONFIRM)
================================================================= */

function showToast({
  title = "Thông báo",
  message = "",
  type = "success",
  duration = 3000,
}) {
  const main = document.getElementById("toast-container");
  if (main) {
    const toast = document.createElement("div");
    const icons = {
      success: "fa-circle-check",
      error: "fa-circle-xmark",
      warning: "fa-triangle-exclamation",
    };
    const icon = icons[type];
    const delay = (duration / 1000).toFixed(2);

    toast.classList.add("toast", `toast--${type}`);
    toast.style.animation = `slideInLeft 0.3s ease, fadeOut linear 1s ${delay}s forwards`;

    toast.innerHTML = `
            <div class="toast__icon"><i class="fa-solid ${icon}"></i></div>
            <div class="toast__body">
                <h3 class="toast__title">${title}</h3>
                <p class="toast__msg">${message}</p>
            </div>
            <div class="toast__close"><i class="fa-solid fa-xmark"></i></div>
        `;
    main.appendChild(toast);
    const autoRemoveId = setTimeout(
      () => main.removeChild(toast),
      duration + 1000,
    );
    toast.onclick = function (e) {
      if (e.target.closest(".toast__close")) {
        main.removeChild(toast);
        clearTimeout(autoRemoveId);
      }
    };
  }
}

function customConfirm(message, callback) {
  $("#confirm-msg").text(message);
  $("#custom-confirm").css("display", "flex");
  $("#btn-confirm-yes")
    .off("click")
    .on("click", function () {
      $("#custom-confirm").hide();
      if (callback) callback();
    });
}

function closeConfirm() {
  $("#custom-confirm").hide();
}

/* =================================================================
    MAIN DOCUMENT READY (EVENT LISTENERS)
================================================================= */
$(document).ready(function () {
  currentUserId = parseInt($("body").attr("data-user-id")) || 0;
  console.log("Current User ID:", currentUserId);
  if ($("#product-list").length || $("#hot-product-list").length) {
    loadProducts();
  }
  if ($("#review-list-container").length) {
    loadReviews(true);
  }

  // Load Giỏ hàng
  if ($(".cart-wrapper").length) {
    calcTotal();
    toggleDeleteSelectedBtn();
    const urlParams = new URLSearchParams(window.location.search);
    const buyNowId = urlParams.get("buy_now");
    if (buyNowId) {
      let target = $(`.pay-check[value="${buyNowId}"]`);
      if (target.length) {
        target.prop("checked", true).trigger("change");
        const newUrl =
          window.location.protocol +
          "//" +
          window.location.host +
          window.location.pathname;
        if (window.history.replaceState)
          window.history.replaceState({ path: newUrl }, "", newUrl);
      }
    }
  }

  updateCartCount();
  if (typeof checkUserNotifications === "function") {
    checkUserNotifications();
    setInterval(checkUserNotifications, 30000);
  }

  /* --- SỰ KIỆN GIỎ HÀNG (CART) --- */
  $(document).on("change", ".pay-check", function () {
    calcTotal();
    toggleDeleteSelectedBtn();

    let pid = $(this).val();
    let $cartItem = $(this).closest(".cart-item");

    if (!$(this).prop("checked")) {
      $("#check-all").prop("checked", false);
      let $recBlock = $("#rec-item-" + pid);
      if ($recBlock.length > 0) {
        $recBlock.find(".pay-check").prop("checked", false);
        calcTotal();

        if (window.innerWidth <= 768) {
          $recBlock.wrap('<div style="overflow:hidden;"></div>');
          $recBlock.parent().slideUp(300, function () {
            $(this).remove();
          });
        } else {
          $recBlock.slideUp(300, function () {
            $(this).remove();
          });
        }
      }
    } else {
      if ($(".pay-check:checked").length === $(".pay-check").length)
        $("#check-all").prop("checked", true);

      if ($("#rec-item-" + pid).length === 0) {
        $.get(
          "api/recommendation_api.php",
          { action: "get_recommendations", product_id: pid, limit: 1 },
          function (res) {
            if (
              res &&
              res.status === "success" &&
              res.data &&
              res.data.length > 0
            ) {
              let p = res.data[0];
              let fmt = new Intl.NumberFormat("vi-VN");
              let starsHtml = renderStars(parseFloat(p.avg_rating) || 0);
              let html = `
            <div class="cart-item" id="rec-item-${pid}" style="background-color:#fef9f9; border:1px dashed #d70018; margin-top:5px; margin-bottom:15px; margin-left:35px; padding:10px; display:none; border-radius:8px; position:relative; overflow:visible !important;">
              <div style="position:absolute; top:-10px; left:15px; background:#d70018; color:#fff; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:bold;">Gợi ý cho bạn</div>
              <a href="${p.product_url}" style="display:block; text-decoration:none;">
                <img src="${p.image_url}" alt="${p.name}" style="width:80px; height:80px; object-fit:contain;" onerror="this.style.display='none'">
              </a>
              <div class="cart-info" style="flex:1; min-width: 0; margin-left:0 !important;">
                <a href="${p.product_url}" style="text-decoration:none; color:inherit;">
                  <h4 style="font-size:14px; margin:0 0 5px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${p.name}</h4>
                </a>
                <div class="cart-price">
                  <span style="color:#d70018; font-weight:bold; font-size:14px;">${fmt.format(p.display_price)} ₫</span>
                  ${starsHtml}
                </div>
              </div>
              <button type="button" class="js-add-rec-item" data-id="${p.id}" data-price="${p.display_price}" data-type="simple" style="width:90px;white-space: nowrap; flex-shrink: 0; margin-right: 10px;; background: linear-gradient(to right, #e6394c, #f94c4c); color:#fff; border:none; border-radius:4px; font-size:12px; font-weight:600; cursor:pointer;">
                <i class="fa fa-cart-plus"></i> Thêm
              </button>
            </div>`;
              $cartItem.after(html);

              let $newRec = $("#rec-item-" + pid);
              if (window.innerWidth <= 768) {
                $newRec.wrap(
                  '<div style="display:none; overflow:hidden;"></div>',
                );
                $newRec.parent().slideDown(300, function () {
                  $newRec.unwrap();
                });
              } else {
                $newRec.slideDown(300);
              }
            }
          },
        );
      }
    }
  });

  $(document).on("click", ".js-add-rec-item", function (e) {
    e.preventDefault();
    let btn = $(this);
    if (btn.data("loading")) return;
    btn.data("loading", true);

    let pid = btn.data("id");
    let price = parseFloat(btn.data("price")) || 0;

    let oldHtml = btn.html();
    btn.html('<i class="fa fa-spinner fa-spin"></i>');

    $.post(
      "api/cart_api.php",
      { action: "add", product_id: pid, quantity: 1, variation_id: "" },
      function () {
        btn.data("loading", false);
        updateCartCount();
        let checkHtml = `<input type="checkbox" class="pay-check" value="${pid}" data-qty="1" data-price="${price}" checked style="display:none;">`;
        btn.closest(".cart-item").append(checkHtml);

        if (typeof calcTotal === "function") {
          calcTotal();
        }

        btn.removeClass("js-add-rec-item").addClass("btn-remove-item");
        btn.html('<i class="fa fa-trash-can"></i> Xóa');
        btn.css({
          background: "none",
          border: "1px solid #d70018",
          color: "#d70018",
        });
      },
    ).fail(function () {
      btn.data("loading", false);
      btn.html(oldHtml);
    });
  });

  $(document).on("change", "#check-all", function () {
    $(".pay-check").prop("checked", $(this).prop("checked"));
    calcTotal();
    toggleDeleteSelectedBtn();
  });

  $(document).on("click", ".qty-btn", function () {
    let btn = $(this);
    let pid = btn.data("id");
    let vid = btn.data("vid") || 0;
    let delta = parseInt(btn.data("delta"));
    let qtySpan = btn.siblings("span");
    let checkbox = btn.closest(".cart-item").find(".pay-check");

    btn.prop("disabled", true);
    $.post(
      "api/cart_api.php",
      {
        action: "update_qty",
        product_id: pid,
        variation_id: vid,
        delta: delta,
      },
      function (data) {
        btn.prop("disabled", false);
        try {
          let res = typeof data === "object" ? data : JSON.parse(data);
          if (res.status === "success") {
            let newQty = parseInt(res.new_qty);
            qtySpan.text(newQty);
            checkbox.attr("data-qty", newQty).data("qty", newQty);
            if (checkbox.prop("checked")) calcTotal();
            updateCartCount();
          }
        } catch (e) {}
      },
    );
  });

  $(document).on("click", "#btn-delete-all", function () {
    if ($(".cart-item").length === 0)
      return showToast({
        title: "Cảnh báo",
        message: "Giỏ hàng trống!",
        type: "warning",
      });
    customConfirm("Xóa sạch giỏ hàng?", function () {
      $.post("api/cart_api.php", { action: "delete_all" }, function () {
        $(".cart-item").fadeOut(250, function () {
          $(this).remove();
          _cartCheckEmpty();
          calcTotal();
          updateCartCount();
        });
      });
    });
  });

  $(document).on("click", "#btn-delete-selected", function () {
    let items = [];
    $(".pay-check:checked").each(function () {
      items.push({ id: $(this).val(), vid: $(this).data("vid") || 0 });
    });
    if (items.length === 0) return;
    customConfirm(`Xóa ${items.length} sản phẩm đã chọn?`, function () {
      $.post(
        "api/cart_api.php",
        { action: "delete_list", items: JSON.stringify(items) },
        function () {
          $(".pay-check:checked").each(function () {
            $(this)
              .closest(".cart-item")
              .fadeOut(250, function () {
                $(this).remove();
                _cartCheckEmpty();
                calcTotal();
                updateCartCount();
              });
          });
        },
      );
    });
  });

  $(document).on("click", ".btn-remove-item", function () {
    let pid = $(this).data("id");
    let vid = $(this).data("vid") || 0;
    let $item = $(this).closest(".cart-item");
    customConfirm("Xóa sản phẩm này?", function () {
      $.post(
        "api/cart_api.php",
        { action: "delete", id: pid, vid: vid },
        function () {
          $item.fadeOut(250, function () {
            $item.remove();
            _cartCheckEmpty();
            calcTotal();
            updateCartCount();
          });
        },
      );
    });
  });

  /* --- ĐỔI BIẾN THỂ TRONG GIỎ HÀNG --- */
  $(document).on("click", ".cart-variant-box", function (e) {
    if ($(e.target).closest(".variant-dropdown-menu").length) return;
    e.stopPropagation();

    $(".cart-item").css("z-index", "1");

    $(".variant-dropdown-menu")
      .not($(this).find(".variant-dropdown-menu"))
      .removeClass("show");

    $(".cart-variant-box").not($(this)).removeClass("open");

    let $dropdown = $(this).find(".variant-dropdown-menu");
    $dropdown.toggleClass("show");
    $(this).toggleClass("open", $dropdown.hasClass("show"));

    if ($dropdown.hasClass("show")) {
      $(this).closest(".cart-item").css({
        position: "relative",
        "z-index": "999",
      });
      $(this).css("z-index", "999");
    } else {
      $(this).closest(".cart-item").css("z-index", "1");
      $(this).css("z-index", "auto");
    }
  });

  $(document).on("click", ".variant-option", function (e) {
    e.stopPropagation();
    let $variantBox = $(this).closest(".cart-variant-box");
    if ($(this).hasClass("active")) {
      $(this).closest(".variant-dropdown-menu").removeClass("show");
      $variantBox.removeClass("open");
      return;
    }
    let $opt = $(this);
    let pid = $opt.data("pid");
    let oldVid = $opt.data("old-vid") || 0;
    let newVid = $opt.data("new-vid");
    let newLabel = $opt.find(".v-name").text();
    let $cartItem = $variantBox.closest(".cart-item");

    $opt.addClass("loading").css("opacity", 0.6);
    $.post(
      "api/cart_api.php",
      {
        action: "update_variant",
        product_id: pid,
        old_vid: oldVid,
        new_vid: newVid,
      },
      function (res) {
        try {
          res = typeof res === "object" ? res : JSON.parse(res);
        } catch (e) {}
        if (res && res.status === "success") {
          $variantBox.find(".variant-text").text(newLabel);
          $opt.siblings().each(function () {
            $(this).removeClass("active").attr("data-old-vid", newVid);
          });
          $opt.addClass("active").attr("data-old-vid", newVid);
          $cartItem
            .find(".pay-check, .qty-btn, .btn-remove-item")
            .attr("data-vid", newVid)
            .data("vid", newVid);
          $variantBox.find(".variant-option").attr("data-old-vid", newVid);

          let calcPrice = parseFloat($opt.attr("data-calc-price"));
          let origPrice = parseFloat($opt.attr("data-orig-price"));
          let isSale = parseInt($opt.attr("data-is-sale")) === 1;
          let isFlash = parseInt($opt.attr("data-is-flash")) === 1;

          if (!isNaN(calcPrice)) {
            let fmt = new Intl.NumberFormat("vi-VN");
            $cartItem
              .find(".pay-check")
              .attr("data-price", calcPrice)
              .data("price", calcPrice);

            let newHtml = "";
            if (isSale) {
              if (isFlash) {
                newHtml +=
                  '<span class="flash-badge flash-badge--price">FLASH SALE</span>';
              } else {
                newHtml +=
                  '<span class="flash-badge flash-badge--price" style="background:#ddd; color:#333;">KHUYẾN MÃI</span>';
              }
              newHtml += '<br class="br-mobile">';
              newHtml +=
                '<span class="current-price">' +
                fmt.format(calcPrice) +
                " ₫</span>";
              newHtml +=
                '<del class="old-price">' + fmt.format(origPrice) + " ₫</del>";
            } else {
              newHtml +=
                '<span class="current-price">' +
                fmt.format(calcPrice) +
                " ₫</span>";
            }
            $cartItem.find(".cart-item-price").html(newHtml);

            if (typeof calcTotal === "function") {
              calcTotal();
            }
          }

          $variantBox.removeClass("open");
          $variantBox.find(".variant-dropdown-menu").removeClass("show");
          showToast({
            title: "Đã đổi",
            message: "Phân loại: " + newLabel,
            type: "success",
          });
        }
        $opt.removeClass("loading").css("opacity", 1);
      },
    );
  });

  $(document).click(function () {
    $(".variant-dropdown-menu").removeClass("show");
    $(".cart-variant-box").removeClass("open");
  });

  /* --- SỰ KIỆN THANH TOÁN (CHECKOUT) --- */
  $(document).on("click", "#btn-checkout-init", function () {
    if ($(".pay-check:checked").length === 0)
      return showToast({
        title: "Nhắc nhở",
        message: "Vui lòng chọn sản phẩm!",
        type: "warning",
      });

    let hasUnselectedVariant = false;
    let $unselectedItems = [];

    $(".pay-check:checked").each(function () {
      let $item = $(this).closest(".cart-item");
      let hasVariations = $item.find(".variant-option").length > 0;
      let vid = $(this).data("vid") || 0;
      if (hasVariations && vid == 0) {
        hasUnselectedVariant = true;
        $unselectedItems.push($item);
      }
    });

    if (hasUnselectedVariant) {
      $unselectedItems.forEach(($item) => {
        $item.addClass("shake-warning");
        setTimeout(() => $item.removeClass("shake-warning"), 600);
      });

      $("html, body").animate(
        {
          scrollTop: $unselectedItems[0].offset().top - 100,
        },
        300,
      );

      return showToast({
        title: "Cảnh báo",
        message: "Vui lòng chọn phân loại cho sản phẩm trước khi thanh toán!",
        type: "warning",
      });
    }

    if (currentUserId === 0)
      return customConfirm("Bạn cần ĐĂNG NHẬP để mua hàng.", function () {
        window.location.href = "login.php";
      });
    $("#modal-total-money").text($("#total-money").text());
    $("#checkout-modal").fadeIn(200);
  });

  $(document).on("click", "#btn-confirm-order", function () {
    handleCheckout($(this));
  });

  $(document).on("click", "#btn-finish-banking", function () {
    let btn = $(this);
    btn
      .prop("disabled", true)
      .html('<i class="fa fa-spinner fa-spin"></i> Đang xác nhận...');
    showToast({
      title: "Đã ghi nhận",
      message: "Hệ thống sẽ kiểm tra giao dịch.",
      type: "success",
    });
    setTimeout(() => {
      window.location.href = "order_history.php";
    }, 1500);
  });

  /* --- SỰ KIỆN MUA HÀNG (ADD TO CART / BUY NOW) --- */
  $(document).on("click", ".js-add-to-cart", function (e) {
    handleAddToCart(e, $(this), false);
  });

  $(document).on("click", ".js-buy-now", function (e) {
    handleAddToCart(e, $(this), true);
  });

  /* --- SỰ KIỆN CHAT --- */
  $(".chat-header .chat-tab").click(function () {
    let tab = $(this).index() === 0 ? "bot" : "shop";
    switchChatTab(tab);
  });
  $("#chat-toggle, #chat-now, .close-chat").click(function () {
    toggleChat();
  });
  $(document).on("click", "#chat-backdrop", function () {
    toggleChat();
  });

  $("#chat-toggle")
    .on("chat:open", function () {
      if (window.innerWidth <= 768) {
        $("body").css({ overflow: "hidden", touchAction: "none" });
      }
    })
    .on("chat:close", function () {
      $("body").css({ overflow: "", touchAction: "" });
    });
  $("#chat-input").keypress(function (e) {
    if (e.which == 13) sendMessage();
  });
  $(".chat-footer button").click(function () {
    sendMessage();
  });

  $(document).on("click", ".user-dropdown-container", function (e) {
    e.stopPropagation();
    $("#userDropdown").toggleClass("show");
  });
  $(window).click(function () {
    $("#userDropdown").removeClass("show");
  });

  $("#sortPrice").change(function () {
    loadProducts();
  });
  $(".filter-btn").click(function () {
    let brand = $(this).data("brand");
    loadProducts(brand);
  });

  // Product Detail Image & Color
  $(document).on("click", ".thumb-img, .pd-thumb-item", function () {
    let src = $(this).attr("src");
    $("#pd-main-img")
      .attr("src", src)
      .css("opacity", 0)
      .animate({ opacity: 1 }, 300);
    $(".thumb-img, .pd-thumb-item").removeClass("active");
    $(this).addClass("active");
  });
  $(document).on("click", ".btn-color", function () {
    $(".btn-color").removeClass("active");
    $(this).addClass("active");
  });

  // Search Suggestion
  let searchTimeout = null;
  $("#search-input").on("input", function () {
    let keyword = $(this).val().trim();
    let box = $("#search-suggestions");
    clearTimeout(searchTimeout);
    if (keyword.length < 2) {
      box.hide();
      return;
    }
    searchTimeout = setTimeout(function () {
      $.get("api/search_suggest.php", { q: keyword }, function (data) {
        try {
          let products = typeof data === "string" ? JSON.parse(data) : data;
          if (products && products.length > 0) {
            let html = "";
            let fmt = new Intl.NumberFormat("vi-VN", {
              style: "currency",
              currency: "VND",
            });
            products.forEach((p) => {
              let img = p.image.startsWith("http")
                ? p.image
                : `assets/img/${p.image}`;
              html += `<a href="product_detail.php?id=${p.id}" class="suggest-item"><img src="${img}"><div><b>${p.name}</b><br><span style="color:red">${fmt.format(p.price)}</span></div></a>`;
            });
            box.html(html).fadeIn(200);
          } else box.hide();
        } catch (e) {}
      });
    }, 300);
  });
  $(document).click(function (e) {
    if (!$(e.target).closest(".search-form-wrap").length)
      $("#search-suggestions").hide();
  });

  /* =================================================================
       REVIEW SYSTEM EVENTS 
    ================================================================= */

  $(document).on(
    "click",
    ".js-btn-write-review, .btn-write-review",
    function (e) {
      e.preventDefault();
      currentUserId = parseInt($("body").attr("data-user-id")) || 0;

      if (currentUserId === 0) {
        showToast({
          title: "Yêu cầu",
          message: "Vui lòng đăng nhập để viết đánh giá!",
          type: "warning",
        });
        return;
      }
      $("#review-modal").fadeIn().css("display", "flex");
    },
  );

  $(document).on("click", ".close-modal", function () {
    $("#review-modal").fadeOut();
  });
  $(window).on("click", function (e) {
    if ($(e.target).is("#review-modal")) $("#review-modal").fadeOut();
  });

  $(document).on("change", "#review-images-input", function () {
    let preview = $("#preview-images");
    preview.html("");
    if (this.files) {
      Array.from(this.files).forEach((file) => {
        let reader = new FileReader();
        reader.onload = function (e) {
          preview.append(
            `<div class="preview-item" style="position:relative; display:inline-block; margin:5px;"><img src="${e.target.result}" style="width:60px; height:60px; object-fit:cover; border-radius:4px;"><span onclick="$(this).parent().remove()" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border-radius:50%; width:15px; height:15px; display:flex; align-items:center; justify-content:center; font-size:10px; cursor:pointer;">×</span></div>`,
          );
        };
        reader.readAsDataURL(file);
      });
    }
  });

  $(document).on("submit", "#form-product-review", function (e) {
    e.preventDefault();
    let btn = $(this).find(".btn-submit-review");
    btn.prop("disabled", true).text("Đang gửi...");

    let formData = new FormData(this);
    formData.append("action", "submit_review");

    $.ajax({
      url: "api/product_api.php",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      success: function (res) {
        btn.prop("disabled", false).text("GỬI ĐÁNH GIÁ");
        try {
          let data = typeof res === "object" ? res : JSON.parse(res);
          if (data.status === "success") {
            showToast({
              title: "Thành công",
              message: "Đánh giá đã được đăng!",
              type: "success",
            });
            $("#review-modal").fadeOut();
            $("#form-product-review")[0].reset();
            $("#preview-images").html("");
            reviewState.page = 1;
            loadReviews(true);
          } else {
            showToast({ title: "Lỗi", message: data.message, type: "error" });
          }
        } catch (err) {
          console.error(err);
        }
      },
      error: function () {
        btn.prop("disabled", false).text("GỬI ĐÁNH GIÁ");
        showToast({
          title: "Lỗi mạng",
          message: "Không thể kết nối server",
          type: "error",
        });
      },
    });
  });

  $(document).on("click", ".review-filter .filter-btn", function () {
    if ($(this).hasClass("active")) return;
    $(".review-filter .filter-btn").removeClass("active");
    $(this).addClass("active");

    reviewState.filter = $(this).data("star");
    reviewState.page = 1;

    loadReviews(true);
  });

  $(document).on("click", "#btn-load-more-reviews", function () {
    reviewState.page++;
    loadReviews(false);
  });

  $(document).on("click", ".btn-like-review", function () {
    let btn = $(this);
    let reviewId = btn.data("id");

    if (currentUserId === 0) {
      showToast({
        title: "Yêu cầu đăng nhập",
        message: "Bạn cần đăng nhập để thả Like cho đánh giá này!",
        type: "warning",
      });
      return;
    }

    if (btn.data("loading")) return;
    btn.data("loading", true);

    $.post(
      "api/product_api.php",
      { action: "like_review", review_id: reviewId },
      function (res) {
        btn.data("loading", false);
        try {
          let data = typeof res === "object" ? res : JSON.parse(res);

          if (data.status === "error" && data.message === "require_login") {
            showToast({
              title: "Yêu cầu",
              message: "Phiên đăng nhập hết hạn, vui lòng tải lại trang!",
              type: "error",
            });
            return;
          }

          if (data.status === "success") {
            if (data.liked) {
              btn.addClass("liked");
              btn.html(
                `<i class="fa-solid fa-thumbs-up"></i> Đã Like (${data.new_likes})`,
              );
            } else {
              btn.removeClass("liked");
              btn.html(
                `<i class="fa-regular fa-thumbs-up"></i> Like (${data.new_likes})`,
              );
            }
          }
        } catch (e) {}
      },
    ).fail(function () {
      btn.data("loading", false);
    });
  });

  // --- RELATED PRODUCT SLIDER ---
  if ($("#related-track").length) {
    let relIndex = 0;
    const track = $("#related-track");
    const items = $(".related-card");
    const totalItems = items.length;
    let startX = 0;
    let endX = 0;

    function moveRelatedSlide(direction) {
      const itemsVisible = window.innerWidth > 768 ? 4 : 2;
      const maxIndex = Math.max(0, totalItems - itemsVisible);
      const itemWidth = items.first().outerWidth() + 15;
      relIndex += direction;
      if (relIndex > maxIndex) relIndex = 0;
      else if (relIndex < 0) relIndex = maxIndex;
      track.css("transform", `translateX(-${relIndex * itemWidth}px)`);
    }
    $(document).on("click", "#btn-prev-rel", function () {
      moveRelatedSlide(-1);
    });
    $(document).on("click", "#btn-next-rel", function () {
      moveRelatedSlide(1);
    });

    track.on("touchstart", function (e) {
      startX = e.originalEvent.touches[0].clientX;
      endX = startX;
    });

    track.on("touchmove", function (e) {
      endX = e.originalEvent.touches[0].clientX;
    });

    track.on("touchend", function (e) {
      if (startX - endX > 50) {
        moveRelatedSlide(1);
      } else if (endX - startX > 50) {
        moveRelatedSlide(-1);
      }
    });
  }
});

/* =================================================================
    CORE FUNCTIONS DEFINITIONS
================================================================= */

function loadReviews(isReset) {
  const params = new URLSearchParams(window.location.search);
  const pid = params.get("id");
  if (!pid || reviewState.isLoading) return;

  reviewState.isLoading = true;

  if (isReset)
    $("#review-list-container").html(
      '<div style="text-align:center; padding:30px; color:#666;"><i class="fa fa-spinner fa-spin"></i> Đang tải đánh giá...</div>',
    );
  else
    $("#btn-load-more-reviews").html(
      '<i class="fa fa-spinner fa-spin"></i> Đang tải thêm...',
    );

  $.post(
    "api/product_api.php",
    {
      action: "get_reviews",
      product_id: pid,
      star: reviewState.filter,
      page: reviewState.page,
    },
    function (res) {
      try {
        let data = typeof res === "object" ? res : JSON.parse(res);
        let html = "";

        if (data.reviews && data.reviews.length > 0) {
          data.reviews.forEach((rev) => {
            let stars = "";
            for (let i = 1; i <= 5; i++)
              stars +=
                i <= rev.rating
                  ? '<i class="fa-solid fa-star" style="color:#f39c12"></i>'
                  : '<i class="fa-regular fa-star" style="color:#ccc"></i>';

            let imgs = "";
            if (rev.images && rev.images.length > 0) {
              imgs = '<div class="review-gallery">';
              rev.images.forEach((src) => {
                imgs += `<img src="assets/img/reviews/${src}" onclick="window.open(this.src)">`;
              });
              imgs += "</div>";
            }
            let likeClass = rev.is_liked ? "liked" : "";
            let likeIcon = rev.is_liked ? "fa-solid" : "fa-regular";
            let likeText = rev.is_liked ? "Đã Like" : "Like";
            let avatarChar = rev.username
              ? rev.username.charAt(0).toUpperCase()
              : "U";

            html += `
                    <div class="review-card fade-in">
                        <div class="review-avatar">${avatarChar}</div>
                        <div class="review-body">
                            <div>
                                <span class="review-author">${rev.username}</span>
                                <span class="review-date"><i class="fa-regular fa-clock"></i> ${rev.date}</span>
                            </div>
                            <div class="review-stars">${stars}</div>
                            <div class="review-content">${rev.comment}</div>
                            ${imgs}
                           <button class="btn-like-review ${likeClass}" data-id="${rev.id}">
                              <i class="${likeIcon} fa-thumbs-up"></i> ${likeText} (${rev.likes})
                          </button>
                        </div>
                    </div>
                    ${
                      rev.reply
                        ? `<div class="review-reply">
                        <div class="reply-author">
                            <i class="fa-solid fa-headset"></i>
                            ${rev.reply_author || "Quản trị viên"}
                            <span class="reply-badge">Quản trị viên</span>
                        </div>
                        <div class="reply-text">${rev.reply}</div>
                        <div class="reply-date">${rev.reply_date || ""}</div>
                    </div>`
                        : ""
                    }`;
          });
        } else if (isReset) {
          html = `<div style="text-align:center; padding:40px; color:#999; background:#f9f9f9; border-radius:8px;"><i class="fa-regular fa-comments" style="font-size:40px; margin-bottom:10px;"></i><p>Chưa có đánh giá nào phù hợp.</p></div>`;
        }

        if (isReset) $("#review-list-container").html(html);
        else $("#review-list-container").append(html);
        $("#review-load-more-wrap").remove();
        if (data.has_more) {
          $("#review-list-container").after(`
                    <div id="review-load-more-wrap" style="text-align:center; margin-top:20px;">
                        <button id="btn-load-more-reviews" class="btn-view-all-reviews">Xem thêm đánh giá cũ hơn</button>
                    </div>
                `);
        }
      } catch (e) {
        console.error("Lỗi JSON:", e);
      } finally {
        reviewState.isLoading = false;
      }
    },
  );
}

// --- CART & CHECKOUT FUNCTIONS ---
// ==========================================
// FUNCTION BIẾN TOÀN CỤC LƯU TRỮ VOUCHER & TỔNG TIỀN
// ==========================================
let currentSubtotal = 0;
let selectedVoucher = null;

$(document).on("click", ".cart-item", function (e) {
  if (
    $(e.target).closest("a, button, input, .qty-control, .cart-variant-box")
      .length
  ) {
    return;
  }
  let chk = $(this).find(".pay-check");
  if (chk.length) {
    chk.prop("checked", !chk.prop("checked")).trigger("change");
  }
});

function calcTotal() {
  let total = 0;
  $(".pay-check:checked").each(function () {
    let price = parseInt($(this).data("price")) || 0;
    let qty = parseInt($(this).data("qty")) || 0;
    total += price * qty;
  });

  let fmt = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(total);

  $("#total-money").text(fmt);
  $("#selected-count").text($(".pay-check:checked").length);
  $("#modal-subtotal").text(fmt);

  currentSubtotal = total;

  applyVoucherLogic();
}

function _cartCheckEmpty() {
  let remaining = $(".cart-item").length;
  $(".select-all").text("Chọn tất cả (" + remaining + " sản phẩm)");
  if (remaining === 0) {
    $(".cart-wrapper").replaceWith(
      '<div class="empty-cart-state" style="text-align:center;padding:60px 20px;">' +
        '<i class="fa-solid fa-cart-shopping" style="font-size:80px;color:#787878;margin-bottom:20px;"></i>' +
        '<h3 style="color:#555;">Giỏ hàng của bạn đang trống</h3>' +
        '<p style="color:#888;margin-bottom:20px;">Hãy chọn thêm sản phẩm để mua sắm nhé</p>' +
        '<a href="index.php" style="color:#00487a;font-weight:bold;">← Quay lại trang chủ</a>' +
        "</div>",
    );
  }
}

// ==========================================
//  CÁC HÀM XỬ LÝ VOUCHER TẠI MODAL ĐẶT HÀNG
// ==========================================
function closeVoucherList() {
  $("#voucher-list-modal").fadeOut(200, function () {
    $("#checkout-modal").fadeIn(200);
  });
}

function openVoucherList() {
  if (currentSubtotal === 0) {
    Swal.fire("Lưu ý", "Bạn chưa chọn sản phẩm nào để thanh toán!", "warning");
    return;
  }

  $("#checkout-modal").fadeOut(200, function () {
    $("#voucher-list-modal").fadeIn(200);
  });

  $("#voucher-list-container").html(
    '<div style="text-align: center; color: #888; padding: 20px;"><i class="fa fa-spinner fa-spin" style="font-size: 24px;"></i> Đang tải danh sách...</div>',
  );

  let apiUrl = "api/voucher_api.php";

  $.post(apiUrl, { action: "get_my_vouchers" }, function (res) {
    try {
      let response;
      if (typeof res === "object") {
        response = res;
      } else {
        let cleanRes = res.substring(res.indexOf("{"));
        response = JSON.parse(cleanRes);
      }

      let container = $("#voucher-list-container");
      container.empty();

      if (response.status === "success" && response.data.length > 0) {
        response.data.forEach(function (v) {
          let isEligible = currentSubtotal >= parseFloat(v.min_order_value);
          let opacity = isEligible ? "1" : "0.5";
          let discountText =
            v.type === "percent"
              ? `Giảm ${parseFloat(v.discount_amount)}%`
              : `Giảm ${new Intl.NumberFormat("vi-VN").format(v.discount_amount)}đ`;
          let maxDesc =
            v.type === "percent" && parseFloat(v.max_discount) > 0
              ? `<br><small style="color:#666;">Tối đa ${new Intl.NumberFormat("vi-VN").format(v.max_discount)}đ</small>`
              : "";
          let minDesc =
            parseFloat(v.min_order_value) > 0
              ? `Đơn từ ${new Intl.NumberFormat("vi-VN").format(v.min_order_value)}đ`
              : "Áp dụng cho mọi đơn hàng";

          let btnAction = isEligible
            ? `<button type="button" class="btn-primary" style="padding: 6px 12px; font-size: 13px; border-radius:14px;width: 80px;
    font-weight: 600;" onclick='selectVoucher(${JSON.stringify(v)})'>Chọn mã</button>`
            : `<span style="color:#d70018; font-size: 12px; font-weight:bold;">Chưa đủ điều kiện</span>`;

          let html = `
                        <div style="border: 1px dashed #00487a; background: #f8fcfd; border-radius: 8px; padding: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; opacity: ${opacity};">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 5px 0; color: #00487a; font-size: 16px;">${v.code}</h4>
                                <p style="margin: 0; font-size: 14px; font-weight: bold; color: #d70018;">${discountText} ${maxDesc}</p>
                                <p style="margin: 5px 0 0 0; font-size: 12px; color: #555;">${minDesc}</p>
                            </div>
                            <div style="margin-left: 10px; text-align:right;">
                                ${btnAction}
                            </div>
                        </div>
                    `;
          container.append(html);
        });
      } else {
        container.html(
          '<div style="text-align: center; color: #888; padding: 30px;">Bạn chưa có mã giảm giá nào hoặc không có mã phù hợp.</div>',
        );
      }
    } catch (e) {
      console.error("Lỗi Parse JSON:", e, res);
      $("#voucher-list-container").html(
        '<div style="text-align: center; color: red; padding: 20px;">Lỗi xử lý dữ liệu từ máy chủ.</div>',
      );
    }
  }).fail(function () {
    $("#voucher-list-container").html(
      '<div style="text-align: center; color: red; padding: 20px;">Không thể kết nối đến máy chủ.</div>',
    );
  });
}

function selectVoucher(v) {
  selectedVoucher = v;

  $("#voucher-list-modal").fadeOut(200, function () {
    $("#checkout-modal").fadeIn(200);
  });

  $("#c-coupon").val(v.code);
  $("#c-voucher-id").val(v.id);
  $("#btn-clear-voucher").show();

  applyVoucherLogic();

  Swal.fire({
    toast: true,
    position: "top-end",
    icon: "success",
    title: "Đã áp dụng mã " + v.code,
    showConfirmButton: false,
    timer: 1500,
  });
}

let voucherTypeTimer;
$(document).on("keyup", "#c-coupon", function () {
  clearTimeout(voucherTypeTimer);
  let code = $(this).val().trim().toUpperCase();

  if (code.length === 0) {
    clearVoucher();
    return;
  }

  voucherTypeTimer = setTimeout(function () {
    if (currentSubtotal === 0) return;

    $.post(
      "api/voucher_api.php",
      { action: "get_my_vouchers" },
      function (res) {
        try {
          let response =
            typeof res === "object"
              ? res
              : JSON.parse(res.substring(res.indexOf("{")));
          if (response.status === "success" && response.data.length > 0) {
            let found = response.data.find(
              (v) => v.code.toUpperCase() === code,
            );
            if (found && currentSubtotal >= parseFloat(found.min_order_value)) {
              selectedVoucher = found;
              $("#c-voucher-id").val(found.id);
              $("#btn-clear-voucher").show();
              applyVoucherLogic();
            } else {
              if (
                selectedVoucher &&
                selectedVoucher.code.toUpperCase() !== code
              ) {
                selectedVoucher = null;
                $("#c-voucher-id").val("");
                $("#btn-clear-voucher").hide();
                applyVoucherLogic();
              }
            }
          }
        } catch (e) {
          console.error("Lỗi Parse JSON voucher:", e);
        }
      },
    );
  }, 500);
});

function clearVoucher() {
  selectedVoucher = null;
  $("#c-coupon").val("");
  $("#c-voucher-id").val("");
  $("#btn-clear-voucher").hide();

  applyVoucherLogic();
}

// ==========================================
// 3. LOGIC TÍNH TOÁN TIỀN KHI CÓ VOUCHER
// ==========================================
function applyVoucherLogic() {
  let fmtTotal = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  });

  if (!selectedVoucher || currentSubtotal === 0) {
    $("#voucher-discount-info").hide();
    $("#modal-old-total").hide();
    $("#modal-total-money").text(fmtTotal.format(currentSubtotal));

    if (
      selectedVoucher &&
      currentSubtotal > 0 &&
      currentSubtotal < parseFloat(selectedVoucher.min_order_value)
    ) {
      clearVoucher();
      Swal.fire(
        "Hủy áp dụng",
        "Tổng tiền không còn đủ điều kiện để dùng mã này.",
        "info",
      );
    }
    return;
  }

  let discountValue = 0;

  if (selectedVoucher.type === "percent") {
    discountValue =
      currentSubtotal * (parseFloat(selectedVoucher.discount_amount) / 100);
    let maxDiscount = parseFloat(selectedVoucher.max_discount);
    if (maxDiscount > 0 && discountValue > maxDiscount) {
      discountValue = maxDiscount;
    }
  } else {
    discountValue = parseFloat(selectedVoucher.discount_amount);
  }

  if (discountValue > currentSubtotal) {
    discountValue = currentSubtotal;
  }

  let finalTotal = currentSubtotal - discountValue;

  $("#discount-label").text(selectedVoucher.code);
  $("#modal-discount-amount").text("-" + fmtTotal.format(discountValue));
  $("#voucher-discount-info").css("display", "flex");

  $("#modal-old-total").text(fmtTotal.format(currentSubtotal)).show();
  $("#modal-total-money").text(fmtTotal.format(finalTotal));
}
// ==========================================
// 3. LOGIC TÍNH TOÁN TIỀN KHI CÓ VOUCHER
// ==========================================
function applyVoucherLogic() {
  let fmtTotal = new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  });

  if (!selectedVoucher || currentSubtotal === 0) {
    $("#voucher-discount-info").hide();
    $("#modal-old-total").hide();
    $("#modal-total-money").text(fmtTotal.format(currentSubtotal));

    if (
      selectedVoucher &&
      currentSubtotal > 0 &&
      currentSubtotal < parseFloat(selectedVoucher.min_order_value)
    ) {
      clearVoucher();
      Swal.fire(
        "Hủy áp dụng",
        "Tổng tiền không còn đủ điều kiện để dùng mã này.",
        "info",
      );
    }
    return;
  }

  let discountValue = 0;

  if (selectedVoucher.type === "percent") {
    discountValue =
      currentSubtotal * (parseFloat(selectedVoucher.discount_amount) / 100);
    let maxDiscount = parseFloat(selectedVoucher.max_discount);
    if (maxDiscount > 0 && discountValue > maxDiscount) {
      discountValue = maxDiscount;
    }
  } else {
    discountValue = parseFloat(selectedVoucher.discount_amount);
  }

  if (discountValue > currentSubtotal) {
    discountValue = currentSubtotal;
  }

  let finalTotal = currentSubtotal - discountValue;

  $("#discount-label").text(selectedVoucher.code);
  $("#modal-discount-amount").text("-" + fmtTotal.format(discountValue));
  $("#voucher-discount-info").css("display", "flex"); // Hiện dòng giảm giá
  $("#modal-old-total").text(fmtTotal.format(currentSubtotal)).show();
  $("#modal-total-money").text(fmtTotal.format(finalTotal));
}

function toggleDeleteSelectedBtn() {
  if ($(".pay-check:checked").length > 0) $("#btn-delete-selected").show();
  else $("#btn-delete-selected").hide();
}

function updateCartCount() {
  $.post("api/cart_api.php", { action: "count" }, function (data) {
    try {
      let res = typeof data === "object" ? data : JSON.parse(data);
      const count = parseInt(res.count) || 0;
      const $cartBadge = $(".menu-cart-box .menu-cart-badge");
      if (count > 0) {
        if ($cartBadge.length) {
          $cartBadge.text(count).removeClass("hidden").css("display", "");
        } else {
          $(".menu-cart-box .menu-cart-icon").after(
            $('<span class="menu-cart-badge">').text(count),
          );
        }
      } else {
        $cartBadge.addClass("hidden").css("display", "none");
      }

      const $mBadge = $("#m-btn-cart .m-badge");
      if (count > 0) {
        if ($mBadge.length) {
          $mBadge.text(count).css("display", "");
        } else {
          $("#m-btn-cart .fa-cart-shopping").after(
            $('<span class="m-badge">').text(count),
          );
        }
      } else {
        $mBadge.css("display", "none");
      }
    } catch (e) {}
  });
}

function handleCheckout(btn) {
  let name = $("#c-name").val().trim();
  let phone = $("#c-phone").val().trim();
  let address = $("#c-address").val().trim();
  let coupon = $("#c-coupon").val().trim();
  let paymentMethod = $('input[name="payment_method"]:checked').val() || "cod";

  if (coupon) address += ` (Voucher: ${coupon})`;

  if (!name || !phone || !address)
    return showToast({
      title: "Lỗi",
      message: "Vui lòng điền đủ thông tin!",
      type: "error",
    });

  let items = [];
  $(".pay-check:checked").each(function () {
    let $cartItem = $(this).closest(".cart-item");
    let variantText = $cartItem.find(".variant-text").text().trim();
    if (variantText === "Chọn phân loại" || variantText === "")
      variantText = "";
    items.push({
      product_id: $(this).val(),
      quantity: $(this).data("qty"),
      variant_text: variantText,
    });
  });

  if (items.length === 0)
    return showToast({
      title: "Lỗi",
      message: "Chưa chọn sản phẩm!",
      type: "error",
    });

  let oldText = btn.text();
  btn.prop("disabled", true).text("Đang xử lý...");

  $.post(
    "api/cart_api.php",
    {
      action: "checkout",
      info: { name: name, phone: phone, address: address },
      items: JSON.stringify(items),
      payment_method: paymentMethod,
      voucher_id: selectedVoucher ? selectedVoucher.id : 0,
    },
    function (res) {
      btn.prop("disabled", false).text(oldText);
      try {
        let data = typeof res === "object" ? res : JSON.parse(res);
        if (data.status === "success") {
          $("#checkout-modal").hide();
          if (paymentMethod === "banking") {
            showBankingQR(data.order_id, data.total_money);
          } else if (paymentMethod === "vnpay") {
            window.location.href = `api/vnpay_create.php?order_code=${data.order_code}&amount=${data.total_money}`;
          } else {
            showToast({
              title: "Thành công",
              message: "Đặt hàng thành công!",
              type: "success",
            });
            setTimeout(
              () => (window.location.href = "order_history.php"),
              1500,
            );
          }
        } else {
          showToast({
            title: "Thất bại",
            message: data.message,
            type: "error",
          });
        }
      } catch (e) {
        showToast({
          title: "Lỗi hệ thống",
          message: "Thử lại sau.",
          type: "error",
        });
      }
    },
  ).fail(function () {
    btn.prop("disabled", false).text(oldText);
    showToast({
      title: "Lỗi mạng",
      message: "Không thể kết nối server.",
      type: "error",
    });
  });
}

function showBankingQR(orderId, amount) {
  if (!orderId || !amount) return alert("Lỗi dữ liệu!");
  let bankId = "BIDV";
  let accNo = "0334960320";
  let accName = "DAM NGOC BINH";
  let content = "DH" + orderId;
  let qrUrl = `https://img.vietqr.io/image/${bankId}-${accNo}-compact.png?amount=${amount}&addInfo=${content}&accountName=${encodeURIComponent(accName)}`;

  $("#qr-loader").show();
  $("#qr-img").hide().attr("src", "");
  $("#qr-content").text(content);
  $("#qr-amount").text(
    new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
    }).format(amount),
  );
  $("#banking-modal").fadeIn();

  let imgObj = new Image();
  imgObj.onload = function () {
    $("#qr-img").attr("src", qrUrl).fadeIn();
    $("#qr-loader").hide();
  };
  imgObj.src = qrUrl;
}

// --- PRODUCT & SHOPPING FUNCTIONS ---
function renderStars(rating) {
  const filled = Math.round(rating);
  let html = '<div class="card-stars">';
  for (let i = 1; i <= 5; i++) {
    html +=
      i <= filled
        ? '<i class="fa-solid fa-star" style="color:#f39c12;"></i>'
        : '<i class="fa-regular fa-star" style="color:#ccc;"></i>';
  }
  html += `<span class="card-rating-num">${rating > 0 ? rating.toFixed(1) : ""}</span>`;
  html += "</div>";
  return html;
}

function loadProducts(brandInput) {
  let brand = brandInput || "all";
  let sort = $("#sortPrice").val() || "asc";

  $.post("api/product_api.php", { brand: brand, sort: sort }, function (data) {
    try {
      let products = typeof data === "object" ? data : JSON.parse(data);
      let htmlAll = "",
        htmlHot = "";
      let fmt = new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
      });

      products.forEach((p, index) => {
        let img = p.image.startsWith("http")
          ? p.image
          : `assets/img/${p.image}`;
        let priceDisplay = "",
          badgeHtml = "";

        if (p.is_flash_sale && p.flash_price !== null) {
          let flashPrice = fmt.format(p.flash_price);
          let origPrice = fmt.format(p.price);
          priceDisplay = `<div class="price-wrap"><span class="price-new">${flashPrice}</span><span class="price-old">${origPrice}</span></div>`;
          badgeHtml = `<span class="sale-badge discount-badge">${p.flash_discount_label}</span>`;
        } else if (parseFloat(p.sale_price) > 0) {
          let oldPrice = fmt.format(p.price);
          let newPrice = fmt.format(p.sale_price);
          let percent = Math.round(((p.price - p.sale_price) / p.price) * 100);
          priceDisplay = `<div class="price-wrap"><span class="price-new">${newPrice}</span><span class="price-old">${oldPrice}</span></div>`;
          badgeHtml = `<span class="sale-badge">-${percent}%</span>`;
        } else {
          priceDisplay = `<p class="price">${fmt.format(p.price)}</p>`;
        }

        let starsHtml = renderStars(parseFloat(p.avg_rating) || 0);

        let item = `
                    <div class="product-card">
                        ${badgeHtml}
                        <a href="product_detail.php?id=${p.id}"><img src="${img}"><h3>${p.name}</h3></a>
                        ${priceDisplay}
                        ${starsHtml}
                        <button class="js-add-to-cart btn-add" data-id="${p.id}">THÊM VÀO GIỎ</button>
                    </div>`;

        if (index < 4) htmlHot += item;
        else htmlAll += item;
      });

      if ($("#hot-product-list").length) $("#hot-product-list").html(htmlHot);
      $("#product-list").html(htmlAll);
    } catch (e) {
      console.log(e);
    }
  });
}

function handleAddToCart(e, btn, isBuyNow) {
  e.preventDefault();
  e.stopImmediatePropagation();
  if (btn.data("loading")) return;

  let type = btn.attr("data-type");
  let vid = btn.attr("data-variation-id");

  if (type === "variable" && (!vid || vid === "")) {
    showToast({
      title: "Nhắc nhở",
      message: "Vui lòng chọn đầy đủ thuộc tính sản phẩm!",
      type: "warning",
    });
    return;
  }

  btn.data("loading", true);

  let pid = btn.data("id");
  let oldHtml = btn.html();
  let oldWidth = btn.outerWidth();
  let oldHeight = btn.outerHeight();
  btn
    .css({ "min-width": oldWidth + "px", "min-height": oldHeight + "px" })
    .html('<i class="fa fa-spinner fa-spin"></i>');
  btn.prop("disabled", true);

  $.post(
    "api/cart_api.php",
    { action: "add", product_id: pid, quantity: 1, variation_id: vid || "" },
    function () {
      if (isBuyNow) {
        window.location.href =
          "cart.php?buy_now=" + pid + "&vid=" + (vid || "");
      } else {
        showToast({
          title: "Thành công",
          message: "Đã thêm sản phẩm vào giỏ!",
          type: "success",
        });
        updateCartCount();
        setTimeout(() => {
          btn
            .html(oldHtml)
            .prop("disabled", false)
            .css({ "min-width": "", "min-height": "", width: "" })
            .data("loading", false);
        }, 500);
      }
    },
  ).fail(function () {
    showToast({ title: "Lỗi", message: "Lỗi kết nối!", type: "error" });
    btn
      .html(oldHtml)
      .prop("disabled", false)
      .css({ "min-width": "", "min-height": "", width: "" })
      .data("loading", false);
  });
}

// --- NOTIFICATION FUNCTIONS ---
function checkUserNotifications() {
  const notifList = document.getElementById("notif-list");
  const notifBadge = document.getElementById("nav-notif-badge");
  if (!notifList || !notifBadge) return;

  fetch("/api/notification_api.php?action=get_notifications&limit=15")
    .then((r) => r.json())
    .then(function (res) {
      const unread = parseInt(res.unread) || 0;
      if (unread > 0) {
        notifBadge.textContent = unread > 99 ? "99+" : unread;
        notifBadge.style.display = "";
      } else {
        notifBadge.style.display = "none";
        notifBadge.textContent = "0";
      }

      if (
        window._navRefreshBadges &&
        !document.getElementById("notif-dropdown").classList.contains("open")
      ) {
        return;
      }
    })
    .catch(function () {});
}

setTimeout(() => {
  if (!$("#chat-box").hasClass("open")) {
    $("#chat-welcome-bubble").fadeIn();
    setTimeout(() => {
      $("#chat-welcome-bubble").fadeOut();
    }, 10000);
  }
}, 1000);

function toggleChat() {
  var box = $("#chat-box");
  $("#chat-welcome-bubble").fadeOut();

  if (!box.hasClass("open")) {
    box.addClass("open");
    $(".chat-badge-notify").remove();
    $("#main-chat-badge").addClass("hidden");
    $.post("api/chat_api.php", { action: "mark_read_user" });
    switchChatTab(currentChatTab);
    if (!chatInterval)
      chatInterval = setInterval(() => loadMessages(false), 3000);
    setTimeout(scrollToBottom, 200);
    if (!$("#chat-backdrop").length) {
      $("body").append(
        '<div id="chat-backdrop" style="' +
          "position:fixed;inset:0;background:rgba(0,0,0,0.45);" +
          "z-index:9999;display:none;" +
          '"></div>',
      );
    }
    if (window.innerWidth <= 768) {
      $("#chat-backdrop").fadeIn(200);
      $("#chat-widget").fadeOut(150);
    }

    $("#chat-toggle").trigger("chat:open");
  } else {
    const ANIM_MS = 350;

    box.addClass("is-closing");
    $("#chat-backdrop").fadeOut(ANIM_MS);

    setTimeout(function () {
      box.removeClass("open is-closing");

      $("#chat-widget").fadeIn(200);

      if (chatInterval) {
        clearInterval(chatInterval);
        chatInterval = null;
      }
    }, ANIM_MS);

    $("#chat-toggle").trigger("chat:close");
  }
}

function switchChatTab(tab) {
  currentChatTab = tab;
  $(".chat-tab").removeClass("active");
  $(`.chat-tab[data-tab="${tab}"]`).addClass("active");
  $(`#badge-${tab}`).removeClass("show").text("");
  $("#chat-content").html("");
  loadMessages(true);
}

function loadMessages(isScroll) {
  if (currentChatTab === "shop" && currentUserId === 0) {
    $("#chat-content").html(
      `<div style="height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px;"><i class="fa-solid fa-user-lock" style="font-size:48px; color:#ccc; margin-bottom:15px;"></i><p style="color:#555; margin-bottom:15px; font-size:14px;">Bạn cần đăng nhập để chat trực tiếp với nhân viên.</p><a href="login.php" style="background:var(--primary); color:white; padding:10px 20px; border-radius:5px; text-decoration:none;">Đăng nhập ngay</a></div>`,
    );
    return;
  }

  $.post(
    "api/chat_api.php",
    { action: "get_messages", tab: currentChatTab },
    function (data) {
      try {
        let msgs = typeof data === "object" ? data : JSON.parse(data);
        let html = "";
        if (msgs.length > 0) {
          msgs.forEach((msg) => {
            let cls =
              msg.role == "user" ? "user" : msg.role == "bot" ? "bot" : "shop";
            html += `<div class="msg ${cls}"><div class="msg-text">${msg.message}</div></div>`;
          });
          $("#chat-content").html(html);
        } else {
          if (currentChatTab === "bot")
            $("#chat-content").html(
              `<div class="msg bot"><div class="msg-text">🤖 Chào bạn! Mình là <b>AI TechMate</b>. Mình có thể giúp gì cho bạn?</div></div>`,
            );
          else
            $("#chat-content").html(
              '<p style="text-align:center; color:#999; margin-top:50px;">Chưa có tin nhắn nào.</p>',
            );
        }
        if (isScroll) scrollToBottom();
      } catch (e) {}
    },
  );
}

function checkUserNotifications() {
  if (!$("#chat-box").hasClass("open")) {
    $.post(
      "api/chat_api.php",
      { action: "check_notification" },
      function (data) {
        try {
          let res = typeof data === "object" ? data : JSON.parse(data);
          $(".chat-badge-notify").remove();
          if (res.unread > 0) {
            $("#chat-toggle").append(
              `<span class="chat-badge chat-badge-notify">${res.unread}</span>`,
            );
            $("#badge-shop").text(res.unread).addClass("show");
          } else $("#badge-shop").removeClass("show");
        } catch (e) {}
      },
    );
  }
}

function showBotTyping() {
  if ($("#bot-typing-indicator").length > 0) return;
  let typingHtml = `
    <div class="msg bot" id="bot-typing-indicator">
      <div class="msg-text" style="padding: 10px 14px; background: #f1f2f6; border-radius: 14px; border-bottom-left-radius: 4px;">
        <div class="typing-indicator">
          <span></span><span></span><span></span>
        </div>
      </div>
    </div>
  `;
  $("#chat-content").append(typingHtml);
  scrollToBottom();
}

function hideBotTyping() {
  $("#bot-typing-indicator").remove();
}

function sendMessage() {
  let msg = $("#chat-input").val().trim();
  if (!msg || isSending) return;
  isSending = true;
  $(".chat-footer button").css("opacity", "0.5");

  // 1. Hiện tin nhắn user
  $("#chat-input").val("");
  let safeMsg = msg.replace(/</g, "&lt;").replace(/>/g, "&gt;");
  $("#chat-content").append(
    `<div class="msg user"><div class="msg-text">${safeMsg}</div></div>`,
  );
  scrollToBottom();

  if (currentChatTab === "bot") {
    showBotTyping();
  }

  // 3. Gọi AJAX
  $.post(
    "api/chat_api.php",
    { action: "send_message", message: msg, tab: currentChatTab },
    function () {
      // Ẩn hoạt ảnh: Khi Server trả về và trước khi in câu trả lời thật (sẽ load qua loadMessages)
      hideBotTyping();
      loadMessages(true);

      isSending = false;
      $(".chat-footer button").css("opacity", "1");
    },
  ).fail(function () {
    hideBotTyping();
    isSending = false;
    $(".chat-footer button").css("opacity", "1");
  });
}

function scrollToBottom() {
  let d = document.getElementById("chat-content");
  if (d) d.scrollTop = d.scrollHeight;
}

// Chage_password
function changePassword() {
  let oldPass = $("#old-pass").val().trim();
  let newPass = $("#new-pass").val().trim();

  // Kiểm tra rỗng: Hiển thị Popup cảnh báo
  if (!oldPass || !newPass) {
    Swal.fire({
      icon: "warning",
      title: "Thiếu thông tin",
      text: "Vui lòng nhập đầy đủ mật khẩu cũ và mật khẩu mới!",
      confirmButtonColor: "#00487a",
    });
    return;
  }

  $.post(
    "api/profile_api.php",
    {
      action: "change_password",
      old_password: oldPass,
      new_password: newPass,
    },
    function (res) {
      try {
        let response = typeof res === "object" ? res : JSON.parse(res);

        if (response.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Thành công!",
            text: response.message,
            showConfirmButton: false,
            timer: 1500,
          }).then(() => {
            window.location.href = "profile.php";
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Lỗi",
            text: response.message,
            confirmButtonColor: "#d70018",
          });
        }
      } catch (e) {
        console.error(e);
        Swal.fire({
          icon: "error",
          title: "Lỗi hệ thống",
          text: "Đã có lỗi xảy ra, vui lòng thử lại sau!",
        });
      }
    },
  );
}

// =========================================
// ORDER HISTORY - HỦY ĐƠN HÀNG
// =========================================
function confirmCancel(orderId) {
  customConfirm(
    "Bạn có chắc chắn muốn hủy đơn hàng #" + orderId + " không?",
    function () {
      const btn = document.querySelector(".btn-cancel-order");
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Đang hủy...';
      }

      $.post(
        "api/order_api.php",
        {
          action: "cancel_order",
          order_id: orderId,
        },
        function (res) {
          try {
            let data = typeof res === "object" ? res : JSON.parse(res);
            if (data.status === "success") {
              Swal.fire({
                icon: "success",
                title: "Thành công!",
                text: data.message,
                showConfirmButton: false,
                timer: 1500,
              });

              $(".btn-cancel-order").fadeOut(300, function () {
                $(this).remove();
              });

              $(".timeline").fadeOut(300, function () {
                $(this).replaceWith(`
                  <div class="alert fade-in" style="background:#ffecec; color:#d70018; padding:15px; border-radius:6px; text-align:center; margin-bottom:30px; font-weight:bold;">
                      <i class="fa fa-circle-exclamation"></i> Đơn hàng này đã bị hủy.
                  </div>
                  `);
              });
            } else {
              Swal.fire({
                icon: "error",
                title: "Thất bại",
                text: data.message,
                confirmButtonColor: "#d70018",
              });
              if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-trash"></i> Hủy đơn hàng';
              }
            }
          } catch (e) {
            Swal.fire({
              icon: "error",
              title: "Lỗi",
              text: "Lỗi hệ thống khi hủy đơn hàng.",
              confirmButtonColor: "#d70018",
            });
            if (btn) {
              btn.disabled = false;
              btn.innerHTML = '<i class="fa fa-trash"></i> Hủy đơn hàng';
            }
          }
        },
      ).fail(function () {
        Swal.fire({
          icon: "error",
          title: "Lỗi kết nối",
          text: "Không thể kết nối đến máy chủ.",
          confirmButtonColor: "#d70018",
        });
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-trash"></i> Hủy đơn hàng';
        }
      });
    },
  );
}

/* =================================================================
   FLASH SALE LOADER (index.php)
================================================================= */
var flashSaleEndTime = null;
var flashTimerInterval = null;

function loadFlashSale() {
  if (!$("#flash-sale-section").length) return;

  $.get("api/flash_sale_api.php", { action: "get_flash_sale" }, function (res) {
    try {
      var data = typeof res === "object" ? res : JSON.parse(res);
      if (
        data.status !== "active" ||
        !data.products ||
        data.products.length === 0
      ) {
        $("#flash-sale-section").hide();
        return;
      }

      $("#flash-sale-section").show();

      if (data.config && data.config.title) {
        $("#fs-display-title").text(data.config.title);
      }

      flashSaleEndTime = new Date(
        data.config.end_time.replace(" ", "T"),
      ).getTime();
      if (flashTimerInterval) clearInterval(flashTimerInterval);
      flashTimerInterval = setInterval(updateFlashTimer, 1000);
      updateFlashTimer();

      var fmt = new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
      });
      var html = "";
      data.products.forEach(function (p, idx) {
        var img =
          p.image && p.image.startsWith("http")
            ? p.image
            : "assets/img/" + (p.image || "");
        var flashPrice = fmt.format(p.flash_price);
        var origPrice = fmt.format(p.price);
        html += `
          <div class="product-card" style="animation-delay:${idx * 0.07}s">
            <span class="sale-badge discount-badge">${p.discount_display}</span>
            <a href="product_detail.php?id=${p.id}">
              <img src="${img}" alt="${p.name}" onerror="this.src='assets/img/no-image.png'">
              <h3>${p.name}</h3>
            </a>
            <div class="price-wrap">
              <span class="price-new">${flashPrice}</span>
              <span class="price-old">${origPrice}</span>
            </div>
            <button class="js-add-to-cart btn-add" data-id="${p.id}">THÊM VÀO GIỎ</button>
          </div>`;
      });
      $("#hot-product-list").html(html);
    } catch (e) {
      console.error("Flash Sale parse error:", e);
      $("#flash-sale-section").hide();
    }
  }).fail(function () {
    $("#flash-sale-section").hide();
  });
}

function updateFlashTimer() {
  if (!flashSaleEndTime) return;
  var now = new Date().getTime();
  var diff = flashSaleEndTime - now;
  if (diff <= 0) {
    clearInterval(flashTimerInterval);
    $("#flash-sale-section").hide();
    return;
  }
  var days = Math.floor(diff / (1000 * 60 * 60 * 24));
  var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  var secs = Math.floor((diff % (1000 * 60)) / 1000);
  $("#fs-days").text(String(days).padStart(2, "0"));
  $("#fs-hours").text(String(hours).padStart(2, "0"));
  $("#fs-mins").text(String(mins).padStart(2, "0"));
  $("#fs-secs").text(String(secs).padStart(2, "0"));
}

/* =================================================================
   HERO SLIDESHOW 
================================================================= */
$(document).ready(function () {
  // Flash Sale
  if ($("#flash-sale-section").length) {
    loadFlashSale();
  }

  var heroIdx = 0;
  var $heroSlides = $(".hero-slide");
  var heroTotal = $heroSlides.length;
  var heroInterval = null;

  if (heroTotal <= 1) return;

  function heroGoTo(n) {
    heroIdx = ((n % heroTotal) + heroTotal) % heroTotal;
    $heroSlides.removeClass("active");
    $heroSlides.eq(heroIdx).addClass("active");
    $("#hero-dots .hero-dot")
      .removeClass("active")
      .eq(heroIdx)
      .addClass("active");
  }

  function heroNext() {
    heroGoTo(heroIdx + 1);
  }

  function startHeroAuto() {
    heroInterval = setInterval(heroNext, 4000);
  }

  function resetHeroAuto() {
    clearInterval(heroInterval);
    startHeroAuto();
  }

  startHeroAuto();

  $(document).on("click", "#hero-next", function () {
    heroNext();
    resetHeroAuto();
  });
  $(document).on("click", "#hero-prev", function () {
    heroGoTo(heroIdx - 1);
    resetHeroAuto();
  });
  $(document).on("click", ".hero-dot", function () {
    heroGoTo($(this).data("idx"));
    resetHeroAuto();
  });

  let heroStartX = 0;
  let heroEndX = 0;
  const $heroSlider = $("#hero-slider");

  $heroSlider.on("touchstart", function (e) {
    heroStartX = e.originalEvent.touches[0].clientX;
    heroEndX = heroStartX;
    clearInterval(heroInterval);
  });

  $heroSlider.on("touchmove", function (e) {
    heroEndX = e.originalEvent.touches[0].clientX;
  });

  $heroSlider.on("touchend", function (e) {
    if (heroStartX - heroEndX > 50) {
      heroNext();
    } else if (heroEndX - heroStartX > 50) {
      heroGoTo(heroIdx - 1);
    }
    resetHeroAuto();
  });
});

/* =================================================================
   MOBILE UI INTERACTIONS (BOTTOM SHEETS, DRAWERS)
================================================================= */
$(document).ready(function () {
  const $backdrop = $("#m-backdrop");
  let openSheet = null;

  function closeAllMobilePanels() {
    $(".m-bottom-sheet").removeClass("open");
    $(".sidebar-menu").removeClass("open");
    $backdrop.removeClass("show");
    $("body").css("overflow", "");
    $(".m-nav-item").removeClass("active");
    openSheet = null;
  }

  function toggleMobilePanel(panelSelector, btn) {
    const $panel = $(panelSelector);
    if ($panel.hasClass("open")) {
      closeAllMobilePanels();
    } else {
      closeAllMobilePanels();
      $panel.addClass("open");
      $backdrop.addClass("show");
      $("body").css("overflow", "hidden");
      if (btn) $(btn).addClass("active");
      openSheet = panelSelector;
    }
  }

  // Categories Drawer
  $("#m-btn-categories").on("click", function (e) {
    if ($(".sidebar-menu").length) {
      toggleMobilePanel(".sidebar-menu", this);
    } else {
      let btn = this;
      let $icon = $(btn).find("i");
      let oldClass = $icon.attr("class");
      $icon.attr("class", "fa-solid fa-spinner fa-spin");

      $.get(BASE_URL + "/index.php", function (data) {
        $icon.attr("class", oldClass);
        let $menuHtml = $(data).find(".sidebar-menu");
        if ($menuHtml.length) {
          if ($("#m-dynamic-sidebar-style").length === 0) {
            $("head").append(
              "<style id='m-dynamic-sidebar-style'>@media (min-width: 769px) { .m-dynamic-sidebar-wrapper { display: none !important; } }</style>",
            );
          }
          let $wrap = $('<div class="m-dynamic-sidebar-wrapper"></div>').append(
            $menuHtml,
          );
          $("body").append($wrap);

          setTimeout(function () {
            toggleMobilePanel(".sidebar-menu", btn);
          }, 10);
        } else {
          window.location.href = BASE_URL + "/index.php";
        }
      }).fail(function () {
        $icon.attr("class", oldClass);
        window.location.href = BASE_URL + "/index.php";
      });
    }
  });

  // User Sheet
  $("#m-btn-user").on("click", function () {
    toggleMobilePanel("#m-user-sheet", this);
  });

  // Notifications Sheet
  $("#m-btn-notif").on("click", function () {
    let sheet = $("#m-notif-sheet");
    if (
      !sheet.hasClass("open") &&
      typeof window._loadNotifList === "function"
    ) {
      window._loadNotifList();
    }
    toggleMobilePanel("#m-notif-sheet", this);
  });

  $(".m-close-sheet").on("click", closeAllMobilePanels);
  $backdrop.on("click", closeAllMobilePanels);
});

(function () {
  if (!document.body.classList.contains("has-scroll-top")) return;

  var $btn = $("#scroll-top-btn");
  if (!$btn.length) return;

  var ticking = false;
  var THRESHOLD = 200;

  function onScroll() {
    if (!ticking) {
      requestAnimationFrame(function () {
        if (window.scrollY > THRESHOLD) {
          $btn.addClass("is-visible");
        } else {
          $btn.removeClass("is-visible");
        }
        ticking = false;
      });
      ticking = true;
    }
  }

  window.addEventListener("scroll", onScroll, { passive: true });

  $btn.on("click", function () {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
})();

/* =================================================================
   PRODUCT IMAGE LIGHTBOX
================================================================= */
(function () {
  var ZOOM_MIN = 1;
  var ZOOM_MAX = 5;
  var ZOOM_STEP = 0.4;
  var zoomLevel = 1;
  var panX = 0;
  var panY = 0;
  var isDragging = false;
  var dragStartX = 0;
  var dragStartY = 0;
  var panStartX = 0;
  var panStartY = 0;

  var lastPinchDist = 0;

  function buildLightbox() {
    if ($("#img-lightbox-overlay").length) return;
    var html = [
      '<div id="img-lightbox-overlay" role="dialog" aria-modal="true" aria-label="Xem ảnh">',
      '<a id="img-lightbox-close" title="Đóng (Esc)"><i class="fa fa-xmark"></i></a>',
      '<div id="img-lightbox-wrap">',
      '<img id="img-lightbox-img" src="" alt="Product Image">',
      "</div>",
      '<div id="img-lightbox-zoom-bar">',
      '<button id="lb-btn-zoom-out" title="Thu nhỏ"><i class="fa fa-minus"></i></button>',
      '<span id="img-lightbox-zoom-label">100%</span>',
      '<button id="lb-btn-zoom-in"  title="Phóng to"><i class="fa fa-plus"></i></button>',
      "</div>",
      "</div>",
    ].join("");
    $("body").append(html);
    bindLightboxEvents();
  }

  function applyTransform(animated) {
    var $img = $("#img-lightbox-img");
    if (animated) {
      $img.css("transition", "transform 0.2s ease");
    } else {
      $img.css("transition", "none");
    }
    $img.css(
      "transform",
      "scale(" +
        zoomLevel +
        ") translate(" +
        panX / zoomLevel +
        "px, " +
        panY / zoomLevel +
        "px)",
    );
    $("#img-lightbox-zoom-label").text(Math.round(zoomLevel * 100) + "%");
  }

  function resetZoom(animated) {
    zoomLevel = 1;
    panX = 0;
    panY = 0;
    applyTransform(animated !== false);
  }

  function clampPan() {
    if (zoomLevel <= 1) {
      panX = 0;
      panY = 0;
      return;
    }
    var $img = $("#img-lightbox-img");
    var imgW = $img.width() * zoomLevel;
    var imgH = $img.height() * zoomLevel;
    var winW = window.innerWidth;
    var winH = window.innerHeight;
    var maxPanX = Math.max(0, (imgW - winW) / 2);
    var maxPanY = Math.max(0, (imgH - winH) / 2);
    panX = Math.max(-maxPanX, Math.min(maxPanX, panX));
    panY = Math.max(-maxPanY, Math.min(maxPanY, panY));
  }

  function openLightbox(src) {
    buildLightbox();
    $("#img-lightbox-img").attr("src", src);
    resetZoom(false);
    $("#img-lightbox-overlay").addClass("active");
    $("body").css({ overflow: "hidden" });
  }

  function closeLightbox() {
    $("#img-lightbox-overlay").removeClass("active");
    $("body").css({ overflow: "" });
    resetZoom(false);
  }

  function bindLightboxEvents() {
    // Close
    $("#img-lightbox-close").on("click", closeLightbox);
    $("#img-lightbox-overlay").on("click", function (e) {
      if (e.target === this) closeLightbox();
    });

    $(document).on("keydown.lightbox", function (e) {
      if (!$("#img-lightbox-overlay").hasClass("active")) return;
      if (e.key === "Escape") closeLightbox();
      if (e.key === "+" || e.key === "=") {
        zoomLevel = Math.min(ZOOM_MAX, zoomLevel + ZOOM_STEP);
        clampPan();
        applyTransform(true);
      }
      if (e.key === "-") {
        zoomLevel = Math.max(ZOOM_MIN, zoomLevel - ZOOM_STEP);
        clampPan();
        applyTransform(true);
      }
      if (e.key === "0") resetZoom(true);
    });

    $("#lb-btn-zoom-in").on("click", function () {
      zoomLevel = Math.min(ZOOM_MAX, zoomLevel + ZOOM_STEP);
      clampPan();
      applyTransform(true);
    });
    $("#lb-btn-zoom-out").on("click", function () {
      zoomLevel = Math.max(ZOOM_MIN, zoomLevel - ZOOM_STEP);
      clampPan();
      applyTransform(true);
    });

    document.getElementById("img-lightbox-overlay").addEventListener(
      "wheel",
      function (e) {
        if (!$("#img-lightbox-overlay").hasClass("active")) return;
        e.preventDefault();
        var delta = e.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP;
        zoomLevel = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, zoomLevel + delta));
        clampPan();
        applyTransform(false);
      },
      { passive: false },
    );

    var $wrap = $("#img-lightbox-wrap");
    $wrap.on("mousedown", function (e) {
      if (zoomLevel <= 1) return;
      e.preventDefault();
      isDragging = true;
      dragStartX = e.clientX;
      dragStartY = e.clientY;
      panStartX = panX;
      panStartY = panY;
      $wrap.addClass("dragging");
    });
    $(document).on("mousemove.lightbox", function (e) {
      if (!isDragging) return;
      panX = panStartX + (e.clientX - dragStartX);
      panY = panStartY + (e.clientY - dragStartY);
      clampPan();
      applyTransform(false);
    });
    $(document).on("mouseup.lightbox mouseleave.lightbox", function () {
      if (isDragging) {
        isDragging = false;
        $("#img-lightbox-wrap").removeClass("dragging");
      }
    });

    var touchStartX = 0,
      touchStartY = 0;
    var touchPanStartX = 0,
      touchPanStartY = 0;
    var isTouchDragging = false;

    document.getElementById("img-lightbox-overlay").addEventListener(
      "touchstart",
      function (e) {
        if (e.touches.length === 2) {
          lastPinchDist = Math.hypot(
            e.touches[1].clientX - e.touches[0].clientX,
            e.touches[1].clientY - e.touches[0].clientY,
          );
          isTouchDragging = false;
        } else if (e.touches.length === 1 && zoomLevel > 1) {
          isTouchDragging = true;
          touchStartX = e.touches[0].clientX;
          touchStartY = e.touches[0].clientY;
          touchPanStartX = panX;
          touchPanStartY = panY;
        }
      },
      { passive: true },
    );

    document.getElementById("img-lightbox-overlay").addEventListener(
      "touchmove",
      function (e) {
        if (e.touches.length === 2) {
          var dist = Math.hypot(
            e.touches[1].clientX - e.touches[0].clientX,
            e.touches[1].clientY - e.touches[0].clientY,
          );
          if (lastPinchDist > 0) {
            var ratio = dist / lastPinchDist;
            zoomLevel = Math.min(
              ZOOM_MAX,
              Math.max(ZOOM_MIN, zoomLevel * ratio),
            );
            clampPan();
            applyTransform(false);
          }
          lastPinchDist = dist;
          e.preventDefault();
        } else if (e.touches.length === 1 && isTouchDragging && zoomLevel > 1) {
          panX = touchPanStartX + (e.touches[0].clientX - touchStartX);
          panY = touchPanStartY + (e.touches[0].clientY - touchStartY);
          clampPan();
          applyTransform(false);
          e.preventDefault();
        }
      },
      { passive: false },
    );

    document.getElementById("img-lightbox-overlay").addEventListener(
      "touchend",
      function () {
        lastPinchDist = 0;
        isTouchDragging = false;
      },
      { passive: true },
    );
  }

  $(document).on("click", ".pd-main-img-wrap img", function () {
    openLightbox($(this).attr("src"));
  });
})();
