<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$hostFromEnv = '';
$googleRedirect = $_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI') ?: '';

if ($googleRedirect !== '') {
    $parsedHost = parse_url($googleRedirect, PHP_URL_HOST);
    if (is_string($parsedHost) && $parsedHost !== '') {
        $hostFromEnv = $parsedHost;
    }
}

$host = $_SERVER['HTTP_HOST'] ?? $hostFromEnv ?: 'localhost';

$scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
    ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0])
    : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir  = '';

if ($scriptName !== '') {
    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    if ($scriptDir === '.' || $scriptDir === '/') {
        $scriptDir = '';
    } else {
        $scriptDir = '/' . ltrim($scriptDir, '/');
    }
}

$baseUrl = sprintf('%s://%s%s', $scheme, $host, $scriptDir === '' ? '/' : $scriptDir . '/');

if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['alerts' => ['keys' => ['authInvalidCsrf'], 'mode' => 'modal']];
    header('Location: index.php#authModal');
    exit;
}

$pendingEmail = trim($_SESSION['steam_email'] ?? '');
if ($pendingEmail === '') {
    $pendingEmail = trim($_POST['steam_email'] ?? '');
}

$redirectWithAlert = function (string $alertKey) use ($pendingEmail) {
    $_SESSION['flash'] = [
        'email'  => $pendingEmail,
        'alerts' => [
            'keys'          => [$alertKey],
            'mode'          => 'modal',
            'openAuthModal' => true,
        ],
    ];
    unset($_SESSION['steam_email'], $_SESSION['steam_email_expected_user_id']);
    header('Location: index.php#authModal');
    exit;
};

if ($pendingEmail === '') {
    $redirectWithAlert('steamEmailRequired');
}

if (! filter_var($pendingEmail, FILTER_VALIDATE_EMAIL)) {
    $redirectWithAlert('authInvalidEmail');
}

$lowerEmail = strtolower($pendingEmail);
if (str_ends_with($lowerEmail, '@gmail.com')) {
    $redirectWithAlert('loginGmailBlock');
}

$stmt = $pdo->prepare('SELECT id, steam_id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$pendingEmail]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing && empty($existing['steam_id'])) {
    $redirectWithAlert('authEmailExists');
}

if ($existing && !empty($existing['steam_id'])) {
    $_SESSION['steam_email_expected_user_id'] = (int) $existing['id'];
} else {
    unset($_SESSION['steam_email_expected_user_id']);
}

$_SESSION['steam_email'] = $pendingEmail;

$oid = new LightOpenID($host);
$oid->identity  = 'https://steamcommunity.com/openid';
$oid->returnUrl = $baseUrl . 'steam_callback.php';
$oid->required  = ['steamid'];

header('Location: ' . $oid->authUrl());
exit;
