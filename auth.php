<?php
require_once __DIR__ . '/../config/config.php';

// Gmail-block server-side
$email = trim($_POST['email'] ?? '');
if (str_ends_with(strtolower($email), '@gmail.com')) {
    $_SESSION['flash'] = [
        'errors' => [
            'You cannot log in or register with a Gmail account using this form. Please use the "Continue with Google" button above.'
        ],
        'email'  => $email,
        'type'   => 'warning'
    ];
    header('Location: index.php#authModal');
    exit;
}
// CSRF check
if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['flash'] = [
        'errors' => ['Invalid submission (CSRF token mismatch).'],
        'email'  => trim($_POST['email'] ?? '')
    ];
    header('Location: index.php#authModal');
    exit;
}

// Grab the form inputs
$action   = $_POST['action']   ?? '';
$email    = trim($_POST['email']   ?? '');
$password = $_POST['password'] ?? '';
$errors = [];

// 0) reCAPTCHA check
$recaptcha = $_POST['g-recaptcha-response'] ?? '';
$verifyUrl = 'https://www.google.com/recaptcha/api/siteverify'
           . '?secret='   . urlencode(RECAPTCHA_SECRET)
           . '&response=' . urlencode($recaptcha);
$resp = json_decode(file_get_contents($verifyUrl), true);
if (empty($resp['success'])) {
    $_SESSION['flash'] = [
      'errors' => ['Please complete the CAPTCHA to prove you are not a robot.'],
      'email'  => $email,
      'type'   => 'warning'
    ];
    header('Location: index.php#authModal');
    exit;
}

// 1) Basic validations
if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}

$userId = null;

// 2) If no validation errors, handle register vs login
if (empty($errors)) {
    if ($action === 'register') {
        // a) Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([ $email ]);
        if ($stmt->fetch()) {
            $errors[] = 'Email address already registered. Please try again.';
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
            $errors[] = 'Invalid email/password combination. Please try again.';
        } else {
            $userId = $user['id'];
        }
    }
    else {
        $errors[] = 'Unrecognized action.';
    }
}

// 3) On errors, redirect back with flash data
if (! empty($errors)) {
    $_SESSION['flash'] = [
      'errors' => $errors,
      'email'  => $email
    ];
    header('Location: index.php#authModal');
    exit;
}

// 4) Regenerate session ID, then log the user in and redirect home
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
header('Location: index.php');
exit;
