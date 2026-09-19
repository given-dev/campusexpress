<?php
include "db.php";
require_approved_vendor();

$id = intval($_GET["id"]);
$vendor_id = $_SESSION["vendor_id"];
$vendor_name = $_SESSION["vendor_name"];

$stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ? AND vendor_id = ?");
$stmt->bind_param("ii", $id, $vendor_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    header("Location: dashboard.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png?v=2">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Item | CampusExpress</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { background: #f8fafc; }
    .edit-container {
        padding: 60px 0;
    }
    .edit-grid {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 40px;
        align-items: start;
    }
    .preview-box {
        position: sticky;
        top: 100px;
    }
    .preview-image {
        width: 100%;
        height: 300px;
        object-fit: cover;
        border-radius: var(--radius-lg);
        margin-bottom: 20px;
        box-shadow: var(--shadow-md);
    }
    @media (max-width: 992px) {
        .edit-grid { grid-template-columns: 1fr; }
        .preview-box { position: static; }
    }
  </style>
</head>
<body>

  <nav class="navbar glass">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
      <a href="dashboard.php" class="logo">
        <img src="logo-128.png?v=2" alt="" class="site-logo-image" width="42" height="42">
        <span>Edit Listing</span>
      </a>
      <div style="display: flex; gap: 15px; align-items: center;">
        <a href="dashboard.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.8rem;">Back to Dashboard</a>
      </div>
    </div>
  </nav>

  <div class="container edit-container">
    <div class="edit-grid">

        <!-- Preview Column -->
        <div class="preview-box animate-fade">
            <h3 style="margin-bottom: 20px;">Current Listing</h3>
            <div class="card" style="padding: 20px;">
                <img src="<?php echo htmlspecialchars($item['image']); ?>" class="preview-image">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <span class="badge <?php echo $item['status'] == 'Available' ? 'badge-approved' : 'badge-rejected'; ?>">
                        <?php echo htmlspecialchars($item['status']); ?>
                    </span>
                    <span class="badge" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($item['category']); ?></span>
                </div>
                <h2 style="margin-bottom: 10px;"><?php echo htmlspecialchars($item['food_name']); ?></h2>
                <p class="text-muted" style="margin-bottom: 15px;"><?php echo htmlspecialchars($item['description']); ?></p>
                <div class="item-price" style="font-size: 1.5rem; color: var(--primary); font-weight: 800;"><?php echo htmlspecialchars($item['price']); ?></div>
            </div>
        </div>

        <!-- Form Column -->
        <div class="form-box animate-fade" style="animation-delay: 0.1s;">
            <h3 style="margin-bottom: 20px;">Update Details</h3>
            <div class="card" style="padding: 40px;">
                <form action="update_item.php" method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">

                    <div class="form-group">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="food_name" class="form-control" value="<?php echo htmlspecialchars($item['food_name']); ?>" required>
                    </div>

                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 0;">
                        <div class="form-group">
                            <label class="form-label">Price</label>
                            <input type="text" name="price" class="form-control" value="<?php echo htmlspecialchars($item['price']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control" required>
                                <option value="Food" <?php echo $item['category'] == 'Food' ? 'selected' : ''; ?>>Food</option>
                                <option value="Drinks" <?php echo $item['category'] == 'Drinks' ? 'selected' : ''; ?>>Drinks</option>
                                <option value="Snacks" <?php echo $item['category'] == 'Snacks' ? 'selected' : ''; ?>>Snacks</option>
                                <option value="Groceries" <?php echo $item['category'] == 'Groceries' ? 'selected' : ''; ?>>Groceries</option>
                                <option value="Stationery" <?php echo $item['category'] == 'Stationery' ? 'selected' : ''; ?>>Stationery</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Change Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <p class="text-muted" style="font-size: 0.8rem; margin-top: 5px;">Leave empty to keep the current image.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Availability Status</label>
                        <select name="status" class="form-control">
                            <option value="Available" <?php echo $item['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="Sold Out" <?php echo $item['status'] == 'Sold Out' ? 'selected' : ''; ?>>Sold Out</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="flex: 2; padding: 15px;">Save Changes</button>
                        <a href="dashboard.php" class="btn btn-secondary" style="flex: 1; padding: 15px;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </div>

  <footer style="padding: 40px 0; background: white; border-top: 1px solid var(--border); margin-top: 60px;">
    <div class="container text-muted" style="text-align: center; font-size: 0.9rem;">
        &copy; <?php echo date('Y'); ?> CampusExpress Seller Editor.
    </div>
  </footer>

</body>
</html>
