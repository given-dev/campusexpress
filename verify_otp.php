<?php
include "db.php";

if (!isset($_SESSION["pending_email"])) { header("Location: user_login.php"); exit; }
$email = $_SESSION["pending_email"];
$error = "";
$demoOtp = (!is_production() && getenv("APP_SHOW_OTP") !== "0") ? ($_SESSION["demo_otp"] ?? "") : "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_post();
    $userOtp = trim($_POST["otp"] ?? "");
    $attempts = (int)($_SESSION["otp_attempts"] ?? 0);

    if (auth_is_limited("otp_verify", 10, 15) || $attempts >= 5) {
        unset($_SESSION["pending_email"], $_SESSION["demo_otp"], $_SESSION["otp_attempts"], $_SESSION["pending_password_hash"]);
        header("Location: user_login.php");
        exit;
    }
    if (!preg_match('/^\d{6}$/', $userOtp)) {
        $error = "Enter the complete six-digit code.";
    } else {
        $_SESSION["otp_attempts"] = $attempts + 1;
        $submittedHash = otp_hash($userOtp);
        $stmt = $conn->prepare("SELECT id FROM otps WHERE email = ? AND otp_code = ? AND is_used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("ss", $email, $submittedHash);
        $stmt->execute();
        $otpRow = $stmt->get_result()->fetch_assoc();

        if (!$otpRow) {
            record_auth_failure("otp_verify");
            $remaining = max(0, 5 - $_SESSION["otp_attempts"]);
            $error = $remaining ? "That code is invalid or expired. {$remaining} attempt(s) remaining." : "Too many attempts. Request a new code.";
        } else {
            $conn->begin_transaction();
            try {
                $used = $conn->prepare("UPDATE otps SET is_used = 1 WHERE id = ? AND is_used = 0");
                $used->bind_param("i", $otpRow["id"]);
                $used->execute();
                if ($used->affected_rows !== 1) throw new RuntimeException("OTP was already used");
                $userStmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
                $userStmt->bind_param("s", $email);
                $userStmt->execute();
                $user = $userStmt->get_result()->fetch_assoc();
                if (!$user) {
                    $insert = $conn->prepare("INSERT INTO users (email) VALUES (?)");
                    $insert->bind_param("s", $email);
                    $insert->execute();
                    $user = ["id" => $conn->insert_id, "email" => $email];
                }
                if (!empty($_SESSION['pending_password_hash'])) {
                    $passwordHash = $_SESSION['pending_password_hash'];
                    $setPassword = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    $setPassword->bind_param('si', $passwordHash, $user['id']); $setPassword->execute();
                }
                $conn->commit();
                clear_auth_failures("otp_verify");
                $returnUrl = $_SESSION["login_return"] ?? "index.php";
                unset($_SESSION["pending_email"], $_SESSION["demo_otp"], $_SESSION["otp_attempts"], $_SESSION["otp_requested_at"], $_SESSION["login_return"], $_SESSION["pending_password_hash"]);
                $redirect = complete_login('user', [
                    'user_id' => (int)$user["id"],
                    'user_email' => $user["email"],
                    'return_url' => $returnUrl
                ]);
                header("Location: " . $redirect);
                exit;
            } catch (Throwable $exception) {
                $conn->rollback();
                error_log($exception->getMessage());
                $error = "We could not complete sign in. Please request a new code.";
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
  <title>Verify Your Code | CampusExpress</title>
  <link rel="stylesheet" href="style.css?v=3">
</head>
<body class="customer-auth-body">
<main class="customer-auth-shell">
  <a href="index.php" class="customer-auth-brand" aria-label="CampusExpress home">
    <img src="logo-128.png?v=2" alt="" class="site-logo-image" width="52" height="52">
    <span>CampusExpress</span>
  </a>

  <section class="customer-auth-card" aria-labelledby="verify-title">
    <div class="customer-auth-icon" aria-hidden="true">&#9993;</div>
    <header class="customer-auth-heading">
      <h1 id="verify-title">Verify your email</h1>
      <p>Enter the six-digit code generated for <strong><?php echo e($email); ?></strong>.</p>
    </header>

    <?php if ($demoOtp): ?><div class="demo-code"><strong>Local testing code:</strong> <?php echo e($demoOtp); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="auth-alert auth-alert-error" role="alert"><?php echo e($error); ?></div><?php endif; ?>

    <form method="POST">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label class="form-label" for="otp">Six-digit code</label>
        <input id="otp" type="text" name="otp" class="form-control otp-code" placeholder="000000" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary customer-auth-continue">Verify and continue</button>
    </form>

    <p class="customer-auth-security"><span aria-hidden="true">&#9201;</span> This code expires after 10 minutes.</p>
    <div class="customer-auth-secondary">Didn&rsquo;t receive a code? <a href="user_login.php">Request a new one</a></div>
  </section>
  <a href="user_login.php" class="customer-auth-home">&larr; Use a different email</a>
</main>
</body>
</html>