<?php session_start(); include 'config/db.php'; ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechMate - Hệ thống bán đồ công nghệ hàng đầu Việt Nam</title>
    <meta name="description" content="TechMate - Hệ thống bán đồ công nghệ chính hãng. Giá tốt, giao nhanh, bảo hành uy tín toàn quốc.">
    <!-- CSRF Meta Tag -->
    <?php include_once 'includes/security.php'; ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">

    <!-- Preconnect CDNs để giảm latency -->
    <link rel="preconnect" href="https://cdn.tgdd.vn">
    <link rel="preconnect" href="https://cdnv2.tgdd.vn">
    <link rel="preconnect" href="https://cdn2.cellphones.com.vn">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://code.jquery.com">

    <!-- CSS Critical (đồng bộ) -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <!-- Mobile Responsive -->
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo filemtime('assets/css/mobile.css'); ?>">

    <!-- CSS Non-critical (async) -->
    <link rel="preload" href="assets/css/index_extra.css?v=<?php echo filemtime('assets/css/index_extra.css'); ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="assets/css/index_extra.css"></noscript>

    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>

    <!-- jQuery defer -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
</head>

<body class="has-scroll-top" data-user-id="<?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0 ?>">

    <?php require_once "includes/navbar.php"; ?>

    <main>

    <div class="container hero-section">
        <ul class="sidebar-menu">
            <?php
   
    $menu_data = [
        1 => [ 
            'icon' => 'fa-mobile-screen-button',
            'cols' => [
                [
                    'title' => 'Hãng sản xuất',
                    'type' => 'brand',
                    'items' => ['iPhone', 'Samsung', 'Xiaomi', 'OPPO', 'Realme', 'Vivo']
                ],
                [
                    'title' => 'Mức giá',
                    'type' => 'search',
                    'items' => [
                        'Dưới 2 triệu' => 'duoi-2-trieu',
                        'Từ 2 - 4 triệu' => '2-4-trieu',
                        'Từ 4 - 7 triệu' => '4-7-trieu',
                        'Từ 7 - 13 triệu' => '7-13-trieu',
                        'Trên 13 triệu' => 'tren-13-trieu'
                    ]
                ],
                [
                    'title' => 'Phụ kiện điện thoại',
                    'type' => 'product',
                    'items' => [
                        'Phụ kiện ' => 'search.php?cat_id=20',
                        'Cáp sạc nhanh Type-C ' => '92',
                        'Củ sạc nhanh 4 cổng USB' => '91',
                        'Sạc dự phòng 25000mAh' => '90',
                        'Sạc dự phòng 10000mAh' => '89'
                    ]
                ]
            ]
        ],
        3 => [
            'icon' => 'fa-tablet-screen-button',
            'cols' => [
                [
                    'title' => 'Thương hiệu',
                    'type' => 'brand',
                    'items' => ['iPad', 'Samsung Tab', 'Xiaomi Pad', 'Lenovo Tab', 'OPPO Pad']
                ],
                [
                    'title' => 'Dòng iPad',
                    'type' => 'search',
                    'items' => ['iPad Pro' => 'ipad pro', 'iPad Air' => 'ipad air', 'iPad Gen 9/10' => 'ipad gen', 'iPad Mini' => 'ipad mini']
                ],
                [
                    'title' => 'Nhu cầu',
                    'type' => 'search',
                    'items' => ['Chơi game' => 'tablet choi game', 'Văn phòng & Học tập' => 'tablet van phong', 'Thiết kế đồ họa' => 'tablet thiet ke']
                ]
            ]
        ],
        17 => [ 
            'icon' => 'fa-camera',
            'cols' => [
                [
                    'title' => 'Thương hiệu',
                    'type' => 'brand',
                    'items' => ['Canon', 'Sony', 'Nikon', 'Fujifilm']
                ],
                [
                    'title' => 'Loại máy ảnh',
                    'type' => 'search',
                    'items' => ['DSLR' => 'dslr', 'Mirrorless' => 'mirrorless', 'Compact' => 'compact', 'Action Cam' => 'action cam']
                ],
                [
                    'title' => 'Phụ kiện',
                    'type' => 'search',
                    'items' => ['Ống kính (Lens)' => 'lens', 'Chân máy (Tripod)' => 'tripod', 'Balo - Túi' => 'tui may anh', 'Thẻ nhớ' => 'the nho']
                ]
            ]
        ],
        10 => [ 
            'icon' => 'fa-tv',
            'cols' => [
                [
                    'title' => 'Hãng Tivi',
                    'type' => 'brand',
                    'items' => ['Samsung', 'Sony', 'LG', 'Xiaomi', 'Casper']
                ],
                [
                    'title' => 'Độ phân giải',
                    'type' => 'search',
                    'items' => ['Tivi 4K' => 'tivi 4k', 'Tivi 8K' => 'tivi 8k', 'Tivi OLED' => 'tivi oled', 'Tivi QLED' => 'tivi qled']
                ],
                [
                    'title' => 'Kích thước',
                    'type' => 'search',
                    'items' => ['32 - 43 inch' => 'tivi 32 inch', '50 - 55 inch' => 'tivi 50 inch', 'Trên 65 inch' => 'tivi 65 inch']
                ]
            ]
        ],
        7 => [ 
            'icon' => 'fa-desktop',
            'cols' => [
                [
                    'title' => 'Loại PC',
                    'type' => 'search',
                    'items' => ['PC Gaming' => 'pc gaming', 'PC Văn phòng' => 'pc van phong', 'Workstation' => 'pc workstation', 'All-in-One' => 'pc all in one']
                ],
                [
                    'title' => 'Màn hình PC',
                    'type' => 'search',
                    'items' => ['Asus' => 'man hinh asus', 'Dell' => 'man hinh dell', 'LG' => 'man hinh lg', 'Samsung' => 'man hinh samsung', '144Hz+' => 'man hinh 144hz']
                ],
                [
                    'title' => 'Phụ kiện PC',
                    'type' => 'search',
                    'items' => ['Chuột' => 'chuot', 'Bàn phím' => 'ban phim', 'Ghế Gaming' => 'ghe gaming']
                ]
            ]
        ],
        2 => [ 
            'icon' => 'fa-laptop',
            'cols' => [
                [
                    'title' => 'Thương hiệu',
                    'type' => 'brand',
                    'items' => ['MacBook', 'Asus', 'Dell', 'HP', 'Lenovo', 'MSI']
                ],
                [
                    'title' => 'Nhu cầu',
                    'type' => 'search',
                    'items' => ['Gaming' => 'laptop gaming', 'Văn phòng' => 'laptop van phong', 'Đồ họa' => 'laptop do hoa', 'Cảm ứng' => 'laptop cam ung']
                ],
                [
                    'title' => 'Dòng Mac',
                    'type' => 'search',
                    'items' => ['MacBook Air' => 'macbook air', 'MacBook Pro' => 'macbook pro', 'iMac' => 'imac', 'Mac Mini' => 'mac mini']
                ]
            ]
        ],
        15 => [ 
            'icon' => 'fa-headphones',
            'cols' => [
                [
                    'title' => 'Loại tai nghe',
                    'type' => 'search',
                    'items' => ['Bluetooth' => 'tai nghe bluetooth', 'Chụp tai' => 'tai nghe chup tai', 'Gaming' => 'tai nghe gaming', 'Có dây' => 'tai nghe co day']
                ],
                [
                    'title' => 'Loại loa',
                    'type' => 'search',
                    'items' => ['Bluetooth' => 'loa bluetooth', 'Soundbar' => 'loa soundbar', 'Loa kéo' => 'loa keo', 'Vi tính' => 'loa vi tinh']
                ],
                [
                    'title' => 'Thương hiệu',
                    'type' => 'brand',
                    'items' => ['JBL', 'Sony', 'Marshall', 'Apple AirPods', 'Samsung Buds']
                ]
            ]
        ],
        13 => [ 
            'icon' => 'fa-microchip',
            'cols' => [
                [
                    'title' => 'Linh kiện chính',
                    'type' => 'search',
                    'items' => ['CPU' => 'cpu', 'Mainboard' => 'mainboard', 'RAM' => 'ram', 'VGA' => 'vga']
                ],
                [
                    'title' => 'Lưu trữ & Nguồn',
                    'type' => 'search',
                    'items' => ['SSD' => 'ssd', 'HDD' => 'hdd', 'Nguồn (PSU)' => 'nguon may tinh', 'Vỏ Case' => 'vo case']
                ],
                [
                    'title' => 'Tản nhiệt',
                    'type' => 'search',
                    'items' => ['Tản khí' => 'tan nhiet khi', 'Tản nước' => 'tan nhiet nuoc', 'Quạt (Fan)' => 'quat tan nhiet']
                ]
            ]
        ],
        5 => [ 
            'icon' => 'fa-clock',
            'cols' => [
                [
                    'title' => 'Smartwatch',
                    'type' => 'search',
                    'items' => ['Apple Watch' => 'apple watch', 'Samsung Watch' => 'samsung watch', 'Garmin' => 'garmin', 'Xiaomi' => 'xiaomi watch']
                ],
                [
                    'title' => 'Thời trang',
                    'type' => 'search',
                    'items' => ['Casio' => 'casio', 'Daniel Wellington' => 'daniel wellington', 'Orient' => 'orient', 'Citizen' => 'citizen']
                ],
                [
                    'title' => 'Đối tượng',
                    'type' => 'search',
                    'items' => ['Nam' => 'dong ho nam', 'Nữ' => 'dong ho nu', 'Đôi' => 'dong ho doi']
                ]
            ]
        ]
    ];

    // LẤY DANH MỤC TỪ DATABASE ĐỂ HIỂN THỊ
    $sql_cat = "SELECT * FROM categories WHERE id != 20 ORDER BY id ASC";
    $res_cat = $conn->query($sql_cat);

    if ($res_cat && $res_cat->num_rows > 0) {
        while ($cat = $res_cat->fetch_assoc()) {
            $cid = $cat['id'];
            $config = $menu_data[$cid] ?? null;
            $icon = $config['icon'] ?? 'fa-folder';
            ?>

            <li>
                <a href="search.php?cat_id=<?= $cid ?>">
                    <span class="sidebar-icon-flex">
                        <i class="fa-solid <?= $icon ?> sidebar-icon-width"></i>
                        <?= $cat['name'] ?>
                    </span>
                    <?php if ($config): ?>
                    <i class="fa-solid fa-chevron-right"></i>
                    <?php endif; ?>
                </a>

                <?php if ($config && !empty($config['cols'])): ?>
                <div class="sub-menu">
                    <?php foreach ($config['cols'] as $col): ?>
                    <div class="sub-column">
                        <h4><?= $col['title'] ?></h4>
                        <?php 
                        foreach ($col['items'] as $label => $val): 
                            if ($col['type'] == 'brand') {
                                $brandName = is_numeric($label) ? $val : $val; 
                                $href = "search.php?cat_id=$cid&brand=" . urlencode($brandName);
                                $text = $brandName;
                            } elseif ($col['type'] == 'product') {
                                $text = $label;
                                if (strpos($val, 'search.php') === 0) {
                                    $href = $val;
                                } else {
                                    $href = "product_detail.php?id=" . urlencode($val);
                                }
                            } else {
                                $keyword = $val;
                                $text = $label;
                                $href = "search.php?cat_id=$cid&q=" . urlencode($keyword);
                            }
                        ?>
                        <a href="<?= $href ?>"><?= $text ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </li>

            <?php
        }
    }
    ?>
        </ul>
        <!-- MAIN BANNER SLIDESHOW (DB-driven) -->
        <?php
        // Tạo bảng nếu chưa tồn tại
        try {
            $conn->query("
                CREATE TABLE IF NOT EXISTS site_banners (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    section ENUM('main_banner','right_banners') NOT NULL,
                    image_url VARCHAR(500) NOT NULL,
                    alt_text VARCHAR(255) DEFAULT '',
                    sort_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $banner_res = $conn->query("SELECT * FROM site_banners WHERE section='main_banner' AND is_active=1 ORDER BY sort_order ASC");
            $main_banners_arr = [];
            if ($banner_res) while ($brow = $banner_res->fetch_assoc()) $main_banners_arr[] = $brow;
        } catch (Exception $e) {
            $main_banners_arr = [];
        }
        // Fallback nếu chưa có trong DB
        if (empty($main_banners_arr)) {
            $main_banners_arr = [
                ['image_url' => 'https://genk.mediacdn.vn/139269124445442048/2023/10/29/zfold-5-169850407521135850341-1698554159075-16985541601401247998602.jpg', 'alt_text' => 'Banner']
            ];
        }
        ?>
        <div class="main-banner hero-slider" id="hero-slider">
            <?php foreach ($main_banners_arr as $idx => $b): ?>
            <div class="hero-slide <?= $idx === 0 ? 'active' : '' ?>">
                <img src="<?= htmlspecialchars($b['image_url']) ?>"
                    alt="<?= htmlspecialchars($b['alt_text'] ?? 'Banner quảng cáo TechMate') ?>"
                    width="758" height="380"
                    <?= $idx === 0 ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"' ?>>
            </div>
            <?php endforeach; ?>
            <?php if (count($main_banners_arr) > 1): ?>
            <div class="hero-dots" id="hero-dots">
                <?php foreach ($main_banners_arr as $idx => $b): ?>
                <span class="hero-dot <?= $idx === 0 ? 'active' : '' ?>" data-idx="<?= $idx ?>"></span>
                <?php endforeach; ?>
            </div>
            <button class="hero-arrow hero-prev" id="hero-prev"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="hero-arrow hero-next" id="hero-next"><i class="fa-solid fa-chevron-right"></i></button>
            <?php endif; ?>
        </div>

        <!-- RIGHT BANNERS (DB-driven) -->
        <?php
        try {
            $rb_res = $conn->query("SELECT * FROM site_banners WHERE section='right_banners' AND is_active=1 ORDER BY sort_order ASC LIMIT 3");
            $right_banners_arr = [];
            if ($rb_res && $rb_res->num_rows > 0) {
                while ($rrow = $rb_res->fetch_assoc()) $right_banners_arr[] = $rrow;
            }
        } catch (Exception $e) {
            $right_banners_arr = [];
        }
        // Fallback
        if (empty($right_banners_arr)) {
            $right_banners_arr = [
                ['image_url' => 'https://cdn2.cellphones.com.vn/insecure/rs:fill:690:300/q:50/plain/https://dashboard.cellphones.com.vn/storage/galaxy-a17-5g-0126-RIGHT.png', 'alt_text' => 'Ads 1'],
                ['image_url' => 'https://cdn2.cellphones.com.vn/insecure/rs:fill:690:300/q:90/plain/https://dashboard.cellphones.com.vn/storage/aicopisd.png', 'alt_text' => 'Ads 2'],
                ['image_url' => 'https://cdn2.cellphones.com.vn/insecure/rs:fill:690:300/q:90/plain/https://dashboard.cellphones.com.vn/storage/ggggedfcwef.jpg', 'alt_text' => 'Ads 3'],
            ];
        }
        ?>
        <div class="right-banners">
            <?php foreach ($right_banners_arr as $idx => $rb): ?>
            <img src="<?= htmlspecialchars($rb['image_url']) ?>"
                 alt="<?= htmlspecialchars($rb['alt_text'] ?? 'Quảng cáo TechMate') ?>"
                 width="265" height="120"
                 loading="lazy">
            <?php endforeach; ?>
        </div>
    </div>

    <div class="container" id="flash-sale-section" style="display:none;">
        <div class="hot-sale-container">
            <div class="hot-header">
                <div class="hot-title-wrap">
                    <h2 id="fs-display-title">
                        <i class="fa-solid fa-bolt" style="-webkit-text-fill-color:#ff6b35; color:#ff6b35;"></i>
                        <span>HOT SALE CUỐI TUẦN</span>
                    </h2>
                </div>
                <div class="hot-timer" id="hot-timer-display">
                    <span class="text">Kết thúc sau:</span>
                    <span class="timer-block"><b id="fs-days">00</b><small>Ngày</small></span>
                    <span class="timer-block"><b id="fs-hours">00</b><small>Giờ</small></span>
                    <span class="timer-block"><b id="fs-mins">00</b><small>Phút</small></span>
                    <span class="timer-block"><b id="fs-secs">00</b><small>Giây</small></span>
                </div>
            </div>
            <div class="product-grid" id="hot-product-list"></div>
        </div>
    </div>

    <div class="container">
        <h3 class="section-title">Gợi ý cho bạn</h3>
        <div class="product-grid" id="product-list"></div>
    </div>

    <!-- chat -->
    <div id="chat-welcome-bubble" class="chat-welcome-bubble">
        <span class="close-bubble" onclick="$('#chat-welcome-bubble').fadeOut()">×</span>
        <p>👋 Bạn cần tìm điện thoại nào?<br><b id="chat-now">AI sẽ tư vấn ngay!</b></p>
    </div>

    <div id="chat-widget">
        <button id="scroll-top-btn" aria-label="Lên đầu trang">
            <i class="fa-solid fa-chevron-up"></i>
        </button>
        <div id="chat-toggle">
            <i class="fa-solid fa-comments"></i>
            <span id="main-chat-badge" class="chat-badge hidden">0</span>
        </div>
    </div>

    <div id="chat-box">
        <div class="chat-header">
            <div class="chat-tab active" data-tab="bot">
                <i class="fa-solid fa-robot"></i> AI Bot
                <span id="badge-bot" class="tab-badge"></span>
            </div>
            <div class="chat-tab" data-tab="shop">
                <i class="fa-solid fa-headset"></i> Chat Shop
                <span id="badge-shop" class="tab-badge"></span>
            </div>

            <div class="close-chat">×</div>
        </div>

        <div id="chat-content" class="chat-body"></div>

        <div class="chat-footer">
            <input type="text" id="chat-input" placeholder="Nhập tin nhắn...">
            <button id="btn-send-msg"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </div>
    </main>

    <footer>
        <?php require_once "includes/footer.php"; ?>
    </footer>
</body>

</html>