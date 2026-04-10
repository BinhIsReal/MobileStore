<?php session_start(); include 'config/db.php'; ?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Mobile Store - Hệ thống bán lẻ di động</title>
    <!-- CSRF Meta Tag -->
    <?php include_once 'includes/security.php'; ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body data-user-id="<?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0 ?>">

    <?php require_once "includes/navbar.php"; ?>

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
                    'title' => 'Sản phẩm Hot',
                    'type' => 'search',
                    'items' => [
                        'iPhone 15 Pro Max' => 'iphone 15 pro max',
                        'Samsung S24 Ultra' => 'samsung s24 ultra',
                        'Redmi Note 13' => 'redmi note 13',
                        'OPPO Reno 10' => 'oppo reno 10'
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
    $sql_cat = "SELECT * FROM categories ORDER BY id ASC";
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
        <div class="main-banner">
            <img src="https://genk.mediacdn.vn/139269124445442048/2023/10/29/zfold-5-169850407521135850341-1698554159075-16985541601401247998602.jpg"
                alt="Banner">
        </div>

        <div class="right-banners">
            <img src="https://cdn2.cellphones.com.vn/insecure/rs:fill:690:300/q:50/plain/https://dashboard.cellphones.com.vn/storage/galaxy-a17-5g-0126-RIGHT.png"
                alt="Ads 1">
            <img src="https://cdn2.cellphones.com.vn/insecure/rs:fill:690:300/q:90/plain/https://dashboard.cellphones.com.vn/storage/aicopisd.png"
                alt="Ads 2">
            <img src="https://cdn2.cellphones.com.vn/insecure/rs:fill:690:300/q:90/plain/https://dashboard.cellphones.com.vn/storage/ggggedfcwef.jpg"
                alt="Ads 3">
        </div>
    </div>

    <div class="container">
        <div class="hot-sale-container">
            <div class="hot-header">
                <h2>🔥 HOT SALE CUỐI TUẦN</h2>
                <div class="hot-timer">Kết thúc sau: <b>02</b> ngày <b>12</b> giờ</div>
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
    <footer>
        <?php require_once "includes/footer.php"; ?>
    </footer>
</body>

</html>