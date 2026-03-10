<?php
/**
 * System Activity Logging Helper
 */

function logActivity($conn, $action_type, $entity_type = null, $entity_id = null, $description = '') {
    // Start session if needed
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    $user_id    = $_SESSION['user_id']  ?? 0;
    $username   = $_SESSION['username'] ?? 'guest';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $sql = "INSERT INTO activity_logs (user_id, username, action_type, entity_type, entity_id, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('isssssss', $user_id, $username, $action_type, $entity_type, $entity_id, $description, $ip_address, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}
