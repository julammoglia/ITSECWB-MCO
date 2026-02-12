<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/rate_limit.php';

header('Content-Type: application/json');

$test = $_POST['test'] ?? '';

switch ($test) {
    case 'login':
        testLoginRateLimit();
        break;
    case 'register':
        testRegisterRateLimit();
        break;
    case 'forgot_password':
        testForgotPasswordRateLimit();
        break;
    case 'db_status':
        checkDatabaseStatus();
        break;
    case 'view_logs':
        viewLogs();
        break;
    case 'clear_logs':
        clearLogs();
        break;
    case 'view_table':
        viewDatabaseTable();
        break;
    default:
        echo json_encode(['error' => 'Invalid test']);
}

function testLoginRateLimit() {
    global $conn;
    $result = rate_limit($conn, 'login', 8, 600);
    echo json_encode([
        'allowed' => $result['allowed'],
        'retry_after' => $result['retry_after'],
        'limit' => 5,
        'count' => $result['allowed'] ? 'N/A' : 'Limited'
    ]);
}

function testRegisterRateLimit() {
    global $conn;
    $result = rate_limit($conn, 'register', 5, 600);
    echo json_encode([
        'allowed' => $result['allowed'],
        'retry_after' => $result['retry_after'],
        'limit' => 3,
        'count' => $result['allowed'] ? 'N/A' : 'Limited'
    ]);
}

function testForgotPasswordRateLimit() {
    global $conn;
    $result = rate_limit($conn, 'forgot_password', 8, 600);
    echo json_encode([
        'allowed' => $result['allowed'],
        'retry_after' => $result['retry_after'],
        'limit' => 3,
        'count' => $result['allowed'] ? 'N/A' : 'Limited'
    ]);
}


function checkDatabaseStatus() {
    global $conn;
    try {
        // Check if table exists
        $result = $conn->query("SHOW TABLES LIKE 'rate_limits'");
        $table_exists = $result && $result->num_rows > 0;
        
        // Count records
        $count_result = $conn->query("SELECT COUNT(*) as cnt FROM rate_limits");
        $record_count = 0;
        if ($count_result) {
            $row = $count_result->fetch_assoc();
            $record_count = $row['cnt'];
        }
        
        echo json_encode([
            'success' => true,
            'table_exists' => $table_exists,
            'record_count' => $record_count
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function viewLogs() {
    $log_file = __DIR__ . '/logs/rate_limit.log';
    
    if (!file_exists($log_file)) {
        echo json_encode(['logs' => []]);
        return;
    }
    
    $logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo json_encode(['logs' => array_reverse($logs)]);
}

function clearLogs() {
    $log_file = __DIR__ . '/logs/rate_limit.log';
    
    if (file_exists($log_file)) {
        unlink($log_file);
    }
    
    echo json_encode(['success' => true]);
}

function viewDatabaseTable() {
    global $conn;
    try {
        $result = $conn->query("SELECT id, rl_key, ip, window_start, count, updated_at FROM rate_limits ORDER BY updated_at DESC LIMIT 50");
        $rows = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        
        echo json_encode(['rows' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
