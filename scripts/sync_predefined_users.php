<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security/password_policy.php';

$users = [
    [
        'user_id' => 1,
        'user_role' => 'Customer',
        'first_name' => 'Customer',
        'last_name' => 'One',
        'email' => 'customer1@gmail.com',
        'phone' => '09000000001',
        'password' => 'Customer1@123456',
    ],
    [
        'user_id' => 2,
        'user_role' => 'Customer',
        'first_name' => 'Customer',
        'last_name' => 'Two',
        'email' => 'customer2@gmail.com',
        'phone' => '09000000002',
        'password' => 'Customer2@123456',
    ],
    [
        'user_id' => 3,
        'user_role' => 'Admin',
        'first_name' => 'Admin',
        'last_name' => 'One',
        'email' => 'admin1@gmail.com',
        'phone' => '09000000004',
        'password' => 'Admin1@123456',
    ],
    [
        'user_id' => 4,
        'user_role' => 'Staff',
        'first_name' => 'Staff',
        'last_name' => 'One',
        'email' => 'staff1@gmail.com',
        'phone' => '09000000003',
        'password' => 'Staff1@123456',
    ],
];

$sql = "
    INSERT INTO users (user_id, user_role, first_name, last_name, email, phone, password, profile_picture)
    VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
    ON DUPLICATE KEY UPDATE
        user_role = VALUES(user_role),
        first_name = VALUES(first_name),
        last_name = VALUES(last_name),
        email = VALUES(email),
        phone = VALUES(phone),
        password = VALUES(password),
        profile_picture = NULL
";

$stmt = $conn->prepare($sql);

echo "Syncing predefined users...\n";

foreach ($users as $user) {
    $hashedPassword = security_hash_password($user['password']);
    $stmt->bind_param(
        'issssss',
        $user['user_id'],
        $user['user_role'],
        $user['first_name'],
        $user['last_name'],
        $user['email'],
        $user['phone'],
        $hashedPassword
    );
    $stmt->execute();

    echo "- Synced {$user['first_name']} {$user['last_name']} ({$user['user_role']})\n";
}

$stmt->close();

echo "Predefined users synced.\n";
