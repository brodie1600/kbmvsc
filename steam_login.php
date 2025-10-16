<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['alerts' => ['keys' => ['authInvalidCsrf'], 'mode' => 'modal']];
    header('Location: index.php#authModal');
    exit;
}

$oid = new LightOpenID(parse_url(BASE_URL, PHP_URL_HOST));
$oid->identity  = 'https://steamcommunity.com/openid';
$oid->returnUrl = BASE_URL . 'steam_callback.php';
$oid->required  = ['steamid'];

header('Location: ' . $oid->authUrl());
exit;