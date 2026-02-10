<?php
session_start();
require_once 'includes/db.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productCode = $_POST['product_code'];
    $newQuantity = intval($_POST['quantity']);
    
    try {
        // Update product stock stored procedure
        $stmt = $conn->prepare("CALL update_product_stock(?, ?)");
        $stmt->bind_param("si", $productCode, $newQuantity);
        $stmt->execute();

        // Checker
        $success = "Stock level updated successfully!";
        
    } catch(PDOException $e) {
        // Error messages
        if (strpos($e->getMessage(), 'Insufficient inventory') !== false) {
            $error = "Error: Cannot reduce stock below current level due to inventory constraints.";
        } else {
            $error = "Error updating stock: " . $e->getMessage();
        }
    }
}

// Fetch all products from database
$result = $conn->query("SELECT product_code, category_code, product_name, description, stock_qty, srp_php FROM products ORDER BY product_name");
if (!$result) {
    die("Error fetching products: " . $conn->error);
}
$products = $result->fetch_all(MYSQLI_ASSOC);

function getCategoryName($categoryCode) {
    $categories = [
        1 => 'Headphones',
        2 => 'Monitors', 
        3 => 'Keyboards',
        4 => 'Gaming Mouse',
        5 => 'Speakers'
    ];
    return isset($categories[$categoryCode]) ? $categories[$categoryCode] : 'Unknown';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Stock Management - Staff Dashboard</title>
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
                <a href="stock_management.php" class="tab-nav-item active">Stock Management</a>
                <a href="assigned_orders.php" class="tab-nav-item">Assigned Orders</a>
                <a href="available_orders.php" class="tab-nav-item">Available Orders</a>
            </nav>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
            <div class="alert alert-success">Stock level updated successfully!</div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <svg class="card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <h2 class="card-title">Set Stock Level</h2>
            </div>
            
            <form method="POST" class="stock-form">
                <div class="form-group">
                    <label class="form-label">Product</label>
                    <select name="product_code" class="form-select" required>
                        <option value="">Select product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo htmlspecialchars($product['product_code']); ?>">
                                <?php echo htmlspecialchars($product['product_name']); ?> 
                                (Current: <?php echo $product['stock_qty']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Stock Level</label>
                    <input type="number" name="quantity" class="form-input" value="0" min="0" required>
                </div>
                
                <button type="submit" class="update-button">Set Stock Level</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title">Current Stock Levels</h2>
            
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Price (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td class="product-code"><?php echo htmlspecialchars($product['product_code']); ?></td>
                        <td class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td class="category-text"><?php echo getCategoryName($product['category_code']); ?></td>
                        <td class="stock-quantity"><?php echo $product['stock_qty']; ?></td>
                        <td class="price-text">₱<?php echo number_format($product['srp_php'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>