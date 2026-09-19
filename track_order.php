<?php
include "db.php";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Require user to be logged in
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Login required", "redirect" => "user_login.php"]);
    exit;
}

// Rate limit tracking requests
if (auth_is_limited("track_order", 30, 1)) {
    http_response_code(429);
    echo json_encode(["error" => "Too many requests. Please slow down."]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

// Verify CSRF token
$csrfToken = $input["csrf_token"] ?? $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
if (!hash_equals(csrf_token(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(["error" => "CSRF token required"]);
    exit;
}

$vendor_id = (int)($input["vendor_id"] ?? 0);
$menu_item_id = (int)($input["menu_item_id"] ?? 0);
$order_method = $input["order_method"] ?? "whatsapp";

if ($menu_item_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid menu item ID"]);
    exit;
}

if ($vendor_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid vendor ID"]);
    exit;
}

// Verify vendor exists and is approved
$vendor_check = $conn->prepare("SELECT id FROM vendors WHERE id = ? AND status = 'Approved'");
$vendor_check->bind_param("i", $vendor_id);
$vendor_check->execute();
if (!$vendor_check->get_result()->fetch_assoc()) {
    http_response_code(400);
    echo json_encode(["error" => "Vendor not found or not approved"]);
    exit;
}

// Verify menu item exists and belongs to vendor
$item_check = $conn->prepare("SELECT id FROM menu_items WHERE id = ? AND vendor_id = ?");
$item_check->bind_param("ii", $menu_item_id, $vendor_id);
$item_check->execute();
if (!$item_check->get_result()->fetch_assoc()) {
    http_response_code(400);
    echo json_encode(["error" => "Menu item not found or does not belong to this vendor"]);
    exit;
}

// Get customer info from session
$user_id = $_SESSION["user_id"];
$user_email = $_SESSION["user_email"] ?? null;

// Log the order
$stmt = $conn->prepare("INSERT INTO orders (user_id, vendor_id, menu_item_id, customer_email, order_method, status) VALUES (?, ?, ?, ?, ?, 'initiated')");
$stmt->bind_param("iiiss", $user_id, $vendor_id, $menu_item_id, $user_email, $order_method);
$stmt->execute();

$order_id = $stmt->insert_id;

echo json_encode(["success" => true, "order_id" => $order_id]);
?>
