<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../db.php';
$columns = [];
$result = $conn->query('SHOW COLUMNS FROM users');
while ($column = $result->fetch_assoc()) $columns[] = $column['Field'];
if (!in_array('password_hash', $columns, true)) $conn->query('ALTER TABLE users ADD COLUMN password_hash varchar(255) DEFAULT NULL');
if (!in_array('google_sub', $columns, true)) $conn->query('ALTER TABLE users ADD COLUMN google_sub varchar(255) DEFAULT NULL, ADD UNIQUE KEY idx_users_google_sub (google_sub)');
echo "Customer authentication schema is ready.\n";
