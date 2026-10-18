<?php
require __DIR__ . '/db.php';
require __DIR__ . '/customer_auth.php';
$flow = $_SESSION['google_oauth'] ?? null;
unset($_SESSION['google_oauth']);
try {
    if (!google_auth_configured() || !is_array($flow) || time() - $flow['created'] > 600 || !is_string($_GET['state'] ?? null) || !hash_equals($flow['state'], $_GET['state']) || !is_string($_GET['code'] ?? null) || isset($_GET['error'])) throw new RuntimeException('Invalid OAuth callback');
    if (auth_is_limited('google_login', 10, 15)) throw new RuntimeException('Rate limited');
    $tokens = google_http_json('https://oauth2.googleapis.com/token', ['code' => $_GET['code'], 'client_id' => getenv('GOOGLE_CLIENT_ID'), 'client_secret' => getenv('GOOGLE_CLIENT_SECRET'), 'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'), 'grant_type' => 'authorization_code', 'code_verifier' => $flow['verifier']]);
    if (empty($tokens['access_token'])) throw new RuntimeException('Missing token');
    // Obtain identity directly from Google using this flow's server-exchanged access token.
    $profile = google_http_json('https://openidconnect.googleapis.com/v1/userinfo', null, $tokens['access_token']);
    if (empty($profile['sub']) || !is_string($profile['sub']) || strlen($profile['sub']) > 255 || ($profile['email_verified'] ?? false) !== true || !filter_var($profile['email'] ?? '', FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Unverified identity');
    $email = strtolower($profile['email']); $sub = $profile['sub'];
    $query = $conn->prepare('SELECT id, email, google_sub FROM users WHERE google_sub = ? LIMIT 1');
    $query->bind_param('s', $sub); $query->execute(); $user = $query->get_result()->fetch_assoc();
    if (!$user) {
        $query = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $query->bind_param('s', $email); $query->execute();
        // Existing accounts must use their established login; never auto-link on email alone.
        if ($query->get_result()->fetch_assoc()) throw new RuntimeException('Existing account must use password or email verification');
        $name = mb_substr((string)($profile['name'] ?? ''), 0, 100);
        $insert = $conn->prepare('INSERT INTO users (email, full_name, google_sub) VALUES (?, ?, ?)');
        $insert->bind_param('sss', $email, $name, $sub); $insert->execute();
        $user = ['id' => $conn->insert_id, 'email' => $email];
    }
    clear_auth_failures('google_login'); finish_customer_login($user);
} catch (Throwable $exception) {
    error_log('Google sign-in failed: ' . get_class($exception));
    record_auth_failure('google_login');
    header('Location: user_login.php?google_error=1'); exit;
}
