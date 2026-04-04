<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security/password_policy.php';

echo "Starting users table migration...\n";

$changes = [];

function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $escapedColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '{$escapedColumn}'");
    $exists = $result !== false && $result->num_rows > 0;

    return $exists;
}

function hasIndex(mysqli $conn, string $table, string $indexName): bool
{
    $escapedIndexName = $conn->real_escape_string($indexName);
    $result = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '{$escapedIndexName}'");
    $exists = $result !== false && $result->num_rows > 0;

    return $exists;
}

if (!hasColumn($conn, 'users', 'phone')) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(45) DEFAULT NULL AFTER `email`");
    $changes[] = "Added missing `phone` column.";
} else {
    $changes[] = "`phone` column already exists.";
}

if (!hasIndex($conn, 'users', 'users_email_unique')) {
    $duplicates = $conn->query("
        SELECT email, COUNT(*) AS duplicate_count
        FROM users
        WHERE email IS NOT NULL AND email <> ''
        GROUP BY email
        HAVING COUNT(*) > 1
    ");

    if ($duplicates && $duplicates->num_rows === 0) {
        $conn->query("ALTER TABLE `users` ADD UNIQUE KEY `users_email_unique` (`email`)");
        $changes[] = "Added unique index on `email`.";
    } else {
        $changes[] = "Skipped unique email index because duplicate emails exist.";
    }
} else {
    $changes[] = "Unique email index already exists.";
}

$usersResult = $conn->query("SELECT user_id, email, password FROM users ORDER BY user_id");
$updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");

$rehashCount = 0;

while ($user = $usersResult->fetch_assoc()) {
    $password = (string) ($user['password'] ?? '');
    $passwordInfo = password_get_info($password);

    if ($password === '' || ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown') {
        continue;
    }

    $hashedPassword = security_hash_password($password);
    $userId = (int) $user['user_id'];

    $updateStmt->bind_param('si', $hashedPassword, $userId);
    $updateStmt->execute();
    $rehashCount++;
}

$updateStmt->close();

$changes[] = "Rehashed {$rehashCount} legacy plaintext password(s).";

echo "Migration completed.\n\n";

foreach ($changes as $change) {
    echo "- {$change}\n";
}
