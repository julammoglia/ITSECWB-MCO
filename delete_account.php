<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    $userId = security_require_login('Login.php');

    // Fetch the user's role
    $role = security_get_user_role($conn, $userId);

    if ($role !== null) {

        // Allow deletion only if role is 'customer'
        if (strtolower(trim($role)) === 'customer') {
            $deleteStmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $deleteStmt->bind_param("i", $userId);
            $deleteStmt->execute();

            session_destroy();
            header("Location: index.php");
            exit();
        } else {
            // Staff/other users are not allowed to delete
            header("Location: User.php?error=unauthorized");
            exit();
        }
    } else {
        // User not found
        header("Location: User.php?error=notfound");
        exit();
    }
} else {
    // Not logged in
    header("Location: User.php?error=unauthorized");
    exit();
}
