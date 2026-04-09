<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
require_once 'includes/db.php';
require_once 'includes/db_operations.php';
require_once 'includes/security.php';
require_once 'includes/security/admin.php';
require_once 'includes/security/password_policy.php';

// Handle logout
security_handle_logout('Index.php');

$isOrderStatusRequest = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (($_POST['action'] ?? '') === 'update_order_status');

if ($isOrderStatusRequest) {
    header('Content-Type: application/json');
}

$userId = security_require_admin_access($conn, $isOrderStatusRequest);

$adminProfileStmt = $conn->prepare("
    SELECT first_name, last_name, email, profile_picture
    FROM users
    WHERE user_id = ? AND user_role = 'Admin'
");
$adminProfileStmt->bind_param("i", $userId);
$adminProfileStmt->execute();
$adminProfileResult = $adminProfileStmt->get_result();
$adminProfile = $adminProfileResult->fetch_assoc() ?: [
    'first_name' => $_SESSION['first_name'] ?? 'Admin',
    'last_name' => $_SESSION['last_name'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'profile_picture' => null,
];
$adminProfileStmt->close();

$adminDisplayName = trim(($adminProfile['first_name'] ?? '') . ' ' . ($adminProfile['last_name'] ?? ''));
if ($adminDisplayName === '') {
    $adminDisplayName = 'Admin User';
}

$adminInitials = strtoupper(substr((string) ($adminProfile['first_name'] ?? 'A'), 0, 1) . substr((string) ($adminProfile['last_name'] ?? 'D'), 0, 1));
$debugModeEnabled = security_is_debug_mode();

// Form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'update_order_status') {
            security_require_csrf_api();
        } else {
            security_require_csrf('Admin.php');
        }

        switch ($action) {
            case 'toggle_debug_mode':
                $requestedDebugMode = ($_POST['debug_mode'] ?? '0') === '1';

                if (security_set_debug_mode($requestedDebugMode)) {
                    $debugModeEnabled = $requestedDebugMode;
                    security_log_audit('ADMIN', 'SUCCESS', 'toggle_debug_mode', [
                        'debug_mode' => $requestedDebugMode ? 'enabled' : 'disabled',
                    ], $userId);
                    $success = 'Debug mode ' . ($requestedDebugMode ? 'enabled' : 'disabled') . ' successfully.';
                } else {
                    log_event('ADMIN_ACTION_FAILURE', [
                        'action' => 'toggle_debug_mode',
                        'user_id' => $userId,
                        'reason' => 'Failed to persist debug mode setting.',
                    ]);
                    $error = 'Unable to update debug mode right now. Please try again.';
                }
                break;

            case 'export_logs':
                security_log_audit('ADMIN', 'SUCCESS', 'export_logs', [
                    'format' => 'csv',
                ], $userId);
                security_stream_audit_log_csv('security-log-' . date('Ymd-His') . '.csv');
                break;

            case 'add_product':
                $validation = security_admin_validate_product_payload($conn, $_POST);

                if (!$validation['valid']) {
                    log_event('VALIDATION_FAILURE', [
                        'action' => 'add_product',
                        'reason' => $validation['error'],
                        'user_id' => $userId,
                    ]);
                    $error = $validation['error'];
                    break;
                }

                $productName = $validation['data']['product_name'];
                $categoryCode = $validation['data']['category_code'];
                $description = $validation['data']['description'];
                $stockQty = $validation['data']['stock_qty'];
                $srpPhp = $validation['data']['srp_php'];

                if (db_add_new_product(
                    $conn,
                    $productName,
                    $categoryCode,
                    $description,
                    $stockQty,
                    $srpPhp
                )) {
                    security_log_audit('ADMIN', 'SUCCESS', 'add_product', [
                        'product_name' => $productName,
                        'category_code' => $categoryCode,
                        'stock_qty' => $stockQty,
                        'price_php' => number_format($srpPhp, 2, '.', ''),
                    ], $userId);
                    $success = "Product added successfully.";
                } else {
                    log_event('ADMIN_ACTION_FAILURE', [
                        'action' => 'add_product',
                        'user_id' => $userId,
                        'db_error' => $conn->error,
                    ]);
                    $error = "Unable to add the product right now. Please try again.";
                }
                break;
             
            case 'delete_product':
                $validation = security_admin_validate_delete_product_payload($conn, $_POST);
                if (!$validation['valid']) {
                    log_event('VALIDATION_FAILURE', [
                        'action' => 'delete_product',
                        'reason' => $validation['error'],
                        'user_id' => $userId,
                    ]);
                    $error = $validation['error'];
                    break;
                }

                $productId = $validation['data']['product_id'];

                if (db_delete_product($conn, $productId)) {
                    security_log_audit('ADMIN', 'SUCCESS', 'delete_product', [
                        'product_id' => $productId,
                    ], $userId);
                    $success = "Product deleted successfully.";
                } else {
                    log_event('ADMIN_ACTION_FAILURE', [
                        'action' => 'delete_product',
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'db_error' => $conn->error,
                    ]);
                    $error = "Unable to delete the product right now. Please try again.";
                }
                break;

            case 'update_stock':
                $validation = security_admin_validate_stock_payload($conn, $_POST);

                if (!$validation['valid']) {
                    log_event('VALIDATION_FAILURE', [
                        'action' => 'update_stock',
                        'reason' => $validation['error'],
                        'user_id' => $userId,
                    ]);
                    $error = $validation['error'];
                    break;
                }

                $productCode = $validation['data']['product_code'];
                $newStock = $validation['data']['new_stock'];

                try {
                    if (db_update_product_stock($conn, $productCode, $newStock)) {
                        security_log_audit('ADMIN', 'SUCCESS', 'update_stock', [
                            'product_code' => $productCode,
                            'new_stock' => $newStock,
                        ], $userId);
                        $success = "Stock updated successfully.";
                    } else {
                        log_event('ADMIN_ACTION_FAILURE', [
                            'action' => 'update_stock',
                            'user_id' => $userId,
                            'product_code' => $productCode,
                            'db_error' => $conn->error,
                        ]);
                        $error = "Unable to update stock right now. Please try again.";
                    }
                } catch (Exception $e) {
                    log_event('ADMIN_ACTION_FAILURE', [
                        'action' => 'update_stock',
                        'user_id' => $userId,
                        'product_code' => $productCode,
                        'exception' => $e->getMessage(),
                    ]);
                    $error = "Unable to update stock right now. Please review the quantity and try again.";
                }
                break;

            case 'add_staff':
                $validation = security_admin_validate_staff_payload($conn, $_POST);
                if (!$validation['valid']) {
                    log_event('VALIDATION_FAILURE', [
                        'action' => 'add_staff',
                        'reason' => $validation['error'],
                        'user_id' => $userId,
                    ]);
                    $error = $validation['error'];
                    break;
                }

                $first = $validation['data']['first_name'];
                $last = $validation['data']['last_name'];
                $email = $validation['data']['email'];
                $phone = $validation['data']['phone'];
                $hashedPassword = $validation['data']['password_hash'];
                $role = $validation['data']['user_role'];

                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, user_role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", 
                    $first,
                    $last,
                    $email,
                    $phone,
                    $hashedPassword,
                    $role
                );
                if ($stmt->execute()) {
                    security_log_audit('ADMIN', 'SUCCESS', 'add_staff', [
                        'created_user_role' => $role,
                        'email' => $email,
                        'phone' => $phone,
                    ], $userId);
                    $success = "Staff member added successfully!";
                } else {
                    log_event('ADMIN_ACTION_FAILURE', [
                        'action' => 'add_staff',
                        'user_id' => $userId,
                        'email' => hash('sha256', $email),
                        'db_error' => $conn->error,
                    ]);
                    $error = "Unable to add the account right now. Please try again.";
                }
                $stmt->close();
                break;

            case 'update_order_status':
                $validation = security_admin_validate_order_status_payload($conn, $_POST);
                if (!$validation['valid']) {
                    log_event('VALIDATION_FAILURE', [
                        'action' => 'update_order_status',
                        'reason' => $validation['error'],
                        'user_id' => $userId,
                    ]);
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => $validation['error']]);
                    exit;
                }

                $orderId = $validation['data']['order_id'];
                $newStatus = $validation['data']['new_status'];

                if (db_update_order_status($conn, $orderId, $newStatus)) {
                    security_log_audit('ADMIN', 'SUCCESS', 'update_order_status', [
                        'order_id' => $orderId,
                        'new_status' => $newStatus,
                    ], $userId);
                    echo json_encode(['success' => true, 'message' => 'Order status updated successfully.']);
                } else {
                    log_event('ADMIN_ACTION_FAILURE', [
                        'action' => 'update_order_status',
                        'user_id' => $userId,
                        'order_id' => $orderId,
                        'db_error' => $conn->error,
                    ]);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Unable to update the order status right now.']);
                }
                exit;
                break;

            case 'delete_staff':
                $validation = security_admin_validate_delete_staff_payload($conn, $_POST, $userId);
                if (!$validation['valid']) {
                    log_event('VALIDATION_FAILURE', [
                        'action' => 'delete_staff',
                        'reason' => $validation['error'],
                        'user_id' => $userId,
                    ]);
                    $error = $validation['error'];
                    break;
                }

                $targetUserId = $validation['data']['user_id'];

                try {
                    if (db_delete_user_account($conn, $targetUserId)) {
                        security_log_audit('ADMIN', 'SUCCESS', 'delete_staff', [
                            'target_user_id' => $targetUserId,
                        ], $userId);
                        $success = "Staff member deleted successfully.";
                    } else {
                        log_event('ADMIN_ACTION_FAILURE', [
                            'action' => 'delete_staff',
                            'user_id' => $userId,
                            'target_user_id' => $targetUserId,
                            'db_error' => $conn->error,
                        ]);
                        $error = "Unable to delete the account right now. Please try again.";
                    }
                } catch (Throwable $e) {
                    log_event('ADMIN_ACTION_FAILURE', [
                        'action' => 'delete_staff',
                        'user_id' => $userId,
                        'target_user_id' => $targetUserId,
                        'exception' => $e->getMessage(),
                    ]);
                    $error = "Unable to delete the account right now. Please try again.";
                }
                break;

            case 'delete_customer':
                $validation = security_admin_validate_delete_customer_payload($conn, $_POST);
                if (!$validation['valid']) {
                    log_event('VALIDATION_FAILURE', [
                        'action' => 'delete_customer',
                        'reason' => $validation['error'],
                        'user_id' => $userId,
                    ]);
                    $error = $validation['error'];
                    break;
                }

                $customerId = $validation['data']['customer_id'];

                if (db_delete_customer_account($conn, $customerId)) {
                    security_log_audit('ADMIN', 'SUCCESS', 'delete_customer', [
                        'customer_id' => $customerId,
                    ], $userId);
                    $success = "Customer account deleted successfully.";
                } else {
                    log_event('ADMIN_ACTION_FAILURE', [
                        'action' => 'delete_customer',
                        'user_id' => $userId,
                        'customer_id' => $customerId,
                        'db_error' => $conn->error,
                    ]);
                    $error = "Unable to delete the customer account right now. Please try again.";
                }
                break;

            default:
                log_event('UNEXPECTED_ADMIN_ACTION', [
                    'action' => $action,
                    'user_id' => $userId,
                ]);

                if ($isOrderStatusRequest) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid admin action.']);
                    exit;
                }

                $error = "Invalid admin action.";
                break;
        }
    }
}

// Fetch data with actual column names
$products = $conn->query("
    SELECT p.*, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_code = c.category_code 
    ORDER BY p.product_code DESC
");

$staff = $conn->query("SELECT * FROM users WHERE user_role = 'Staff'");

$orders = $conn->query("
    SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name,
           p.payment_status, p.payment_method
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.user_id 
    LEFT JOIN payments p ON o.order_id = p.order_id
    ORDER BY o.order_date DESC
");

// Get categories for dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// Prepare order details
$orderDetails = [];

// Get order details with items
$orderDetailsQuery = $conn->query("
    SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name,
           p.payment_status, p.payment_method, u.email
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.user_id 
    LEFT JOIN payments p ON o.order_id = p.order_id
    ORDER BY o.order_date DESC
");

while ($order = $orderDetailsQuery->fetch_assoc()) {

    // Get order items for this order
    $orderItemsQuery = $conn->prepare("
        SELECT oi.*, p.product_name 
        FROM order_items oi 
        LEFT JOIN products p ON oi.product_code = p.product_code 
        WHERE oi.order_id = ?
    ");

    $orderItemsQuery->bind_param("i", $order['order_id']);
    $orderItemsQuery->execute();
    $itemsResult = $orderItemsQuery->get_result();
    
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = [
            'product_name' => $item['product_name'] ?? 'Unknown Product',
            'quantity' => $item['quantity'],
            'unit_price' => number_format($item['srp_php'], 2),
            'total_price' => number_format($item['totalprice_php'], 2)
        ];
    }
    
    $orderDetails[$order['order_id']] = [
        'customer' => $order['customer_name'] ?? 'Unknown',
        'email' => $order['email'] ?? 'N/A',
        'status' => $order['order_status'] ?? 'Pending',
        'total' => number_format($order['totalamt_php'], 2),
        'date' => $order['order_date'],
        'payment_status' => $order['payment_status'] ?? 'Unpaid',
        'payment_method' => $order['payment_method'] ?? 'N/A',
        'items' => $items
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="styles/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<div class="container">
  <div class="user-info">
    <div class="user-info-title">
      <h2>Admin Dashboard</h2>
      <div class="admin-header-presence">
        <?php if (!empty($adminProfile['profile_picture'])): ?>
          <img
            src="uploads/profile_pictures/<?= htmlspecialchars($adminProfile['profile_picture']) ?>"
            alt="<?= htmlspecialchars($adminDisplayName) ?>"
            class="admin-header-avatar"
          >
        <?php endif; ?>
        <p class="admin-header-status">Logged in as <strong><?= htmlspecialchars($adminDisplayName) ?></strong></p>
      </div>
    </div>
    <div class="header-actions">
      <form method="POST" class="header-action-form">
        <?php echo security_csrf_input(); ?>
        <input type="hidden" name="action" value="toggle_debug_mode">
        <input type="hidden" name="debug_mode" value="<?= $debugModeEnabled ? '0' : '1' ?>">
        <button type="submit" class="debug-toggle-btn header-action-btn <?= $debugModeEnabled ? 'is-enabled' : 'is-disabled' ?>">
          <i class="fas <?= $debugModeEnabled ? 'fa-bug' : 'fa-shield-alt' ?>"></i>
          Debug: <?= $debugModeEnabled ? 'On' : 'Off' ?>
        </button>
      </form>
      <form method="POST" class="header-action-form">
        <?php echo security_csrf_input(); ?>
        <input type="hidden" name="action" value="export_logs">
        <button type="submit" class="yellow-btn header-action-btn">
          <i class="fas fa-file-export"></i> Export Security Logs
        </button>
      </form>
      <form method="POST" class="header-action-form">
        <?php echo security_csrf_input(); ?>
        <button type="submit" name="logout" value="1" class="logout-btn header-action-btn" onclick="return confirmLogout()">
          Logout
        </button>
      </form>
    </div>
  </div>

  <div class="tabs">
    <button class="tab-btn active" onclick="showTab('products')">Products</button>
    <button class="tab-btn" onclick="showTab('stock')">Stock</button>
    <button class="tab-btn" onclick="showTab('staffusers')">Staff & Users</button>
    <button class="tab-btn" onclick="showTab('orders')">Orders</button>
  </div>

  <!-- Products Tab -->
  <div id="products" class="tab-content active">
    <?php if (isset($success)): ?>
      <div class="alert alert-success" data-auto-dismiss="5000"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid_input'): ?>
      <div class="alert alert-error" data-auto-dismiss="5000">Unable to process the request. Please review the product input and try again.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid_stock_input'): ?>
      <div class="alert alert-error" data-auto-dismiss="5000">Unable to update stock. Please review the stock input and try again.</div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <div class="alert alert-error" data-auto-dismiss="5000"><?= $error ?></div>
    <?php endif; ?>

    <div class="content-box">
    <h3><i class="fas fa-plus"></i> Add New Product</h3>
    <form method="POST" class="form-grid single-row">
      <?php echo security_csrf_input(); ?>
      <input type="hidden" name="action" value="add_product">
      <input name="product_name" placeholder="Product Name" maxlength="45" required>
      
      <select name="category_code" required>
        <option value="">Select Category</option>
        <?php 
        $categories->data_seek(0); // Reset pointer
        while ($cat = $categories->fetch_assoc()): 
        ?>
        <option value="<?= $cat['category_code'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
        <?php endwhile; ?>
      </select>
      
      <input name="srp_php" type="number" step="0.01" min="0.01" placeholder="Price (PHP)" inputmode="decimal" required>
      <input name="stock_qty" type="number" min="1" step="1" placeholder="Stock Quantity" inputmode="numeric" required>
      <input name="description" placeholder="Product Description" maxlength="45">
      <button type="submit" class="yellow-btn">Add Product</button>
    </form>
    </div>

    <!-- Products List-->
    <div class="content-box">
    <h3><i class="fas fa-cube"></i> Product List</h3>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Category</th>
            <th>Stock</th>
            <th>Price (PHP)</th>
            <th>Description</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($p = $products->fetch_assoc()): ?>
          <tr>
            <td><?= $p['product_code'] ?></td>
            <td><?= htmlspecialchars($p['product_name']) ?></td>
            <td><?= htmlspecialchars($p['category_name'] ?? 'N/A') ?></td>
            <td class="<?= $p['stock_qty'] <= 10 ? 'low-stock' : '' ?>"><?= $p['stock_qty'] ?></td>
            <td>₱<?= number_format($p['srp_php'], 2) ?></td>
            <td><?= htmlspecialchars($p['description'] ?? '') ?></td>
            <td class="actions-cell">
              <form method="POST" id="delete-product-<?= $p['product_code'] ?>" style="display: none;">
                <?php echo security_csrf_input(); ?>
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="product_id" value="<?= $p['product_code'] ?>">
              </form>
              <i class="fas fa-trash-alt delete-icon" 
                 title="Delete" 
                 onclick='deleteProduct(<?= (int) $p["product_code"] ?>, <?= json_encode($p["product_name"], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                 style="cursor: pointer; color: #991b1b">
              </i>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    </div>
      
  </div>

  <!-- Stock Tab -->

  <div id="stock" class="tab-content">
  <div class="content-box">
  <h3><i class="fas fa-sync-alt"></i> Update Stock</h3>
    <form method="POST" class="form-grid single-row">
      <?php echo security_csrf_input(); ?>
      <input type="hidden" name="action" value="update_stock">
      <select name="product_code" required>
        <option value="">Select Product</option>
        <?php
        $stockProducts = $conn->query("SELECT product_code, product_name, stock_qty FROM products ORDER BY product_name");
        while ($sp = $stockProducts->fetch_assoc()):
        ?>
        <option value="<?= $sp['product_code'] ?>">
          <?= htmlspecialchars($sp['product_name']) ?> (Current: <?= $sp['stock_qty'] ?>)
        </option>
        <?php endwhile; ?>
      </select>
      <input name="new_stock" type="number" min="0" step="1" inputmode="numeric" placeholder="New Stock Quantity" required>
      <button type="submit" class="yellow-btn">Update Stock</button>
    </form>
    </div>

    <div class="content-box">
    <h3><i class="fas fa-chart-bar"></i> Low Stock Alert</h3>
    <div class="table-container">
      <table>
        <thead>
          <tr><th>Product</th><th>Current Stock</th><th>Category</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php
          $lowStock = $conn->query("
            SELECT p.*, c.category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_code = c.category_code 
            WHERE p.stock_qty <= 10 
            ORDER BY p.stock_qty ASC
          ");
          while ($ls = $lowStock->fetch_assoc()):
          ?>
          <tr class="low-stock-row">
            <td><?= htmlspecialchars($ls['product_name']) ?></td>
            <td class="low-stock"><?= $ls['stock_qty'] ?></td>
            <td><?= htmlspecialchars($ls['category_name'] ?? 'N/A') ?></td>
            <td>
              <!-- Quick Restock Form -->
              <form method="POST" class="form-grid single-row">
                <?php echo security_csrf_input(); ?>
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="product_code" value="<?= $ls['product_code'] ?>">
                <input type="number" name="new_stock" value="100" min="1" style="width: 70px; margin-right: 5px;" required>
                <button type="submit" class="yellow-btn" title="Quick restock <?= htmlspecialchars($ls['product_name']) ?>">
                  <i class="fas fa-plus"></i> Restock
                </button>
              </form>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
  </div>

  <!-- Staff and Users Tab -->
  <div id="staffusers" class="tab-content">
    <div class="content-box">
    <h3><i class="fas fa-user-plus"></i> Add New Staff</h3>
    <form method="POST" class="form-grid single-row staff-form-grid">
      <?php echo security_csrf_input(); ?>
      <input type="hidden" name="action" value="add_staff">
      <input name="first_name" placeholder="First Name" maxlength="45" pattern="^[A-Za-z]+(?:[ .'-][A-Za-z]+)*$" title="Use letters only, with optional spaces, dots, apostrophes, or hyphens between words." autocomplete="given-name" required>
      <input name="last_name" placeholder="Last Name" maxlength="45" pattern="^[A-Za-z]+(?:[ .'-][A-Za-z]+)*$" title="Use letters only, with optional spaces, dots, apostrophes, or hyphens between words." autocomplete="family-name" required>
      <input name="email" type="email" placeholder="Email" maxlength="45" inputmode="email" autocomplete="email" pattern="^[A-Za-z0-9][A-Za-z0-9._%+\-]*@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$" title="Enter a valid email address. The email cannot start with a dot." required>
      <input name="phone" type="tel" placeholder="09XXXXXXXXX" inputmode="numeric" autocomplete="tel-national" pattern="^09\d{9}$" title="Enter exactly 11 digits starting with 09." maxlength="11" required>
      <input name="password" type="password" placeholder="Password (min 12 chars)" required minlength="12" title="At least 12 characters with 3 of 4: uppercase, lowercase, numbers, special characters. No spaces.">
      <input type="hidden" name="user_role" value="Staff">
      <input type="text" value="Staff" aria-label="Staff role" readonly>
      <button type="submit" class="yellow-btn staff-submit-btn">Add Staff Member</button>
    </form>
    </div>

    <div class="content-box">
    <h3><i class="fas fa-clipboard-list"></i> Staff List</h3>
    <div class="table-container">
      <table>
        <thead>
          <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php while ($s = $staff->fetch_assoc()): ?>
          <tr>
            <td><?= $s['user_id'] ?></td>
            <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
            <td><?= htmlspecialchars($s['email']) ?></td>
            <td><?= $s['user_role'] ?></td>
            <td class="actions-cell">
              <form method="POST" id="delete-staff-<?= $s['user_id'] ?>" style="display: none;">
                <?php echo security_csrf_input(); ?>
                <input type="hidden" name="action" value="delete_staff">
                <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
              </form>
              <i class="fas fa-trash-alt delete-icon" 
                 title="Delete" 
                 onclick='deleteStaff(<?= (int) $s["user_id"] ?>, <?= json_encode($s["first_name"] . " " . $s["last_name"], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                 style="cursor: pointer; color: #991b1b">
              </i>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    </div>

    <div class="content-box">
    <h3><i class="fas fa-user-times"></i> Delete Customer Account</h3>
    <form method="POST" class="form-grid single-row">
      <?php echo security_csrf_input(); ?>
      <input type="hidden" name="action" value="delete_customer">
      <select name="customer_id" required>
        <option value="">Select Customer to Delete</option>
        <?php
        $customers = $conn->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE user_role = 'Customer'");
        while ($c = $customers->fetch_assoc()):
        ?>
        <option value="<?= $c['user_id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
        <?php endwhile; ?>
      </select>
      <button type="submit" class="red-btn" onclick="return confirm('This will delete the customer and ALL their orders, cart items, and favorites. Continue?')">Delete Customer Account</button>
    </form>
    </div>
  </div>

  <!-- Orders Tab -->
<div id="orders" class="tab-content">
  <div class="content-box">
  <h3><i class="fas fa-box"></i> Order Management</h3>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Total (PHP)</th>
          <th>Order Status</th>
          <th>Payment Status</th>
          <th>Payment Method</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($o = $orders->fetch_assoc()): ?>
        <tr>
          <td><?= $o['order_id'] ?></td>
          <td><?= htmlspecialchars($o['customer_name'] ?? 'Unknown') ?></td>
          <td>₱<?= number_format($o['totalamt_php'], 2) ?></td>
          <td>
            <span class="status <?= strtolower($o['order_status'] ?? 'pending') ?>">
              <?= strtoupper($o['order_status'] ?? 'PENDING') ?>
            </span>
          </td>
          <td>
            <span class="payment-status <?= strtolower($o['payment_status'] ?? 'unpaid') ?>">
              <?= strtoupper($o['payment_status'] ?? 'UNPAID') ?>
            </span>
          </td>
          <td><?= strtoupper($o['payment_method'] ?? 'N/A') ?></td>
          <td><?= $o['order_date'] ?></td>
          <td class="actions-cell single-row">
  
            <select onchange="updateOrderStatus(<?= $o['order_id'] ?>, this.value)" class="status-select">
              <option value="">Update Status</option>
              <option value="Processing" <?= $o['order_status'] == 'Processing' ? 'selected' : '' ?>>Processing</option>
              <option value="Shipped" <?= $o['order_status'] == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
              <option value="Delivered" <?= $o['order_status'] == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
            </select>
            
            <button class="view-btn" onclick="viewOrderDetails(<?= $o['order_id'] ?>)" title="View Details">
              <i class="fas fa-eye"></i>
          </button>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<script>
const csrfToken = <?php echo json_encode(security_get_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function deleteProduct(productCode, productName) {
  if (confirm(`Delete product: ${productName}? This will remove it from all carts, favorites, and order history.`)) {
    document.getElementById(`delete-product-${productCode}`).submit();
  }
}

function deleteStaff(userId, staffName) {
  if (confirm(`Delete staff member: ${staffName}?`)) {
    document.getElementById(`delete-staff-${userId}`).submit();
  }
}

function showTab(tabId) {
  document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.getElementById(tabId).classList.add('active');
  event.target.classList.add('active');
}

function updateOrderStatus(orderId, newStatus) {
  if (newStatus && confirm(`Update order #${orderId} status to ${newStatus}?`)) {
    const formData = new FormData();
    formData.append('action', 'update_order_status');
    formData.append('order_id', orderId);
    formData.append('new_status', newStatus);
    formData.append('csrf_token', csrfToken);
    
    fetch(window.location.href, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
          alert('Order status updated successfully!');
        location.reload();
      } else {
        alert('Error updating order status: ' + data.message);
      }
    })
    .catch(error => {
      alert('Error updating order status');
    });
  }
}

function viewOrderDetails(orderId) {
    const orders = <?php echo json_encode($orderDetails, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const order = orders[orderId];
    
    if (order) {
        let itemsList = order.items.map(item => 
            `${item.product_name} (Qty: ${item.quantity}, ₱${item.unit_price} each, Total: ₱${item.total_price})`
        ).join('\n');
        
        alert(`Order Details:\n\nOrder ID: ${orderId}\nCustomer: ${order.customer}\nStatus: ${order.status}\nPayment Status: ${order.payment_status}\nPayment Method: ${order.payment_method}\nTotal: ₱${order.total}\nDate: ${order.date}\n\nItems:\n${itemsList}`);
    } else {
        alert('Order details not found.');
    }
}


function quickRestock(productCode, productName) {
  const newStock = prompt(`Enter new stock quantity for ${productName}:`);
  if (newStock && parseInt(newStock) >= 0) {
    const formData = new FormData();
    formData.append('action', 'update_stock');
    formData.append('product_code', productCode);
    formData.append('new_stock', parseInt(newStock));
    formData.append('csrf_token', csrfToken);
    
    fetch(window.location.href, {
      method: 'POST',
      body: formData
    })
    .then(response => {
      // Check if response is ok
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.text();
    })
    .then(data => {
      // Check if there's an error in the response
      if (data.includes('Error')) {
        alert('Error updating stock: ' + data);
      } else {
        alert('Stock updated successfully!');
        location.reload();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error updating stock: ' + error.message);
    });
  }
}

function confirmLogout() {
      return confirm('Are you sure you want to logout?');
}

document.querySelectorAll('input[name="srp_php"], input[name="stock_qty"]').forEach((input) => {
  input.addEventListener('keydown', (event) => {
    if (['e', 'E', '+', '-'].includes(event.key)) {
      event.preventDefault();
    }
  });

  input.addEventListener('input', () => {
    if (input.name === 'stock_qty') {
      input.value = input.value.replace(/[^\d]/g, '');
      return;
    }

    input.value = input.value
      .replace(/[^0-9.]/g, '')
      .replace(/(\..*)\./g, '$1');
  });
});

document.querySelectorAll('input[name="new_stock"]').forEach((input) => {
  input.addEventListener('keydown', (event) => {
    if (['e', 'E', '+', '-', '.'].includes(event.key)) {
      event.preventDefault();
    }
  });

  input.addEventListener('input', () => {
    input.value = input.value.replace(/[^\d]/g, '');
  });
});

function sanitizeAdminEmailInput(input) {
  input.value = input.value.replace(/\s+/g, '').replace(/^\.+/, '');
}

function sanitizeAdminNameInput(input) {
  input.value = input.value
    .replace(/[^A-Za-z .'-]/g, '')
    .replace(/\s{2,}/g, ' ')
    .replace(/^\s+/, '');
}

function sanitizeAdminPhoneInput(input) {
  input.value = input.value.replace(/\D/g, '').slice(0, 11);
}

document.querySelectorAll('input[name="first_name"], input[name="last_name"]').forEach((input) => {
  input.addEventListener('input', () => sanitizeAdminNameInput(input));
  input.addEventListener('paste', () => {
    requestAnimationFrame(() => sanitizeAdminNameInput(input));
  });
});

document.querySelectorAll('input[name="email"]').forEach((input) => {
  input.addEventListener('input', () => sanitizeAdminEmailInput(input));
  input.addEventListener('paste', () => {
    requestAnimationFrame(() => sanitizeAdminEmailInput(input));
  });
});

document.querySelectorAll('input[name="phone"]').forEach((input) => {
  input.addEventListener('input', () => sanitizeAdminPhoneInput(input));
  input.addEventListener('paste', () => {
    requestAnimationFrame(() => sanitizeAdminPhoneInput(input));
  });
});

function autoDismissAlerts() {
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach((alert) => {
    const delay = parseInt(alert.dataset.autoDismiss || '5000', 10);

    window.setTimeout(() => {
      alert.classList.add('is-hiding');
      window.setTimeout(() => alert.remove(), 350);
    }, delay);
  });
}

document.addEventListener('DOMContentLoaded', autoDismissAlerts);
</script>

</body>
</html>
