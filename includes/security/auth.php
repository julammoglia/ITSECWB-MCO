<?php

if (!defined('SECURITY_IDLE_TIMEOUT')) {
    define('SECURITY_IDLE_TIMEOUT', 900);
}

if (!defined('SECURITY_ABSOLUTE_TIMEOUT')) {
    define('SECURITY_ABSOLUTE_TIMEOUT', 28800);
}

if (!defined('SECURITY_REGENERATION_INTERVAL')) {
    define('SECURITY_REGENERATION_INTERVAL', 300);
}

if (!function_exists('security_is_https')) {
    function security_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    }
}

if (!function_exists('security_apply_session_settings')) {
    function security_apply_session_settings(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', security_is_https() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => security_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('security_ensure_session_started')) {
    function security_ensure_session_started(): void
    {
        security_apply_session_settings();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        security_bootstrap_authenticated_session();
    }
}

if (!function_exists('security_destroy_session')) {
    function security_destroy_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            security_apply_session_settings();
            session_start();
        }

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

if (!function_exists('security_add_query_param')) {
    function security_add_query_param(string $location, string $key, string $value): string
    {
        $separator = str_contains($location, '?') ? '&' : '?';

        return $location . $separator . rawurlencode($key) . '=' . rawurlencode($value);
    }
}

require_once __DIR__ . '/csrf.php';
require_once dirname(__DIR__) . '/security.php';

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

if (!function_exists('security_mark_session_expired')) {
    function security_mark_session_expired(): void
    {
        $GLOBALS['security_session_expired'] = true;
    }
}

if (!function_exists('security_session_was_expired')) {
    function security_session_was_expired(): bool
    {
        return !empty($GLOBALS['security_session_expired']);
    }
}

if (!function_exists('security_bootstrap_authenticated_session')) {
    function security_bootstrap_authenticated_session(): void
    {
        static $checked = false;

        if ($checked) {
            return;
        }

        $checked = true;

        if (empty($_SESSION['user_id'])) {
            return;
        }

        $now = time();
        $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? $now);
        $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);
        $lastRegeneratedAt = (int) ($_SESSION['last_regenerated_at'] ?? $authenticatedAt);

        $idleExpired = ($now - $lastActivity) > SECURITY_IDLE_TIMEOUT;
        $absoluteExpired = ($now - $authenticatedAt) > SECURITY_ABSOLUTE_TIMEOUT;

        if ($idleExpired || $absoluteExpired) {
            security_log_audit('AUTH', 'FAILED', 'session_expired', [
                'reason' => $idleExpired ? 'idle_timeout' : 'absolute_timeout',
                'user_role' => $_SESSION['user_role'] ?? null,
            ]);
            security_mark_session_expired();
            security_destroy_session();
            return;
        }

        if (($now - $lastRegeneratedAt) >= SECURITY_REGENERATION_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['last_regenerated_at'] = $now;
        }

        $_SESSION['authenticated_at'] = $authenticatedAt;
        $_SESSION['last_activity'] = $now;
        $_SESSION['last_regenerated_at'] = $_SESSION['last_regenerated_at'] ?? $now;
    }
}

if (!function_exists('security_initialize_authenticated_session')) {
    function security_initialize_authenticated_session(): void
    {
        security_ensure_session_started();

        session_regenerate_id(true);

        $now = time();
        $_SESSION['authenticated_at'] = $now;
        $_SESSION['last_activity'] = $now;
        $_SESSION['last_regenerated_at'] = $now;
        $_SESSION[SECURITY_CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}

if (!function_exists('security_redirect')) {
    function security_redirect(string $location): void
    {
        header("Location: $location");
        exit();
    }
}

if (!function_exists('security_logout')) {
    function security_logout(string $redirect = 'Login.php'): void
    {
        security_prevent_cache();
        security_log_audit('AUTH', 'SUCCESS', 'logout', [
            'reason' => 'user_requested',
        ]);
        security_destroy_session();
        security_redirect($redirect);
    }
}

if (!function_exists('security_handle_logout')) {
    function security_handle_logout(string $redirect = 'index.php', string $param = 'logout'): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$param])) {
            security_require_csrf($redirect);
            security_logout($redirect);
        }
    }
}

if (!function_exists('security_require_login')) {
    function security_require_login(string $redirect = 'Login.php'): int
    {
        security_ensure_session_started();

        if (!security_is_logged_in()) {
            $target = security_session_was_expired()
                ? security_add_query_param($redirect, 'session', 'expired')
                : $redirect;
            security_redirect($target);
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
            $responseMessage = security_session_was_expired()
                ? 'Session expired. Please sign in again.'
                : $message;
            echo json_encode(['success' => false, 'error' => $responseMessage]);
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

if (!function_exists('security_require_role_api')) {
    function security_require_role_api(mysqli $conn, string $role, string $message = 'Forbidden.'): int
    {
        $userId = security_require_login_api('Unauthorized.');
        $userRole = security_get_user_role($conn, $userId);

        if ($userRole === null || strcasecmp(trim($userRole), trim($role)) !== 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => $message]);
            exit();
        }

        return $userId;
    }
}
