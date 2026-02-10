<?php
session_start();
require_once 'includes/db.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}
$userId = $_SESSION['user_id'];

// Verify user is admin
$userCheck = $conn->prepare("SELECT user_role FROM users WHERE user_id = ?");
$userCheck->bind_param("i", $userId);
$userCheck->execute();
$userResult = $userCheck->get_result();
$user = $userResult->fetch_assoc();

if ($user['user_role'] !== 'Admin') {
    header("Location: Login.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: Index.php"); 
    exit();
}

// Form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                // Use add_new_product stored procedure
                $stmt = $conn->prepare("CALL add_new_product(?, ?, ?, ?, ?)");
                $stmt->bind_param("sisid", 
                    $_POST['product_name'], 
                    $_POST['category_code'], 
                    $_POST['description'], 
                    $_POST['stock_qty'], 
                    $_POST['srp_php']
                );
                if ($stmt->execute()) {
                    $success = "Product added successfully! Inventory trigger logged the new stock.";
                } else {
                    $error = "Error adding product: " . $conn->error;
                }
                $stmt->close();
                break;
            
            case 'delete_product':
                // Use delete_product stored procedure
                $stmt = $conn->prepare("CALL delete_product(?)");
                $stmt->bind_param("i", $_POST['product_id']);
                if ($stmt->execute()) {
                    $success = "Product deleted successfully! Delete product trigger logged the deleted product.";
                } else {
                    $error = "Error deleting product: " . $conn->error;
                }
                $stmt->close();
                break;

            case 'update_stock':
              // Use update_product_stock stored procedure
              $stmt = $conn->prepare("CALL update_product_stock(?, ?)");
              $stmt->bind_param("ii", $_POST['product_code'], $_POST['new_stock']);
              
              try {
                  if ($stmt->execute()) {
                      $success = "Stock updated successfully! Inventory adjustment trigger logged the change.";
                  } else {
                      $error = "Error updating stock: " . $conn->error;
                  }
              } catch (Exception $e) {
                  $error = "Error. Invalid stock quantity.";
              }
              $stmt->close();
              break;

            case 'add_staff':
                // Add staff member
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, user_role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", 
                    $_POST['first_name'], 
                    $_POST['last_name'], 
                    $_POST['email'], 
                    $_POST['password'], 
                    $_POST['user_role']
                );
                if ($stmt->execute()) {
                    $success = "Staff member added successfully!";
                } else {
                    $error = "Error adding staff: " . $conn->error;
                }
                $stmt->close();
                break;

            case 'update_order_status':
                // Use update_order_status stored procedure
                $stmt = $conn->prepare("CALL update_order_status(?, ?)");
                $stmt->bind_param("is", $_POST['order_id'], $_POST['new_status']);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Order status updated! Status change trigger logged the update.']);
                } else {
                    echo json_encode(['success' => false, 'message' => $conn->error]);
                }
                $stmt->close();
                exit;
                break;

            case 'delete_staff':
                // Delete staff (will trigger customer_deletion_log_trigger if customer)
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND user_role IN ('Staff', 'Admin')");
                $stmt->bind_param("i", $_POST['user_id']);
                if ($stmt->execute()) {
                    $success = "Staff member deleted successfully!";
                } else {
                    $error = "Error deleting staff: " . $conn->error;
                }
                $stmt->close();
                break;

            case 'delete_customer':
                // Use delete_customer_account stored procedure
                $stmt = $conn->prepare("CALL delete_customer_account(?)");
                $stmt->bind_param("i", $_POST['customer_id']);
                if ($stmt->execute()) {
                    $success = "Customer account deleted successfully! Deletion trigger logged the action.";
                } else {
                    $error = "Error deleting customer: " . $conn->error;
                }
                $stmt->close();
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
  <h2>Admin Dashboard</h2>
    <button class="logout-btn" onclick="return confirmLogout()">
      <a href="?logout=1"> Logout </a>
    </button>
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
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <div class="content-box">
    <h3><i class="fas fa-plus"></i> Add New Product (Using Stored Procedure)</h3>
    <form method="POST" class="form-grid single-row">
      <input type="hidden" name="action" value="add_product">
      <input name="product_name" placeholder="Product Name" required>
      
      <select name="category_code" required>
        <option value="">Select Category</option>
        <?php 
        $categories->data_seek(0); // Reset pointer
        while ($cat = $categories->fetch_assoc()): 
        ?>
        <option value="<?= $cat['category_code'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
        <?php endwhile; ?>
      </select>
      
      <input name="srp_php" type="number" step="0.01" placeholder="Price (PHP)" required>
      <input name="stock_qty" type="number" placeholder="Stock Quantity" required>
      <input name="description" placeholder="Product Description" maxlength="45"></input>
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
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="product_id" value="<?= $p['product_code'] ?>">
              </form>
              <i class="fas fa-trash-alt delete-icon" 
                 title="Delete" 
                 onclick="deleteProduct(<?= $p['product_code'] ?>, '<?= htmlspecialchars($p['product_name']) ?>')" 
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
  <h3><i class="fas fa-sync-alt"></i> Update Stock (Using Stored Procedure)</h3>
    <form method="POST" class="form-grid single-row">
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
      <input name="new_stock" type="number" placeholder="New Stock Quantity">
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
    <form method="POST" class="form-grid single-row">
      <input type="hidden" name="action" value="add_staff">
      <input name="first_name" placeholder="First Name" required>
      <input name="last_name" placeholder="Last Name" required>
      <input name="email" type="email" placeholder="Email" required>
      <input name="password" type="password" placeholder="Password" required minlength="6">
      <select name="user_role">
        <option value="Staff">Staff</option>
        <option value="Admin">Admin</option>
      </select>
      <button type="submit" class="yellow-btn">Add Staff Member</button>
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
                <input type="hidden" name="action" value="delete_staff">
                <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
              </form>
              <i class="fas fa-trash-alt delete-icon" 
                 title="Delete" 
                 onclick="deleteStaff(<?= $s['user_id'] ?>, '<?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>')" 
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
  if (newStatus && confirm(`Update order #${orderId} status to ${newStatus}? This will trigger the order status logging.`)) {
    const formData = new FormData();
    formData.append('action', 'update_order_status');
    formData.append('order_id', orderId);
    formData.append('new_status', newStatus);
    
    fetch(window.location.href, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Order status updated successfully! Status change logged by trigger.');
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
    const orders = <?php echo json_encode($orderDetails); ?>;
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
        alert('Stock updated successfully! Inventory adjustment logged by trigger.');
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
</script>

</body>
</html>