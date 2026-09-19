<?php
include "db.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: admin_login.php");
    exit();
}

$admin_name = $_SESSION["admin_name"];
$tab = $_GET['tab'] ?? 'overview';
$search = trim($_GET['q'] ?? '');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $action = $_POST['action'] ?? '';
    $vendor_id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // Vendor actions
    if (in_array($action, ['approve', 'reject', 'suspend', 'activate'], true) && $vendor_id > 0) {
        $statusMap = ['approve' => 'Approved', 'reject' => 'Rejected', 'suspend' => 'Suspended', 'activate' => 'Approved'];
        $stmt = $conn->prepare("UPDATE vendors SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $statusMap[$action], $vendor_id);
        $stmt->execute();
        header("Location: admin_dashboard.php?tab=" . e($tab) . "&updated=1");
        exit;
    }

    // Delete user
    if ($action === 'delete_user' && $user_id > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        header("Location: admin_dashboard.php?tab=users&updated=1");
        exit;
    }

    // Delete vendor
    if ($action === 'delete_vendor' && $vendor_id > 0) {
        $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ?");
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        header("Location: admin_dashboard.php?tab=vendors&updated=1");
        exit;
    }
}

// ==================== STATS ====================
$stats = [];

// Vendor stats
$r = $conn->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'Suspended' THEN 1 ELSE 0 END) as suspended
    FROM vendors")->fetch_assoc();
$stats['vendors'] = $r;

// User stats
$r = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc();
$stats['users'] = $r;

// Order stats
$r = $conn->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
    SUM(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as week,
    SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as month,
    COUNT(DISTINCT customer_email) as unique_customers
    FROM orders")->fetch_assoc();
$stats['orders'] = $r;

// Item stats
$r = $conn->query("SELECT COUNT(*) as total FROM menu_items")->fetch_assoc();
$stats['items'] = $r;

// ==================== VENDORS LIST ====================
$vendorFilter = '';
if ($tab === 'vendors' && !empty($search)) {
    $vendorFilter = "WHERE (v.vendor_name LIKE ? OR v.email LIKE ? OR v.business_type LIKE ?)";
}
$v_params = [];
$v_types = '';
if (!empty($vendorFilter)) {
    $like = "%{$search}%";
    $v_params = [$like, $like, $like];
    $v_types = "sss";
}

$vendors_sql = "SELECT v.*,
    (SELECT COUNT(*) FROM menu_items WHERE vendor_id = v.id) as item_count,
    (SELECT COUNT(DISTINCT customer_email) FROM orders WHERE vendor_id = v.id) as customer_count,
    (SELECT COUNT(*) FROM orders WHERE vendor_id = v.id) as order_count,
    (SELECT MAX(created_at) FROM orders WHERE vendor_id = v.id) as last_order_at
    FROM vendors v {$vendorFilter} ORDER BY v.id DESC";
$vendors_stmt = $conn->prepare($vendors_sql);
if (!empty($v_params)) {
    $vendors_stmt->bind_param($v_types, ...$v_params);
}
$vendors_stmt->execute();
$all_vendors = $vendors_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ==================== USERS LIST ====================
$users_sql = "SELECT u.*,
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
    (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order_at
    FROM users u";

if ($tab === 'users' && !empty($search)) {
    $users_sql .= " WHERE (u.full_name LIKE ? OR u.email LIKE ?)";
    $ulike = "%{$search}%";
    $users_stmt = $conn->prepare($users_sql);
    $users_stmt->bind_param("ss", $ulike, $ulike);
} else {
    $users_stmt = $conn->prepare($users_sql);
}
$users_stmt->execute();
$all_users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ==================== RECENT ORDERS ====================
$orders_sql = "SELECT o.*, v.vendor_name, mi.food_name
    FROM orders o
    LEFT JOIN vendors v ON o.vendor_id = v.id
    LEFT JOIN menu_items mi ON o.menu_item_id = mi.id";

if ($tab === 'orders' && !empty($search)) {
    $orders_sql .= " WHERE (v.vendor_name LIKE ? OR o.customer_email LIKE ? OR mi.food_name LIKE ?)";
    $olike = "%{$search}%";
    $orders_stmt = $conn->prepare($orders_sql);
    $orders_stmt->bind_param("sss", $olike, $olike, $olike);
} else {
    $orders_stmt = $conn->prepare($orders_sql);
}
$orders_stmt->execute();
$all_orders = $orders_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ==================== PENDING VENDORS ====================
$pending = array_filter($all_vendors, fn($v) => $v['status'] === 'Pending');

// Status filter for vendors tab
if ($tab === 'vendors') {
    $statusFilter = $_GET['status'] ?? 'all';
    if ($statusFilter !== 'all') {
        $all_vendors = array_filter($all_vendors, fn($v) => $v['status'] === $statusFilter);
    }
}

$searchUrl = !empty($search) ? "&q=" . urlencode($search) : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png?v=2">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | CampusExpress</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { background: #f1f5f9; }
    .admin-nav {
        background: #1e293b;
        color: white;
        padding: 12px 0;
        margin-bottom: 0;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    }
    .admin-nav .container { display: flex; justify-content: space-between; align-items: center; }
    .admin-nav .logo { color: white; font-size: 1.1rem; }
    .admin-nav .logo span { color: white; }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }
    .stat-card {
        padding: 24px;
        text-align: left;
        position: relative;
        overflow: hidden;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        border-radius: 0 4px 4px 0;
    }
    .stat-card.blue::before { background: #3b82f6; }
    .stat-card.green::before { background: #10b981; }
    .stat-card.orange::before { background: #f97316; }
    .stat-card.red::before { background: #ef4444; }
    .stat-card.purple::before { background: #8b5cf6; }
    .stat-card.yellow::before { background: #f59e0b; }
    .stat-card .stat-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: grid; place-items: center;
        font-size: 1.2rem;
        margin-bottom: 12px;
    }
    .stat-card.blue .stat-icon { background: #eff6ff; color: #3b82f6; }
    .stat-card.green .stat-icon { background: #ecfdf5; color: #10b981; }
    .stat-card.orange .stat-icon { background: #fff7ed; color: #f97316; }
    .stat-card.red .stat-icon { background: #fef2f2; color: #ef4444; }
    .stat-card.purple .stat-icon { background: #f5f3ff; color: #8b5cf6; }
    .stat-card.yellow .stat-icon { background: #fffbeb; color: #f59e0b; }
    .stat-card .stat-val { font-size: 2rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px; }
    .stat-card .stat-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

    /* Tabs */
    .tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 24px;
        background: white;
        padding: 6px;
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    .tab {
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-muted);
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .tab:hover { background: var(--bg-light); color: var(--text-main); }
    .tab.active { background: var(--primary); color: white; }
    .tab .count {
        background: rgba(0,0,0,0.1);
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .tab.active .count { background: rgba(255,255,255,0.25); }

    /* Search Bar */
    .search-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
    }
    .search-bar form {
        display: flex;
        gap: 12px;
        flex: 1;
    }
    .search-bar .form-control {
        min-height: 44px;
        border-radius: 12px;
    }
    .search-bar .btn { min-height: 44px; border-radius: 12px; padding: 0 24px; }

    /* Table */
    .table-wrap {
        background: white;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    .table-wrap table { width: 100%; border-collapse: collapse; }
    .table-wrap th {
        background: #f8fafc;
        padding: 14px 16px;
        text-align: left;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
        font-weight: 700;
    }
    .table-wrap td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.88rem;
        vertical-align: middle;
    }
    .table-wrap tr:last-child td { border-bottom: none; }
    .table-wrap tr:hover td { background: #fafbfc; }

    /* Badges */
    .badge-suspended { background: #fef3c7; color: #92400e; }
    .badge-available { background: #d1fae5; color: #065f46; }

    /* Action Buttons */
    .action-btns { display: flex; gap: 6px; flex-wrap: wrap; }
    .btn-sm {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: var(--transition);
    }
    .btn-approve { background: #d1fae5; color: #065f46; }
    .btn-approve:hover { background: #a7f3d0; }
    .btn-reject { background: #fee2e2; color: #991b1b; }
    .btn-reject:hover { background: #fecaca; }
    .btn-suspend { background: #fef3c7; color: #92400e; }
    .btn-suspend:hover { background: #fde68a; }
    .btn-activate { background: #dbeafe; color: #1e40af; }
    .btn-activate:hover { background: #bfdbfe; }
    .btn-delete { background: #fef2f2; color: #991b1b; }
    .btn-delete:hover { background: #fee2e2; }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }
    .empty-state .empty-icon { font-size: 3rem; margin-bottom: 16px; }

    /* Section Header */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .section-header h2 { font-size: 1.1rem; }

    /* Info Card */
    .info-row {
        display: flex;
        gap: 6px;
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    /* Quick Actions Panel */
    .quick-panel {
        background: white;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        padding: 20px;
        margin-bottom: 24px;
    }
    .quick-panel h3 { font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px; }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .tabs { gap: 0; padding: 4px; }
        .tab { padding: 8px 12px; font-size: 0.78rem; }
        .table-wrap { overflow-x: auto; }
        .table-wrap table { min-width: 700px; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- Navigation -->
<nav class="admin-nav">
    <div class="container">
        <a href="admin_dashboard.php" class="logo">
            <img src="logo-128.png?v=2" alt="" class="site-logo-image" width="40" height="40">
            <span>Admin Portal</span>
        </a>
        <div style="display: flex; gap: 20px; align-items: center;">
            <span style="font-weight: 600; font-size: 0.85rem; color: #94a3b8;">Welcome, <?php echo e($admin_name); ?></span>
            <a href="logout.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.8rem;">Logout</a>
        </div>
    </div>
</nav>

<div class="container" style="padding: 30px 0;">

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="card stat-card blue animate-fade">
            <div class="stat-icon">🏪</div>
            <div class="stat-val"><?php echo (int)$stats['vendors']['total']; ?></div>
            <div class="stat-label">Total Vendors</div>
        </div>
        <div class="card stat-card green animate-fade" style="animation-delay: 0.05s;">
            <div class="stat-icon"></div>
            <div class="stat-val"><?php echo (int)$stats['vendors']['approved']; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="card stat-card orange animate-fade" style="animation-delay: 0.1s;">
            <div class="stat-icon"></div>
            <div class="stat-val"><?php echo (int)$stats['vendors']['pending']; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="card stat-card red animate-fade" style="animation-delay: 0.15s;">
            <div class="stat-icon"></div>
            <div class="stat-val"><?php echo (int)$stats['vendors']['suspended'] + (int)$stats['vendors']['rejected']; ?></div>
            <div class="stat-label">Suspended / Rejected</div>
        </div>
        <div class="card stat-card purple animate-fade" style="animation-delay: 0.2s;">
            <div class="stat-icon"></div>
            <div class="stat-val"><?php echo (int)$stats['users']['total']; ?></div>
            <div class="stat-label">Registered Users</div>
        </div>
        <div class="card stat-card yellow animate-fade" style="animation-delay: 0.25s;">
            <div class="stat-icon"></div>
            <div class="stat-val"><?php echo number_format((int)$stats['orders']['total']); ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
    </div>

    <!-- Pending Approvals Quick Panel -->
    <?php if (!empty($pending)): ?>
    <div class="quick-panel" style="border-left: 4px solid var(--warning);">
        <h3>⏳ Pending Approvals (<?php echo count($pending); ?>)</h3>
        <div style="display: grid; gap: 12px;">
            <?php foreach ($pending as $pv): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #fffbeb; border-radius: 12px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong style="font-size: 0.95rem;"><?php echo e($pv['vendor_name']); ?></strong>
                    <div class="info-row">
                        <span><?php echo e($pv['business_type']); ?></span>
                        <span>·</span>
                        <span><?php echo e($pv['email']); ?></span>
                        <span>·</span>
                        <span>Registered <?php echo date('M d, Y', strtotime($pv['registration_date'])); ?></span>
                    </div>
                </div>
                <div class="action-btns">
                    <form method="POST" style="display:inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$pv['id']; ?>">
                        <button type="submit" name="action" value="approve" class="btn-sm btn-approve">✓ Approve</button>
                    </form>
                    <form method="POST" style="display:inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$pv['id']; ?>">
                        <button type="submit" name="action" value="reject" class="btn-sm btn-reject">✗ Reject</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <a href="admin_dashboard.php?tab=overview<?php echo $searchUrl; ?>" class="tab <?php echo $tab === 'overview' ? 'active' : ''; ?>">
            Overview
        </a>
        <a href="admin_dashboard.php?tab=vendors<?php echo $searchUrl; ?>" class="tab <?php echo $tab === 'vendors' ? 'active' : ''; ?>">
            Vendors <span class="count"><?php echo (int)$stats['vendors']['total']; ?></span>
        </a>
        <a href="admin_dashboard.php?tab=users<?php echo $searchUrl; ?>" class="tab <?php echo $tab === 'users' ? 'active' : ''; ?>">
             Users <span class="count"><?php echo (int)$stats['users']['total']; ?></span>
        </a>
        <a href="admin_dashboard.php?tab=orders<?php echo $searchUrl; ?>" class="tab <?php echo $tab === 'orders' ? 'active' : ''; ?>">
             Orders <span class="count"><?php echo number_format((int)$stats['orders']['total']); ?></span>
        </a>
    </div>

    <!-- Search Bar -->
    <div class="search-bar">
        <form method="GET">
            <input type="hidden" name="tab" value="<?php echo e($tab); ?>">
            <input type="text" name="q" class="form-control" placeholder="Search vendors, users, or orders..." value="<?php echo e($search); ?>">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if (!empty($search)): ?>
                <a href="admin_dashboard.php?tab=<?php echo e($tab); ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ==================== OVERVIEW TAB ==================== -->
    <?php if ($tab === 'overview'): ?>
        <!-- Top Vendors by Orders -->
        <div class="section-header"><h2>Top Vendors by Orders</h2></div>
        <div class="table-wrap" style="margin-bottom: 32px;">
            <table>
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Customers</th>
                        <th>Orders</th>
                        <th>Last Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $top_vendors = $all_vendors;
                    usort($top_vendors, fn($a, $b) => $b['order_count'] <=> $a['order_count']);
                    $top_vendors = array_slice($top_vendors, 0, 10);
                    if (empty($top_vendors)):
                    ?>
                    <tr><td colspan="7" class="empty-state"><div class="empty-icon">🏪</div>No vendors yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($top_vendors as $v): ?>
                    <tr>
                        <td>
                            <div>
                                <strong><?php echo e($v['vendor_name']); ?></strong>
                                <div class="info-row"><span><?php echo e($v['business_type']); ?></span></div>
                            </div>
                        </td>
                        <td><span class="badge badge-<?php echo strtolower($v['status']); ?>"><?php echo e($v['status']); ?></span></td>
                        <td><?php echo (int)$v['item_count']; ?></td>
                        <td><?php echo (int)$v['customer_count']; ?></td>
                        <td><strong><?php echo (int)$v['order_count']; ?></strong></td>
                        <td style="font-size: 0.82rem; color: var(--text-muted);"><?php echo $v['last_order_at'] ? date('M d, H:i', strtotime($v['last_order_at'])) : 'N/A'; ?></td>
                        <td>
                            <a href="admin_dashboard.php?tab=vendors&q=<?php echo e($v['vendor_name']); ?>" class="btn-sm btn-activate" style="text-decoration: none;">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Orders -->
        <div class="section-header"><h2>Recent Orders</h2></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Vendor</th>
                        <th>Item</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $recent_orders = $all_orders;
                    usort($recent_orders, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
                    $recent_orders = array_slice($recent_orders, 0, 15);
                    if (empty($recent_orders)):
                    ?>
                    <tr><td colspan="7" class="empty-state"><div class="empty-icon">📦</div>No orders yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($recent_orders as $o): ?>
                    <tr>
                        <td><strong>#<?php echo (int)$o['id']; ?></strong></td>
                        <td><?php echo e($o['vendor_name'] ?? 'N/A'); ?></td>
                        <td><?php echo e($o['food_name'] ?? 'N/A'); ?></td>
                        <td><?php echo e($o['customer_email'] ?? 'Guest'); ?></td>
                        <td>
                            <span class="badge" style="background: <?php echo $o['order_method'] === 'whatsapp' ? '#dcfce7' : '#fef3c7'; ?>; color: <?php echo $o['order_method'] === 'whatsapp' ? '#166534' : '#92400e'; ?>;">
                                <?php echo e(ucfirst($o['order_method'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background: <?php echo $o['status'] === 'completed' ? '#d1fae5' : ($o['status'] === 'cancelled' ? '#fee2e2' : '#dbeafe'); ?>; color: <?php echo $o['status'] === 'completed' ? '#065f46' : ($o['status'] === 'cancelled' ? '#991b1b' : '#1e40af'); ?>;">
                                <?php echo e(ucfirst($o['status'])); ?>
                            </span>
                        </td>
                        <td style="font-size: 0.82rem; color: var(--text-muted);"><?php echo date('M d, H:i', strtotime($o['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ==================== VENDORS TAB ==================== -->
    <?php elseif ($tab === 'vendors'): ?>
        <!-- Sub-filters -->
        <?php
        $currentStatus = $_GET['status'] ?? 'all';
        $statusCounts = [
            'all' => (int)$stats['vendors']['total'],
            'Approved' => (int)$stats['vendors']['approved'],
            'Pending' => (int)$stats['vendors']['pending'],
            'Suspended' => (int)$stats['vendors']['suspended'],
            'Rejected' => (int)$stats['vendors']['rejected'],
        ];
        ?>
        <div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
            <?php foreach ($statusCounts as $label => $count): ?>
            <a href="admin_dashboard.php?tab=vendors&status=<?php echo urlencode($label); ?><?php echo $searchUrl; ?>"
               class="btn <?php echo $currentStatus === $label ? 'btn-primary' : 'btn-secondary'; ?>"
               style="padding: 8px 16px; font-size: 0.8rem;">
                <?php echo $label === 'all' ? 'All' : $label; ?> (<?php echo $count; ?>)
            </a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Contact</th>
                        <th>Business</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Customers</th>
                        <th>Orders</th>
                        <th>Last Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_vendors)): ?>
                    <tr><td colspan="9" class="empty-state"><div class="empty-icon">🏪</div>No vendors found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($all_vendors as $v): ?>
                    <tr>
                        <td>
                            <strong><?php echo e($v['vendor_name']); ?></strong>
                            <div class="info-row"><span>Since <?php echo date('M d, Y', strtotime($v['registration_date'])); ?></span></div>
                        </td>
                        <td>
                            <div style="font-size: 0.85rem;">
                                <div><?php echo e($v['email']); ?></div>
                                <div style="color: var(--text-muted);"><?php echo e($v['phone']); ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-available" style="font-size: 0.7rem;"><?php echo e($v['business_type']); ?></span>
                            <div class="info-row" style="margin-top: 4px;"><span style="font-size: 0.8rem;"><?php echo e($v['business_address']); ?></span></div>
                        </td>
                        <td><span class="badge badge-<?php echo strtolower($v['status']); ?>"><?php echo e($v['status']); ?></span></td>
                        <td><?php echo (int)$v['item_count']; ?></td>
                        <td><strong><?php echo (int)$v['customer_count']; ?></strong></td>
                        <td><?php echo (int)$v['order_count']; ?></td>
                        <td style="font-size: 0.82rem; color: var(--text-muted);"><?php echo $v['last_order_at'] ? date('M d, H:i', strtotime($v['last_order_at'])) : 'N/A'; ?></td>
                        <td>
                            <div class="action-btns">
                                <?php if ($v['status'] === 'Pending'): ?>
                                <form method="POST" style="display:inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                    <button type="submit" name="action" value="approve" class="btn-sm btn-approve">Approve</button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                    <button type="submit" name="action" value="reject" class="btn-sm btn-reject">Reject</button>
                                </form>
                                <?php elseif ($v['status'] === 'Approved'): ?>
                                <form method="POST" style="display:inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                    <button type="submit" name="action" value="suspend" class="btn-sm btn-suspend">Suspend</button>
                                </form>
                                <?php elseif ($v['status'] === 'Suspended'): ?>
                                <form method="POST" style="display:inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                    <button type="submit" name="action" value="activate" class="btn-sm btn-activate">Re-activate</button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                    <button type="submit" name="action" value="reject" class="btn-sm btn-reject">Reject</button>
                                </form>
                                <?php elseif ($v['status'] === 'Rejected'): ?>
                                <form method="POST" style="display:inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                    <button type="submit" name="action" value="activate" class="btn-sm btn-activate">Re-approve</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Permanently delete this vendor? This cannot be undone.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                    <button type="submit" name="action" value="delete_vendor" class="btn-sm btn-delete">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ==================== USERS TAB ==================== -->
    <?php elseif ($tab === 'users'): ?>
        <div class="section-header">
            <h2>All Registered Users (<?php echo count($all_users); ?>)</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Orders Placed</th>
                        <th>Last Activity</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_users)): ?>
                    <tr><td colspan="7" class="empty-state"><div class="empty-icon">👥</div>No users registered yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($all_users as $u): ?>
                    <tr>
                        <td><strong>#<?php echo (int)$u['id']; ?></strong></td>
                        <td><?php echo e($u['full_name'] ?? 'Guest'); ?></td>
                        <td><?php echo e($u['email']); ?></td>
                        <td><strong><?php echo (int)$u['order_count']; ?></strong></td>
                        <td style="font-size: 0.82rem; color: var(--text-muted);"><?php echo $u['last_order_at'] ? date('M d, H:i', strtotime($u['last_order_at'])) : 'N/A'; ?></td>
                        <td style="font-size: 0.82rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user? Their order history will be preserved.');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                <button type="submit" name="action" value="delete_user" class="btn-sm btn-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ==================== ORDERS TAB ==================== -->
    <?php elseif ($tab === 'orders'): ?>
        <div class="section-header">
            <h2>All Orders (<?php echo count($all_orders); ?>)</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Vendor</th>
                        <th>Item</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    usort($all_orders, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
                    if (empty($all_orders)):
                    ?>
                    <tr><td colspan="7" class="empty-state"><div class="empty-icon">📦</div>No orders yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($all_orders as $o): ?>
                    <tr>
                        <td><strong>#<?php echo (int)$o['id']; ?></strong></td>
                        <td><?php echo e($o['vendor_name'] ?? 'N/A'); ?></td>
                        <td><?php echo e($o['food_name'] ?? 'N/A'); ?></td>
                        <td><?php echo e($o['customer_email'] ?? 'Guest'); ?></td>
                        <td>
                            <span class="badge" style="background: <?php echo $o['order_method'] === 'whatsapp' ? '#dcfce7' : '#fef3c7'; ?>; color: <?php echo $o['order_method'] === 'whatsapp' ? '#166534' : '#92400e'; ?>;">
                                <?php echo e(ucfirst($o['order_method'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background: <?php echo $o['status'] === 'completed' ? '#d1fae5' : ($o['status'] === 'cancelled' ? '#fee2e2' : '#dbeafe'); ?>; color: <?php echo $o['status'] === 'completed' ? '#065f46' : ($o['status'] === 'cancelled' ? '#991b1b' : '#1e40af'); ?>;">
                                <?php echo e(ucfirst($o['status'])); ?>
                            </span>
                        </td>
                        <td style="font-size: 0.82rem; color: var(--text-muted);"><?php echo date('M d, Y H:i', strtotime($o['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<footer style="margin-top: 40px; padding: 30px 0; background: white; border-top: 1px solid var(--border);">
    <div class="container" style="text-align: center; font-size: 0.85rem; color: var(--text-muted);">
        &copy; <?php echo date('Y'); ?> CampusExpress Admin Dashboard
    </div>
</footer>

</body>
</html>
