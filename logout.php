<?php
require_once __DIR__ . '/../config/config.php';
// Clear session and redirect home
session_unset();
session_destroy();
header('Location: index.php');
exit;
