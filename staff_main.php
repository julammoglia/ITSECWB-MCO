<?php 
session_start();
include 'includes/db.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}
$userId = $_SESSION['user_id'];

// Verify user is staff
$userCheck = $conn->prepare("SELECT user_role FROM users WHERE user_id = ?");
$userCheck->bind_param("i", $userId);
$userCheck->execute();
$userResult = $userCheck->get_result();
$user = $userResult->fetch_assoc();

if ($user['user_role'] !== 'Staff') {
    header("Location: Login.php");
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Dashboard</title>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <h2 class="card-title">Welcome to Staff Dashboard</h2>
            </div>
            
            <p style="color: #6b7280; font-size: 16px; line-height: 1.6;">
                Select a tab above to manage your tasks:
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 24px;">
                <div style="padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
                    <h3 style="color: #1a202c; margin: 0 0 8px 0; font-size: 16px;">Stock Management</h3>
                    <p style="color: #6b7280; margin: 0; font-size: 14px;">Update inventory levels and monitor stock status</p>
                </div>
                
                <div style="padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
                    <h3 style="color: #1a202c; margin: 0 0 8px 0; font-size: 16px;">Assigned Orders</h3>
                    <p style="color: #6b7280; margin: 0; font-size: div;">View and manage orders assigned to you</p>
                </div>
                
                <div style="padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
                    <h3 style="color: #1a202c; margin: 0 0 8px 0; font-size: 16px;">Available Orders</h3>
                    <p style="color: #6b7280; margin: 0; font-size: 14px;">Browse and pick up available orders</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>