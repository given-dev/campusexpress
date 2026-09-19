<?php
include "db.php";
if (isset($_SESSION["vendor_id"])) { header("Location: dashboard.php"); exit; }
$error = ""; $email = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_post();
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    if (auth_is_limited("vendor_login", 5, 15)) {
        $error = "Too many sign-in attempts. Please try again in 15 minutes.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {
        record_auth_failure("vendor_login");
        $error = "Enter a valid email address and password.";
    } else {
        // Try vendor login first
        $stmt = $conn->prepare("SELECT id, vendor_name, phone, password, status FROM vendors WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email); $stmt->execute(); $vendor = $stmt->get_result()->fetch_assoc();
        
        if ($vendor && password_verify($password, $vendor["password"])) {
            // Vendor found and password matches
            if ($vendor["status"] === "Pending") {
                $error = "Your vendor account is awaiting administrator approval.";
            } elseif ($vendor["status"] === "Rejected") {
                $error = "This vendor account is unavailable. Please contact support.";
            } elseif ($vendor["status"] === "Suspended") {
                $error = "Your vendor account has been suspended. Please contact support.";
            } else {
                clear_auth_failures("vendor_login");
                $redirect = complete_login('vendor', [
                    'vendor_id' => (int)$vendor["id"],
                    'vendor_name' => $vendor["vendor_name"],
                    'phone' => $vendor["phone"]
                ]);
                header("Location: $redirect"); exit;
            }
        } else {
            record_auth_failure("vendor_login");
            $error = "The email or password is incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png?v=2">
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vendor Login | CampusExpress</title><link rel="stylesheet" href="style.css">
</head>
<body>
<main class="auth-page-shell">
  <section class="auth-brand-panel" aria-label="Vendor portal introduction">
    <a href="index.php" class="auth-brand-logo"><img src="logo-128.png?v=2" alt="" class="site-logo-image" width="46" height="46"><span>CampusExpress</span></a>
    <div class="auth-brand-copy"><h1>Grow your campus business.</h1><p>Manage products, availability, and student orders from one simple vendor dashboard.</p></div>
    <p class="auth-brand-foot">CampusExpress Vendor Portal</p>
  </section>
  <section class="auth-form-panel">
    <div class="auth-form-wrap">
      <a href="index.php" class="auth-back">← Back to marketplace</a>
      <div class="auth-card-clean">
        <header class="auth-heading"><h2>Vendor sign in</h2><p class="text-muted">Use your vendor or admin account.</p></header>
        <?php if ($error): ?><div class="auth-alert auth-alert-error" role="alert"><?php echo e($error); ?></div><?php endif; ?>
        <form method="POST">
          <?php echo csrf_field(); ?>
          <div class="form-group"><label class="form-label" for="email">Email address</label><input id="email" type="email" name="email" class="form-control" value="<?php echo e($email); ?>" placeholder="vendor@example.com" autocomplete="email" required autofocus></div>
          <div class="form-group"><label class="form-label" for="password">Password</label><input id="password" type="password" name="password" class="form-control" placeholder="••••••••" autocomplete="current-password" required></div>
          <button type="submit" class="btn btn-primary auth-submit">Sign in to dashboard</button>
        </form>
        <div class="auth-options"><p>New vendor? <a href="register.php">Create an account</a></p><p>Shopping on campus? <a href="user_login.php">Student sign in</a></p></div>
      </div>
    </div>
  </section>
</main>
</body>
</html>