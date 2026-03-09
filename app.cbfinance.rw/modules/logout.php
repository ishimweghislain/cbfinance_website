<?php
// logout.php
?>
<script>
    localStorage.removeItem('authSession');
    localStorage.removeItem('authExpiry');
    window.location.href = 'login.php';
</script>
