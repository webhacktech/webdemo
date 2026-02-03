<?php
session_start();
$_SESSION = []; // Clear session
session_destroy(); // Destroy the session
header('Location: login.php');
exit;
?>