<?php

if (!defined('SECURITY_APP_TIMEZONE')) {
    define('SECURITY_APP_TIMEZONE', 'Asia/Manila');
}

if (!defined('SECURITY_GENERIC_ERROR_MESSAGE')) {
    define('SECURITY_GENERIC_ERROR_MESSAGE', 'Something went wrong. Please try again later.');
}

if (!function_exists('security_apply_timezone')) {
    function security_apply_timezone(): void
    {
        if (date_default_timezone_get() !== SECURITY_APP_TIMEZONE) {
            date_default_timezone_set(SECURITY_APP_TIMEZONE);
        }
    }
}

security_apply_timezone();

if (!function_exists('security_get_project_root')) {
    function security_get_project_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('security_get_config_directory')) {
    function security_get_config_directory(): string
    {
        return security_get_project_root() . '/config';
    }
}

if (!function_exists('security_get_debug_state_file_path')) {
    function security_get_debug_state_file_path(): string
    {
        return security_get_config_directory() . '/debug_mode.json';
    }
}

if (!function_exists('security_get_error_log_file_path')) {
    function security_get_error_log_file_path(): string
    {
        return security_get_log_directory() . '/error.log';
    }
}

if (!function_exists('security_ensure_directory')) {
    function security_ensure_directory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }
}

if (!function_exists('security_is_debug_mode')) {
    function security_is_debug_mode(): bool
    {
        if (array_key_exists('security_debug_mode_cache', $GLOBALS)) {
            return (bool) $GLOBALS['security_debug_mode_cache'];
        }

        $debugFile = security_get_debug_state_file_path();
        if (!is_file($debugFile)) {
            $GLOBALS['security_debug_mode_cache'] = false;
            return false;
        }

        $contents = file_get_contents($debugFile);
        if ($contents === false) {
            $GLOBALS['security_debug_mode_cache'] = false;
            return false;
        }

        $decoded = json_decode($contents, true);
        $GLOBALS['security_debug_mode_cache'] = is_array($decoded) && !empty($decoded['enabled']);

        return (bool) $GLOBALS['security_debug_mode_cache'];
    }
}

if (!function_exists('security_set_debug_mode')) {
    function security_set_debug_mode(bool $enabled): bool
    {
        security_ensure_directory(security_get_config_directory());

        $payload = [
            'enabled' => $enabled,
            'updated_at' => date('c'),
        ];

        $written = file_put_contents(
            security_get_debug_state_file_path(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($written === false) {
            return false;
        }

        $GLOBALS['security_debug_mode_cache'] = $enabled;
        ini_set('display_errors', $enabled ? '1' : '0');

        return true;
    }
}

if (!function_exists('security_request_expects_json')) {
    function security_request_expects_json(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $headers = headers_list();

        if (str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest') {
            return true;
        }

        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type: application/json') === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('security_build_error_context')) {
    function security_build_error_context(Throwable $exception, string $type = 'uncaught_exception'): array
    {
        return [
            'timestamp' => date('c'),
            'type' => $type,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user' => security_get_log_user_label(),
        ];
    }
}

if (!function_exists('security_log_error_details')) {
    function security_log_error_details(Throwable $exception, string $type = 'uncaught_exception'): void
    {
        $context = security_build_error_context($exception, $type);
        security_ensure_directory(security_get_log_directory());

        $entry = sprintf(
            "[%s]\nType: %s\nMessage: %s\nFile: %s\nLine: %d\nUser: %s\nMethod: %s\nURI: %s\nIP: %s\nTrace:\n%s\n%s\n",
            date('Y-m-d h:i:s A'),
            $context['type'],
            $context['message'],
            $context['file'],
            $context['line'],
            $context['user'],
            $context['method'],
            $context['uri'],
            $context['ip'],
            $context['trace'],
            str_repeat('-', 80)
        );

        error_log($entry, 3, security_get_error_log_file_path());
    }
}

if (!function_exists('security_render_exception_payload')) {
    function security_render_exception_payload(Throwable $exception): array
    {
        $payload = [
            'success' => false,
            'error' => SECURITY_GENERIC_ERROR_MESSAGE,
        ];

        if (security_is_debug_mode()) {
            $payload['debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return $payload;
    }
}

if (!function_exists('security_render_exception_page')) {
    function security_render_exception_page(Throwable $exception): string
    {
        $isDebug = security_is_debug_mode();
        $debugMessage = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $debugFile = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
        $debugLine = (int) $exception->getLine();
        $debugTrace = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        $title = $isDebug ? 'Debug Error' : 'Error';
        $subtitle = $isDebug
            ? 'Debug mode is enabled, so the full error details are shown below.'
            : SECURITY_GENERIC_ERROR_MESSAGE;

        $debugSection = '';
        if ($isDebug) {
            $debugSection = '
                <div class="debug-list">
                    <div class="debug-item">
                        <span class="debug-label">Message</span>
                        <div class="debug-value">' . $debugMessage . '</div>
                    </div>
                    <div class="debug-item">
                        <span class="debug-label">File</span>
                        <div class="debug-value">' . $debugFile . '</div>
                    </div>
                    <div class="debug-item">
                        <span class="debug-label">Line</span>
                        <div class="debug-value">' . $debugLine . '</div>
                    </div>
                </div>
                <div class="trace-wrap">
                    <span class="debug-label">Stack Trace</span>
                    <pre class="trace">' . $debugTrace . '</pre>
                </div>
            ';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <style>
        body {
            font-family: Segoe UI, Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 24px;
        }

        .error-card {
            width: 100%;
            max-width: ' . ($isDebug ? '860px' : '640px') . ';
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin: 0 0 12px;
            font-size: 28px;
            color: #0f172a;
        }

        .subtitle {
            margin: 0;
            color: #475569;
            font-size: 16px;
            line-height: 1.6;
        }

        .debug-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-top: 24px;
        }

        .debug-item,
        .trace-wrap {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
        }

        .debug-label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }

        .debug-value {
            color: #0f172a;
            line-height: 1.6;
            word-break: break-word;
        }

        .trace-wrap {
            margin-top: 16px;
        }

        .trace {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            color: #334155;
            line-height: 1.55;
            font-size: 14px;
            max-height: 360px;
            overflow: auto;
        }

        @media (max-width: 720px) {
            .error-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="error-card">
        <h1>' . $title . '</h1>
        <p class="subtitle">' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p>
        ' . $debugSection . '
    </div>
</body>
</html>';
    }
}

if (!function_exists('security_handle_exception')) {
    function security_handle_exception(Throwable $exception): void
    {
        static $handling = false;

        if ($handling) {
            exit(1);
        }

        $handling = true;

        security_log_error_details($exception);

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (PHP_SAPI === 'cli') {
            $payload = security_render_exception_payload($exception);
            fwrite(STDERR, ($payload['debug']['message'] ?? $payload['error']) . PHP_EOL);
            if (!empty($payload['debug']['trace'])) {
                fwrite(STDERR, $payload['debug']['trace'] . PHP_EOL);
            }
            exit(1);
        }

        if (security_request_expects_json()) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(security_render_exception_payload($exception));
            exit;
        }

        echo security_render_exception_page($exception);
        exit;
    }
}

if (!function_exists('security_handle_php_error')) {
    function security_handle_php_error(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }
}

if (!function_exists('security_handle_shutdown_error')) {
    function security_handle_shutdown_error(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        $exception = new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        );

        security_handle_exception($exception);
    }
}

if (!function_exists('security_register_error_handlers')) {
    function security_register_error_handlers(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;

        error_reporting(E_ALL);
        ini_set('display_errors', security_is_debug_mode() ? '1' : '0');

        set_error_handler('security_handle_php_error');
        set_exception_handler('security_handle_exception');
        register_shutdown_function('security_handle_shutdown_error');
    }
}

security_register_error_handlers();

if (!function_exists('security_get_log_directory')) {
    function security_get_log_directory(): string
    {
        return dirname(__DIR__) . '/logs';
    }
}

if (!function_exists('security_get_log_file_path')) {
    function security_get_log_file_path(): string
    {
        return security_get_log_directory() . '/security.log';
    }
}

if (!function_exists('security_ensure_log_directory')) {
    function security_ensure_log_directory(): void
    {
        $logDir = security_get_log_directory();

        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
    }
}

if (!function_exists('security_normalize_log_category')) {
    function security_normalize_log_category(string $category): string
    {
        $normalized = strtoupper(trim($category));
        $allowed = ['AUTH', 'TRANSACTION', 'ADMIN'];

        return in_array($normalized, $allowed, true) ? $normalized : 'ADMIN';
    }
}

if (!function_exists('security_normalize_log_status')) {
    function security_normalize_log_status(string $status): string
    {
        return strtoupper(trim($status)) === 'SUCCESS' ? 'SUCCESS' : 'FAILED';
    }
}

if (!function_exists('security_sanitize_log_text')) {
    function security_sanitize_log_text($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $text = trim((string) $value);
        $text = preg_replace('/\s+/', ' ', $text);

        return $text ?? '';
    }
}

if (!function_exists('security_format_log_details')) {
    function security_format_log_details(array $details = []): string
    {
        if ($details === []) {
            return '';
        }

        $meaningfulDetails = [];

        foreach ($details as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $formattedValue = security_sanitize_log_text($value);

            if ($formattedValue === '') {
                continue;
            }

            $meaningfulDetails[(string) $key] = $formattedValue;
        }

        if ($meaningfulDetails === []) {
            return '';
        }

        if (count($meaningfulDetails) === 1) {
            $singleKey = array_key_first($meaningfulDetails);
            if (in_array($singleKey, ['reason', 'summary', 'message', 'details'], true)) {
                return (string) $meaningfulDetails[$singleKey];
            }
        }

        $parts = [];

        foreach ($meaningfulDetails as $key => $formattedValue) {
            $label = ucwords(str_replace('_', ' ', (string) $key));
            $parts[] = $label . ': ' . $formattedValue;
        }

        return implode(' | ', $parts);
    }
}

if (!function_exists('security_get_log_user_label')) {
    function security_get_log_user_label($user = null): string
    {
        $sessionUserId = (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id']))
            ? (int) $_SESSION['user_id']
            : 0;

        if (is_int($user) && $user > 0 && $sessionUserId === $user) {
            $name = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
            if ($name !== '') {
                return $name . ' (#' . $sessionUserId . ')';
            }

            if (!empty($_SESSION['email'])) {
                return (string) $_SESSION['email'];
            }
        }

        if (is_string($user)) {
            $user = trim($user);
            if ($user !== '') {
                return $user;
            }
        }

        if (is_int($user) && $user > 0) {
            return 'User #' . $user;
        }

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
            $name = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
            if ($name !== '') {
                return $name . ' (#' . (int) $_SESSION['user_id'] . ')';
            }

            if (!empty($_SESSION['email'])) {
                return (string) $_SESSION['email'];
            }

            return 'User #' . (int) $_SESSION['user_id'];
        }

        return 'Guest';
    }
}

if (!function_exists('security_write_log_entry')) {
    function security_write_log_entry(array $payload): void
    {
        security_ensure_log_directory();

        error_log(
            security_format_audit_log_line($payload) . PHP_EOL,
            3,
            security_get_log_file_path()
        );
    }
}

if (!function_exists('security_format_audit_log_line')) {
    function security_format_audit_log_line(array $payload): string
    {
        $prefix = implode(' ', [
            '[' . date('Y-m-d h:i:s A', strtotime((string) ($payload['timestamp'] ?? 'now'))) . ']',
            '[' . security_normalize_log_category((string) ($payload['category'] ?? 'ADMIN')) . ']',
            '[' . security_normalize_log_status((string) ($payload['status'] ?? 'FAILED')) . ']',
        ]);

        $segments = [
            'User: ' . security_sanitize_log_text($payload['user'] ?? 'Guest'),
            'Action: ' . security_sanitize_log_text($payload['action'] ?? 'unknown_action'),
        ];

        $details = security_sanitize_log_text($payload['details'] ?? '');
        $ip = security_sanitize_log_text($payload['ip'] ?? '');
        $uri = security_sanitize_log_text($payload['uri'] ?? '');

        if ($details !== '') {
            $segments[] = 'Details: ' . $details;
        }

        if ($ip !== '') {
            $segments[] = 'IP: ' . $ip;
        }

        if ($uri !== '') {
            $segments[] = 'URI: ' . $uri;
        }

        return $prefix . ' ' . implode(' | ', $segments);
    }
}

if (!function_exists('security_log_audit')) {
    function security_log_audit(
        string $category,
        string $status,
        string $action,
        array $details = [],
        $user = null
    ): void {
        $payload = [
            'timestamp' => date('c'),
            'category' => security_normalize_log_category($category),
            'status' => security_normalize_log_status($status),
            'user' => security_get_log_user_label($user),
            'action' => security_sanitize_log_text($action) ?: 'unknown_action',
            'details' => security_format_log_details($details),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        ];

        security_write_log_entry($payload);
    }
}

if (!function_exists('security_guess_legacy_log_category')) {
    function security_guess_legacy_log_category(string $eventType, array $context = []): string
    {
        $needle = strtoupper($eventType . ' ' . ($context['action'] ?? ''));

        if (str_contains($needle, 'LOGIN') || str_contains($needle, 'REGISTER') || str_contains($needle, 'LOGOUT') || str_contains($needle, 'AUTH')) {
            return 'AUTH';
        }

        if (str_contains($needle, 'CHECKOUT') || str_contains($needle, 'PAYMENT') || str_contains($needle, 'TRANSACTION')) {
            return 'TRANSACTION';
        }

        return 'ADMIN';
    }
}

if (!function_exists('security_guess_legacy_log_status')) {
    function security_guess_legacy_log_status(string $eventType, array $context = []): string
    {
        $needle = strtoupper($eventType . ' ' . ($context['status'] ?? '') . ' ' . ($context['reason'] ?? ''));

        return (str_contains($needle, 'SUCCESS') || str_contains($needle, 'SUCCEEDED'))
            ? 'SUCCESS'
            : 'FAILED';
    }
}

if (!function_exists('security_parse_log_line')) {
    function security_parse_log_line(string $line): ?array
    {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            return security_parse_plaintext_audit_log_line($line);
        }

        if (isset($decoded['category'], $decoded['status'], $decoded['action'])) {
            return [
                'timestamp' => $decoded['timestamp'] ?? '',
                'category' => security_normalize_log_category((string) $decoded['category']),
                'status' => security_normalize_log_status((string) $decoded['status']),
                'user' => security_sanitize_log_text($decoded['user'] ?? 'Guest'),
                'action' => security_sanitize_log_text($decoded['action'] ?? 'unknown_action'),
                'details' => is_array($decoded['details'] ?? null)
                    ? security_format_log_details($decoded['details'])
                    : security_sanitize_log_text($decoded['details'] ?? ''),
                'ip' => security_sanitize_log_text($decoded['ip'] ?? ''),
                'uri' => security_sanitize_log_text($decoded['uri'] ?? ''),
            ];
        }

        $context = is_array($decoded['context'] ?? null) ? $decoded['context'] : [];

        return [
            'timestamp' => $decoded['timestamp'] ?? '',
            'category' => security_guess_legacy_log_category((string) ($decoded['event'] ?? '')),
            'status' => security_guess_legacy_log_status((string) ($decoded['event'] ?? ''), $context),
            'user' => security_get_log_user_label(
                isset($context['user'])
                    ? $context['user']
                    : (isset($context['email']) ? $context['email'] : ($context['user_id'] ?? null))
            ),
            'action' => security_sanitize_log_text($context['action'] ?? ($decoded['event'] ?? 'legacy_event')),
            'details' => security_format_log_details(array_merge(['event' => $decoded['event'] ?? 'legacy_event'], $context)),
            'ip' => security_sanitize_log_text($decoded['ip'] ?? ''),
            'uri' => security_sanitize_log_text($decoded['uri'] ?? ''),
        ];
    }
}

if (!function_exists('security_parse_plaintext_audit_log_line')) {
    function security_parse_plaintext_audit_log_line(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (preg_match('/^\[(?<timestamp>[^\]]+)\]\s*\|?\s*\[(?<category>[^\]]+)\]\s*\|?\s*\[(?<status>[^\]]+)\]\s*\|?\s*(?<rest>.*)$/', $line, $matches) !== 1) {
            return null;
        }

        $entry = [
            'timestamp' => '',
            'category' => security_normalize_log_category($matches['category']),
            'status' => security_normalize_log_status($matches['status']),
            'user' => 'Guest',
            'action' => 'unknown_action',
            'details' => '',
            'ip' => '',
            'uri' => '',
        ];

        $timestamp = DateTime::createFromFormat('Y-m-d h:i:s A', trim($matches['timestamp']));
        if ($timestamp instanceof DateTime) {
            $entry['timestamp'] = $timestamp->format('c');
        } else {
            $entry['timestamp'] = trim($matches['timestamp']);
        }

        $segments = array_filter(array_map('trim', explode('|', trim($matches['rest']))), static fn ($segment) => $segment !== '');

        foreach ($segments as $segment) {
            if (!str_contains($segment, ':')) {
                continue;
            }

            [$label, $value] = array_map('trim', explode(':', $segment, 2));
            $normalizedLabel = strtolower($label);

            switch ($normalizedLabel) {
                case 'user':
                    $entry['user'] = security_sanitize_log_text($value);
                    break;
                case 'action':
                    $entry['action'] = security_sanitize_log_text($value);
                    break;
                case 'details':
                    $normalizedDetails = security_sanitize_log_text($value);
                    if (preg_match('/^(Reason|Summary|Message|Details):\s*(.+)$/i', $normalizedDetails, $detailMatches) === 1) {
                        $normalizedDetails = security_sanitize_log_text($detailMatches[2]);
                    }
                    $entry['details'] = $normalizedDetails;
                    break;
                case 'ip':
                    $entry['ip'] = security_sanitize_log_text($value);
                    break;
                case 'uri':
                    $entry['uri'] = security_sanitize_log_text($value);
                    break;
            }
        }

        return $entry;
    }
}

if (!function_exists('security_read_audit_log_entries')) {
    function security_read_audit_log_entries(): array
    {
        $logFile = security_get_log_file_path();
        if (!is_file($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $entries = [];

        foreach ($lines as $line) {
            $entry = security_parse_log_line($line);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}

if (!function_exists('security_stream_audit_log_csv')) {
    function security_stream_audit_log_csv(?string $filename = null): void
    {
        $entries = security_read_audit_log_entries();
        $filename = $filename ?: 'security-log-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $handle = fopen('php://output', 'w');
        if ($handle === false) {
            exit;
        }

        fputcsv($handle, ['Timestamp', 'Category', 'Status', 'User', 'Action', 'Details', 'IP', 'URI']);

        foreach ($entries as $entry) {
            fputcsv($handle, [
                $entry['timestamp'],
                $entry['category'],
                $entry['status'],
                $entry['user'],
                $entry['action'],
                $entry['details'],
                $entry['ip'],
                $entry['uri'],
            ]);
        }

        fclose($handle);
        exit;
    }
}

if (!function_exists('log_event')) {
    function log_event(string $eventType, array $context = []): void
    {
        $category = security_guess_legacy_log_category($eventType, $context);
        $status = security_guess_legacy_log_status($eventType, $context);
        $action = $context['action'] ?? strtolower(trim($eventType));
        $user = $context['user'] ?? ($context['email'] ?? ($context['user_id'] ?? null));

        security_log_audit($category, $status, $action, array_merge(['event' => $eventType], $context), $user);
    }
}

if (!function_exists('security_validate_positive_number')) {
    function security_validate_positive_number($value, bool $allowFloat = false)
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null || !is_scalar($value)) {
            return false;
        }

        if ($allowFloat) {
            if (!is_numeric($value)) {
                return false;
            }

            $number = (float) $value;

            if ($number <= 0) {
                return false;
            }

            return $number;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT);
        if ($normalized === false) {
            return false;
        }

        $number = (int) $normalized;

        if ($number <= 0) {
            return false;
        }

        return $number;
    }
}

if (!function_exists('security_validate_non_negative_integer')) {
    function security_validate_non_negative_integer($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null || !is_scalar($value)) {
            return false;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT);
        if ($normalized === false) {
            return false;
        }

        $number = (int) $normalized;

        if ($number < 0) {
            return false;
        }

        return $number;
    }
}

if (!function_exists('security_sanitize_limited_string')) {
    function security_sanitize_limited_string($value, int $maxLength)
    {
        if (!is_scalar($value)) {
            return false;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if (mb_strlen($value) > $maxLength) {
            return false;
        }

        // Require at least one letter or number so punctuation-only values like "" are rejected.
        if (preg_match('/[A-Za-z0-9]/', $value) !== 1) {
            return false;
        }

        return $value;
    }
}
