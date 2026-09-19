<?php
// Simple .env loader
function load_env($path = __DIR__ . '/.env') {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}
load_env();

$host = getenv("DB_HOST") ?: "localhost";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "";
$dbname = getenv("DB_NAME") ?: "campusexpress";

function app_env() { return strtolower(getenv("APP_ENV") ?: "local"); }
function is_production() { return app_env() === "production"; }

// Force OTP display off in production
if (is_production() && getenv("APP_SHOW_OTP") !== "0") {
    error_log("SECURITY: APP_SHOW_OTP forced to 0 in production");
    putenv("APP_SHOW_OTP=0");
}

function app_key() {
    $key = getenv("APP_KEY") ?: "local-development-key-change-me";
    if (is_production() && strlen($key) < 32) {
        error_log("APP_KEY must contain at least 32 characters in production.");
        http_response_code(500);
        exit("The service is not configured correctly.");
    }
    return $key;
}
function request_is_https() {
    return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
}

if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    if (is_production() && request_is_https()) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}

// Skip DB connection if credentials are placeholder or empty
if ($host && $user && $dbname) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli($host, $user, $pass, $dbname);
        $conn->set_charset("utf8mb4");
        $conn->query("SET time_zone = '+00:00'");
    } catch (mysqli_sql_exception $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        exit("The service is temporarily unavailable.");
    }
} else {
    $conn = null;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name("campusexpress_session");
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "httponly" => true,
        "secure" => request_is_https(),
        "samesite" => "Lax"
    ]);
    session_start();
}

// Periodic cleanup of auth_attempts (once per hour)
if ($conn && (!isset($_SESSION['auth_gc_run']) || time() - $_SESSION['auth_gc_run'] > 3600)) {
    $_SESSION['auth_gc_run'] = time();
    try {
        $conn->query("DELETE FROM auth_attempts WHERE attempted_at < NOW() - INTERVAL 24 HOUR");
    } catch (Throwable $e) {
        error_log("auth_attempts cleanup failed: " . $e->getMessage());
    }
}

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); }
function csrf_token() {
    if (empty($_SESSION["csrf_token"])) $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    return $_SESSION["csrf_token"];
}
function csrf_field() { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function verify_post() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") { http_response_code(405); header("Allow: POST"); exit("Method not allowed."); }
    if (!hash_equals(csrf_token(), (string)($_POST["csrf_token"] ?? ""))) { http_response_code(403); exit("Invalid or expired form. Refresh and try again."); }
}
function client_identifier() {
    $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    return hash_hmac("sha256", $ip, app_key());
}
function auth_is_limited($action, $maxAttempts = 5, $windowMinutes = 15) {
    global $conn;
    if (!$conn) return false;
    $identifier = client_identifier();
    $stmt = $conn->prepare("SELECT COUNT(*) AS attempts FROM auth_attempts WHERE identifier_hash = ? AND action_name = ? AND attempted_at > (NOW() - INTERVAL ? MINUTE)");
    $stmt->bind_param("ssi", $identifier, $action, $windowMinutes);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()["attempts"] >= $maxAttempts;
}
function record_auth_failure($action) {
    global $conn;
    if (!$conn) return;
    $identifier = client_identifier();
    $stmt = $conn->prepare("INSERT INTO auth_attempts (identifier_hash, action_name) VALUES (?, ?)");
    $stmt->bind_param("ss", $identifier, $action);
    $stmt->execute();
}
function clear_auth_failures($action) {
    global $conn;
    if (!$conn) return;
    $identifier = client_identifier();
    $stmt = $conn->prepare("DELETE FROM auth_attempts WHERE identifier_hash = ? AND action_name = ?");
    $stmt->bind_param("ss", $identifier, $action);
    $stmt->execute();
}
function otp_hash($otp) { return hash_hmac("sha256", (string)$otp, app_key()); }
function remove_uploaded_image($path) {
    if (!preg_match('#^uploads/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $path)) return;
    $full = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (is_file($full)) unlink($full);
}
function store_uploaded_image($file, $required = true) {
    $error = (int)($file["error"] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE && !$required) return null;
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException("The image upload failed.");

    // 1. File size check (5MB max)
    $maxSize = 5 * 1024 * 1024;
    if ((int)$file["size"] > $maxSize) throw new RuntimeException("The image must be 5 MB or smaller.");

    // 2. Check file extension matches expected types
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowedExts = ["jpg", "jpeg", "png", "webp"];
    if (!in_array($ext, $allowedExts, true)) {
        throw new RuntimeException("Invalid file extension. Only JPG, PNG, and WEBP are allowed.");
    }

    // 3. MIME type validation (magic bytes, not extension)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file["tmp_name"]);
    $allowedMimes = ["image/jpeg", "image/png", "image/webp"];
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException("File content does not match a valid image type.");
    }

    // 4. Verify it's actually an image using getimagesize
    $imageInfo = @getimagesize($file["tmp_name"]);
    if ($imageInfo === false) {
        throw new RuntimeException("The file is not a valid image.");
    }

    // 5. Check minimum dimensions (prevents tiny/blank images)
    $minWidth = 100;
    $minHeight = 100;
    if ($imageInfo[0] < $minWidth || $imageInfo[1] < $minHeight) {
        throw new RuntimeException("Image must be at least {$minWidth}x{$minHeight} pixels.");
    }

    // 6. Check maximum dimensions (prevents extremely large images)
    $maxWidth = 4000;
    $maxHeight = 4000;
    if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
        throw new RuntimeException("Image must be no larger than {$maxWidth}x{$maxHeight} pixels.");
    }

    // 7. Check aspect ratio (reject extremely skewed images - potential exploits)
    $ratio = $imageInfo[0] / $imageInfo[1];
    if ($ratio < 0.1 || $ratio > 10) {
        throw new RuntimeException("Image aspect ratio is invalid.");
    }

    // 8. Create upload directory if needed
    $directory = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException("Upload directory unavailable.");
    }

    // 9. Generate secure random filename
    $mimeToExt = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    $filename = bin2hex(random_bytes(16)) . "." . $mimeToExt[$mime];

    // 10. Move file to uploads directory
    $dest = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file["tmp_name"], $dest)) {
        throw new RuntimeException("The image could not be saved.");
    }

    // 11. Strip EXIF data (removes metadata like GPS, camera info)
    if ($mime === "image/jpeg" || $mime === "image/pjpeg") {
        $img = @imagecreatefromjpeg($dest);
        if ($img) {
            imagejpeg($img, $dest, 90);
            imagedestroy($img);
        }
    }

    // 12. Set restrictive permissions (read-only for everyone)
    @chmod($dest, 0644);

    return "uploads/" . $filename;
}

// Shared login helpers - eliminates duplicate session logic across login files

/**
 * Clear all competing role sessions before setting new role
 */
function clear_competing_sessions() {
    unset(
        $_SESSION["admin_id"], $_SESSION["admin_name"],
        $_SESSION["vendor_id"], $_SESSION["vendor_name"], $_SESSION["phone"],
        $_SESSION["user_id"], $_SESSION["user_email"]
    );
}

/**
 * Complete login process: regenerate session, clear competing roles, set new role
 * @param string $role - 'admin', 'vendor', or 'user'
 * @param array $data - Session data to set for this role
 */
function complete_login($role, $data = []) {
    session_regenerate_id(true);
    clear_competing_sessions();
    
    foreach ($data as $key => $value) {
        $_SESSION[$key] = $value;
    }
    
    // Set role-specific redirect
    switch ($role) {
        case 'admin':
            return 'admin_dashboard.php';
        case 'vendor':
            return 'dashboard.php';
        case 'user':
            return $data['return_url'] ?? 'index.php';
        default:
            return 'index.php';
    }
}

/**
 * Require an approved vendor to be logged in. Re-checks status from DB.
 * Call this instead of checking isset($_SESSION["vendor_id"])
 */
function require_approved_vendor() {
    global $conn;
    if (!isset($_SESSION["vendor_id"])) {
        header("Location: login.php");
        exit;
    }
    $stmt = $conn->prepare("SELECT status FROM vendors WHERE id = ?");
    $stmt->bind_param("i", $_SESSION["vendor_id"]);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || $row["status"] !== "Approved") {
        unset($_SESSION["vendor_id"], $_SESSION["vendor_name"], $_SESSION["phone"]);
        header("Location: login.php?error=account_suspended");
        exit;
    }
}
?>
