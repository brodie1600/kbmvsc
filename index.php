<?php
require_once __DIR__ . '/../config/config.php';
// Pull any flash data for errors and old inputs
$flash     = $_SESSION['flash'] ?? [];
$errors    = $flash['errors']     ?? [];
$oldEmail  = $flash['email']      ?? '';
$flashType = $flash['type']       ?? 'danger';
unset($_SESSION['flash']);

// 1. Determine current user
$userId = $_SESSION['user_id'] ?? null;

// 2. Load this user’s votes (game_id => vote_type) if logged in
$userVotes = [];
if ($userId) {
    $stmtVotes = $pdo->prepare("
      SELECT game_id, vote_type
        FROM votes
       WHERE user_id = :uid
    ");
    $stmtVotes->execute([':uid' => $userId]);
    // fetchAll(PDO::FETCH_KEY_PAIR) gives [ game_id => vote_type, … ]
    $userVotes = $stmtVotes->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Games will be loaded dynamically via AJAX
$games = [];
$voteAgg = [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KBM vs Controller</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <script src="https://accounts.google.com/gsi/client" defer></script>

</head>
<body>
<div id="main">
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid d-flex align-items-center position-relative">
    <div class="d-flex align-items-center" id="leftGroup">
      <a class="navbar-brand mb-0">KBM vs Controller</a>
      <a href="about.php" id="about" class="btn btn-outline-light btn-sm">About</a>
    </div>
    <form class="search-container position-absolute top-50 start-50 translate-middle">
      <div class="input-group">
        <input
          type="text"
          id="gameSearch"
          class="form-control bg-dark text-light border-secondary"
          placeholder="Search titles..."
          aria-label="Search titles"
        >
        <span class="input-group-text bg-dark border-secondary"><i class="bi bi-search text-light"></i></span>
      </div>
    </form>
      <?php if ($userId): ?>
        <div class="d-flex align-items-center position-absolute end-0 pe-3">
          <?php
            $u = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $u->execute([$userId]);
            $me = $u->fetchColumn();
          ?>
          <span class="navbar-text text-light me-3">Welcome, <?= htmlspecialchars($me) ?></span>
          <a href="logout.php" class="btn btn-outline-light">Log Out</a>
        </div>
      <?php else: ?>
        <button id="loginBtn" class="btn btn-outline-light position-absolute end-0 me-3">Log In / Register</button>
      <?php endif; ?>
  </div>
</nav>
<div class="announce">
  Click on a title to expand details and cast your vote. Votes are tied to your account, so you must be logged in first. Registration is easy and takes two clicks.
</div>
  <div class="games-list"></div>
  <div class="text-center my-3">
    <button id="loadMoreBtn" class="btn btn-outline-light">Load More</button>
  </div>
  </div>
  <script>
    const isLoggedIn = <?= $userId ? 'true' : 'false' ?>;
    window.isLoggedIn = isLoggedIn;
    const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
    window.csrfToken = csrfToken;
  </script>
  <script src="js/alerts.js"></script>
  <script src="js/script.js"></script>
  <script src="js/search.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
<div class="modal fade" id="authModal" tabindex="-1">
  <div class="modal-dialog">
    <div id="login-reg-form" class="modal-content p-3 bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Log In or Register</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Google button -->
        <div id="g_id_signin"></div>
        <hr>
        <!-- Email form -->
        <form id="authForm" method="post" action="auth.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <div class="mb-2">
            <label for="authEmail">Email:</label>
            <input
              type="email"
              id="authEmail"
              name="email"
              class="form-control bg-dark text-light"
              required
              value="<?= htmlspecialchars($oldEmail) ?>"
            >
          </div>
          <div class="mb-2">
            <label for="authPassword">Password:</label>
            <input
              type="password"
              id="authPassword"
              name="password"
              class="form-control bg-dark text-light"
              required
            >
          </div>

          <!-- Forgot password link -->
           <div class="mb-2">
            <a href="#" id="forgotLink" class="text-light small">Forgot password?</a>
          </div>

          <br>

          <!-- reCAPTCHA v2 checkbox -->
          <div class="mb-3">
            <div
              class="g-recaptcha"
              data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY) ?>"
            ></div>
          </div>


          <!-- Error display -->
            <?php if (!empty($errors)): ?>
              <div class="alert alert-<?= htmlspecialchars($flashType) ?> fade show" role="alert" data-bs-theme="dark">
                <span class="mb-0">
                  <?php foreach ($errors as $err): ?>
                    <?= htmlspecialchars($err) ?>
                  <?php endforeach; ?>
                </span>
              </div>
            <?php endif; ?>
            
          <div class="d-flex justify-content-between mt-3">
            <button
              type="submit"
              name="action"
              value="login"
              class="btn btn-outline-primary"
            >Log In</button>
            <button
              type="submit"
              name="action"
              value="register"
              class="btn btn-outline-secondary"
            >Register</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<!-- GSI -->
<script>
// 1. Initialize the Google Identity Services client
window.addEventListener('DOMContentLoaded', () => {
  google.accounts.id.initialize({
    client_id: '<?= GOOGLE_CLIENT_ID ?>',
    callback: handleCredentialResponse,
    ux_mode: 'popup'
  });
  if (window.google && google.accounts && google.accounts.id) {
    google.accounts.id.initialize({
      client_id: '<?= GOOGLE_CLIENT_ID ?>',
      callback: handleCredentialResponse,
      ux_mode: 'popup'
    });

// 2. Render the "Continue with Google" button
  google.accounts.id.renderButton(
    document.getElementById('g_id_signin'),
    { theme: 'outline', size: 'large', text: 'continue_with' }
  );
    // 2. Render the "Continue with Google" button
    google.accounts.id.renderButton(
      document.getElementById('g_id_signin'),
      { theme: 'outline', size: 'large', text: 'continue_with' }
    );
  }
});
// 3. Called when the user successfully picks a Google account
function handleCredentialResponse(response) {
  // response.credential is the ID token (JWT)
  fetch('oauth_callback.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ id_token: response.credential })
  })
  .then(r=>r.json())
  .then(data => {
    if (data.success) {
      // reload so navbar updates and votes can work
      window.location.reload();
    } else {
      Alerts.showModal('googleSigninError', { message: data.error || Alerts.config.googleSigninError.message });
    }
  })
  .catch(() => {
    Alerts.showModal('googleSigninNetworkError');
  });
}
</script>

<?php if (!empty($errors)): ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // grab the modal element
    var el = document.getElementById('authModal');
    // initialize it
    var myModal = new bootstrap.Modal(el);
    // show it
    myModal.show();
  });
</script>
<?php endif; ?>
<script>
  const authModal = new bootstrap.Modal(document.getElementById('authModal'));
  document.getElementById('loginBtn').onclick = () => authModal.show();
  const loginBtn  = document.getElementById('loginBtn');
  if (loginBtn) loginBtn.onclick = () => authModal.show();

  // AJAX helper
  async function postJSON(url, data) {
    const res = await fetch(url, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(data)
    });
    return res.json();
  }
</script>
<div id="alert-container"></div>
<div id="footer">
  <p class="text-center mb-0">
    This website is not affiliated with Steam, Valve, or any game developer/publisher.<br>
    All copyrights and trademarks are property of their respective owners.
  </p>
</div>
</body>
</html>
