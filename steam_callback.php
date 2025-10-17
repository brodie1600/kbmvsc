<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

function fetchSteamPlayerEmail(string $steamId): ?string
{
    $apiKey = $_ENV['STEAM_WEB_API_KEY'] ?? getenv('STEAM_WEB_API_KEY') ?: '';
    if ($apiKey === '') {
        return null;
    }

    $endpoint = sprintf(
        'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s',
        rawurlencode($apiKey),
        rawurlencode($steamId)
    );

    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_FAILONERROR    => true,
            ]);
            $curlBody = curl_exec($ch);
            if (is_string($curlBody)) {
                $response = $curlBody;
            }
            curl_close($ch);
        }
    }

    if ($response === null) {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 5,
            ],
        ]);

        $streamBody = @file_get_contents($endpoint, false, $context);
        if ($streamBody === false) {
            return null;
        }
        $response = $streamBody;
    }

    $decoded = json_decode($response, true);
    if (! is_array($decoded)) {
        return null;
    }

    $players = $decoded['response']['players'] ?? [];
    if (! is_array($players) || ! isset($players[0]) || ! is_array($players[0])) {
        return null;
    }

    $player = $players[0];
    if (! isset($player['email'])) {
        return null;
    }

    $maybeEmail = is_string($player['email']) ? trim($player['email']) : '';
    if ($maybeEmail === '' || ! filter_var($maybeEmail, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return $maybeEmail;
}

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

    if ($steamEmail === null) {
        $steamEmail = fetchSteamPlayerEmail($steamId);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE steam_id = ? LIMIT 1');
        $stmt->execute([$steamId]);
        $existingUser = $stmt->fetch();

        if (! $existingUser) {
            if ($steamEmail !== null) {
                $ins = $pdo->prepare('INSERT INTO users (steam_id, email, created_at) VALUES (?, ?, NOW())');
                $ins->execute([$steamId, $steamEmail]);
            } else {
                $ins = $pdo->prepare('INSERT INTO users (steam_id, created_at) VALUES (?, NOW())');
                $ins->execute([$steamId]);
            }
            $userId = $pdo->lastInsertId();
        } else {
            $userId = $existingUser['id'];
            if ($steamEmail !== null) {
                $currentEmail = $existingUser['email'] ?? '';
                $normalizedCurrent = is_string($currentEmail) ? trim($currentEmail) : '';
                if ($normalizedCurrent === '' || strcasecmp($normalizedCurrent, $steamEmail) !== 0) {
                    $upd = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                    $upd->execute([$steamEmail, $userId]);
                }
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Steam callback failed: ' . $e->getMessage());
        $_SESSION['flash'] = ['alerts' => ['keys' => ['steamSigninFailed'], 'mode' => 'modal']];
        header('Location: index.php#authModal');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    header('Location: index.php');
    exit;
}

$_SESSION['flash'] = ['alerts' => ['keys' => ['steamSigninFailed'], 'mode' => 'modal']];
header('Location: index.php#authModal');
exit;
