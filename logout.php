<?php
require __DIR__ . '/db.php';

// Check who is logged in before destroying session
$is_admin = isset($_SESSION["admin_id"]);
$is_vendor = isset($_SESSION["vendor_id"]);
$is_user = isset($_SESSION["user_id"]);

session_regenerate_id(true);
session_unset();
session_destroy();

// Clear the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect based on who was logged in
if ($is_admin) {
    header("Location: admin_login.php");
} elseif ($is_vendor) {
    header("Location: login.php");
} else {
    header("Location: index.php");
}
exit();
?>
