<?php
function deliver_otp_email($email, $otp) {
    if (!is_production() && getenv("APP_SHOW_OTP") !== "0") return true;

    $apiKey = getenv("SENDGRID_API_KEY") ?: "";
    $from = getenv("MAIL_FROM") ?: "";
    if ($apiKey === "" || $from === "") {
        error_log("SENDGRID_API_KEY and MAIL_FROM are required for OTP delivery.");
        return false;
    }

    $payload = json_encode([
        "personalizations" => [
            [
                "to" => [["email" => $email]],
                "subject" => "Your Campus Express login code"
            ]
        ],
        "from" => ["email" => $from, "name" => "Campus Express"],
        "content" => [
            [
                "type" => "text/plain",
                "value" => "Your Campus Express login code is {$otp}. It expires in 10 minutes."
            ],
            [
                "type" => "text/html",
                "value" => '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:24px"><h2>Campus Express Login</h2><p>Your one-time login code is:</p><p style="font-size:32px;font-weight:800;letter-spacing:8px;background:#f3f4f6;padding:16px;border-radius:8px;text-align:center">' . htmlspecialchars($otp) . '</p><p>This code expires in 10 minutes. If you did not request it, you can ignore this email.</p></div>'
            ]
        ]
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init("https://api.sendgrid.com/v3/mail/send");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => $payload
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false || $status < 200 || $status >= 300) {
        error_log("OTP email delivery failed: HTTP {$status} {$error}");
        return false;
    }
    return true;
}
?>
