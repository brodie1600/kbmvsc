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

if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['alerts' => ['keys' => ['authInvalidCsrf'], 'mode' => 'modal']];
    header('Location: index.php#authModal');
    exit;
}

$oid = new LightOpenID($host);
$oid->identity  = 'https://steamcommunity.com/openid';
$oid->returnUrl = $callbackUrl;
$oid->required  = ['steamid'];
$oid->optional  = ['contact/email'];

header('Location: ' . $oid->authUrl());
exit;
