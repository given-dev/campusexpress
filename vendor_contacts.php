<?php
include "db.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: admin_login.php");
    exit();
}

$vendor_id = (int)($_GET['id'] ?? 0);
if ($vendor_id < 1) {
    header("Location: admin_dashboard.php");
    exit();
}

// Get vendor info
$vendor_stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ?");
$vendor_stmt->bind_param("i", $vendor_id);
$vendor_stmt->execute();
$vendor = $vendor_stmt->get_result()->fetch_assoc();

if (!$vendor) {
    header("Location: admin_dashboard.php");
    exit();
}

// Get contact stats
$stats_stmt = $conn->prepare("SELECT
    COUNT(DISTINCT customer_email) as unique_customers,
    COUNT(*) as total_contacts,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_contacts,
    SUM(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as week_contacts,
    SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as month_contacts
    FROM orders WHERE vendor_id = ?");
$stats_stmt->bind_param("i", $vendor_id);
$stats_stmt->execute();
$contact_stats = $stats_stmt->get_result()->fetch_assoc();

// Get all contacts for this vendor
$contacts_stmt = $conn->prepare("SELECT o.*, mi.food_name, u.full_name
    FROM orders o
    LEFT JOIN menu_items mi ON o.menu_item_id = mi.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.vendor_id = ?
    ORDER BY o.created_at DESC");
$contacts_stmt->bind_param("i", $vendor_id);
$contacts_stmt->execute();
$contacts = $contacts_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png?v=2">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vendor Contacts | CampusExpress Admin</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { background: #f1f5f9; }
    .admin-nav {
        background: #1e293b;
        color: white;
        padding: 15px 0;
        margin-bottom: 30px;
    }
    .back-link {
        color: #94a3b8;
        text-decoration: none;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 20px;
    }
    .back-link:hover { color: white; }
    .vendor-header {
        background: white;
        border-radius: var(--radius-lg);
        padding: 30px;
        margin-bottom: 30px;
        border: 1px solid var(--border);
    }
    .vendor-header h1 {
        margin-bottom: 5px;
        font-size: 1.5rem;
    }
    .vendor-meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-top: 10px;
    }
    .vendor-meta span {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    .stat-card {
        padding: 20px;
        text-align: center;
    }
    .stat-card .val { font-size: 1.8rem; font-weight: 800; }
    .stat-card .lab { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-top: 5px; }
    .table-container {
        background: white;
        border-radius: var(--radius-lg);
        overflow: hidden;
        border: 1px solid var(--border);
    }
    table { width: 100%; border-collapse: collapse; }
    th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }
    td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }
    .method-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .method-whatsapp { background: #dcfce7; color: #166534; }
    .method-call { background: #fef3c7; color: #92400e; }
    .status-initiated { color: #2563eb; }
    .status-completed { color: #16a34a; }
    .status-cancelled { color: #dc2626; }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }
  </style>
</head>
<body>

<nav class="admin-nav">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
        <div class="logo">
            <img src="logo-128.png?v=2" alt="" class="site-logo-image" width="42" height="42">
            <span style="color: white;">Admin Portal</span>
        </div>
        <div style="display: flex; gap: 20px; align-items: center;">
            <a href="admin_dashboard.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.8rem;">Dashboard</a>
            <a href="logout.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.8rem;">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <a href="admin_dashboard.php" class="back-link">&larr; Back to Dashboard</a>

    <div class="vendor-header">
        <h1><?php echo htmlspecialchars($vendor['vendor_name']); ?></h1>
        <div class="vendor-meta">
            <span><?php echo htmlspecialchars($vendor['business_type']); ?></span>
            <span><?php echo htmlspecialchars($vendor['email']); ?></span>
            <span><?php echo htmlspecialchars($vendor['phone']); ?></span>
            <span>Registered: <?php echo date('M d, Y', strtotime($vendor['registration_date'])); ?></span>
        </div>
    </div>

    <div class="stats-row">
        <div class="card stat-card">
            <div class="val" style="color: #1d4ed8;"><?php echo number_format($contact_stats['unique_customers'] ?? 0); ?></div>
            <div class="lab">Unique Customers</div>
        </div>
        <div class="card stat-card">
            <div class="val" style="color: #ff6b0b;"><?php echo number_format($contact_stats['total_contacts'] ?? 0); ?></div>
            <div class="lab">Total Inquiries</div>
        </div>
        <div class="card stat-card">
            <div class="val" style="color: #25D366;"><?php echo number_format($contact_stats['today_contacts'] ?? 0); ?></div>
            <div class="lab">Today</div>
        </div>
        <div class="card stat-card">
            <div class="val" style="color: #4f46e5;"><?php echo number_format($contact_stats['week_contacts'] ?? 0); ?></div>
            <div class="lab">This Week</div>
        </div>
        <div class="card stat-card">
            <div class="val" style="color: #ec4899;"><?php echo number_format($contact_stats['month_contacts'] ?? 0); ?></div>
            <div class="lab">This Month</div>
        </div>
    </div>

    <h2 style="margin-bottom: 20px;">Customer Inquiries</h2>

    <div class="table-container shadow-md">
        <?php if ($contacts->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Item</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $contacts->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?php echo (int)$c['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($c['full_name'] ?? 'Guest'); ?></td>
                        <td><?php echo htmlspecialchars($c['customer_email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($c['food_name'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="method-badge method-<?php echo e($c['order_method']); ?>">
                                <?php echo e(ucfirst($c['order_method'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-<?php echo e($c['status']); ?>">
                                <?php echo e(ucfirst($c['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($c['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No customer inquiries yet for this vendor.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
