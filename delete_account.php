<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Fetch the user's role
    $stmt = $conn->prepare("SELECT user_role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $role = $user['user_role'];

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
