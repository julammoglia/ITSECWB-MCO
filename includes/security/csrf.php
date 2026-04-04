<?php

if (!defined('SECURITY_CSRF_TOKEN_KEY')) {
    define('SECURITY_CSRF_TOKEN_KEY', 'csrf_token');
}

if (!function_exists('security_get_csrf_token')) {
    function security_get_csrf_token(): string
    {
        security_ensure_session_started();

        if (empty($_SESSION[SECURITY_CSRF_TOKEN_KEY])) {
            $_SESSION[SECURITY_CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[SECURITY_CSRF_TOKEN_KEY];
    }
}

if (!function_exists('security_csrf_input')) {
    function security_csrf_input(): string
    {
        return '<input type="hidden" name="csrf_token" value="' .
            htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') .
            '">';
    }
}

if (!function_exists('security_csrf_meta_tag')) {
    function security_csrf_meta_tag(): string
    {
        return '<meta name="csrf-token" content="' .
            htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') .
            '">';
    }
}

if (!function_exists('security_verify_csrf_token')) {
    function security_verify_csrf_token(?string $token): bool
    {
        security_ensure_session_started();

        if (!is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION[SECURITY_CSRF_TOKEN_KEY] ?? '';

        return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('security_get_request_csrf_token')) {
    function security_get_request_csrf_token(string $field = 'csrf_token'): ?string
    {
        $token = $_POST[$field] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        return is_string($token) ? $token : null;
    }
}

if (!function_exists('security_require_csrf')) {
    function security_require_csrf(string $redirect = 'Index.php', string $messageParam = 'error', string $messageValue = 'csrf'): void
    {
        if (security_verify_csrf_token(security_get_request_csrf_token())) {
            return;
        }

        http_response_code(403);
        security_redirect(security_add_query_param($redirect, $messageParam, $messageValue));
    }
}

if (!function_exists('security_require_csrf_api')) {
    function security_require_csrf_api(string $message = 'Invalid security token.'): void
    {
        if (security_verify_csrf_token(security_get_request_csrf_token())) {
            return;
        }

        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $message]);
        exit();
    }
}
