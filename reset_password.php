<?php
require_once __DIR__ . '/../config/config.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Grab & sanitize token
$token   = isset($_GET['token']) ? trim($_GET['token']) : '';
$errors  = [];
$success = false;
$email   = '';
$userId  = null;
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
        $errors[] = 'Invalid or expired token.';
        $invalidTok = true;
    } elseif (strtotime($row['expires_at']) < time()) {
        $errors[] = 'This reset link has expired.';
        $invalidTok = true;
    } else {
        $email  = $row['email'];
        $userId = $row['user_id'];
    }
} else {
    $errors[] = 'No token provided.';
    $invalidTok = true;
}

// 2) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$invalidTok) {
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validate passwords
    if (strlen($newPass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($newPass !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // 3) Update user password
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $upd  = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $upd->execute([$hash, $userId]);

        // 4) Delete used token
        $del = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
        $del->execute([$token]);

        $success = true;
    }
}
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

      <?php if ($success): ?>
        <div class="alert alert-success fade show" role="alert" data-bs-theme="dark">
          Your password has been reset. You may now <a href="index.php" class="alert-link">return to the login page</a>.
        </div>

      <?php elseif ($invalidTok): ?>
        <div class="alert alert-danger" role="alert">
          <?php foreach ($errors as $err): ?>
            <div><?= htmlspecialchars($err) ?></div>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
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

          <!-- Validation errors appear HERE -->
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
              <?php foreach ($errors as $err): ?>
                <div><?= htmlspecialchars($err) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-outline-primary">Save</button>
        </form>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
