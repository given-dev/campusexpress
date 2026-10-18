<?php
function finish_customer_login($user) {
    $return = $_SESSION['login_return'] ?? 'index.php';
    if (!is_string($return) || !preg_match('/^index\.php(?:#item-\d+)?$/D', $return)) $return = 'index.php';
    unset($_SESSION['login_return'], $_SESSION['pending_email'], $_SESSION['pending_password_hash'], $_SESSION['demo_otp'], $_SESSION['otp_attempts'], $_SESSION['otp_requested_at']);
    $destination = complete_login('user', ['user_id' => (int)$user['id'], 'user_email' => $user['email'], 'return_url' => $return]);
    header('Location: ' . $destination); exit;
}
function google_auth_configured() {
    $redirect = getenv('GOOGLE_REDIRECT_URI') ?: '';
    $scheme = parse_url($redirect, PHP_URL_SCHEME);
    $host = parse_url($redirect, PHP_URL_HOST);
    return getenv('GOOGLE_CLIENT_ID') && getenv('GOOGLE_CLIENT_SECRET') && filter_var($redirect, FILTER_VALIDATE_URL)
        && ($scheme === 'https' || (!is_production() && $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)));
}
function google_http_json($url, $post = null, $accessToken = null) {
    if (!function_exists('curl_init')) throw new RuntimeException('cURL is required');
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($accessToken !== null) $headers[] = 'Authorization: Bearer ' . $accessToken;
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($body === false || $status !== 200) throw new RuntimeException('Google request failed');
    $data = json_decode($body, true);
    if (!is_array($data)) throw new RuntimeException('Invalid Google response');
    return $data;
}
