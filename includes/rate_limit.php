<?php
/**
 * Rate limiting helper function
 * 
 * @param mysqli $conn Database connection
 * @param string $key Rate limit key (e.g., 'login', 'cart_actions', 'payment')
 * @param int $limit Maximum requests allowed in the period
 * @param int $periodSeconds Time window in seconds
 * @return array ['allowed' => bool, 'retry_after' => int]
 */
function rate_limit($conn, $key, $limit, $periodSeconds) {
    // Get client IP
    $ip = get_client_ip();
    
    // Validate IP
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        // Fall back to REMOTE_ADDR if validation fails
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            // If still invalid, allow the request (don't block)
            return ['allowed' => true, 'retry_after' => 0];
        }
    }
    
    $now = time();
    
    try {
        // Check if rate limit entry exists
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
            $window_start = (int)$row['window_start'];
            $count = (int)$row['count'];
            
            // Check if window has expired
            if ($now - $window_start >= $periodSeconds) {
                // Reset window - new request in new window
                $reset_sql = "UPDATE rate_limits SET window_start = ?, count = 1 WHERE rl_key = ? AND ip = ?";
                $reset_stmt = $conn->prepare($reset_sql);
                if ($reset_stmt) {
                    $reset_stmt->bind_param("iss", $now, $key, $ip);
                    $reset_stmt->execute();
                    $reset_stmt->close();
                }
                $check_stmt->close();
                return ['allowed' => true, 'retry_after' => 0];
            } else {
                // Window still active - check if limit exceeded BEFORE incrementing
                if ($count >= $limit) {
                    // Rate limit exceeded - don't increment
                    $retry_after = $periodSeconds - ($now - $window_start);
                    $check_stmt->close();
                    return ['allowed' => false, 'retry_after' => $retry_after];
                } else {
                    // Still under limit - increment and allow
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
            }
        } else {
            // No entry exists, create one with count = 1
            $insert_sql = "INSERT INTO rate_limits (rl_key, ip, window_start, count) VALUES (?, ?, ?, 1)";
            $insert_stmt = $conn->prepare($insert_sql);
            if ($insert_stmt) {
                $insert_stmt->bind_param("ssi", $key, $ip, $now);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            $check_stmt->close();
            return ['allowed' => true, 'retry_after' => 0];
        }
    } catch (Exception $e) {
        // On error, allow the request (fail open)
        return ['allowed' => true, 'retry_after' => 0];
    }
}

/**
 * Get client IP address
 * Checks X-Forwarded-For header first (takes first IP if comma-separated)
 * Falls back to REMOTE_ADDR
 * 
 * @return string Client IP address
 */
function get_client_ip() {
    // Check for X-Forwarded-For header (proxy/load balancer)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    
    // Fall back to REMOTE_ADDR
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Log rate limit block
 * 
 * @param string $ip Client IP
 * @param string $rl_key Rate limit key
 * @param int $retry_after Seconds until retry
 * @param string|null $email User email (optional, for auth endpoints)
 */
function log_rate_limit_block($ip, $rl_key, $retry_after, $email = null) {
    $log_file = __DIR__ . '/../logs/rate_limit.log';
    
    // Create logs directory if it doesn't exist
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $email_hash = $email ? hash('sha256', strtolower(trim($email))) : 'N/A';
    
    $log_entry = sprintf(
        "[%s] IP: %s | Key: %s | Retry-After: %d | Email-Hash: %s\n",
        $timestamp,
        $ip,
        $rl_key,
        $retry_after,
        $email_hash
    );
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Check if request expects JSON response
 * 
 * @return bool True if JSON response is expected
 */
function expects_json() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    
    return (
        strpos($accept, 'application/json') !== false ||
        strtolower($requested_with) === 'xmlhttprequest'
    );
}

/**
 * Send rate limit error response
 * 
 * @param int $retry_after Seconds until retry
 * @param string|null $email User email (optional, for logging)
 * @param string|null $rl_key Rate limit key (for logging)
 * @return array Error info to display on page
 */
function send_rate_limit_error($retry_after, $email = null, $rl_key = null) {
    $ip = get_client_ip();
    
    // Log the block
    log_rate_limit_block($ip, $rl_key ?? $_REQUEST['rl_key'] ?? 'unknown', $retry_after, $email);
    
    // For JSON requests, send 429
    if (expects_json()) {
        http_response_code(429);
        header('Retry-After: ' . $retry_after);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Too many requests. Please try again later.',
            'retry_after' => $retry_after
        ]);
        exit;
    }
    
    // For normal page requests, return error info (don't exit, let page render)
    return [
        'error' => 'Too many requests. Please try again later.',
        'retry_after' => $retry_after
    ];
}
?>
