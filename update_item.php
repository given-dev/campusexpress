<?php
include "db.php";
require_approved_vendor();
verify_post();
$id = (int)($_POST["id"] ?? 0); $vendorId = (int)$_SESSION["vendor_id"];
$name = trim($_POST["food_name"] ?? ""); $price = trim($_POST["price"] ?? "");
$category = trim($_POST["category"] ?? ""); $description = trim($_POST["description"] ?? ""); $status = $_POST["status"] ?? "";
$categories = ["Food", "Drinks", "Snacks", "Groceries", "Stationery"];
if ($id < 1 || $name === "" || strlen($name) > 100) { http_response_code(400); exit("Invalid input."); }
if (!is_numeric($price) || (float)$price <= 0) { http_response_code(400); exit("Invalid input."); }
if (!in_array($category, $categories, true) || !in_array($status, ["Available", "Sold Out"], true)) { http_response_code(400); exit("Invalid input."); }
if ($description === "" || strlen($description) > 500) { http_response_code(400); exit("Invalid input."); }
$find = $conn->prepare("SELECT image FROM menu_items WHERE id = ? AND vendor_id = ?"); $find->bind_param("ii", $id, $vendorId); $find->execute(); $item = $find->get_result()->fetch_assoc();
if (!$item) { http_response_code(404); exit("Item not found."); }
try { $newImage = store_uploaded_image($_FILES["image"] ?? [], false); } catch (RuntimeException $e) { exit(e($e->getMessage())); }
$image = $newImage ?: $item["image"];
$stmt = $conn->prepare("UPDATE menu_items SET food_name=?, price=?, category=?, image=?, description=?, status=? WHERE id=? AND vendor_id=?");
$stmt->bind_param("sdssssii", $name, $price, $category, $image, $description, $status, $id, $vendorId); $stmt->execute();
if ($newImage) remove_uploaded_image($item["image"]);
header("Location: dashboard.php"); exit;
?>
