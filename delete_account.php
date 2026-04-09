<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
require_once 'includes/db.php';
require_once 'includes/db_operations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    security_redirect('User.php');
}

security_require_csrf('User.php', 'error', 'csrf');

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

if (!db_delete_user_account($conn, $userId)) {
    header("Location: User.php?error=notfound");
    exit();
}

security_logout('Index.php');
