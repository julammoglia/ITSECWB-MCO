<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/rate_limit.php';

// Simple test interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Limit Tester</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }
        .test-section h2 {
            margin-top: 0;
            color: #007bff;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px 5px 5px 0;
        }
        button:hover {
            background: #0056b3;
        }
        button.danger {
            background: #dc3545;
        }
        button.danger:hover {
            background: #c82333;
        }
        button.reset {
            background: #6c757d;
        }
        button.reset:hover {
            background: #5a6268;
        }
        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .result.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .result.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .counter {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            margin-left: 10px;
            font-weight: bold;
        }
        .log-viewer {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
        }
        .log-viewer.empty {
            color: #888;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #007bff;
            color: white;
        }
        table tr:hover {
            background: #f5f5f5;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🔒 Rate Limit Tester</h1>
    
    <div class="test-section">
        <h2>Test 1: Login Rate Limit (5 per 10 minutes)</h2>
        <p>Simulates rapid login attempts. Should block on the 6th request.</p>
        <button onclick="testLoginRateLimit()">Test Login Rate Limit</button>
        <span class="counter" id="login-counter">0/5</span>
        <div id="login-result"></div>
    </div>

    <div class="test-section">
        <h2>Test 2: Registration Rate Limit (3 per 10 minutes)</h2>
        <p>Simulates rapid registration attempts. Should block on the 4th request.</p>
        <button onclick="testRegisterRateLimit()">Test Registration Rate Limit</button>
        <button class="reset" onclick="resetRegisterCounter()">Reset Counter</button>
        <span class="counter" id="register-counter">0/3</span>
        <div id="register-result"></div>
    </div>

    <div class="test-section">
        <h2>Test 3: Forgot Password Rate Limit (3 per 10 minutes)</h2>
        <p>Simulates rapid password reset attempts. Should block on the 4th request.</p>
        <button onclick="testForgotPasswordRateLimit()">Test Forgot Password Rate Limit</button>
        <button class="reset" onclick="resetForgotPasswordCounter()">Reset Counter</button>
        <span class="counter" id="forgot-password-counter">0/3</span>
        <div id="forgot-password-result"></div>
    </div>

    <div class="test-section">
        <h2>Database Status</h2>
        <button onclick="checkDatabaseStatus()">Check Database</button>
        <div id="db-result"></div>
    </div>

    <div class="test-section">
        <h2>Rate Limit Log Viewer</h2>
        <button onclick="viewLogs()">Refresh Logs</button>
        <button class="danger" onclick="clearLogs()">Clear Logs</button>
        <div id="log-viewer" class="log-viewer empty">No logs yet. Trigger a rate limit to see entries.</div>
    </div>

    <div class="test-section">
        <h2>Database Rate Limits Table</h2>
        <button onclick="viewDatabaseTable()">View Rate Limits Table</button>
        <div id="db-table"></div>
    </div>
</div>

<script>
    let loginCount = 0;
    let registerCount = 0;
    let forgotPasswordCount = 0;

    function testLoginRateLimit() {
        loginCount++;
        document.getElementById('login-counter').textContent = loginCount + '/5';
        
        fetch('test_rate_limit_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'test=login'
        })
        .then(response => response.json())
        .then(data => {
            const resultDiv = document.getElementById('login-result');
            const resultClass = data.allowed ? 'success' : 'error';
            const message = data.allowed 
                ? `✓ Request ${loginCount} ALLOWED\nCount: ${data.count}/${data.limit}`
                : `✗ Request ${loginCount} BLOCKED\nRetry after: ${data.retry_after}s`;
            
            resultDiv.innerHTML = `<div class="result ${resultClass}">${message}</div>`;
        })
        .catch(error => {
            document.getElementById('login-result').innerHTML = `<div class="result error">Error: ${error.message}</div>`;
        });
    }

    function testRegisterRateLimit() {
        registerCount++;
        document.getElementById('register-counter').textContent = registerCount + '/3';
        
        fetch('test_rate_limit_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'test=register'
        })
        .then(response => response.json())
        .then(data => {
            const resultDiv = document.getElementById('register-result');
            const resultClass = data.allowed ? 'success' : 'error';
            const message = data.allowed 
                ? `✓ Request ${registerCount} ALLOWED\nYou can register.`
                : `✗ Request ${registerCount} BLOCKED\nRetry after: ${data.retry_after}s`;
            
            resultDiv.innerHTML = `<div class="result ${resultClass}">${message}</div>`;
            
            // Auto-refresh logs and table
            setTimeout(() => {
                viewLogs();
                viewDatabaseTable();
            }, 500);
        })
        .catch(error => {
            document.getElementById('register-result').innerHTML = `<div class="result error">Error: ${error.message}</div>`;
        });
    }

    function resetRegisterCounter() {
        registerCount = 0;
        document.getElementById('register-counter').textContent = '0/3';
        document.getElementById('register-result').innerHTML = '';
    }

    function testForgotPasswordRateLimit() {
        forgotPasswordCount++;
        document.getElementById('forgot-password-counter').textContent = forgotPasswordCount + '/3';
        
        fetch('test_rate_limit_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'test=forgot_password'
        })
        .then(response => response.json())
        .then(data => {
            const resultDiv = document.getElementById('forgot-password-result');
            const resultClass = data.allowed ? 'success' : 'error';
            const message = data.allowed 
                ? `✓ Request ${forgotPasswordCount} ALLOWED\nYou can reset password.`
                : `✗ Request ${forgotPasswordCount} BLOCKED\nRetry after: ${data.retry_after}s`;
            
            resultDiv.innerHTML = `<div class="result ${resultClass}">${message}</div>`;
            
            // Auto-refresh logs and table
            setTimeout(() => {
                viewLogs();
                viewDatabaseTable();
            }, 500);
        })
        .catch(error => {
            document.getElementById('forgot-password-result').innerHTML = `<div class="result error">Error: ${error.message}</div>`;
        });
    }

    function resetForgotPasswordCounter() {
        forgotPasswordCount = 0;
        document.getElementById('forgot-password-counter').textContent = '0/3';
        document.getElementById('forgot-password-result').innerHTML = '';
    }

    function checkDatabaseStatus() {
        fetch('test_rate_limit_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'test=db_status'
        })
        .then(response => response.json())
        .then(data => {
            const resultDiv = document.getElementById('db-result');
            const resultClass = data.success ? 'success' : 'error';
            const message = data.success 
                ? `✓ Database Connected\nTable exists: ${data.table_exists ? 'YES' : 'NO'}\nRecords: ${data.record_count}`
                : `✗ Database Error: ${data.error}`;
            
            resultDiv.innerHTML = `<div class="result ${resultClass}">${message}</div>`;
        })
        .catch(error => {
            document.getElementById('db-result').innerHTML = `<div class="result error">Error: ${error.message}</div>`;
        });
    }

    function viewLogs() {
        fetch('test_rate_limit_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'test=view_logs'
        })
        .then(response => response.json())
        .then(data => {
            const logViewer = document.getElementById('log-viewer');
            if (data.logs && data.logs.length > 0) {
                logViewer.textContent = data.logs.join('\n');
                logViewer.classList.remove('empty');
            } else {
                logViewer.textContent = 'No logs yet. Trigger a rate limit to see entries.';
                logViewer.classList.add('empty');
            }
        })
        .catch(error => {
            document.getElementById('log-viewer').textContent = 'Error loading logs: ' + error.message;
        });
    }

    function clearLogs() {
        if (confirm('Are you sure you want to clear all rate limit logs?')) {
            fetch('test_rate_limit_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'test=clear_logs'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('log-viewer').textContent = 'Logs cleared.';
                    document.getElementById('log-viewer').classList.add('empty');
                }
            });
        }
    }

    function viewDatabaseTable() {
        fetch('test_rate_limit_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'test=view_table'
        })
        .then(response => response.json())
        .then(data => {
            const tableDiv = document.getElementById('db-table');
            if (data.rows && data.rows.length > 0) {
                let html = '<table><thead><tr><th>ID</th><th>Key</th><th>IP</th><th>Window Start</th><th>Count</th><th>Updated At</th></tr></thead><tbody>';
                data.rows.forEach(row => {
                    html += `<tr><td>${row.id}</td><td>${row.rl_key}</td><td>${row.ip}</td><td>${row.window_start}</td><td>${row.count}</td><td>${row.updated_at}</td></tr>`;
                });
                html += '</tbody></table>';
                tableDiv.innerHTML = html;
            } else {
                tableDiv.innerHTML = '<div class="result info">No records in rate_limits table yet.</div>';
            }
        })
        .catch(error => {
            document.getElementById('db-table').innerHTML = `<div class="result error">Error: ${error.message}</div>`;
        });
    }

    // Auto-load logs on page load
    window.addEventListener('load', function() {
        viewLogs();
        checkDatabaseStatus();
    });
</script>

</body>
</html>
