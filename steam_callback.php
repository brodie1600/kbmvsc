<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$oid = new LightOpenID(parse_url(BASE_URL, PHP_URL_HOST));

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