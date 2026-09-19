<?php
include "db.php";
require_approved_vendor();
verify_post();
$vendorId = (int)$_SESSION["vendor_id"];
$name = trim($_POST["food_name"] ?? "");
$price = trim($_POST["price"] ?? "");
$category = trim($_POST["category"] ?? "");
$description = trim($_POST["description"] ?? "");
$status = $_POST["status"] ?? "";
$categories = ["Food", "Drinks", "Snacks", "Groceries", "Stationery"];
if ($name === "" || strlen($name) > 100) { http_response_code(400); exit("Invalid input."); }
if (!is_numeric($price) || (float)$price <= 0) { http_response_code(400); exit("Invalid input."); }
if (!in_array($category, $categories, true)) { http_response_code(400); exit("Invalid input."); }
if ($description === "" || strlen($description) > 500) { http_response_code(400); exit("Invalid input."); }
if (!in_array($status, ["Available", "Sold Out"], true)) { http_response_code(400); exit("Invalid input."); }
try { $image = store_uploaded_image($_FILES["image"] ?? [], true); } catch (RuntimeException $e) { http_response_code(400); exit("Invalid input."); }
$stmt = $conn->prepare("INSERT INTO menu_items (vendor_id, food_name, price, category, image, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isdssss", $vendorId, $name, $price, $category, $image, $description, $status);
$stmt->execute();
header("Location: dashboard.php");
exit;
?>
