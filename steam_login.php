<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$host = $_SERVER['HTTP_HOST'] ?? parse_url($_ENV['GOOGLE_REDIRECT_URI'] ?? '', PHP_URL_HOST) ?? 'localhost';
$scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
    ? trim(explode(',', $_SERVER['HTTP_X_FOWARDED_PROTO'])[0])
    : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '' )), '/');
$baseUrl = sprintf('%s://%s%s/', $scheme, $host, $basePath ? $basePath : '');

if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['alerts' => ['keys' => ['authInvalidCsrf'], 'mode' => 'modal']];
    header('Location: index.php#authModal');
    exit;
}

$oid = new LightOpenID(parse_url($host));
$oid->identity  = 'https://steamcommunity.com/openid';
$oid->returnUrl = $baseUrl . 'steam_callback.php';
$oid->required  = ['steamid'];

header('Location: ' . $oid->authUrl());
exit;