<?php
include "db.php";

$error = "";
$success = "";
$password = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verify_post();
    
    // Rate limit registration attempts
    if (auth_is_limited("vendor_register", 3, 60)) {
        $error = "Too many registration attempts. Please try again later.";
    } else {
    $vendor_name = trim($_POST["vendor_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["business_address"]);
    $type = trim($_POST["business_type"]);
    $rawPassword = $_POST["password"] ?? "";
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    }
    
    // Phone validation
    if (empty($error) && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $error = "Enter a valid phone number.";
    }
    
    // Password strength validation
    if (strlen($rawPassword) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $rawPassword) || !preg_match('/[a-z]/', $rawPassword) || !preg_match('/[0-9]/', $rawPassword)) {
        $error = "Password must contain uppercase, lowercase, and a number.";
    } else {
        $password = password_hash($rawPassword, PASSWORD_DEFAULT);
    }

    // Check if email exists
    if (empty($error)) {
    $check = $conn->prepare("SELECT id FROM vendors WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "Email already registered";
    } else {
        $stmt = $conn->prepare("INSERT INTO vendors (vendor_name, email, phone, business_address, business_type, password, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("ssssss", $vendor_name, $email, $phone, $address, $type, $password);

        if ($stmt->execute()) {
            $success = "Registration successful! Your account is pending admin approval.";
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png?v=2">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vendor Registration | CampusExpress</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        background: linear-gradient(135deg, #fff5ed 0%, #fff 100%);
        padding: 40px 0;
    }
    .auth-card {
        width: min(92%, 500px);
        padding: 40px;
        animation: fadeIn 0.8s ease-out;
    }
    .auth-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .auth-header h2 {
        font-size: 2rem;
        color: var(--primary-dark);
        margin-bottom: 8px;
    }
    .alert {
        padding: 15px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        font-size: 0.9rem;
        font-weight: 600;
    }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
  </style>
</head>
<body>

<div class="card auth-card glass">
  <div class="auth-header">
    <a href="index.php" class="logo" style="justify-content: center; margin-bottom: 20px;">
        <img src="logo-128.png?v=2" alt="" class="site-logo-image" width="42" height="42">
        <span>CampusExpress</span>
    </a>
    <h2>Vendor Join</h2>
    <p class="text-muted">Register your business and start selling to students.</p>
  </div>

    <?php if($error): ?>
    <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if($success): ?>
    <div class="alert alert-success">
        <?php echo e($success); ?>
        <p style="margin-top: 10px;"><a href="login.php" class="btn btn-primary" style="width: 100%;">Go to Login</a></p>
    </div>
  <?php else: ?>
    <form method="POST">
    <?php echo csrf_field(); ?>
      <div class="form-group">
        <label class="form-label">Business Name</label>
        <input type="text" name="vendor_name" class="form-control" placeholder="e.g. Campus Bites" required>
      </div>

      <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 0;">
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="vendor@example.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" placeholder="2567..." required>
          </div>
      </div>

      <div class="form-group">
        <label class="form-label">Business Type</label>
        <select name="business_type" class="form-control" required>
            <option value="">Select Type</option>
            <option value="Restaurant">Restaurant / Fast Food</option>
            <option value="Groceries">Groceries</option>
            <option value="Electronics">Electronics</option>
            <option value="Stationery">Stationery</option>
            <option value="Services">Services (Laundry, etc.)</option>
            <option value="Other">Other</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Business Address / Location</label>
        <textarea name="business_address" class="form-control" rows="2" placeholder="e.g. Near West Gate, Hostel Block B" required></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px;">Register Business</button>
    </form>
  <?php endif; ?>

  <p style="margin-top: 25px; text-align: center; font-size: 0.9rem;">
    Already have an account? <a href="login.php" style="color: var(--primary); font-weight: 700;">Login here</a>
  </p>
</div>

</body>
</html>