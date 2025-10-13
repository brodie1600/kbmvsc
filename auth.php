<?php
require_once __DIR__ . '/../config/config.php';

function authFlashRedirect(array $keys, string $email = '', array $options = []): void {
    $_SESSION['flash'] = [
        'email'  => $email,
        'alerts' => [
            'keys'           => array_values(array_unique($keys)),
            'mode'           => $options['mode'] ?? 'modal',
            'openAuth Modal' => $options['openAuthModal'] ?? true,
        ],
    ];
    header('Location: index.php#authModal');
    exit;
}

// Gmail-block server-side
$email = trim($_POST['email'] ?? '');
if (str_ends_with(strtolower($email) '@gmail.com')) {
    authFlashRedirect(['loginGmailBlock'], $email);
}
// CSRF check
if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    authFlashRedirect(['authInvalidCsrf'], $email);
}

// Grab the form inputs
$action    = $_POST['action']   ?? '';
$email     = trim($_POST['email']   ?? '');
$password  = $_POST['password'] ?? '';
$errorKeys = [];

// 0) reCAPTCHA check
$recaptcha = $_POST['g-recaptcha-response'] ?? '';
$verifyUrl = 'https://www.google.com/recaptcha/api/siteverify'
           . '?secret='   . urlencode(RECAPTCHA_SECRET)
           . '&response=' . urlencode($recaptcha);
$resp = json_decode(file_get_contents($verifyUrl), true);
if (empty($resp['success'])) {
    authFlashRedirect(['authRecaptchaRequired'], $email);
}

// 1) Basic validations
if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errorKeys[] = 'authInvalidEmail';
}
if (strlen($password) < 8) {
    $errorKeys[] = 'authPasswordTooShort';
}

$userId = null;

// 2) If no validation errors, handle register vs login
if (empty($errorKeys)) {
    if ($action === 'register') {
        // a) Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([ $email ]);
        if ($stmt->fetch()) {
            $errorKeys[] = 'authEmailExists';
        } else {
            // b) Insert new user
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare("
              INSERT INTO users (email, password_hash, created_at)
              VALUES (?, ?, NOW())
            ");
            $ins->execute([ $email, $hash ]);
            $userId = $pdo->lastInsertId();
        }
    }
    elseif ($action === 'login') {
        // a) Fetch user row
        $stmt = $pdo->prepare("
          SELECT id, password_hash
          FROM users
          WHERE email = ?
          LIMIT 1
        ");
        $stmt->execute([ $email ]);
        $user = $stmt->fetch();
        if (! $user || ! password_verify($password, $user['password_hash'])) {
            $errorKeys[] = 'authInvalidCredentials';
        } else {
            $userId = $user['id'];
        }
    }
    else {
        $errorKeys[] = 'authUnrecognizedAction';
    }
}

// 3) On errors, redirect back with flash data
if (! empty($errorKeys)) {
    authFlashRedirect($errorKeys, $email);
}

// 4) Regenerate session ID, then log the user in and redirect home
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
header('Location: index.php');
exit;
