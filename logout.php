<?php
session_start(); // Start the session
session_destroy(); // Destroy all session data
header('Location: Login.php'); // Redirect to login page
exit;
?>
