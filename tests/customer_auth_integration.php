<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../db.php';
if (is_production() || getenv('APP_SHOW_OTP') === '0') throw new RuntimeException('Use local development OTP mode only.');
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8081', '/');
if (!preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?$#D', $base)) throw new RuntimeException('Loopback HTTP server required.');
$email = 'auth-test-' . bin2hex(random_bytes(8)) . '@example.invalid';
$password = bin2hex(random_bytes(16));
$http = curl_init();
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "PASS: $message\n"; }
function request_page($path, $post = null) {
    global $base, $http;
    $ch = $http; curl_reset($ch); curl_setopt($ch, CURLOPT_URL, $base . '/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL); 
    if ($body === false) throw new RuntimeException('Local HTTP request failed');
    return ['body' => $body, 'status' => $status, 'redirect' => $redirect];
}
function token_from($page) { if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page['body'], $matches)) throw new RuntimeException('Missing CSRF token'); return $matches[1]; }
function code_from($page) { if (!preg_match('/Local testing code:<\/strong>\s*(\d{6})/', $page['body'], $matches)) throw new RuntimeException('Development OTP not available'); return $matches[1]; }
try {
    check(request_page('user_login.php', ['email' => $email, 'password' => $password])['status'] === 403, 'CSRF rejects password POST without token');
    $page = request_page('user_login.php?mode=register');
    $weak = request_page('user_login.php?mode=register', ['csrf_token' => token_from($page), 'email' => $email, 'password' => 'short', 'password_confirm' => 'short']);
    check(str_contains($weak['body'], 'Use a password between 12 and 72'), 'Weak passwords are rejected server-side');
    $mismatch = request_page('user_login.php?mode=register', ['csrf_token' => token_from($page), 'email' => $email, 'password' => $password, 'password_confirm' => 'different-password']);
    check(str_contains($mismatch['body'], 'The passwords do not match'), 'Password confirmation is checked server-side');
    $response = request_page('user_login.php?mode=register', ['csrf_token' => token_from($page), 'email' => $email, 'password' => $password, 'password_confirm' => $password]);
    if ($response['status'] !== 302) { preg_match('/id="login-error">(.*?)<\/div>/', $response['body'], $detail); echo 'Registration HTTP ' . $response['status'] . ': ' . strip_tags($detail[1] ?? 'no alert') . PHP_EOL; }
    check($response['status'] === 302 && str_contains($response['redirect'], 'verify_otp.php'), 'Registration requires email verification');
    $query = $conn->prepare('SELECT id FROM users WHERE email = ?'); $query->bind_param('s', $email); $query->execute();
    check(!$query->get_result()->fetch_assoc(), 'Unverified registration does not create an account');
    $page = request_page('verify_otp.php');
    $wrongCode = str_pad((string)(((int)code_from($page) + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    $invalid = request_page('verify_otp.php', ['csrf_token' => token_from($page), 'otp' => $wrongCode]);
    check(str_contains($invalid['body'], 'That code is invalid or expired'), 'Incorrect verification code is rejected');
    $response = request_page('verify_otp.php', ['csrf_token' => token_from($page), 'otp' => code_from($page)]);
    check($response['status'] === 302 && str_contains($response['redirect'], 'index.php'), 'Valid verification code signs in');
    $query = $conn->prepare('SELECT password_hash FROM users WHERE email = ?'); $query->bind_param('s', $email); $query->execute(); $user = $query->get_result()->fetch_assoc();
    check($user && $user['password_hash'] !== $password && password_verify($password, $user['password_hash']), 'Password is hashed and verifies');
    request_page('logout.php');
    $page = request_page('user_login.php');
    $response = request_page('user_login.php', ['csrf_token' => token_from($page), 'email' => $email, 'password' => 'invalid-password']);
    check(str_contains($response['body'], 'Email or password is incorrect'), 'Incorrect password is rejected');
    $response = request_page('user_login.php', ['csrf_token' => token_from($response), 'email' => $email, 'password' => $password]);
    check($response['status'] === 302 && str_contains($response['redirect'], 'index.php'), 'Correct email/password signs in');
    request_page('logout.php');
    $page = request_page('user_login.php?mode=register'); $newPassword = bin2hex(random_bytes(16));
    request_page('user_login.php?mode=register', ['csrf_token' => token_from($page), 'email' => $email, 'password' => $newPassword, 'password_confirm' => $newPassword]);
    $page = request_page('verify_otp.php');
    request_page('verify_otp.php', ['csrf_token' => token_from($page), 'otp' => code_from($page)]);
    $query->execute(); $user = $query->get_result()->fetch_assoc();
    check(password_verify($newPassword, $user['password_hash']) && !password_verify($password, $user['password_hash']), 'Verified recovery replaces the password');
    request_page('logout.php');
    $response = request_page('google_callback.php?state=forged&code=forged');
    check($response['status'] === 302 && str_contains($response['redirect'], 'google_error=1'), 'Forged Google callback is rejected');
    check(request_page('google_login.php')['status'] === 405, 'Google initiation requires POST');
} finally {
    $cleanup = $conn->prepare('DELETE FROM otps WHERE email = ?'); $cleanup->bind_param('s', $email); $cleanup->execute();
    $cleanup = $conn->prepare('DELETE FROM users WHERE email = ?'); $cleanup->bind_param('s', $email); $cleanup->execute();
    curl_close($http);
}
