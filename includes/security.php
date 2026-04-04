<?php

if (!function_exists('log_event')) {
    function log_event(string $eventType, array $context = []): void
    {
        $logDir = dirname(__DIR__) . '/logs';
        $logFile = $logDir . '/security.log';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $payload = [
            'timestamp' => date('c'),
            'event' => $eventType,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'context' => $context,
        ];

        error_log(json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $logFile);
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
