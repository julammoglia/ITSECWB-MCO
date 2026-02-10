<?php
session_start(); 

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

require_once 'includes/db.php'; 

$userId = $_SESSION['user_id'];

// Handle order assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'];
    $action = $_POST['action'];

    if ($action === 'assign') {
        // Insert into staff_assigned_orders
        $stmt = $conn->prepare("INSERT INTO staff_assigned_orders (user_id, order_id, status) VALUES (?, ?, ?)");
        $status = 'ASSIGNED';
        $stmt->bind_param("iis", $userId, $orderId, $status);
        $stmt->execute();
    }

    header('Location: available_orders.php');
    exit();
}

// Fetch assigned order IDs
$assignedQuery = "SELECT order_id FROM staff_assigned_orders";
$assignedResult = $conn->query($assignedQuery);

$assignedOrderIds = [];
while ($row = $assignedResult->fetch_assoc()) {
    $assignedOrderIds[] = $row['order_id'];
}

// Fetch available orders (excluding assigned ones) with customer details and items
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
    ORDER BY o.order_date DESC
";

$ordersResult = $conn->query($ordersQuery);

$availableOrders = [];
while ($order = $ordersResult->fetch_assoc()) {
    if (!in_array($order['order_id'], $assignedOrderIds)) {
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
        
        $availableOrders[$order['order_id']] = [
            'customer' => $order['first_name'] . ' ' . $order['last_name'],
            'email' => $order['email'],
            'total' => $order['totalamt_php'],
            'date' => $order['order_date'],
            'status' => $order['order_status'],
            'items' => $orderItems
        ];
        
        $itemsStmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Available Orders - Staff Dashboard</title>
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
                <a href="?logout=true" class="staff-btn staff-btn-secondary" 
                   onclick="return confirm('Are you sure you want to logout?');">
                    Logout
                </a>
            </div>
        </div>

            <nav class="tab-navigation">
                <a href="stock_management.php" class="tab-nav-item">Stock Management</a>
                <a href="assigned_orders.php" class="tab-nav-item">Assigned Orders</a>
                <a href="available_orders.php" class="tab-nav-item active">Available Orders</a>
            </nav>
        </div>

        <div class="card">
            <div class="card-header">
                <svg class="card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <h2 class="card-title">Available Orders to Pick Up</h2>
            </div>
            
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Order Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($availableOrders)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #6b7280;">
                            No orders available for pickup at the moment.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($availableOrders as $orderId => $order): ?>
                        <tr>
                            <td class="product-name"><?php echo htmlspecialchars($orderId); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($order['customer']); ?></div>
                            </td>
                            <td class="price-text">₱<?php echo number_format($order['total'], 2); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($order['date']); ?></div>
                                <div class="items-list">
                                    <?php echo count($order['items']); ?> item(s)
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view-btn" onclick="viewOrderDetails('<?php echo $orderId; ?>')" title="View Details">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                        </svg>
                                    </button>
                                    
                                    <form method="POST" class="action-form" onsubmit="return confirmAssign('<?php echo $orderId; ?>')">
                                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>">
                                        <input type="hidden" name="action" value="assign">
                                        <button type="submit" class="assign-to-btn">Assign to Me</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    </div>

    <script>
        function viewOrderDetails(orderId) {
            const orders = <?php echo json_encode($availableOrders); ?>;
            const order = orders[orderId];
            
            if (order) {
                // Format items list 
                let itemsList = order.items.map(item => 
                    `${item.product_name} (Qty: ${item.quantity}, ₱${item.unit_price} each, Total: ₱${item.total_price})`).join('\n');
                alert(`Order Details:\n\nOrder ID: ${orderId}\nCustomer: ${order.customer}\nEmail: ${order.email}\nStatus: ${order.status}\nTotal: ₱${order.total}\nDate: ${order.date}\n\nItems:\n${itemsList}`);
            }
        }

        function confirmPickup(orderId) {
            return confirm(`Are you sure you want to mark order ${orderId} as picked up?\n\nThis action will remove the order from the available list.`);
        }

        function confirmAssign(orderId) {
            return confirm(`Are you sure you want to assign order ${orderId} to yourself?\n\nThis will move the order to your assigned orders list.`);
        }

        // Hover effects
        document.querySelectorAll('.stock-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                if (!this.querySelector('td[colspan]')) {
                    this.style.backgroundColor = '#f8fafc';
                }
            });
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });

        // Auto refresh to check for new orders
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
