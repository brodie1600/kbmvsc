<?php
require_once __DIR__ . '/../config/config.php';
// Pull any flash data for errors and old inputs
$flash        = $_SESSION['flash'] ?? [];
$oldEmail     = $flash['email']    ?? '';
$alertDetails = $flash['alerts']   ?? null;
unset($_SESSION['flash']);

$serverAlertPayload = null;
if ($alertDetails) {
  $keys = array_values(array_filter(
    $alertDetails['keys'] ?? [],
    function ($k) {
      return is_string($k) && $k !== '';
    }
  ));
  $openAuthModal = !empty($alertDetails['openAuthModal']);
  if ($keys || $openAuthModal) {
    $serverAlertPayload = [
      'keys'          => $keys,
      'mode'          => $alertDetails['mode'] ?? 'modal',
      'openAuthModal' => $openAuthModal,
    ];
  }
}

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
  Click on a title to expand details and cast your vote. Votes are tied to your account, so you must be logged in first. Registration is easy and takes just a few clicks.
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
    window.serverAlerts = <?= json_encode($serverAlertPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
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
        <div id="steam_signin">
          <form id="steamSignInForm" action="steam_login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="steam_email" id="steamEmailField" value="">
            <button type="button" id="steamSignInButton" class="btn btn-steam">
              <img src="https://community.fastly.steamstatic.com/public/images/signinthroughsteam/sits_01.png" alt="">
            </button>
          </form>
          </div>
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

<div class="modal fade" id="steamEmailModal" tabindex="-1" aria-labelledby="steamEmailModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-3 bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title" id="steamEmailModalLabel">Add Your Email</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Steam's Web API does not expose email addresses. Please provide a valid email address to link it to your <b>KBM vs Controller</b> account. This does not need to be the same email address you use for Steam.</p>
        <form id="steamEmailForm" novalidate>
          <div class="mb-3">
            <label for="steamEmailInput" class="form-label">Email address</label>
            <input type="email" class="form-control bg-dark text-light" id="steamEmailInput" autocomplete="email" required>
          </div>
          <div class="d-flex justify-content-end">
            <button type="submit" id="steamEmailContinue" class="btn btn-outline-light">Continue</button>
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
      const alertKey = data.alertKey || 'googleSigninError';
      Alerts.showModal(alertKey);
    }
  })
  .catch(() => {
    Alerts.showModal('googleSigninNetworkError');
  });
}
</script>

<script>
  (function () {
    const modalEl = document.getElementById('authModal');
    const loginBtn = document.getElementById('loginBtn');
    if (modalEl && loginBtn) {
      if (window.bootstrap && window.bootstrap.Modal) {
        const authModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        loginBtn.addEventListener('click', () => authModal.show());
      } else {
        loginBtn.addEventListener('click', () => {
          modalEl.classList.add('show');
          modalEl.style.display = 'block';
          modalEl.removeAttribute('aria-hidden');
          document.body.classList.add('modal-open');
          if (!document.querySelector('.modal-backdrop')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
          }
        });
      }
  }
})();

  // Helpers for showing/hiding Bootstrap modals when Bootstrap isn't available yet
  function fallbackShowModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');
    if (!document.querySelector('.modal-backdrop')) {
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      document.body.appendChild(backdrop);
    }
  }

  function fallbackHideModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
      backdrop.remove();
    }
  }

  // Steam email collection modal handling
  const steamSignInForm   = document.getElementById('steamSignInForm');
  const steamSignInButton = document.getElementById('steamSignInButton');
  const steamEmailField   = document.getElementById('steamEmailField');
  const steamEmailModalEl = document.getElementById('steamEmailModal');
  const steamEmailInput   = document.getElementById('steamEmailInput');
  const steamEmailForm    = document.getElementById('steamEmailForm');

  let steamEmailModalInstance = null;

  const ensureSteamEmailModal = () => {
    if (!steamEmailModalEl) return null;
    if (steamEmailModalInstance) return steamEmailModalInstance;
    if (window.bootstrap && window.bootstrap.Modal) {
      steamEmailModalInstance = window.bootstrap.Modal.getOrCreateInstance(steamEmailModalEl);
    }
    return steamEmailModalInstance;
  };

  const showSteamEmailModal = () => {
    if (!steamEmailModalEl) return;
    const instance = ensureSteamEmailModal();
    if (instance) {
      instance.show();
    } else {
      fallbackShowModal(steamEmailModalEl);
    }
    if (steamEmailInput) {
      setTimeout(() => steamEmailInput.focus(), 150);
    }
  };

  const hideSteamEmailModal = () => {
    if (!steamEmailModalEl) return;
    const instance = ensureSteamEmailModal();
    if (instance) {
      instance.hide();
    } else {
      fallbackHideModal(steamEmailModalEl);
    }
  };

  if (steamEmailModalEl) {
    steamEmailModalEl.addEventListener('click', evt => {
      if (evt.target === steamEmailModalEl) {
        hideSteamEmailModal();
      }
    });
    const closeBtn = steamEmailModalEl.querySelector('[data-bs-dismiss="modal"]');
    if (closeBtn) {
      closeBtn.addEventListener('click', hideSteamEmailModal);
    }
    steamEmailModalEl.addEventListener('shown.bs.modal', () => {
      if (steamEmailInput) {
        steamEmailInput.focus();
      }
    });
  }

  if (steamSignInButton) {
    steamSignInButton.addEventListener('click', event => {
      event.preventDefault();
      if (steamEmailInput) {
        steamEmailInput.value = steamEmailInput.value.trim();
      }
      showSteamEmailModal();
    });
  }

  if (steamEmailForm && steamSignInForm) {
    steamEmailForm.addEventListener('submit', event => {
      event.preventDefault();
      if (!steamEmailInput) return;
      const email = steamEmailInput.value.trim();
      const lower = email.toLowerCase();
      const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

      if (!validEmail) {
        Alerts.showModal('authInvalidEmail');
        return;
      }
      if (lower.endsWith('@gmail.com')) {
        Alerts.showModal('loginGmailBlock');
        return;
      }

      fetch('steam_email_validate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, csrf_token: csrfToken })
      })
      .then(res => res.json())
      .then(data => {
        if (!data || typeof data.success === 'undefined') {
          throw new Error('invalid_response');
        }
        if (!data.success) {
          const key = data.alertKey || 'authInvalidEmail';
          Alerts.showModal(key);
          return;
        }
        if (steamEmailField) {
          steamEmailField.value = email;
        }
        hideSteamEmailModal();
        steamSignInForm.submit();
      })
      .catch(() => {
        Alerts.showModal('steamEmailNetworkError');
      });
    });
  }

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
    All copyrights and trademarks are property of their respective owners.<br>
    <a href="privacy.php">Privacy Policy</a>
  </p>
</div>
</body>
</html>
