<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
require_once 'includes/db.php';

$userId = security_require_login('Login.php');

$role = security_get_user_role($conn, $userId);

if ($role === null) {
    header("Location: User.php?error=notfound");
    exit();
}

if (strtolower(trim($role)) !== 'customer') {
    header("Location: User.php?error=unauthorized");
    exit();
}

$deleteStmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$deleteStmt->bind_param("i", $userId);
$deleteStmt->execute();

security_logout('Index.php');
