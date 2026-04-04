<?php
if (!function_exists('security_ensure_session_started')) {
    function security_ensure_session_started(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('security_prevent_cache')) {
    function security_prevent_cache(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Expires: Tue, 01 Jan 2000 00:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }
}

if (!function_exists('security_is_logged_in')) {
    function security_is_logged_in(): bool
    {
        security_ensure_session_started();

        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('security_redirect')) {
    function security_redirect(string $location): void
    {
        header("Location: $location");
        exit();
    }
}

if (!function_exists('security_destroy_session')) {
    function security_destroy_session(): void
    {
        security_ensure_session_started();

        $_SESSION = [];
        session_unset();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}

if (!function_exists('security_logout')) {
    function security_logout(string $redirect = 'Login.php'): void
    {
        security_prevent_cache();
        security_destroy_session();
        security_redirect($redirect);
    }
}

if (!function_exists('security_handle_logout')) {
    function security_handle_logout(string $redirect = 'index.php', string $param = 'logout'): void
    {
        if (isset($_GET[$param])) {
            security_logout($redirect);
        }
    }
}

if (!function_exists('security_require_login')) {
    function security_require_login(string $redirect = 'Login.php'): int
    {
        security_ensure_session_started();

        if (!security_is_logged_in()) {
            security_redirect($redirect);
        }

        security_prevent_cache();

        return (int) $_SESSION['user_id'];
    }
}

if (!function_exists('security_require_login_api')) {
    function security_require_login_api(string $message = 'Unauthorized.'): int
    {
        security_ensure_session_started();
        security_prevent_cache();

        if (!security_is_logged_in()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => $message]);
            exit();
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
    function security_require_role(
        mysqli $conn,
        string $role,
        string $redirect = 'Login.php',
        ?string $unauthorizedRedirect = null
    ): int
    {
        $userId = security_require_login($redirect);
        $userRole = security_get_user_role($conn, $userId);

        if ($userRole === null || strcasecmp(trim($userRole), trim($role)) !== 0) {
            security_redirect($unauthorizedRedirect ?? $redirect);
        }

        return $userId;
    }
}
