<?php
require __DIR__ . '/db.php';
require __DIR__ . '/customer_auth.php';
verify_post();
if (!google_auth_configured()) { header('Location: user_login.php?google_error=1'); exit; }
$_SESSION['google_oauth'] = ['state' => bin2hex(random_bytes(32)), 'verifier' => bin2hex(random_bytes(32)), 'created' => time()];
$flow = $_SESSION['google_oauth'];
$query = http_build_query(['client_id' => getenv('GOOGLE_CLIENT_ID'), 'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'), 'response_type' => 'code', 'scope' => 'openid email profile', 'state' => $flow['state'], 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $flow['verifier'], true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256', 'prompt' => 'select_account']);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query); exit;
