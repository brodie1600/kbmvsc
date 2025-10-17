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

$oid = new LightOpenID($host);
$oid->returnUrl = $baseUrl . 'steam_callback.php';

if (! $oid->mode) {
    header('Location: index.php#authModal');
    exit;
}

if ($oid->mode !== 'cancel' && $oid->validate()) {
    $steamId = basename($oid->identity); // The final path segment is the 64-bit SteamID

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE steam_id = ? LIMIT 1');
    $stmt->execute([$steamId]);
    $userId = $stmt->fetchColumn();

    if (! $userId) {
        $ins = $pdo->prepare('INSERT INTO users (steam_id, created_at) VALUES (?, NOW())');
        $ins->execute([$steamId]);
        $userId = $pdo->lastInsertId();
    }

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    header('Location: index.php');
    exit;
}

$_SESSION['flash'] = ['alerts' => ['keys' => ['steamSigninFailed'], 'mode' => 'modal']];
header('Location: index.php#authModal');
exit;
