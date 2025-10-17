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
$pathPrefix = '';

if ($scriptName !== '') {
    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    if ($scriptDir !== '.' && $scriptDir !== '/') {
        $pathPrefix = '/' . ltrim($scriptDir, '/');
    }
}

$callbackUrl = sprintf('%s://%s%s/steam_callback.php', $scheme, $host, $pathPrefix);

$oid = new LightOpenID($host);
$oid->returnUrl = $callbackUrl;

if (! $oid->mode) {
    header('Location: index.php#authModal');
    exit;
}

if ($oid->mode !== 'cancel' && $oid->validate()) {
    $steamId = basename($oid->identity); // The final path segment is the 64-bit SteamID

    $attributes = $oid->getAttributes();
    $steamEmail = null;
    if (isset($attributes['contact/email']) && is_string($attributes['contact/email'])) {
        $maybeEmail = trim($attributes['contact/email']);
        if ($maybeEmail !== '' && filter_var($maybeEmail, FILTER_VALIDATE_EMAIL)) {
            $steamEmail = $maybeEmail;
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE steam_id = ? LIMIT 1');
    $stmt->execute([$steamId]);
    $existingUser = $stmt->fetch();

    if (! $existingUser) {
        $ins = $pdo->prepare('INSERT INTO users (steam_id, email, created_at) VALUES (?, ?, NOW())');
        $ins->execute([$steamId, $steamEmail]);
        $userId = $pdo->lastInsertId();
    } else {
        $userId = $existingUser['id'];
        if ($steamEmail) {
            $currentEmail = $existingUser['email'] ?? '';
            $normalizedCurrent = is_string($currentEmail) ? trim($currentEmail) : '';
            if ($normalizedCurrent === '' || strcasecmp($normalizedCurrent, $steamEmail) !== 0) {
                $upd = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                $upd->execute([$steamEmail, $userId]);
            }
        }
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
