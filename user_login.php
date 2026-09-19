<?php
include "db.php";
include "mailer.php";

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
$error = "";
$email = "";
$accountMode = ($_GET["mode"] ?? "") === "register" ? "register" : "signin";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_post();
    $email = strtolower(trim($_POST["email"] ?? ""));
    $lastRequest = (int)($_SESSION["otp_requested_at"] ?? 0);

    if (auth_is_limited("otp_request", 5, 15)) {
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
</head>
<body class="customer-auth-body">
<main class="customer-auth-shell">
  <a href="index.php" class="customer-auth-brand" aria-label="CampusExpress home">
    <img src="logo-128.png?v=2" alt="" class="site-logo-image" width="52" height="52">
    <span>CampusExpress</span>
  </a>

  <section class="customer-auth-card" aria-labelledby="customer-login-title">
    <div class="customer-auth-icon" aria-hidden="true">&#128100;</div>
    <header class="customer-auth-heading">
      <h1 id="customer-login-title"><?php echo $accountMode === "register" ? "Create your CampusExpress account" : "Welcome to CampusExpress"; ?></h1>
      <p><?php echo $accountMode === "register" ? "Enter your email address to register with a secure one-time code." : "Enter your email address to sign in to your student account."; ?></p>
    </header>

    <?php if ($error): ?><div class="auth-alert auth-alert-error" role="alert"><?php echo e($error); ?></div><?php endif; ?>

    <form method="POST">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label class="form-label" for="email">Email address</label>
        <input id="email" type="email" name="email" class="form-control" value="<?php echo e($email); ?>" placeholder="Enter your email address" autocomplete="email" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary customer-auth-continue">Continue</button>
    </form>

    <p class="customer-auth-note">By continuing, you agree to use CampusExpress responsibly and accept the marketplace account policies.</p>
    <p class="customer-auth-security"><span aria-hidden="true">&#128274;</span> Your sign-in is protected with a one-time code.</p>
    <?php if (!is_production() && getenv("APP_SHOW_OTP") !== "0"): ?><p class="auth-helper" style="text-align:center">Local testing mode: the code appears on the next screen.</p><?php endif; ?>

    <div class="customer-auth-secondary">Selling on campus? <a href="login.php">Go to Vendor Sign In</a></div>
  </section>
  <a href="index.php" class="customer-auth-home">&larr; Return to marketplace</a>
</main>
</body>
</html>