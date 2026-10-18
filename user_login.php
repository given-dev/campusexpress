<?php
include "db.php";
include "mailer.php";
require_once __DIR__ . "/customer_auth.php";

$returnTarget = $_GET["return"] ?? "";
if (is_string($returnTarget) && preg_match('/^index\.php#item-\d+$/', $returnTarget)) {
    $_SESSION["login_return"] = $returnTarget;
}
if (isset($_SESSION["user_id"])) {
    $destination = $_SESSION["login_return"] ?? "index.php";
    unset($_SESSION["login_return"]);
    if (!preg_match('#^index\.php#', $destination)) $destination = "index.php";
    header("Location: " . $destination);
    exit;
}
$error = isset($_GET["google_error"]) ? "Google sign-in could not be completed. Please try again or use your password." : "";
$email = "";
$accountMode = ($_GET["mode"] ?? "") === "register" ? "register" : "signin";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_post();
    $email = is_string($_POST["email"] ?? null) ? strtolower(trim($_POST["email"])) : "";
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";
    $confirmation = is_string($_POST["password_confirm"] ?? null) ? $_POST["password_confirm"] : "";
    $lastRequest = (int)($_SESSION["otp_requested_at"] ?? 0);

    if ($accountMode === 'signin') {
        if (auth_is_limited('customer_password', 5, 15)) {
            $error = 'Too many sign-in attempts. Try again in 15 minutes.';
        } else {
            $query = $conn->prepare('SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1');
            $query->bind_param('s', $email); $query->execute(); $user = $query->get_result()->fetch_assoc();
            // Always verify a hash, including when the account does not exist.
            $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid = password_verify($password, $hash);
            if (!$user || empty($user['password_hash']) || !$valid || strlen($password) > 72) {
                record_auth_failure('customer_password');
                $error = 'Email or password is incorrect. Existing email-code users can set a password below.';
            } else {
                clear_auth_failures('customer_password');
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    $update->bind_param('si', $newHash, $user['id']); $update->execute();
                }
                finish_customer_login($user);
            }
        }
    } elseif (strlen($password) < 12 || strlen($password) > 72 || strpos($password, "\0") !== false) {
        $error = 'Use a password between 12 and 72 bytes long.';
    } elseif (!hash_equals($password, $confirmation)) {
        $error = 'The passwords do not match.';
    } elseif (auth_is_limited("otp_request", 5, 15)) {
        $error = "Too many code requests. Please try again in 15 minutes.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        record_auth_failure("otp_request");
        $error = "Enter a valid campus email address.";
    } elseif (time() - $lastRequest < 60) {
        $error = "Please wait a minute before requesting another code.";
    } else {
        $otp = (string)random_int(100000, 999999);
        $otpHash = otp_hash($otp);
        $expires = gmdate("Y-m-d H:i:s", time() + 600);
        try {
            $invalidate = $conn->prepare("UPDATE otps SET is_used = 1 WHERE email = ? AND is_used = 0");
            $invalidate->bind_param("s", $email);
            $invalidate->execute();
            $stmt = $conn->prepare("INSERT INTO otps (email, otp_code, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $otpHash, $expires);
            $stmt->execute();

            if (!deliver_otp_email($email, $otp)) {
                $invalidateNew = $conn->prepare("UPDATE otps SET is_used = 1 WHERE id = ?");
                $invalidateNew->bind_param("i", $conn->insert_id);
                $invalidateNew->execute();
                throw new RuntimeException("OTP delivery failed");
            }

            clear_auth_failures("otp_request");
            $_SESSION["pending_email"] = $email;
            $_SESSION["pending_password_hash"] = password_hash($password, PASSWORD_DEFAULT);
            $_SESSION["otp_requested_at"] = time();
            $_SESSION["otp_attempts"] = 0;
            if (!is_production() && getenv("APP_SHOW_OTP") !== "0") $_SESSION["demo_otp"] = $otp;
            else unset($_SESSION["demo_otp"]);
            header("Location: verify_otp.php");
            exit;
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            record_auth_failure("otp_request");
            $error = "We could not send a login code. Please try again later.";
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
  <title>Sign In | CampusExpress</title>
  <link rel="stylesheet" href="style.css?v=3">
  <link rel="stylesheet" href="assets/customer-login.css?v=2">
</head>
<body class="customer-auth-body reference-login">
<main class="customer-auth-shell">
  <header class="login-banner">
    <a href="index.php" class="login-banner-copy" aria-label="CampusExpress home"><span class="login-wordmark">CampusExpress<span aria-hidden="true">.</span></span><span class="login-tagline">Campus essentials,<br>right at your door.</span></a>
    <img src="logo-128.png?v=2" alt="CampusExpress logo" class="login-banner-logo" width="144" height="144">
  </header>

  <section class="customer-auth-card" aria-labelledby="customer-login-title">
    <header class="customer-auth-heading">
      <h1 id="customer-login-title"><?php echo $accountMode === "register" ? "Create Account" : "Welcome Back"; ?></h1>
      <p><?php echo $accountMode === "register" ? "Choose a password. We’ll verify your email with a one-time code." : "Sign in with your email address and password."; ?></p>
    </header>

    <?php if ($error): ?><div class="auth-alert auth-alert-error" role="alert" id="login-error"><?php echo e($error); ?></div><?php endif; ?>

    <form method="POST">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label class="form-label" for="email">Email address</label>
        <div class="login-email-wrap"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="4"/><path d="m4 7 8 6 8-6"/></svg><input id="email" type="email" name="email" class="form-control" value="<?php echo e($email); ?>" placeholder="Enter your email address" autocomplete="email" required aria-describedby="email-help<?php echo $error ? ' login-error' : ''; ?>"></div><p id="email-help" class="login-email-help"><?php echo $accountMode === "register" ? "Verify your email to create or update your password." : "Use the email address linked to your account."; ?></p>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="login-email-wrap"><input id="password" type="password" name="password" class="form-control password-input" placeholder="Enter your password" autocomplete="<?php echo $accountMode === 'register' ? 'new-password' : 'current-password'; ?>" <?php echo $accountMode === 'register' ? 'minlength="12"' : ''; ?> maxlength="72" required></div>
      </div>
      <?php if ($accountMode === 'register'): ?>
      <div class="form-group"><label class="form-label" for="password-confirm">Confirm password</label><input id="password-confirm" type="password" name="password_confirm" class="form-control password-input" placeholder="Repeat your password" autocomplete="new-password" minlength="12" maxlength="72" required><p class="login-email-help">Use at least 12 characters.</p></div>
      <?php else: ?>
      <a class="password-recovery" href="user_login.php?mode=register">Forgot password or signing in for the first time?</a>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary customer-auth-continue"><?php echo $accountMode === "register" ? "Sign Up" : "Log In"; ?></button>
    </form>

    <div class="login-divider"><span>Or continue with</span></div>
    <form method="POST" action="google_login.php" class="google-login-form">
      <?php echo csrf_field(); ?>
      <button class="google-button" type="submit" <?php echo google_auth_configured() ? '' : 'disabled aria-describedby="google-setup-note"'; ?>><span class="google-letter" aria-hidden="true">G</span> Continue with Google</button>
    </form>
    <?php if (!google_auth_configured()): ?><p id="google-setup-note" class="login-email-help">Google sign-in will be available soon.</p><?php endif; ?>
    <p class="customer-auth-note">By continuing, you agree to use CampusExpress responsibly and accept the marketplace account policies.</p>
    <p class="customer-auth-security"><span aria-hidden="true">&#128274;</span> Your account is protected with secure sign-in.</p>

    <p class="login-switch"><?php if ($accountMode === 'register'): ?>Already have an account? <a href="user_login.php">Sign In</a><?php else: ?>Don’t have an account? <a href="user_login.php?mode=register">Sign Up</a><?php endif; ?></p>
    <div class="customer-auth-secondary">Selling on campus? <a href="login.php">Go to Vendor Sign In</a></div>
  </section>
  <a href="index.php" class="customer-auth-home">&larr; Return to marketplace</a>
</main>
</body>
</html>