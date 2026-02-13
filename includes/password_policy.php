<?php
// Centralized password policy enforcement
// Rules:
// - Minimum length: 12 characters
// - Must include at least 3 of 4 categories: uppercase, lowercase, digit, special
// - Must not contain whitespace

if (!function_exists('validate_password_policy')) {
    /**
     * Validate a password against the application's policy.
     *
     * @param string $password
     * @param array $context Optional context for additional checks, e.g. ['email' => 'user@example.com', 'first_name' => 'John', 'last_name' => 'Doe']
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    function validate_password_policy(string $password, array $context = []): array
    {
        $errors = [];

        // 1) Length
        if (mb_strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters long.';
        }

        // 2) Whitespace
        if (preg_match('/\s/', $password)) {
            $errors[] = 'Password must not contain whitespace characters.';
        }

        // 3) Character class diversity (at least 3 of 4)
        $hasLower = preg_match('/[a-z]/', $password) === 1;
        $hasUpper = preg_match('/[A-Z]/', $password) === 1;
        $hasDigit = preg_match('/\d/', $password) === 1;
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;
        $classCount = ($hasLower ? 1 : 0) + ($hasUpper ? 1 : 0) + ($hasDigit ? 1 : 0) + ($hasSpecial ? 1 : 0);
        if ($classCount < 3) {
            $errors[] = 'Password must include at least three of the following: uppercase letters, lowercase letters, numbers, and special characters.';
        }

        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
