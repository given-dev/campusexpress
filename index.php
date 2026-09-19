<?php
include "db.php";

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png?v=2">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
  <title>CampusExpress | Student Marketplace</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Hero Section */
    .hero {
        padding: 70px 0 50px;
        background: linear-gradient(135deg, rgba(20, 33, 61, 0.92), rgba(15, 23, 42, 0.95)),
                    url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1600&q=80');
        background-size: cover;
        background-position: center;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 400px;
        height: 400px;
        background: rgba(255, 107, 11, 0.15);
        border-radius: 50%;
        filter: blur(60px);
    }
    .hero::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 107, 11, 0.1);
        border-radius: 50%;
        filter: blur(50px);
    }
    .hero .container {
        position: relative;
        z-index: 1;
    }
    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255, 107, 11, 0.2);
        border: 1px solid rgba(255, 107, 11, 0.4);
        color: #ffb27f;
        padding: 8px 18px;
        border-radius: 30px;
        font-size: 0.8rem;
        font-weight: 700;
        margin-bottom: 24px;
        letter-spacing: 0.5px;
    }
    .hero-badge svg {
        width: 16px;
        height: 16px;
    }
    .hero h1 {
        font-size: clamp(2rem, 6vw, 3.2rem);
        line-height: 1.15;
        margin-bottom: 16px;
        font-weight: 800;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
    .hero p {
        font-size: 1rem;
        opacity: 0.85;
        max-width: 440px;
        margin: 0 auto 28px;
        line-height: 1.6;
    }
    .hero-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }
    .hero-btn-primary {
        padding: 14px 32px;
        background: #ff6b0b;
        color: white;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1rem;
        text-decoration: none;
        transition: all 0.2s;
        box-shadow: 0 4px 15px rgba(255, 107, 11, 0.4);
    }
    .hero-btn-primary:hover {
        background: #e85d04;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 107, 11, 0.5);
    }
    .hero-btn-secondary {
        padding: 14px 32px;
        background: transparent;
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.4);
        border-radius: 10px;
        font-weight: 700;
        font-size: 1rem;
        text-decoration: none;
        transition: all 0.2s;
    }
    .hero-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.7);
    }

    @media (max-width: 768px) {
        .hero {
            padding: 50px 0 40px;
        }
        .hero h1 {
            font-size: 1.8rem;
        }
        .hero p {
            font-size: 0.9rem;
        }
        .hero-btn-primary, .hero-btn-secondary {
            padding: 12px 24px;
            font-size: 0.9rem;
            width: 100%;
            max-width: 280px;
            text-align: center;
        }
    }

    /* Search Section */
    .search-section {
        background: #ff6b0b;
        padding: 16px 0;
    }
    .search-bar {
        display: flex;
        gap: 0;
        max-width: 700px;
        margin: 0 auto;
    }
    .search-input-wrap {
        flex: 1;
        position: relative;
        display: flex;
        align-items: center;
    }
    .search-icon {
        position: absolute;
        left: 16px;
        color: #9ca3af;
        pointer-events: none;
    }
    .search-input {
        width: 100%;
        padding: 14px 16px 14px 48px;
        border: none;
        border-radius: 8px 0 0 8px;
        font-size: 0.95rem;
        background: white;
        outline: none;
    }
    .search-input::placeholder {
        color: #9ca3af;
    }
    .search-btn {
        padding: 14px 28px;
        background: #14213d;
        color: white;
        border: none;
        border-radius: 0 8px 8px 0;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .search-btn:hover {
        background: #1a2744;
    }

    .category-pills {
        display: flex;
        gap: 10px;
        margin-top: 14px;
        overflow-x: auto;
        padding-bottom: 4px;
        -webkit-overflow-scrolling: touch;
    }
    .category-pills::-webkit-scrollbar {
        display: none;
    }
    .pill {
        padding: 8px 18px;
        background: rgba(255,255,255,0.2);
        color: white;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        transition: background 0.2s;
        border: 1px solid rgba(255,255,255,0.3);
    }
    .pill:hover, .pill.active {
        background: white;
        color: #ff6b0b;
    }

    @media (max-width: 600px) {
        .search-section {
            padding: 12px 0;
        }
        .search-input {
            padding: 12px 12px 12px 42px;
            font-size: 0.9rem;
        }
        .search-btn {
            padding: 12px 18px;
            font-size: 0.9rem;
        }
        .pill {
            padding: 6px 14px;
            font-size: 0.8rem;
        }
    }

    .marketplace-topbar { background: #14213d; color: white; border-top: 3px solid #ff6b0b; }
    .marketplace-nav-inner { min-height: 66px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
    .marketplace-brand { display: inline-flex; align-items: center; gap: 11px; color: white; font-size: 1.35rem; font-weight: 800; }
    .marketplace-brand .site-logo-image { width: 46px; height: 46px; }
    .marketplace-account-actions { display: flex; align-items: center; gap: 22px; font-size: .86rem; font-weight: 700; white-space: nowrap; }
    .customer-entry-links { display: flex; align-items: center; gap: 9px; }
    .customer-entry-links a, .marketplace-text-link { color: white; }
    .customer-entry-links a:hover, .marketplace-text-link:hover { color: #ffb27f; }
    .marketplace-separator { color: #64748b; }
    .marketplace-sell-button { min-width: 120px; padding: 13px 24px; border-radius: 9px; background: #ff6b0b; color: white; text-align: center; box-shadow: 0 6px 16px rgba(255,107,11,.25); }
    .marketplace-sell-button:hover { background: #e85d04; transform: translateY(-1px); }
    .marketplace-identity { color: #e2e8f0; font-weight: 600; }

    .site-footer { background: #0f172a; color: white; margin-top: 30px; }
    .footer-main { display: grid; grid-template-columns: minmax(260px, 1.8fr) 1fr 1fr; gap: 60px; padding: 58px 0 44px; }
    .footer-logo { color: white; margin-bottom: 16px; }
    .footer-brand p { max-width: 410px; color: #94a3b8; font-size: .9rem; line-height: 1.7; }
    .footer-links { display: flex; flex-direction: column; align-items: flex-start; gap: 11px; }
    .footer-links h3 { color: white; font-size: .86rem; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 5px; }
    .footer-links a { color: #cbd5e1; font-size: .88rem; }
    .footer-links a:hover, .footer-links a:focus { color: white; }
    .footer-bottom { display: flex; justify-content: space-between; align-items: center; gap: 20px; padding: 20px 0; border-top: 1px solid #273449; color: #94a3b8; font-size: .78rem; }
    .admin-access { color: #64748b; font-weight: 600; }
    .admin-access:hover, .admin-access:focus { color: #cbd5e1; }

    @media (max-width: 768px) {
        .marketplace-nav-inner { min-height: 60px; gap: 8px; }
        .marketplace-brand span { display: none; }
        .marketplace-brand .site-logo-image { width: 40px; height: 40px; }
        .marketplace-account-actions { gap: 8px; font-size: .72rem; }
        .customer-entry-links { gap: 5px; }
        .marketplace-sell-button { min-width: auto; padding: 10px 12px; border-radius: 7px; }
        .marketplace-identity { display: none; }
        .footer-main { grid-template-columns: 1fr 1fr; gap: 34px 24px; padding: 42px 0 32px; }
        .footer-brand { grid-column: 1 / -1; min-width: 0; }
        .footer-brand p { max-width: 100%; }
        .footer-logo { width: auto; }
        .footer-bottom { align-items: flex-start; }
        .hero { padding: 80px 0 100px; }
        .food-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .food-card-title { font-size: 0.85rem; }
        .food-card-price { font-size: 0.95rem; }
    }

    /* Jumia-style Food Cards */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 0 4px;
    }
    .section-header h2 {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--text-main);
        margin: 0;
    }
    .see-all-btn {
        color: #ff6b0b;
        font-weight: 700;
        font-size: 0.9rem;
        text-decoration: none;
    }
    .see-all-btn:hover { text-decoration: underline; }

    .food-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 12px;
    }

    .food-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex;
        flex-direction: column;
        border: 1px solid #f0f0f0;
    }
    .food-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }

    .food-card-img-wrap {
        position: relative;
        width: 100%;
        padding-top: 100%;
        background: #f8f9fa;
        overflow: hidden;
    }
    .food-card-img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s;
    }
    .food-card:hover .food-card-img {
        transform: scale(1.05);
    }

    .sold-out-badge {
        position: absolute;
        top: 8px;
        left: 8px;
        background: #dc3545;
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .food-card-body {
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex: 1;
    }

    .food-card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: #1a1a2e;
        margin: 0;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .food-card-vendor {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 0.75rem;
        color: #6c757d;
        font-weight: 500;
    }
    .food-card-vendor svg {
        flex-shrink: 0;
        color: #ff6b0b;
    }

    .food-card-rating {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .stars {
        color: #ffc107;
        font-size: 0.75rem;
        letter-spacing: 1px;
    }
    .rating-count {
        font-size: 0.7rem;
        color: #6c757d;
    }

    .food-card-price {
        font-size: 1.05rem;
        font-weight: 800;
        color: #ff6b0b;
        margin-top: auto;
    }

    .order-badge {
        display: inline-block;
        background: #25D366;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        text-align: center;
        margin-top: 4px;
    }

    .empty-state {
        grid-column: 1/-1;
        text-align: center;
        padding: 80px 20px;
    }
    .empty-icon {
        font-size: 4rem;
        display: block;
        margin-bottom: 16px;
    }
    .empty-state h3 {
        margin-bottom: 8px;
    }
    .empty-state p {
        color: #6c757d;
        margin-bottom: 20px;
    }  </style>
</head>
<body>

  <nav class="marketplace-topbar" aria-label="Main navigation">
    <div class="container marketplace-nav-inner">
      <a href="index.php" class="marketplace-brand" aria-label="CampusExpress home">
        <img src="logo-128.png?v=2" alt="" class="site-logo-image" width="46" height="46">
        <span>CampusExpress</span>
      </a>

      <div class="marketplace-account-actions">
        <?php if(isset($_SESSION['user_id'])): ?>
          <span class="marketplace-identity"><?php echo e($_SESSION['user_email']); ?></span>
          <a href="logout.php" class="marketplace-text-link">Logout</a>
        <?php elseif(isset($_SESSION['vendor_id'])): ?>
          <a href="dashboard.php" class="marketplace-text-link">Vendor Dashboard</a>
          <span class="marketplace-separator">|</span>
          <a href="logout.php" class="marketplace-text-link">Logout</a>
        <?php else: ?>
          <div class="customer-entry-links">
            <a href="user_login.php">Sign in</a>
            <span class="marketplace-separator">|</span>
            <a href="user_login.php?mode=register">Registration</a>
          </div>
          <a href="register.php" class="marketplace-sell-button">SELL</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <header class="hero">
    <div class="container">
        <span class="hero-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
            </svg>
            UNIVERSITY MARKETPLACE
        </span>
        <h1>Campus Favorites, Delivered Daily</h1>
        <p>The smartest way to find the best campus vendors. Fresh meals, stationery, and essentials at your fingertips.</p>
        <div class="hero-buttons">
            <a href="#marketplace" class="hero-btn-primary">Browse Market</a>
            <a href="register.php" class="hero-btn-secondary">Become a Vendor</a>
        </div>
    </div>
  </header>

  <section class="search-section" id="marketplace">
    <div class="container">
      <form class="search-bar" method="GET">
        <div class="search-input-wrap">
          <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
          <input type="text" name="search" class="search-input" placeholder="Search products, brands and categories" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="search-btn">Search</button>
      </form>

      <div class="category-pills">
        <a href="index.php" class="pill <?php echo empty($category) ? 'active' : ''; ?>">All</a>
        <a href="index.php?category=Food" class="pill <?php echo $category == 'Food' ? 'active' : ''; ?>">Food</a>
        <a href="index.php?category=Drinks" class="pill <?php echo $category == 'Drinks' ? 'active' : ''; ?>">Drinks</a>
        <a href="index.php?category=Snacks" class="pill <?php echo $category == 'Snacks' ? 'active' : ''; ?>">Snacks</a>
        <a href="index.php?category=Groceries" class="pill <?php echo $category == 'Groceries' ? 'active' : ''; ?>">Groceries</a>
        <a href="index.php?category=Stationery" class="pill <?php echo $category == 'Stationery' ? 'active' : ''; ?>">Stationery</a>
        <?php if ($available == '1'): ?>
          <a href="index.php?category=<?php echo e($category); ?>" class="pill">✕ Clear Filter</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <main class="container" style="padding: 40px 0 60px;">
    <div class="section-header">
        <h2>Flash Sales</h2>
        <a href="#marketplace" class="see-all-btn">See All →</a>
    </div>

    <div class="food-grid">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $whatsappMsg = urlencode("Hello " . $row['vendor_name'] . ", I want to order " . $row['food_name'] . " (" . $row['price'] . ") from CampusExpress.");
            $waLink = "https://wa.me/" . preg_replace('/[^0-9]/', '', $row['phone']) . "?text=" . $whatsappMsg;
          ?>
          <a href="<?php echo isset($_SESSION['user_id']) ? e($waLink) : 'user_login.php'; ?>" 
             class="food-card" 
             <?php echo isset($_SESSION['user_id']) ? 'target="_blank"' : ''; ?>
             id="item-<?php echo (int)$row['id']; ?>"
             data-vendor-id="<?php echo (int)$row['vendor_id']; ?>"
             data-item-id="<?php echo (int)$row['id']; ?>"
             onclick="trackOrder(this, event)">
            
            <div class="food-card-img-wrap">
              <img src="<?php echo htmlspecialchars($row['image']); ?>" 
                   alt="<?php echo htmlspecialchars($row['food_name']); ?>" 
                   class="food-card-img"
                   loading="lazy">
              <?php if ($row['status'] !== 'Available'): ?>
                <span class="sold-out-badge">Sold Out</span>
              <?php endif; ?>
            </div>

            <div class="food-card-body">
              <h3 class="food-card-title"><?php echo htmlspecialchars($row['food_name']); ?></h3>
              
              <div class="food-card-vendor">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                  <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <?php echo htmlspecialchars($row['vendor_name']); ?>
              </div>

              <div class="food-card-rating">
                <span class="stars">★★★★★</span>
                <span class="rating-count">(<?php echo rand(4, 20); ?>)</span>
              </div>

              <div class="food-card-price">
                UGX <?php echo number_format((float)str_replace(['UGX', ','], '', $row['price'])); ?>
              </div>

              <?php if ($row['status'] === 'Available' && isset($_SESSION['user_id'])): ?>
                <span class="order-badge">Order Now</span>
              <?php endif; ?>
            </div>
          </a>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-state">
            <span class="empty-icon">🍽️</span>
            <h3>No items found</h3>
            <p>Try adjusting your filters or search terms.</p>
            <a href="index.php" class="btn btn-primary">Reset Filters</a>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-main">
      <div class="footer-brand">
        <a href="index.php" class="logo footer-logo">
          <img src="logo-128.png?v=2" alt="" class="site-logo-image" width="42" height="42">
          <span>CampusExpress</span>
        </a>
        <p>Your trusted campus marketplace for meals, essentials, and local student businesses.</p>
      </div>
      <nav class="footer-links" aria-label="Marketplace links">
        <h3>Marketplace</h3>
        <a href="#marketplace">Browse items</a>
        <a href="user_login.php">Student sign in</a>
      </nav>
      <nav class="footer-links" aria-label="Vendor links">
        <h3>For Vendors</h3>
        <a href="login.php">Vendor sign in</a>
        <a href="register.php">Register your business</a>
      </nav>
    </div>
    <div class="container footer-bottom">
      <p>&copy; <?php echo date('Y'); ?> CampusExpress Marketplace. All rights reserved.</p>
      <!-- Admin access hidden -->
    </div>
  </footer>

  <script>
  function trackOrder(card, event) {
    const vendorId = card.dataset.vendorId;
    const itemId = card.dataset.itemId;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    if (vendorId && vendorId !== '0') {
      fetch('track_order.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          vendor_id: parseInt(vendorId),
          menu_item_id: parseInt(itemId),
          order_method: 'whatsapp',
          csrf_token: csrfToken
        })
      }).catch(err => console.log('Track error:', err));
    }
  }
  </script>

</body>
</html>