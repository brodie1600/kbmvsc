<?php
require_once __DIR__ . '/../config/config.php';

$contactEmail = $_ENV['CONTACT_EMAIL'] ?? getenv('CONTACT_EMAIL') ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KBM vs Controller - About</title>
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
<div class="announce">
  <h3>KBM vs Controller</h3>
  <p>This lightweight application was designed to provide PC gamers with a quick and easy resource to determine which games are better played with a keyboard and mouse versus a controller. Some of you may do this already when starting a new game. For some titles, the difference is negligible. But for others, it may offer a completely different playing experience! <b>KBM vs Controller</b> is a centralized page that allows users to vote on which method they recommend and view the results of nearly every modern PC game.</p>
  <p>If you think a game should be added to the list, see any incorrect or mismatched information, or encounter any issues while using the site, 
      <a href="#" id="showEmailLink">click this link</a><span id="emailPlaceholder"></span>.</p><br>
  <p>Like what I'm doing?<br>
  <a href="https://www.buymeacoffee.com/kbmvscontroller" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/arial-yellow.png" alt="Buy Me A Coffee" style="height: 40px !important;width: 144px !important;" ></a></p>
  <p>💚Zach</p>
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
