<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$pendingEmail = trim($_SESSION['steam_email'] ?? '');
$expectedUserId = isset($_SESSION['steam_email_expected_user_id'])
    ? (int) $_SESSION['steam_email_expected_user_id']
    : null;

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

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE steam_id = ? LIMIT 1');
        $stmt->execute([$steamId]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $existingUser ? (int) $existingUser['id'] : null;
        $existingEmail = ($existingUser && array_key_exists('email', $existingUser))
            ? trim((string) $existingUser['email'])
            : null;
        if ($existingEmail === '') {
            $existingEmail = null;
        }

        if (! $userId) {
            if ($pendingEmail !== '') {
                $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $emailCheck->execute([$pendingEmail]);
                $conflictId = $emailCheck->fetchColumn();
                if ($conflictId && (! $expectedUserId || (int) $conflictId !== $expectedUserId)) {
                    throw new RuntimeException('steam_email_conflict');
                }
            }

            $ins = $pdo->prepare('INSERT INTO users (steam_id, email, created_at) VALUES (?, ?, NOW())');
            $ins->execute([$steamId, $pendingEmail !== '' ? $pendingEmail : null]);
            $userId = $pdo->lastInsertId();
        } else {
            if ($expectedUserId && $expectedUserId !== (int) $userId) {
                throw new RuntimeException('steam_email_conflict');
            }

            if ($pendingEmail !== '') {
                if ($existingEmail !== null && strcasecmp($existingEmail, $pendingEmail) !== 0) {
                    throw new RuntimeException('steam_already_registered');
                }

                $conflictCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                $conflictCheck->execute([$pendingEmail, $userId]);
                if ($conflictCheck->fetch()) {
                    throw new RuntimeException('steam_email_conflict');
                }

                if ($existingEmail === null) {
                    $upd = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                    $upd->execute([$pendingEmail, $userId]);
                }
            }
        }

        $pdo->commit();
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        unset($_SESSION['steam_email'], $_SESSION['steam_email_expected_user_id']);
        $alertKey = $e->getMessage() === 'steam_already_registered'
            ? 'steamAlreadyRegistered'
            : 'steamEmailMismatch';
        $_SESSION['flash'] = [
            'alerts' => [
                'keys'          => [$alertKey],
                'mode'          => 'modal',
                'openAuthModal' => true,
            ],
        ];
        header('Location: index.php#authModal');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Steam callback failed: ' . $e->getMessage());
        unset($_SESSION['steam_email'], $_SESSION['steam_email_expected_user_id']);
        $_SESSION['flash'] = [
            'alerts' => [
                'keys'          => ['steamSigninFailed'],
                'mode'          => 'modal',
                'openAuthModal' => true,
            ],
        ];
        header('Location: index.php#authModal');
        exit;
    }

    unset($_SESSION['steam_email'], $_SESSION['steam_email_expected_user_id']);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    header('Location: index.php');
    exit;
}

unset($_SESSION['steam_email'], $_SESSION['steam_email_expected_user_id']);

$_SESSION['flash'] = ['alerts' => ['keys' => ['steamSigninFailed'], 'mode' => 'modal']];
header('Location: index.php#authModal');
exit;
