<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true);
$id_token  = $input['id_token'] ?? '';

if (! $id_token) {
    echo json_encode(['success'=>false,'error'=>'No token provided.']);
    exit;
}

// Verify the token with Google
$client = new Google_Client(['client_id'=>GOOGLE_CLIENT_ID]);
$payload = $client->verifyIdToken($id_token);
if (! $payload) {
    echo json_encode(['success'=>false,'error'=>'Invalid ID token.']);
    exit;
}

$email     = $payload['email'];
$google_id = $payload['sub'];

try {
    // 1) Look for an existing user by google_id or email
    $stmt = $pdo->prepare("
      SELECT id
        FROM users
       WHERE google_id = ?
          OR email     = ?
       LIMIT 1
    ");
    $stmt->execute([$google_id, $email]);
    $userId = $stmt->fetchColumn();

    if ($userId) {
        // 2a) If they exist but have no google_id, update it
        $upd = $pdo->prepare("
          UPDATE users
             SET google_id = ?
           WHERE id = ?
        ");
        $upd->execute([$google_id, $userId]);
    } else {
        // 2b) New user → insert
        $ins = $pdo->prepare("
          INSERT INTO users (email, google_id, created_at)
          VALUES (?, ?, NOW())
        ");
        $ins->execute([$email, $google_id]);
        $userId = $pdo->lastInsertId();
    }

    // 3) Regenerate session ID, then log them in
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    echo json_encode(['success'=>true]);
    exit;
}
catch (Exception $e) {
    // Log
    $msg = '[' . date('Y-m-d H:i:s') . "] oauth_callback.php error: "
      . $e->getMessage() . "\n"
      . $e->getTraceAsString() . "\n\n";
    error_log($msg, 3, __DIR__ . '/../logs/oauth_errors.log');

    // Return error
    echo json_encode(['success'=>false,'error'=>'Server error.']);
    exit;
}
