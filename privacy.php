<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KBM vs Controller - Privacy</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid justify-content-start">
    <a class="navbar-brand">KBM vs Controller</a>
    <a href="index.php" id="home" class="btn btn-outline-light btn-sm" role="button">Home</a>
  </div>
</nav>
<br>
<div class="privacy">
  <h3>KBM vs Controller Privacy Policy</h3>
  <h6><i>Last updated: September 16, 2025</i></h6>

  <br>

  <p>By using this site, you agree to the following:</p>

  <section>
      <h4>1. Information We Collect</h4>
      <ul>
        <li><strong>Account credentials</strong> - When you register using the form, we collect your email address and the password you provide; the password is stored as a hashed value and is checked during login.</li>
        <li><strong>Google sign-in data</strong> - If you choose “Continue with Google,” the app loads Google Identity Services, receives the verified email address and Google account ID from the returned token, and stores that Google ID alongside your account record.</li>
        <li><strong>Voting activity</strong> - Each vote you cast is saved with your user ID, and the service updates or removes the record if you change your choice. Aggregate counts are calculated to display community results for every title you view.</li>
        <li><strong>Password reset data</strong> - When you ask for a password reset, the system generates a temporary token, enforces rate limits, emails reset instructions, and later removes the token after use.</li>
        <li><strong>Diagnostic information</strong> - OAuth-related server errors are appended to a local log file to help troubleshoot authentication issues.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>2. How We Use Information</h4>
      <ul>
        <li><strong>Provide the service</strong> - We use your credentials or Google identity to authenticate you, keep your session active, and associate votes with your account so you can manage them later.</li>
        <li><strong>Protect the platform</strong> - The application embeds CSRF tokens in forms, regenerates session IDs after login, and rate-limits voting calls to guard against fraud and abuse.</li>
        <li><strong>Send account emails</strong> - Password reset messages are delivered from the no-reply address you see in the reset workflow so you can regain access to your account.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>3. Cookies and Similar Technologies</h4>
      <ul>
        <li>We rely on the standard PHP session cookie to remember that you are logged in, store the CSRF token used by authenticated requests, and enforce short-term voting limits; clearing or blocking this cookie will log you out and may disable parts of the site.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>4. Third-Party Services and Content</h4>
      <ul>
        <li><strong>Google reCAPTCHA v2</strong> helps prevent automated abuse on the email login form. Google may collect device or usage data when the widget loads.</li>
        <li><strong>Google Identity Services</strong> powers the “Continue with Google” button and token verification used for authentication.</li>
        <li><strong>Steam CDN assets</strong> (header and capsule images) are requested directly from Valve's servers for Steam-listed titles, which lets them receive standard web request information such as your IP address.</li>
        <li><strong>Interface and donation embeds</strong> load from third-party CDNs (Bootstrap, Bootstrap Icons) and Buy Me A Coffee when you view the relevant pages.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>5. Data Sharing and Disclosure</h4>
      <ul>
        <li>Apart from the integrations listed above, account, vote, and password-reset records are written to our own database via server-side code; we do not publish this information publicly except in aggregated vote totals.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>6. Data Retention</h4>
      <ul>
        <li>Account and vote records remain in our database so you can continue using the service. Password reset tokens are cleared when a new token is issued or once the reset completes. Contact us if you would like assistance deleting an account or removing votes.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>7. Security</h4>
      <ul>
        <li>Passwords are hashed before storage, session IDs are regenerated after successful sign-in, CSRF tokens protect forms and APIs, and voting endpoints throttle rapid submissions to reduce abuse.</li>
        <li>Error logs are maintained for OAuth failures so we can investigate suspicious behavior or outages.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>8. Your Choices and Rights</h4>
      <ul>
        <li>You can sign in with Google or with the email form, update your password through the reset workflow, and request account or vote removal by contacting the site administrator.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>9. Children's Privacy</h4>
      <ul>
        <li>KBM vs Controller is intended for general PC gaming audiences and is not designed for children under 13. If you believe we have collected a child's information, please notify us so we can delete it.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>10. Changes to This Policy</h4>
      <ul>
        <li>We may update this notice from time to time. Significant changes will be posted on the site, and the “Last updated” date will reflect the revision.</li>
      </ul>
    </section>

    <br>

    <section>
      <h4>11. Contact Us</h4>
      <ul>
        <li>For questions, privacy requests, or feedback, please see the About page.</li>
      </ul>
    </section>
</div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
        const link = document.getElementById('showEmailLink');
        const placeholder = document.getElementById('emailPlaceholder');
        const email = <?php echo json_encode($contactEmail); ?>;
        if (!link || !placeholder || !email) return;
    
        link.addEventListener('click', e => {
            e.preventDefault();
            placeholder.innerHTML = ' email <a href="mailto:' + email + '">' + email + '</a>';
            link.style.display = 'none';
        });
    });
  </script>
<div id="footer">
  <p class="text-center mb-0">
    This website is not affiliated with Steam, Valve, or any game developer/publisher.<br>
    All copyrights and trademarks are property of their respective owners.<br>
    <a href="privacy.php">Privacy Policy</a>
  </p>
</div>
</body>
</html>
