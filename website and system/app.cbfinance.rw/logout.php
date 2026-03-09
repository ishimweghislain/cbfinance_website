<?php
session_start();

// Destroy all session data

$_SESSION = array();

// Destroy the session cookie

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session

session_destroy();

echo "<script>window.location.href='index.php?page=login'</script>";

exit;
?>