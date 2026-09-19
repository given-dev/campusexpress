<?php
// Usage: php scripts/reset_admin_password.php

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
require dirname(__DIR__) . "/db.php";

$email = readline("Admin email: ");
$newPassword = readline("New password (min 12 chars): ");

if (strlen($newPassword) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}
if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{12,}$/', $newPassword)) {
    fwrite(STDERR, "Password must have uppercase, lowercase, and a digit.\n");
    exit(1);
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $hash, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    fwrite(STDOUT, "Password updated successfully for: $email\n");
} else {
    fwrite(STDERR, "No admin found with email: $email\n");
}
?>
