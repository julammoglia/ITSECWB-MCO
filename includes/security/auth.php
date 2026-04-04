<?php
if (!function_exists('security_ensure_session_started')) {
    function security_ensure_session_started(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('security_redirect')) {
    function security_redirect(string $location): void
    {
        header("Location: $location");
        exit();
    }
}

if (!function_exists('security_handle_logout')) {
    function security_handle_logout(string $redirect = 'index.php', string $param = 'logout'): void
    {
        if (isset($_GET[$param])) {
            session_destroy();
            security_redirect($redirect);
        }
    }
}

if (!function_exists('security_require_login')) {
    function security_require_login(string $redirect = 'Login.php'): int
    {
        if (!isset($_SESSION['user_id'])) {
            security_redirect($redirect);
        }

        return (int) $_SESSION['user_id'];
    }
}

if (!function_exists('security_get_user_role')) {
    function security_get_user_role(mysqli $conn, int $userId): ?string
    {
        $stmt = $conn->prepare("SELECT user_role FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        return $user['user_role'] ?? null;
    }
}

if (!function_exists('security_require_role')) {
    function security_require_role(mysqli $conn, string $role, string $redirect = 'Login.php'): int
    {
        $userId = security_require_login($redirect);
        $userRole = security_get_user_role($conn, $userId);

        if ($userRole !== $role) {
            security_redirect($redirect);
        }

        return $userId;
    }
}
