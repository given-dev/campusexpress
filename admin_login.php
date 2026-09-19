<?php
include "db.php";
if (isset($_SESSION["admin_id"])) { header("Location: admin_dashboard.php"); exit; }
$error = ""; $email = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_post();
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    if (auth_is_limited("admin_login", 5, 15)) {
        $error = "Too many sign-in attempts. Please try again in 15 minutes.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {
        record_auth_failure("admin_login");
        $error = "Enter a valid email address and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email); $stmt->execute(); $admin = $stmt->get_result()->fetch_assoc();
        if (!$admin || !password_verify($password, $admin["password"])) {
            record_auth_failure("admin_login");
            $error = "The email or password is incorrect.";
        } else {
            clear_auth_failures("admin_login");
            $redirect = complete_login('admin', [
                'admin_id' => (int)$admin["id"],
                'admin_name' => $admin["username"]
            ]);
            header("Location: $redirect"); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png?v=2"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin Login | CampusExpress</title><link rel="stylesheet" href="style.css"></head>
<body>
<main class="auth-page-shell">
  <section class="auth-brand-panel admin" aria-label="Administration portal introduction">
    <a href="index.php" class="auth-brand-logo"><img src="logo-128.png?v=2" alt="" class="site-logo-image" width="46" height="46"><span>CampusExpress Admin</span></a>
    <div class="auth-brand-copy"><h1>Marketplace administration.</h1><p>Review vendor applications and protect the quality of the CampusExpress marketplace.</p></div>
    <p class="auth-brand-foot">Authorized administrators only</p>
  </section>
  <section class="auth-form-panel">
    <div class="auth-form-wrap">
      <a href="index.php" class="auth-back">← Back to marketplace</a>
      <div class="auth-card-clean">
        <header class="auth-heading"><h2>Administrator sign in</h2><p class="text-muted">Enter your administrator credentials.</p></header>
        <?php if ($error): ?><div class="auth-alert auth-alert-error" role="alert"><?php echo e($error); ?></div><?php endif; ?>
        <form method="POST">
          <?php echo csrf_field(); ?>
          <div class="form-group"><label class="form-label" for="email">Admin email</label><input id="email" type="email" name="email" class="form-control" value="<?php echo e($email); ?>" placeholder="admin@campusexpress.com" autocomplete="username" required autofocus></div>
          <div class="form-group"><label class="form-label" for="password">Password</label><input id="password" type="password" name="password" class="form-control" placeholder="••••••••" autocomplete="current-password" required></div>
          <button type="submit" class="btn btn-primary auth-submit" style="background:#4f46e5">Sign in securely</button>
        </form>
        <p class="auth-helper">This area is restricted. Failed attempts return a generic response to avoid exposing administrator accounts.</p>
      </div>
    </div>
  </section>
</main>
</body>
</html>