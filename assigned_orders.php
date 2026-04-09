<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
include ('includes/db.php');
require_once 'includes/db_operations.php';
require_once 'includes/security/admin.php';

security_handle_logout('index.php');

$isAjaxAction = $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', ['update_status', 'complete_order'], true);

$userId = $isAjaxAction
    ? security_require_role_api($conn, 'Staff')
    : security_require_role($conn, 'Staff', 'Login.php', 'Index.php');

// Handle status update
if (isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    header('Content-Type: application/json');
    security_require_csrf_api();

    $validation = security_admin_validate_order_status_payload($conn, $_POST);
    if (!$validation['valid']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $validation['error']]);
        exit;
    }

    $order_id = $validation['data']['order_id'];
    $new_status = $validation['data']['new_status'];

    try {
        if (db_update_order_status($conn, $order_id, $new_status)) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    exit;
}

// Handle order completion
if (isset($_POST['action']) && $_POST['action'] === 'complete_order' && isset($_POST['order_id'])) {
    header('Content-Type: application/json');
    security_require_csrf_api();

    $order_id = intval($_POST['order_id']);
    try {
        if (db_update_staff_assignment_status($conn, $userId, $order_id, 'COMPLETED')) {
            echo json_encode(['success' => true, 'message' => 'Order marked as completed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark order as completed']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


// Fetch assigned order IDs for the current staff member
$assignedQuery = "SELECT order_id, status FROM staff_assigned_orders WHERE user_id = ?";
$assignedStmt = $conn->prepare($assignedQuery);
$assignedStmt->bind_param("i", $userId);
$assignedStmt->execute();
$assignedResult = $assignedStmt->get_result();

$assignedOrderIds = [];
$orderStatuses = [];
while ($row = $assignedResult->fetch_assoc()) {
    $assignedOrderIds[] = $row['order_id'];
    $orderStatuses[$row['order_id']] = $row['status'];
}
$assignedStmt->close();

// Fetch assigned orders with customer details
$activeOrders = [];
$completedOrders = [];
if (!empty($assignedOrderIds)) {
    $orderIds = implode(',', $assignedOrderIds);
    
    $ordersQuery = "
        SELECT 
            o.order_id,
            o.user_id,
            o.order_date,
            o.totalamt_php,
            o.order_status,
            u.first_name,
            u.last_name,
            u.email
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id IN ($orderIds)
        ORDER BY o.order_date DESC
    ";
    
    $ordersResult = $conn->query($ordersQuery);
    
    while ($order = $ordersResult->fetch_assoc()) {
        // Fetch order items for this order
        $itemsQuery = "
            SELECT 
                oi.quantity,
                oi.srp_php,
                oi.totalprice_php,
                p.product_name,
                p.description
            FROM order_items oi
            JOIN products p ON oi.product_code = p.product_code
            WHERE oi.order_id = ?
        ";
        
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bind_param("i", $order['order_id']);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        
        $orderItems = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $orderItems[] = [
                'product_name' => $item['product_name'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['srp_php'],
                'total_price' => $item['totalprice_php']
            ];
        }
        
        $orderData = [
            'order_id' => $order['order_id'],
            'customer' => $order['first_name'] . ' ' . $order['last_name'],
            'email' => $order['email'],
            'total' => $order['totalamt_php'],
            'date' => $order['order_date'],
            'status' => $order['order_status'],
            'assignment_status' => $orderStatuses[$order['order_id']],
            'items' => $orderItems
        ];
        
        // Separate completed and active orders
        if ($orderStatuses[$order['order_id']] === 'COMPLETED') {
            $completedOrders[] = $orderData;
        } else {
            $activeOrders[] = $orderData;
        }
        
        $itemsStmt->close();
    }
}

function getStatusBadge($status) {
    switch(strtolower($status)) {
        case 'processing':
            return '<span class="status-badge low-stock">PROCESSING</span>';
        case 'shipped':
            return '<span class="status-badge in-stock">SHIPPED</span>';
        case 'delivered':
            return '<span class="status-badge in-stock">DELIVERED</span>';
        default:
            return '<span class="status-badge out-of-stock">' . htmlspecialchars($status) . '</span>';
    }
}

function getStatusDropdown($currentStatus, $orderId, $assignmentStatus) {
    // If assignment is completed --> read-only
    if ($assignmentStatus === 'COMPLETED') {
        return '<span class="status-badge completed">COMPLETED</span>';
    }

    $normalizedCurrentStatus = security_admin_validate_order_status($currentStatus) ?: 'Processing';
    $allowedTransitions = security_admin_get_allowed_order_status_transitions($normalizedCurrentStatus);

    $dropdown = '<select class="status-dropdown" onchange="updateOrderStatus(' . $orderId . ', this.value)"';
    if ($allowedTransitions === []) {
        $dropdown .= ' disabled';
    }
    $dropdown .= '>';
    $dropdown .= '<option value="">' . htmlspecialchars($normalizedCurrentStatus) . '</option>';

    foreach ($allowedTransitions as $status) {
        $dropdown .= '<option value="' . htmlspecialchars($status) . '">' . htmlspecialchars($status) . '</option>';
    }

    $dropdown .= '</select>';
    return $dropdown;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assigned Orders - Staff Dashboard</title>
    <link rel="stylesheet" href="styles/staff_main.css?v=<?php echo time(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/staff.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Staff Dashboard</h1>
            <div class="staff-action-buttons">
                <a href="Index.php" class="staff-btn staff-btn-primary">
                    Go to Customer View
                </a>
                <form method="POST" style="display: inline;">
                    <?php echo security_csrf_input(); ?>
                    <button type="submit" name="logout" value="1" class="staff-btn staff-btn-secondary"
                            onclick="return confirm('Are you sure you want to logout?');">
                        Logout
                    </button>
                </form>
            </div>
        </div>

            <nav class="tab-navigation">
                <a href="stock_management.php" class="tab-nav-item">Stock Management</a>
                <a href="assigned_orders.php" class="tab-nav-item active">Assigned Orders</a>
                <a href="available_orders.php" class="tab-nav-item">Available Orders</a>
            </nav>
        </div>

        <!-- Active Orders Section -->
        <div class="card orders-section">
            <h2 class="card-title">My Assigned Orders</h2>
            
            <?php if (empty($activeOrders)): ?>
                <p>No active orders assigned to you at the moment.</p>
            <?php else: ?>
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Order Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeOrders as $order): ?>
                    <tr>
                        <td class="product-name"><?php echo htmlspecialchars($order['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer']); ?></td>
                        <td class="price-text">₱<?php echo number_format($order['total'], 2); ?></td>
                        <td><?php echo getStatusDropdown($order['status'], $order['order_id'], $order['assignment_status']); ?></td>
                        <td class="items-cell">
                            <?php 
                            $itemsDisplay = [];
                            foreach ($order['items'] as $item) {
                                $itemsDisplay[] = $item['product_name'] . ' (x' . $item['quantity'] . ')';
                            }
                            echo htmlspecialchars(implode(', ', $itemsDisplay));
                            ?>
                        </td>
                        <td class="category-text"><?php echo htmlspecialchars($order['date']); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view-btn" title="View Order" onclick="viewOrder('<?php echo $order['order_id']; ?>')">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                </button>
                                <button class="check-btn" title="Mark as Completed" onclick="completeOrder('<?php echo $order['order_id']; ?>')"
                                        <?php echo ($order['status'] !== 'Delivered') ? 'disabled' : ''; ?>>
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                    </svg>
                                    Complete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Completed Orders Section -->
        <?php if (!empty($completedOrders)): ?>
        <div class="section-separator"></div>
        <div class="card orders-section">
            <h2 class="card-title">Completed Orders</h2>
            
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Order Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedOrders as $order): ?>
                    <tr>
                        <td class="product-name"><?php echo htmlspecialchars($order['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer']); ?></td>
                        <td class="price-text">₱<?php echo number_format($order['total'], 2); ?></td>
                        <td><span class="status-badge completed">COMPLETED</span></td>
                        <td class="items-cell">
                            <?php 
                            $itemsDisplay = [];
                            foreach ($order['items'] as $item) {
                                $itemsDisplay[] = $item['product_name'] . ' (x' . $item['quantity'] . ')';
                            }
                            echo htmlspecialchars(implode(', ', $itemsDisplay));
                            ?>
                        </td>
                        <td class="category-text"><?php echo htmlspecialchars($order['date']); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view-btn" title="View Order" onclick="viewOrder('<?php echo $order['order_id']; ?>')">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                </button>
                                <span class="status-badge completed">COMPLETED</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const csrfToken = <?php echo json_encode(security_get_csrf_token()); ?>;
        const orderData = <?php echo json_encode(array_column(array_merge($activeOrders, $completedOrders), null, 'order_id')); ?>;

        function viewOrder(orderId) {
            const order = orderData[orderId];
            if (order) {
                let itemsList = order.items.map(item => 
                    `${item.product_name} (Qty: ${item.quantity}, ₱${item.unit_price} each, Total: ₱${item.total_price})`
                ).join('\n');
                
                alert(`Order Details:\n\nOrder ID: ${orderId}\nCustomer: ${order.customer}\nEmail: ${order.email}\nStatus: ${order.status}\nAssignment Status: ${order.assignment_status}\nTotal: ₱${order.total}\nDate: ${order.date}\n\nItems:\n${itemsList}`);
            }
        }

        function updateOrderStatus(orderId, newStatus) {
            if (confirm(`Update order ${orderId} status to ${newStatus}?`)) {
                // Make form data
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('order_id', orderId);
                formData.append('new_status', newStatus);
                formData.append('csrf_token', csrfToken);

                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Order status updated successfully!');
                        location.reload(); // Refresh the page to show updated data
                    } else {
                        alert('Failed to update order status: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error updating order status: ' + error);
                });
            }
        }

        function completeOrder(orderId) {
            if (confirm(`Mark order ${orderId} as completed? This action cannot be undone.`)) {
                // Make form data
                const formData = new FormData();
                formData.append('action', 'complete_order');
                formData.append('order_id', orderId);
                formData.append('csrf_token', csrfToken);

                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Order marked as completed successfully!');
                        location.reload(); // Refresh the page to show updated data
                    } else {
                        alert('Failed to mark order as completed: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error completing order: ' + error);
                });
            }
        }
    </script>
</body>
</html>
