<?php
require_once __DIR__ . '/../config/config.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Grab & sanitize token
$token      = isset($_GET['token']) ? trim($_GET['token']) : '';
$alertKeys  = [];
$success    = false;
$email      = '';
$userId     = null;
$invalidTok = false;

// 1) Fetch and validate token
if ($token) {
    $stmt = $pdo->prepare(
        "SELECT pr.user_id, u.email, pr.expires_at
         FROM password_resets pr
         JOIN users u ON pr.user_id = u.id
         WHERE pr.token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $alertKeys[] = 'resetInvalidToken';
        $invalidTok = true;
    } elseif (strtotime($row['expires_at']) < time()) {
        $alertKeys[] = 'resetExpiredToken';
        $invalidTok = true;
    } else {
        $email  = $row['email'];
        $userId = $row['user_id'];
    }
} else {
    $alertKeys[] = 'resetNoToken';
    $invalidTok = true;
}

// 2) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$invalidTok) {
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validate passwords
    if (strlen($newPass) < 8) {
        $alertKeys[] = 'resetPasswordTooShort';
    }
    if ($newPass !== $confirm) {
        $alertKeys[] = 'resetPasswordMismatch';
    }

    if (empty($alertKeys)) {
        // 3) Update user password
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $upd  = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $upd->execute([$hash, $userId]);

        // 4) Delete used token
        $del = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
        $del->execute([$token]);

        $success   = true;
        $alertKeys = ['resetSuccess'];
    }
}

$pageAlerts = null;
if ($success) {
  $pageAlerts = ['keys' => ['resetSuccess']];
} elseif (!empty($alertKeys)) {
  $pageAlerts = ['keys' => array_values(array_unique($alertKeys))];
}
$showForm = !$success && !$invalidTok;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KBM vs Controller - Reset Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container py-5" id="reset-password">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <h2 class="mb-4"><b>KBM vs Controller</b><br>Reset Password</h2>
      <div id="alert-container" class="mb-4"></div>

    <?php if ($showForm): ?>
      <form method="post">
          <div class="mb-3">
            <label>Email</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($email) ?>" disabled>
          </div>
          <div class="mb-3">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
          </div>

          <button type="submit" class="btn btn-outline-primary">Save</button>
        </form>
      <?php endif; ?>

    </div>
  </div>
</div>
<script>
  window.pageAlerts = <?= json_encode($pageAlerts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="js/alerts.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.pageAlerts && Array.isArray(window.pageAlerts.keys) && window.pageAlerts.keys.length) {
      if (window.pageAlerts.keys.length === 1) {
        Alerts.show(window.pageAlerts.keys[0]);
      } else {
        Alerts.showFromKeys(window.pageAlerts.keys);
      }
    }
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
