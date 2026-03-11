<?php
session_start();

// Log activity BEFORE destroying session
require_once 'config/database.php';
require_once 'includes/activity_logger.php';
$conn = getConnection();

if ($conn && isset($_SESSION['user_id'])) {
    logActivity($conn, 'logout', 'user', $_SESSION['user_id'], "User {$_SESSION['username']} logged out.");
}

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

echo "<script>
    localStorage.removeItem('authSession');
    localStorage.removeItem('authExpiry');
    window.location.href='login.php?logout=success';
</script>";
exit;
?>