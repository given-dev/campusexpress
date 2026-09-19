<?php
include "db.php";
require_approved_vendor();
verify_post();
$id = (int)($_POST["id"] ?? 0); $vendorId = (int)$_SESSION["vendor_id"];
$stmt = $conn->prepare("SELECT image FROM menu_items WHERE id=? AND vendor_id=?"); $stmt->bind_param("ii", $id, $vendorId); $stmt->execute(); $item = $stmt->get_result()->fetch_assoc();
if ($item) { $delete = $conn->prepare("DELETE FROM menu_items WHERE id=? AND vendor_id=?"); $delete->bind_param("ii", $id, $vendorId); $delete->execute(); remove_uploaded_image($item["image"]); }
header("Location: dashboard.php"); exit;
?>
