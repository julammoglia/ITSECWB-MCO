<?php
require_once dirname(__DIR__) . '/security.php';

/**
 * Rate limiting helper function
 *
 * @param mysqli $conn Database connection
 * @param string $key Rate limit key (e.g., 'login', 'cart_actions', 'payment')
 * @param int $limit Maximum requests allowed in the period
 * @param int $periodSeconds Time window in seconds
 * @return array ['allowed' => bool, 'retry_after' => int]
 */
function rate_limit($conn, $key, $limit, $periodSeconds)
{
    $ip = get_client_ip();

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['allowed' => true, 'retry_after' => 0];
        }
    }

    $now = time();

    try {
        $check_sql = "SELECT window_start, count FROM rate_limits WHERE rl_key = ? AND ip = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $check_stmt->bind_param("ss", $key, $ip);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $window_start = (int) $row['window_start'];
            $count = (int) $row['count'];

            if ($now - $window_start >= $periodSeconds) {
                $reset_sql = "UPDATE rate_limits SET window_start = ?, count = 1 WHERE rl_key = ? AND ip = ?";
                $reset_stmt = $conn->prepare($reset_sql);
                if ($reset_stmt) {
                    $reset_stmt->bind_param("iss", $now, $key, $ip);
                    $reset_stmt->execute();
                    $reset_stmt->close();
                }
                $check_stmt->close();
                return ['allowed' => true, 'retry_after' => 0];
            }

            if ($count >= $limit) {
                $retry_after = $periodSeconds - ($now - $window_start);
                $check_stmt->close();
                return ['allowed' => false, 'retry_after' => $retry_after];
            }

            $increment_sql = "UPDATE rate_limits SET count = count + 1, updated_at = NOW() WHERE rl_key = ? AND ip = ?";
            $increment_stmt = $conn->prepare($increment_sql);
            if ($increment_stmt) {
                $increment_stmt->bind_param("ss", $key, $ip);
                $increment_stmt->execute();
                $increment_stmt->close();
            }
            $check_stmt->close();
            return ['allowed' => true, 'retry_after' => 0];
        }

        $insert_sql = "INSERT INTO rate_limits (rl_key, ip, window_start, count) VALUES (?, ?, ?, 1)";
        $insert_stmt = $conn->prepare($insert_sql);
        if ($insert_stmt) {
            $insert_stmt->bind_param("ssi", $key, $ip, $now);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        $check_stmt->close();
        return ['allowed' => true, 'retry_after' => 0];
    } catch (Exception $e) {
        return ['allowed' => true, 'retry_after' => 0];
    }
}

/**
 * Get client IP address.
 *
 * @return string
 */
function get_client_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Log rate limit block.
 *
 * @param string $ip
 * @param string $rl_key
 * @param int $retry_after
 * @param string|null $email
 */
function log_rate_limit_block($ip, $rl_key, $retry_after, $email = null)
{
    $email_hash = $email ? hash('sha256', strtolower(trim($email))) : 'N/A';

    security_log_audit('AUTH', 'FAILED', 'rate_limited', [
        'key' => $rl_key,
        'retry_after' => $retry_after,
        'email_hash' => $email_hash,
    ], $email ?: null);
}

/**
 * Check if request expects JSON response.
 *
 * @return bool
 */
function expects_json()
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return (
        strpos($accept, 'application/json') !== false ||
        strtolower($requested_with) === 'xmlhttprequest'
    );
}

/**
 * Send rate limit error response.
 *
 * @param int $retry_after
 * @param string|null $email
 * @param string|null $rl_key
 * @return array
 */
function send_rate_limit_error($retry_after, $email = null, $rl_key = null)
{
    $ip = get_client_ip();

    log_rate_limit_block($ip, $rl_key ?? $_REQUEST['rl_key'] ?? 'unknown', $retry_after, $email);

    if (expects_json()) {
        http_response_code(429);
        header('Retry-After: ' . $retry_after);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Too many requests. Please try again later.',
            'retry_after' => $retry_after,
        ]);
        exit;
    }

    return [
        'error' => 'Too many requests. Please try again later.',
        'retry_after' => $retry_after,
    ];
}
