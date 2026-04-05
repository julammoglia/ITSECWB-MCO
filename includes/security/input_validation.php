<?php

if (!function_exists('security_normalize_email')) {
    function security_normalize_email(string $email): string
    {
        return strtolower(trim($email));
    }
}

if (!function_exists('security_is_valid_email')) {
    function security_is_valid_email(string $email): bool
    {
        $email = security_normalize_email($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        [$localPart] = explode('@', $email, 2);

        if ($localPart === '' || $localPart[0] === '.' || str_contains($localPart, '..')) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9][a-z0-9._%+\-]*@[a-z0-9.-]+\.[a-z]{2,}$/', $email);
    }
}

if (!function_exists('security_is_valid_name')) {
    function security_is_valid_name(string $name): bool
    {
        $name = trim($name);

        return $name !== '' && preg_match("/^[\p{L}]+(?:[ .'-][\p{L}]+)*$/u", $name) === 1;
    }
}

if (!function_exists('security_is_valid_phone')) {
    function security_is_valid_phone(string $phone): bool
    {
        return preg_match('/^09\d{9}$/', trim($phone)) === 1;
    }
}

if (!function_exists('security_phone_to_local_format')) {
    function security_phone_to_local_format(?string $phone): string
    {
        $phone = trim((string) $phone);

        if ($phone !== '' && preg_match('/^\+639\d{9}$/', $phone) === 1) {
            return '0' . substr($phone, 3);
        }

        return $phone;
    }
}
