<?php

if (!function_exists('db_fetch_product_snapshot')) {
    function db_fetch_product_snapshot(mysqli $conn, int $productCode, bool $lockForUpdate = false): ?array
    {
        $query = "
            SELECT product_code, category_code, product_name, description, stock_qty
            FROM products
            WHERE product_code = ?
        ";

        if ($lockForUpdate) {
            $query .= " FOR UPDATE";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $productCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $product ?: null;
    }
}

if (!function_exists('db_fetch_order_status')) {
    function db_fetch_order_status(mysqli $conn, int $orderId, bool $lockForUpdate = false): ?string
    {
        $query = "SELECT order_status FROM orders WHERE order_id = ?";

        if ($lockForUpdate) {
            $query .= " FOR UPDATE";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row['order_status'] ?? null;
    }
}

if (!function_exists('db_fetch_user_snapshot')) {
    function db_fetch_user_snapshot(mysqli $conn, int $userId, bool $lockForUpdate = false): ?array
    {
        $query = "
            SELECT user_id, user_role, first_name, last_name, email, phone, profile_picture
            FROM users
            WHERE user_id = ?
        ";

        if ($lockForUpdate) {
            $query .= " FOR UPDATE";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $user ?: null;
    }
}

if (!function_exists('db_insert_inventory_log')) {
    function db_insert_inventory_log(mysqli $conn, int $productCode, int $oldQty, int $newQty): void
    {
        $stmt = $conn->prepare("
            INSERT INTO inventory_log (product_code, old_qty, new_qty, change_date)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP())
        ");
        $stmt->bind_param("iii", $productCode, $oldQty, $newQty);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('db_insert_order_status_log')) {
    function db_insert_order_status_log(mysqli $conn, int $orderId, string $oldStatus, string $newStatus): void
    {
        $stmt = $conn->prepare("
            INSERT INTO order_status_log (order_id, old_status, new_status, change_date)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iss", $orderId, $oldStatus, $newStatus);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('db_insert_product_deletion_log')) {
    function db_insert_product_deletion_log(mysqli $conn, array $product): void
    {
        $stmt = $conn->prepare("
            INSERT INTO product_deletion_log (
                product_code, product_name, category_code, description, deletion_date
            )
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP())
        ");
        $stmt->bind_param(
            "isis",
            $product['product_code'],
            $product['product_name'],
            $product['category_code'],
            $product['description']
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('db_insert_user_deletion_log')) {
    function db_insert_user_deletion_log(mysqli $conn, array $user): void
    {
        $stmt = $conn->prepare("
            INSERT INTO customer_deletion_log (user_id, first_name, last_name, deletion_date)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP())
        ");
        $stmt->bind_param("iss", $user['user_id'], $user['first_name'], $user['last_name']);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('db_insert_customer_edit_log')) {
    function db_insert_customer_edit_log(
        mysqli $conn,
        int $userId,
        string $oldFirstName,
        string $newFirstName,
        string $oldLastName,
        string $newLastName
    ): void {
        $stmt = $conn->prepare("
            INSERT INTO customer_edit_log (
                user_id,
                old_first_name,
                new_first_name,
                old_last_name,
                new_last_name
            )
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $userId, $oldFirstName, $newFirstName, $oldLastName, $newLastName);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('db_apply_product_stock_value')) {
    function db_apply_product_stock_value(
        mysqli $conn,
        int $productCode,
        int $newStock,
        bool $manageTransaction = true
    ): bool {
        if ($newStock < 0) {
            throw new RuntimeException('Stock quantity cannot be negative.');
        }

        if ($manageTransaction) {
            $conn->begin_transaction();
        }

        try {
            $product = db_fetch_product_snapshot($conn, $productCode, true);

            if ($product === null) {
                if ($manageTransaction) {
                    $conn->rollback();
                }
                return false;
            }

            $currentStock = (int) $product['stock_qty'];
            if ($currentStock !== $newStock) {
                $stmt = $conn->prepare("UPDATE products SET stock_qty = ? WHERE product_code = ?");
                $stmt->bind_param("ii", $newStock, $productCode);
                $stmt->execute();
                $stmt->close();

                db_insert_inventory_log($conn, $productCode, $currentStock, $newStock);
            }

            if ($manageTransaction) {
                $conn->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($manageTransaction) {
                $conn->rollback();
            }

            throw $e;
        }
    }
}

if (!function_exists('db_add_new_product')) {
    function db_add_new_product(
        mysqli $conn,
        string $productName,
        int $categoryCode,
        string $description,
        int $stockQty,
        float $srpPhp
    ): bool {
        $stmt = $conn->prepare(
            "INSERT INTO products (product_name, category_code, description, stock_qty, srp_php)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sisid", $productName, $categoryCode, $description, $stockQty, $srpPhp);
        $executed = $stmt->execute();
        $stmt->close();

        return $executed;
    }
}

if (!function_exists('db_update_product_stock')) {
    function db_update_product_stock(
        mysqli $conn,
        int $productCode,
        int $newStock,
        bool $manageTransaction = true
    ): bool {
        return db_apply_product_stock_value($conn, $productCode, $newStock, $manageTransaction);
    }
}

if (!function_exists('db_decrement_product_stock')) {
    function db_decrement_product_stock(
        mysqli $conn,
        int $productCode,
        int $quantityToSubtract,
        bool $manageTransaction = true
    ): bool {
        if ($quantityToSubtract < 0) {
            throw new RuntimeException('Stock adjustment quantity must be positive.');
        }

        if ($manageTransaction) {
            $conn->begin_transaction();
        }

        try {
            $product = db_fetch_product_snapshot($conn, $productCode, true);

            if ($product === null) {
                if ($manageTransaction) {
                    $conn->rollback();
                }
                return false;
            }

            $newStock = (int) $product['stock_qty'] - $quantityToSubtract;
            $updated = db_apply_product_stock_value($conn, $productCode, $newStock, false);

            if ($manageTransaction) {
                $conn->commit();
            }

            return $updated;
        } catch (Throwable $e) {
            if ($manageTransaction) {
                $conn->rollback();
            }

            throw $e;
        }
    }
}

if (!function_exists('db_update_order_status')) {
    function db_update_order_status(mysqli $conn, int $orderId, string $newStatus): bool
    {
        $conn->begin_transaction();

        try {
            $currentStatus = db_fetch_order_status($conn, $orderId, true);

            if ($currentStatus === null) {
                $conn->rollback();
                return false;
            }

            if ($currentStatus !== $newStatus) {
                $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                $stmt->bind_param("si", $newStatus, $orderId);
                $stmt->execute();
                $stmt->close();

                db_insert_order_status_log($conn, $orderId, $currentStatus, $newStatus);
            }

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('db_assign_order_to_staff')) {
    function db_assign_order_to_staff(mysqli $conn, int $userId, int $orderId): bool
    {
        $conn->begin_transaction();

        try {
            $duplicateStmt = $conn->prepare("
                SELECT 1
                FROM staff_assigned_orders
                WHERE order_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $duplicateStmt->bind_param("i", $orderId);
            $duplicateStmt->execute();
            $duplicateResult = $duplicateStmt->get_result();
            $alreadyAssigned = (bool) ($duplicateResult && $duplicateResult->fetch_assoc());
            $duplicateStmt->close();

            if ($alreadyAssigned) {
                throw new RuntimeException('Order is already assigned to a staff member.');
            }

            $status = 'ASSIGNED';
            $stmt = $conn->prepare("
                INSERT INTO staff_assigned_orders (user_id, order_id, status)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $userId, $orderId, $status);
            $executed = $stmt->execute();
            $stmt->close();

            $conn->commit();
            return $executed;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('db_update_staff_assignment_status')) {
    function db_update_staff_assignment_status(mysqli $conn, int $userId, int $orderId, string $newStatus): bool
    {
        $conn->begin_transaction();

        try {
            if ($newStatus === 'COMPLETED') {
                $orderStatus = db_fetch_order_status($conn, $orderId, true);

                if ($orderStatus !== 'Delivered') {
                    throw new RuntimeException(
                        'Cannot mark order as COMPLETED. Order must be in Delivered status first.'
                    );
                }
            }

            $stmt = $conn->prepare("
                UPDATE staff_assigned_orders
                SET status = ?
                WHERE order_id = ? AND user_id = ?
            ");
            $stmt->bind_param("sii", $newStatus, $orderId, $userId);
            $stmt->execute();
            $updated = $stmt->affected_rows > 0;
            $stmt->close();

            if (!$updated) {
                throw new RuntimeException('Assigned order could not be updated.');
            }

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('db_delete_product')) {
    function db_delete_product(mysqli $conn, int $productCode): bool
    {
        $conn->begin_transaction();

        try {
            $product = db_fetch_product_snapshot($conn, $productCode, true);

            if ($product === null) {
                $conn->rollback();
                return false;
            }

            $deleteCartStmt = $conn->prepare("DELETE FROM cart WHERE product_code = ?");
            $deleteCartStmt->bind_param("i", $productCode);
            $deleteCartStmt->execute();
            $deleteCartStmt->close();

            $deleteFavoritesStmt = $conn->prepare("DELETE FROM isfavorite WHERE product_code = ?");
            $deleteFavoritesStmt->bind_param("i", $productCode);
            $deleteFavoritesStmt->execute();
            $deleteFavoritesStmt->close();

            $deleteOrderItemsStmt = $conn->prepare("DELETE FROM order_items WHERE product_code = ?");
            $deleteOrderItemsStmt->bind_param("i", $productCode);
            $deleteOrderItemsStmt->execute();
            $deleteOrderItemsStmt->close();

            $deleteProductStmt = $conn->prepare("DELETE FROM products WHERE product_code = ?");
            $deleteProductStmt->bind_param("i", $productCode);
            $deleteProductStmt->execute();
            $deleted = $deleteProductStmt->affected_rows > 0;
            $deleteProductStmt->close();

            if (!$deleted) {
                throw new RuntimeException('Product could not be deleted.');
            }

            db_insert_product_deletion_log($conn, $product);

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('db_delete_user_account')) {
    function db_delete_user_account(mysqli $conn, int $targetUserId): bool
    {
        $conn->begin_transaction();

        try {
            $user = db_fetch_user_snapshot($conn, $targetUserId, true);

            if ($user === null) {
                $conn->rollback();
                return false;
            }

            $orderIds = [];
            $ordersStmt = $conn->prepare("SELECT order_id FROM orders WHERE user_id = ?");
            $ordersStmt->bind_param("i", $targetUserId);
            $ordersStmt->execute();
            $ordersResult = $ordersStmt->get_result();

            while ($row = $ordersResult->fetch_assoc()) {
                $orderIds[] = (int) $row['order_id'];
            }

            $ordersStmt->close();

            if ($orderIds !== []) {
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $types = str_repeat('i', count($orderIds));

                $deleteAssignedByOrderStmt = $conn->prepare(
                    "DELETE FROM staff_assigned_orders WHERE order_id IN ($placeholders)"
                );
                $deleteAssignedByOrderStmt->bind_param($types, ...$orderIds);
                $deleteAssignedByOrderStmt->execute();
                $deleteAssignedByOrderStmt->close();

                $deletePaymentsStmt = $conn->prepare(
                    "DELETE FROM payments WHERE order_id IN ($placeholders)"
                );
                $deletePaymentsStmt->bind_param($types, ...$orderIds);
                $deletePaymentsStmt->execute();
                $deletePaymentsStmt->close();

                $deleteOrderItemsStmt = $conn->prepare(
                    "DELETE FROM order_items WHERE order_id IN ($placeholders)"
                );
                $deleteOrderItemsStmt->bind_param($types, ...$orderIds);
                $deleteOrderItemsStmt->execute();
                $deleteOrderItemsStmt->close();
            }

            $deleteOwnAssignmentsStmt = $conn->prepare("DELETE FROM staff_assigned_orders WHERE user_id = ?");
            $deleteOwnAssignmentsStmt->bind_param("i", $targetUserId);
            $deleteOwnAssignmentsStmt->execute();
            $deleteOwnAssignmentsStmt->close();

            $deleteOrdersStmt = $conn->prepare("DELETE FROM orders WHERE user_id = ?");
            $deleteOrdersStmt->bind_param("i", $targetUserId);
            $deleteOrdersStmt->execute();
            $deleteOrdersStmt->close();

            $deleteCartStmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $deleteCartStmt->bind_param("i", $targetUserId);
            $deleteCartStmt->execute();
            $deleteCartStmt->close();

            $deleteFavoritesStmt = $conn->prepare("DELETE FROM isfavorite WHERE user_id = ?");
            $deleteFavoritesStmt->bind_param("i", $targetUserId);
            $deleteFavoritesStmt->execute();
            $deleteFavoritesStmt->close();

            $deleteUserStmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $deleteUserStmt->bind_param("i", $targetUserId);
            $deleteUserStmt->execute();
            $deleted = $deleteUserStmt->affected_rows > 0;
            $deleteUserStmt->close();

            if (!$deleted) {
                throw new RuntimeException('User account could not be deleted.');
            }

            db_insert_user_deletion_log($conn, $user);

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('db_delete_customer_account')) {
    function db_delete_customer_account(mysqli $conn, int $customerId): bool
    {
        return db_delete_user_account($conn, $customerId);
    }
}

if (!function_exists('db_update_user_profile')) {
    function db_update_user_profile(
        mysqli $conn,
        int $userId,
        string $firstName,
        string $lastName,
        string $email,
        string $phone
    ): bool {
        $conn->begin_transaction();

        try {
            $user = db_fetch_user_snapshot($conn, $userId, true);

            if ($user === null) {
                $conn->rollback();
                return false;
            }

            $stmt = $conn->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, email = ?, phone = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param("ssssi", $firstName, $lastName, $email, $phone, $userId);
            $stmt->execute();
            $stmt->close();

            if (
                strtolower((string) $user['user_role']) === 'customer'
                && ($user['first_name'] !== $firstName || $user['last_name'] !== $lastName)
            ) {
                db_insert_customer_edit_log(
                    $conn,
                    $userId,
                    (string) $user['first_name'],
                    $firstName,
                    (string) $user['last_name'],
                    $lastName
                );
            }

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
