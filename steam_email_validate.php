<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'alertKey' => 'steamSigninFailed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$decoded  = json_decode($rawInput, true);

if (!is_array($decoded)) {
    $decoded = $_POST;
}

$csrfToken = $decoded['csrf_token'] ?? '';
if ($csrfToken === '' || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'alertKey' => 'authInvalidCsrf']);
    exit;
}

$email = trim($decoded['email'] ?? '');
if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'alertKey' => 'authInvalidEmail']);
    exit;
}

$lowerEmail = strtolower($email);
if (str_ends_with($lowerEmail, '@gmail.com')) {
    echo json_encode(['success' => false, 'alertKey' => 'loginGmailBlock']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, steam_id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Steam email validation failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'alertKey' => 'steamSigninFailed']);
    exit;
}

if ($existing && empty($existing['steam_id'])) {
    echo json_encode(['success' => false, 'alertKey' => 'authEmailExists']);
    exit;
}

if ($existing && !empty($existing['steam_id'])) {
    $_SESSION['steam_email_expected_user_id'] = (int)$existing['id'];
} else {
    unset($_SESSION['steam_email_expected_user_id']);
}

$_SESSION['steam_email'] = $email;

echo json_encode(['success' => true]);
exit;
