<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/input_validation.php';
require_once __DIR__ . '/password_policy.php';
require_once dirname(__DIR__) . '/security.php';

if (!function_exists('security_require_admin_access')) {
    function security_require_admin_access(mysqli $conn, bool $expectsJson = false): int
    {
        return $expectsJson
            ? security_require_role_api($conn, 'Admin', 'Forbidden.')
            : security_require_role($conn, 'Admin', 'Login.php', 'Index.php');
    }
}

if (!function_exists('security_admin_validation_result')) {
    function security_admin_validation_result(bool $valid, ?string $error = null, array $data = []): array
    {
        return [
            'valid' => $valid,
            'error' => $error,
            'data' => $data,
        ];
    }
}

if (!function_exists('security_admin_normalize_string')) {
    function security_admin_normalize_string($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}

if (!function_exists('security_admin_validate_text_pattern')) {
    function security_admin_validate_text_pattern($value, int $maxLength, string $pattern, bool $required = true)
    {
        $normalized = security_admin_normalize_string($value);

        if ($normalized === null) {
            return $required ? false : '';
        }

        if (mb_strlen($normalized) > $maxLength) {
            return false;
        }

        return preg_match($pattern, $normalized) === 1 ? $normalized : false;
    }
}

if (!function_exists('security_admin_record_exists')) {
    function security_admin_record_exists(mysqli $conn, string $sql, string $types, ...$params): bool
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('security_admin_validate_category_code')) {
    function security_admin_validate_category_code(mysqli $conn, $value)
    {
        $categoryCode = security_validate_positive_number($value, false);
        if ($categoryCode === false) {
            return false;
        }

        return security_admin_record_exists(
            $conn,
            "SELECT 1 FROM categories WHERE category_code = ?",
            "i",
            $categoryCode
        ) ? $categoryCode : false;
    }
}

if (!function_exists('security_admin_validate_product_code')) {
    function security_admin_validate_product_code(mysqli $conn, $value)
    {
        $productCode = security_validate_positive_number($value, false);
        if ($productCode === false) {
            return false;
        }

        return security_admin_record_exists(
            $conn,
            "SELECT 1 FROM products WHERE product_code = ?",
            "i",
            $productCode
        ) ? $productCode : false;
    }
}

if (!function_exists('security_admin_validate_order_id')) {
    function security_admin_validate_order_id(mysqli $conn, $value)
    {
        $orderId = security_validate_positive_number($value, false);
        if ($orderId === false) {
            return false;
        }

        return security_admin_record_exists(
            $conn,
            "SELECT 1 FROM orders WHERE order_id = ?",
            "i",
            $orderId
        ) ? $orderId : false;
    }
}

if (!function_exists('security_admin_validate_customer_id')) {
    function security_admin_validate_customer_id(mysqli $conn, $value)
    {
        $customerId = security_validate_positive_number($value, false);
        if ($customerId === false) {
            return false;
        }

        return security_admin_record_exists(
            $conn,
            "SELECT 1 FROM users WHERE user_id = ? AND user_role = 'Customer'",
            "i",
            $customerId
        ) ? $customerId : false;
    }
}

if (!function_exists('security_admin_validate_staff_target_id')) {
    function security_admin_validate_staff_target_id(mysqli $conn, $value, int $currentAdminId)
    {
        $targetUserId = security_validate_positive_number($value, false);
        if ($targetUserId === false || $targetUserId === $currentAdminId) {
            return false;
        }

        return security_admin_record_exists(
            $conn,
            "SELECT 1 FROM users WHERE user_id = ? AND user_role IN ('Staff', 'Admin')",
            "i",
            $targetUserId
        ) ? $targetUserId : false;
    }
}

if (!function_exists('security_admin_validate_product_payload')) {
    function security_admin_validate_product_payload(mysqli $conn, array $input): array
    {
        $productName = security_admin_validate_text_pattern(
            $input['product_name'] ?? null,
            45,
            "/^(?=.*[\p{L}\p{N}])[\p{L}\p{N}][\p{L}\p{N}\s&+.,()#'\/-]*$/u"
        );
        $categoryCode = security_admin_validate_category_code($conn, $input['category_code'] ?? null);
        $description = security_admin_validate_text_pattern(
            $input['description'] ?? null,
            45,
            "/^(?=.*[\p{L}\p{N}])[\p{L}\p{N}\s&+.,()#:'\/-]*$/u",
            false
        );
        $stockQty = security_validate_positive_number($input['stock_qty'] ?? null, false);
        $srpPhp = security_validate_positive_number($input['srp_php'] ?? null, true);

        if ($productName === false) {
            return security_admin_validation_result(false, 'Enter a valid product name up to 45 characters.');
        }

        if ($categoryCode === false) {
            return security_admin_validation_result(false, 'Select a valid category.');
        }

        if ($description === false) {
            return security_admin_validation_result(false, 'Enter a valid product description up to 45 characters.');
        }

        if ($stockQty === false) {
            return security_admin_validation_result(false, 'Enter a valid stock quantity greater than 0.');
        }

        if ($srpPhp === false) {
            return security_admin_validation_result(false, 'Enter a valid price greater than 0.');
        }

        return security_admin_validation_result(true, null, [
            'product_name' => $productName,
            'category_code' => (int) $categoryCode,
            'description' => $description,
            'stock_qty' => (int) $stockQty,
            'srp_php' => (float) $srpPhp,
        ]);
    }
}

if (!function_exists('security_admin_validate_stock_payload')) {
    function security_admin_validate_stock_payload(mysqli $conn, array $input): array
    {
        $productCode = security_admin_validate_product_code($conn, $input['product_code'] ?? null);
        $newStock = security_validate_non_negative_integer($input['new_stock'] ?? null);

        if ($productCode === false) {
            return security_admin_validation_result(false, 'Select a valid product.');
        }

        if ($newStock === false) {
            return security_admin_validation_result(false, 'Enter a valid stock quantity of 0 or more.');
        }

        return security_admin_validation_result(true, null, [
            'product_code' => (int) $productCode,
            'new_stock' => (int) $newStock,
        ]);
    }
}

if (!function_exists('security_admin_validate_role')) {
    function security_admin_validate_role($value)
    {
        $role = security_admin_normalize_string($value);
        if ($role === null) {
            return false;
        }

        if (strcasecmp($role, 'Staff') === 0) {
            return 'Staff';
        }

        return false;
    }
}

if (!function_exists('security_admin_validate_order_status')) {
    function security_admin_validate_order_status($value)
    {
        $status = security_admin_normalize_string($value);
        if ($status === null) {
            return false;
        }

        foreach (['Processing', 'Shipped', 'Delivered'] as $allowedStatus) {
            if (strcasecmp($status, $allowedStatus) === 0) {
                return $allowedStatus;
            }
        }

        return false;
    }
}

if (!function_exists('security_admin_validate_staff_payload')) {
    function security_admin_validate_staff_payload(mysqli $conn, array $input): array
    {
        $firstName = security_admin_validate_text_pattern(
            $input['first_name'] ?? null,
            45,
            "/^[\p{L}]+(?:[ .'-][\p{L}]+)*$/u"
        );
        $lastName = security_admin_validate_text_pattern(
            $input['last_name'] ?? null,
            45,
            "/^[\p{L}]+(?:[ .'-][\p{L}]+)*$/u"
        );
        $email = security_normalize_email($input['email'] ?? '');
        $phone = security_admin_normalize_string($input['phone'] ?? null);
        $password = (string) ($input['password'] ?? '');
        $role = security_admin_validate_role($input['user_role'] ?? null);

        if ($firstName === false || $lastName === false) {
            return security_admin_validation_result(false, 'Enter a valid first and last name.');
        }

        if ($email === '' || mb_strlen($email) > 45 || !security_is_valid_email($email)) {
            return security_admin_validation_result(false, 'Enter a valid email address.');
        }

        if ($phone === null || !security_is_valid_phone($phone)) {
            return security_admin_validation_result(false, 'Enter a valid PH phone number (09XXXXXXXXX).');
        }

        if (security_admin_record_exists($conn, "SELECT 1 FROM users WHERE email = ?", "s", $email)) {
            return security_admin_validation_result(false, 'An account with this email already exists.');
        }

        if ($role === false) {
            return security_admin_validation_result(false, 'Only staff accounts can be created from the admin dashboard.');
        }

        $policyResult = validate_password_policy($password);
        if (!$policyResult['valid']) {
            return security_admin_validation_result(false, implode(' ', $policyResult['errors']));
        }

        return security_admin_validation_result(true, null, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => security_hash_password($password),
            'user_role' => $role,
        ]);
    }
}

if (!function_exists('security_admin_validate_order_status_payload')) {
    function security_admin_validate_order_status_payload(mysqli $conn, array $input): array
    {
        $orderId = security_admin_validate_order_id($conn, $input['order_id'] ?? null);
        $newStatus = security_admin_validate_order_status($input['new_status'] ?? null);

        if ($orderId === false) {
            return security_admin_validation_result(false, 'Select a valid order.');
        }

        if ($newStatus === false) {
            return security_admin_validation_result(false, 'Select a valid order status.');
        }

        return security_admin_validation_result(true, null, [
            'order_id' => (int) $orderId,
            'new_status' => $newStatus,
        ]);
    }
}

if (!function_exists('security_admin_validate_delete_staff_payload')) {
    function security_admin_validate_delete_staff_payload(mysqli $conn, array $input, int $currentAdminId): array
    {
        $targetUserId = security_admin_validate_staff_target_id($conn, $input['user_id'] ?? null, $currentAdminId);

        if ($targetUserId === false) {
            return security_admin_validation_result(false, 'Select a valid staff or admin account to delete.');
        }

        return security_admin_validation_result(true, null, [
            'user_id' => (int) $targetUserId,
        ]);
    }
}

if (!function_exists('security_admin_validate_delete_customer_payload')) {
    function security_admin_validate_delete_customer_payload(mysqli $conn, array $input): array
    {
        $customerId = security_admin_validate_customer_id($conn, $input['customer_id'] ?? null);

        if ($customerId === false) {
            return security_admin_validation_result(false, 'Select a valid customer account to delete.');
        }

        return security_admin_validation_result(true, null, [
            'customer_id' => (int) $customerId,
        ]);
    }
}

if (!function_exists('security_admin_validate_delete_product_payload')) {
    function security_admin_validate_delete_product_payload(mysqli $conn, array $input): array
    {
        $productCode = security_admin_validate_product_code($conn, $input['product_id'] ?? null);

        if ($productCode === false) {
            return security_admin_validation_result(false, 'Select a valid product to delete.');
        }

        return security_admin_validation_result(true, null, [
            'product_id' => (int) $productCode,
        ]);
    }
}
