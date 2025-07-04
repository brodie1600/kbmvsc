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

// 3. Fetch all games
$stmt = $pdo->query("SELECT * FROM games ORDER BY name ASC");
$games = $stmt->fetchAll();

// 3.5) Preload vote counts for every game
$stmtAgg = $pdo->query("
  SELECT 
    game_id,
    SUM(vote_type = 'kbm')        AS kbm_count,
    SUM(vote_type = 'controller') AS controller_count
  FROM votes
  GROUP BY game_id
");
$rows = $stmtAgg->fetchAll(PDO::FETCH_ASSOC);

// Build a lookup: [ game_id => ['kbm'=>int, 'controller'=>int], … ]
$voteAgg = [];
foreach ($rows as $r) {
    $voteAgg[$r['game_id']] = [
      'kbm'        => (int)$r['kbm_count'],
      'controller' => (int)$r['controller_count']
    ];
}

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
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand">KBM vs Controller</a>
    <a href="about.php" id="about" class="btn btn-outline-light btn-sm" role="button">About</a>
      <?php if ($userId): ?>
        <div class="d-flex align-items-center ms-auto">
          <?php
            $u = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $u->execute([$userId]);
            $me = $u->fetchColumn();
          ?>
          <span class="navbar-text text-light me-3">Welcome, <?= htmlspecialchars($me) ?></span>
          <a href="logout.php" class="btn btn-outline-light ms-auto">Log Out</a>
        </div>
      <?php else: ?>
        <button id="loginBtn" class="btn btn-outline-light ms-auto">Log In / Register</button>
      <?php endif; ?>
  </div>
</nav>
<br>
<div class="announce">
  <h3>Test content!</h3>
  <p>Lorum ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
</div>
  <div class="search-container mb-3 px-2">
    <input
      type="text"
      id="gameSearch"
      class="form-control"
      placeholder="Search titles..."
      aria-label="Search titles"
    >
  </div>
  <div class="games-list">
    <?php foreach ($games as $game): ?>
      <?php
        // Determine majority icon for collapsed state
        $gid = (int)$game['id'];
        $yourVote = $userVotes[$gid] ?? null;
        $counts = $voteAgg[$gid] ?? ['kbm'=>0,'controller'=>0];
        if ($counts['kbm'] > $counts['controller']) {
          $majorIcon = 'icons/kbm-drkong.svg';
        } elseif ($counts['controller'] > $counts['kbm']) {
          $majorIcon = 'icons/controller-lgtong.svg';
        } else {
          $majorIcon = 'icons/question.svg';
        }

        $headerURL = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$gid}/header.jpg";
        $capsuleLgURL = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$gid}/capsule_231x87.jpg";
        $capsuleSmUrl = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$gid}/capsule_184x69.jpg";
      ?>
      <div class="game-block collapsed" data-game-id="<?= $gid ?>">
        <!-- Collapsed header -->
        <div class="header-row">
          <img class="cover" loading="lazy" src="<?= htmlspecialchars($capsuleLgURL) ?>" alt="&nbsp;">
          <h1 class="title"><b><?= htmlspecialchars($game['name']) ?></b></h1>
          <img class="majority-icon" src="<?= $majorIcon ?>" alt="majority vote">
        </div>
        <!-- Expanded details -->
        <div class="details">
          <div class="game-info">
            <p><strong>Developer:</strong> <?= htmlspecialchars($game['developer']) ?></p>
            <p><strong>Publisher:</strong> <?= htmlspecialchars($game['publisher']) ?></p>
            <p><strong>Release Date:</strong> <?= htmlspecialchars($game['release_date']) ?></p>
            <div class="platform-icons">
              <?php if ($game['supports_windows']): ?><i class="bi bi-microsoft"></i><?php endif; ?>
              <?php if ($game['supports_mac']): ?><i class="bi bi-apple"></i><?php endif; ?>
              <?php if ($game['supports_linux']): ?><i class="bi bi-tux"></i><?php endif; ?>
            </div>
          </div>
          <div class="vote-row">
            <?php $total = $counts['kbm'] + $counts['controller']; ?>
            <div class="count-label">Votes:<br><span class="count-label-inner"><?= $counts['kbm'] ?> &mdash; <?= $total ?> &mdash; <?= $counts['controller'] ?></span></div>
            <div class="vote-section">
              <div class="vote-icon kbm-icon <?= $yourVote === 'kbm' ? 'active' : '' ?>" data-vote-type="kbm"></div>
              <div class="vote-bar">
                <div class="bar-fill kbm-bar" style="width:<?= $counts['kbm'] + $counts['controller']?($counts['kbm']/($counts['kbm']+$counts['controller'])*100):50 ?>%"></div>
                <div class="bar-fill controller-bar" style="width:<?= $counts['kbm'] + $counts['controller']?($counts['controller']/($counts['kbm']+$counts['controller'])*100):50 ?>%"></div>
                <div class="tick" style="left:<?= $counts['kbm'] + $counts['controller']?($counts['kbm']/($counts['kbm']+$counts['controller'])*100):50 ?>%"></div>
              </div>
              <div class="vote-icon controller-icon <?= $yourVote === 'controller' ? 'active' : '' ?>" data-vote-type="controller"></div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <script>
    const isLoggedIn = <?= $userId ? 'true' : 'false' ?>;
    const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
  </script>
  <script src="js/script.js"></script>
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
              data-sitekey="6Lec8GUrAAAAAEbLpbZWMjE5ImXQpenL9QjW2t3C"
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
      showModalAlert(data.error || 'Google sign-in failed.', 'danger');
    }
  })
  .catch(() => {
    showModalAlert('Network error during Google sign-in.', 'danger');
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