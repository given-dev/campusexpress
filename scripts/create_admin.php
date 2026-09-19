<?php
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
require dirname(__DIR__) . "/db.php";
$email = strtolower(trim(getenv("ADMIN_EMAIL") ?: ""));
$password = getenv("ADMIN_PASSWORD") ?: "";
$name = trim(getenv("ADMIN_NAME") ?: "System Admin");
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Set ADMIN_EMAIL and an ADMIN_PASSWORD of at least 12 characters.\n");
    exit(1);
}
if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{12,}$/', $password)) {
    fwrite(STDERR, "Password must have uppercase, lowercase, and a digit.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE username=VALUES(username), password=VALUES(password)");
$stmt->bind_param("sss", $name, $email, $hash);
$stmt->execute();
fwrite(STDOUT, "Administrator account created or updated.\n");
?>