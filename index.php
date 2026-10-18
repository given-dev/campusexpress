<?php
include "db.php";

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$vendor = isset($_GET['vendor']) ? (int)$_GET['vendor'] : 0;
$available = isset($_GET['available']) ? trim($_GET['available']) : '';

$sql = "SELECT menu_items.*, vendors.vendor_name, vendors.phone
        FROM menu_items
        JOIN vendors ON menu_items.vendor_id = vendors.id
        WHERE vendors.status = 'Approved'";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (menu_items.food_name LIKE ? OR menu_items.description LIKE ? OR vendors.vendor_name LIKE ?)";
    $searchLike = "%" . $search . "%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "sss";
}

if ($category !== "") {
    $sql .= " AND menu_items.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($vendor > 0) {
    $sql .= " AND menu_items.vendor_id = ?";
    $params[] = $vendor;
    $types .= "i";
}

if ($available === "1") {
    $sql .= " AND menu_items.status = 'Available'";
}

$sql .= " ORDER BY menu_items.id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$items = $result->fetch_all(MYSQLI_ASSOC);
$vendors = $conn->query("SELECT vendors.id, vendors.vendor_name, vendors.business_address,
    (SELECT image FROM menu_items WHERE vendor_id = vendors.id ORDER BY id DESC LIMIT 1) AS image
    FROM vendors WHERE status = 'Approved' ORDER BY vendor_name")->fetch_all(MYSQLI_ASSOC);
$catalog = [];
foreach ($items as $item) {
    $catalog[(int)$item['id']] = ['id' => (int)$item['id'], 'vendorId' => (int)$item['vendor_id'],
        'name' => $item['food_name'], 'vendor' => $item['vendor_name'], 'phone' => preg_replace('/[^0-9]/', '', $item['phone']),
        'price' => (float)$item['price'], 'available' => $item['status'] === 'Available'];
}
function listing_image($path) {
    if (!$path) return 'assets/marketplace-placeholder.svg';
    if (preg_match('#^uploads/[a-zA-Z0-9_.-]+$#', $path) && is_file(__DIR__ . '/' . $path)) return $path;
    if (filter_var($path, FILTER_VALIDATE_URL) && parse_url($path, PHP_URL_SCHEME) === 'https') return $path;
    return 'assets/marketplace-placeholder.svg';
}
function nav_icon($name) {
    $paths = [
        'home' => '<path d="m3 10 9-7 9 7v10H3z"/><path d="M9 20v-7h6v7"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'cart' => '<path d="M3 3h2l3 12h11l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
        'account' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>'
    ];
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
<title>CampusExpress | Student Marketplace</title>
<link rel="icon" href="favicon-32.png?v=2">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="assets/marketplace.css">
</head>
<body id="home">
<header class="topbar shell">
  <a href="index.php" class="brand" aria-label="CampusExpress home"><img src="logo-128.png?v=2" alt="CampusExpress" width="42" height="42"></a>
  <div class="header-actions">
    <button class="icon-button" data-open-cart aria-label="Open cart"><?php echo nav_icon('cart'); ?><span class="cart-count" hidden>0</span></button>
    <button class="icon-button" id="menu-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="account-menu"><?php echo nav_icon('menu'); ?></button>
  </div>
  <nav id="account-menu" class="account-menu" aria-label="Account menu" hidden>
    <?php if (isset($_SESSION['user_id']) || isset($_SESSION['vendor_id'])): ?>
      <?php if (isset($_SESSION['vendor_id'])): ?><a href="dashboard.php">Vendor Dashboard</a><?php endif; ?>
      <a href="logout.php">Sign Out</a>
    <?php else: ?>
      <a href="user_login.php">Sign In</a><a href="user_login.php?mode=register">Sign Up</a>
    <?php endif; ?>
    <a href="register.php">Become a Seller</a>
  </nav>
</header>
<main class="shell">
  <button id="campus-toggle" class="delivery-row" aria-haspopup="dialog"><span aria-hidden="true">⌖</span> Deliver to: <strong id="campus-label">Select campus</strong> ▾</button>
  <section class="hero-banner" aria-labelledby="hero-title">
    <div class="hero-copy"><h1 id="hero-title">Campus essentials, delivered.</h1><p>Meals, snacks, and supplies from vendors near you.</p></div>
    <svg class="hero-art" viewBox="0 0 400 300" fill="none" aria-hidden="true">
        <circle cx="192" cy="148" r="128" fill="#fff" fill-opacity=".4"/>
        <path d="M20 236h356" stroke="#14213d" stroke-opacity=".15" stroke-width="3" stroke-linecap="round"/>
        <path d="M32 214V94h62v120M104 214V60h60v154M174 214V112h48v102" fill="#14213d" fill-opacity=".08"/>
        <path d="M117 80h12m12 0h12m-36 22h12m12 0h12M44 115h15m13 0h12m-40 22h15m13 0h12" stroke="#fff" stroke-width="7"/>
        <ellipse cx="220" cy="250" rx="130" ry="12" fill="#14213d" fill-opacity=".1"/>
        <circle cx="139" cy="228" r="29" fill="#14213d"/><circle cx="139" cy="228" r="14" fill="#e2e8f0"/>
        <circle cx="300" cy="228" r="29" fill="#14213d"/><circle cx="300" cy="228" r="14" fill="#e2e8f0"/>
        <path d="M113 218c0-29 16-49 43-49h44l24 36h36l19-53h16l24 65H113Z" fill="#ff6b0b"/>
        <path d="M242 219h-62l-19-40h-26" stroke="#14213d" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="m286 155-10-28h-20" stroke="#14213d" stroke-width="8" stroke-linecap="round"/>
        <rect x="89" y="128" width="69" height="53" rx="9" fill="#14213d"/>
        <path d="M112 141h22m-11-11v22" stroke="#ffb77d" stroke-width="5" stroke-linecap="round"/>
        <path d="m193 136-13 35 49 13-15 29" stroke="#14213d" stroke-width="19" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="m197 105-10 38 38 8 19-27" stroke="#fff" stroke-width="24" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="m234 127 20 10 17-5" stroke="#d78e66" stroke-width="11" stroke-linecap="round"/>
        <circle cx="207" cy="81" r="20" fill="#d78e66"/>
        <path d="M184 80a23 23 0 0 1 46-3h-46Z" fill="#14213d"/><path d="M207 77h29" stroke="#14213d" stroke-width="7" stroke-linecap="round"/>
        <path d="M45 179h25m-37 18h42m-22 18h19" stroke="#ff6b0b" stroke-width="5" stroke-linecap="round"/>
        <path d="m304 63 5 11 12 2-9 8 2 12-10-6-11 6 2-12-9-8 12-2 6-11Z" fill="#ff6b0b"/>
      </svg>
  </section>
  <form class="search-bar" id="search" method="GET" action="index.php" role="search">
    <label class="search-wrap"><?php echo nav_icon('search'); ?><span class="sr-only">Search products and vendors</span><input id="search-input" type="search" name="search" placeholder="What are you looking for?" value="<?php echo e($search); ?>"></label>
    <button class="orange-button" type="submit">Search</button>
  </form>
  <section class="categories" aria-label="Categories">
    <nav class="category-list" aria-label="Product categories">
    <?php foreach (['Food' => ['Meals', '🍲'], 'Drinks' => ['Drinks', '🥤'], 'Snacks' => ['Snacks', '🍿'], 'Groceries' => ['Groceries', '🛒'], 'Stationery' => ['Stationery', '📚']] as $value => [$label, $icon]): ?>
      <a class="category <?php echo $category === $value ? 'selected' : ''; ?>" href="index.php?category=<?php echo rawurlencode($value); ?>#products" <?php echo $category === $value ? 'aria-current="true"' : ''; ?>><span class="category-icon" aria-hidden="true"><?php echo $icon; ?></span><span><?php echo e($label); ?></span></a>
    <?php endforeach; ?>
    </nav>
  </section>
  <section class="vendors-section" aria-labelledby="vendors-title">
    <div class="section-heading"><h2 id="vendors-title">Nearby vendors</h2><span class="muted">Around campus</span></div>
    <div class="vendor-list">
    <?php foreach ($vendors as $shop): ?>
      <a class="vendor-card" href="index.php?vendor=<?php echo (int)$shop['id']; ?>#products">
        <img class="vendor-photo" src="<?php echo e(listing_image($shop['image'])); ?>" alt="<?php echo e($shop['vendor_name']); ?>" loading="lazy">
        <div class="vendor-body"><div class="vendor-heading"><h3><?php echo e($shop['vendor_name']); ?></h3><span class="status">Hours unconfirmed</span></div>
          <?php if (!empty($shop['business_address'])): ?><p class="muted"><?php echo e($shop['business_address']); ?></p><?php endif; ?>
          <div class="vendor-details"><span>Delivery time: Ask vendor</span><span>Delivery fee: Confirm with vendor</span></div>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if (!$vendors): ?><p class="empty-state">Vendors will appear here once approved.</p><?php endif; ?>
    </div>
  </section>
  <section id="products" aria-labelledby="products-title">
    <div class="section-heading"><h2 id="products-title">Popular products</h2><?php if ($search !== '' || $category !== '' || $vendor || $available): ?><a href="index.php#products" class="clear-link">Clear filters</a><?php endif; ?></div>
    <div class="product-grid">
    <?php foreach ($items as $item): ?>
      <article class="product-card" id="item-<?php echo (int)$item['id']; ?>">
        <img class="product-photo" src="<?php echo e(listing_image($item['image'])); ?>" alt="<?php echo e($item['food_name']); ?>" loading="lazy">
        <div class="product-body"><p class="product-vendor"><?php echo e($item['vendor_name']); ?></p><h3><?php echo e($item['food_name']); ?></h3><strong class="price">UGX <?php echo number_format((float)$item['price']); ?></strong>
        <button class="orange-button add-button" data-add="<?php echo (int)$item['id']; ?>" aria-label="Add <?php echo e($item['food_name']); ?> to cart" <?php echo $item['status'] !== 'Available' ? 'disabled' : ''; ?>><?php echo $item['status'] === 'Available' ? '+ Add' : 'Sold Out'; ?></button></div>
      </article>
    <?php endforeach; ?>
    <?php if (!$items): ?><div class="empty-state"><h3>No products found</h3><p>Try another search or category.</p><a href="index.php#products" class="clear-link">Reset filters</a></div><?php endif; ?>
    </div>
  </section>
  <footer class="page-footer">© <?php echo date('Y'); ?> CampusExpress <a href="login.php">Vendor sign in</a></footer>
</main>
<nav class="bottom-nav" aria-label="Bottom navigation">
  <a href="index.php" aria-current="page"><?php echo nav_icon('home'); ?><span>Home</span></a>
  <a href="#search" id="search-nav"><?php echo nav_icon('search'); ?><span>Search</span></a>
  <button data-open-cart><?php echo nav_icon('cart'); ?><span>Cart <span class="cart-count" hidden>0</span></span></button>
  <button id="account-nav"><?php echo nav_icon('account'); ?><span>Account</span></button>
</nav>
<dialog id="campus-dialog"><form method="dialog"><div class="dialog-heading"><h2>Delivery campus</h2><button class="icon-button" aria-label="Close campus selector">✕</button></div></form><form id="campus-form"><label for="campus-input">Enter your campus</label><input id="campus-input" maxlength="100" required placeholder="Your campus name" autocomplete="off"><p class="muted">Confirm delivery availability with your vendor.</p><button class="orange-button" type="submit">Save campus</button></form></dialog>
<dialog id="cart-dialog"><form method="dialog"><div class="dialog-heading"><h2>Your cart</h2><button class="icon-button" aria-label="Close cart">✕</button></div></form><div id="cart-items"></div><div id="cart-total"></div><p class="muted">Delivery fees are confirmed by each vendor. Orders are completed on WhatsApp.</p><div id="cart-checkout"></div></dialog>
<p id="cart-notice" role="status" class="toast" hidden></p>
<script id="catalog" type="application/json"><?php echo json_encode((object)$catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script>window.customerSignedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;</script>
<script src="assets/marketplace.js" defer></script>
</body>
</html>
