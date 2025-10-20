<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

// 1) Parse JSON input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');

// 2) Validate format (return success to avoid enumeration)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => true]);
    exit;
}

// Reject gmail.com addresses (uniform response to avoid enumeration)
if (preg_match('/@gmail\.com$/i', $email)) {
    echo json_encode(['success' => true]);
    exit;
}

try {
    // 3) Lookup user by email
    $stmt = $pdo->prepare("SELECT id, steam_id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['steam_id'])) {
        echo json_encode(['success' => false, 'error' => 'steam_linked']);
        exit;
    }

    $userId = $user['id'] ?? null;

    if ($userId) {
        // 4) Rate limit: no more than 3 requests per hour
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM password_resets
             WHERE user_id = ?
               AND created_at > (NOW() - INTERVAL 1 HOUR)"
        );
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() >= 3) {
            // too many requests; silently succeed
            echo json_encode(['success' => true]);
            exit;
        }

        // 5) Delete any old tokens for this user (cleanup)
        $del = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $del->execute([$userId]);

        // 6) Create a new token & expiration (1 hour)
        $token   = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $ins = $pdo->prepare(
            "INSERT INTO password_resets (user_id, token, expires_at, created_at)
             VALUES (?, ?, ?, NOW())"
        );
        $ins->execute([$userId, $token, $expires]);

        // 7) Send the reset email
        $resetUrl = "https://kbmvscontroller.com/reset_password.php?token={$token}";
        $subject  = 'KBM vs Controller - Password Reset';
        $message  = "You (or someone using this address) requested a password reset at kbmvscontroller.com.\r\n\r\n"
                  . "Click the link below to set a new password:\r\n"
                  . "{$resetUrl}\r\n\r\n"
                  . "This link will expire in 1 hour.\r\n\r\n"
                  . "If you did not request this, you may safely delete this email.\r\n";
        $headers  = "From: no-reply@kbmvscontroller.com\r\n"
                  . "Reply-To: no-reply@kbmvscontroller.com\r\n"
                  . "Content-Type: text/plain; charset=UTF-8\r\n";
        mail($email, $subject, $message, $headers, '-fno-reply@kbmvscontroller.com');
    }
} catch (Exception $e) {
    // Log $e->getMessage() if desired
}

// 8) Uniform success response
echo json_encode(['success' => true]);
exit;
