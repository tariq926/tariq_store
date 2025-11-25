<?php
session_start();
require 'db_config.php'; // Include the database connection script

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// --- SANITIZE USER INPUT FOR SECURITY ---

$user_id = $_SESSION['user_id'];

// Handle filtering
// Already safe: will be used as a parameter in prepared statement
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Handle sorting - CRITICAL WHITELISTING FOR SQL INJECTION PREVENTION
$allowed_sort_columns = ['order_date', 'total_amount', 'status', 'product_name'];
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';

// 1. Validate the column name (Whitelist Check)
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'order_date'; // Default to a safe column
}

// 2. Validate the order direction (Whitelist Check)
$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';

// Handle pagination
// 3. Ensure pagination variables are integers (Defense-in-Depth)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Number of orders per page (keep this hardcoded if possible)
$offset = ($page - 1) * $limit;


// --- PREPARE SQL QUERIES ---

// Prepare the SQL query with filtering and sorting
$sql = "SELECT o.*, p.product_name AS product_name 
        FROM orders o 
        JOIN products p ON o.product_id = p.product_id 
        WHERE o.user_id = ?";
$params = [$user_id];

if ($status_filter) {
    // Already safe: parameter added to $params array
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
}

// Add sorting and pagination using the SECURELY VALIDATED variables
// Now safe because $sort_by and $order are guaranteed to be from the allowed lists
$sql .= " ORDER BY $sort_by $order LIMIT $limit OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Get total orders count for pagination
    $countSql = "SELECT COUNT(*) FROM orders WHERE user_id = ?";
    if ($status_filter) {
        $countSql .= " AND status = ?";
        $countParams = [$user_id, $status_filter];
    } else {
        $countParams = [$user_id];
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total_orders = $countStmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);
} catch (PDOException $e) {
    // Log the error for internal review, but provide a generic message to the user
    error_log("Database error: " . $e->getMessage()); 
    echo "An error occurred while fetching your orders. Please try again later.";
    exit();
}

// Fetch unique statuses for filtering
$statuses = ['Pending', 'Shipped', 'Completed', 'Canceled'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History</title>
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 20px;
    }

    h1 {
        text-align: center;
        color: #333;
    }

    .filter {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
    }

    .filter form {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filter label {
        font-weight: bold;
    }

    .filter select,
    .filter button {
        padding: 5px 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }

    .filter button {
        background-color: #007BFF;
        color: white;
        border: none;
        cursor: pointer;
    }

    .filter button:hover {
        background-color: #0056b3;
    }

    .order {
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .order:hover {
        transform: translateY(-5px);
    }

    .order h2 {
        margin: 0;
        color: #007BFF;
    }

    .order p {
        margin: 5px 0;
        color: #555;
    }

    .order a {
        display: inline-block;
        margin-top: 10px;
        padding: 5px 10px;
        background-color: #007BFF;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        transition: background-color 0.2s;
    }

    .order a:hover {
        background-color: #0056b3;
    }

    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }

    .pagination a {
        margin: 0 5px;
        padding: 5px 10px;
        text-decoration: none;
        color: #007BFF;
        border: 1px solid #ccc;
        border-radius: 5px;
        transition: background-color 0.2s;
    }

    .pagination a.active {
        font-weight: bold;
        background-color: #007BFF;
        color: white;
    }

    .pagination a:hover {
        background-color: #0056b3;
        color: white;
    }
    </style>
</head>

<body>
    <h1>Your Order History</h1>

    <!-- Filter Form -->
    <div class="filter">
        <form method="GET" action="">
            <label for="status">Filter by Status:</label>
            <select name="status" id="status">
                <option value="">All</option>
                <?php foreach ($statuses as $status): ?>
                <option value="<?php echo htmlspecialchars($status); ?>"
                    <?php echo ($status === $status_filter) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($status); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <label for="sort">Sort by:</label>
            <select name="sort" id="sort">
                <option value="order_date" <?php echo ($sort_by === 'order_date') ? 'selected' : ''; ?>>Order Date
                </option>
                <option value="status" <?php echo ($sort_by === 'status') ? 'selected' : ''; ?>>Status</option>
            </select>
            <label for="order">Order:</label>
            <select name="order" id="order">
                <option value="asc" <?php echo ($order === 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                <option value="desc" <?php echo ($order === 'DESC') ? 'selected' : ''; ?>>Descending</option>
            </select>
            <button type="submit">Filter</button>
        </form>
    </div>

    <?php if ($orders): ?>
    <?php foreach ($orders as $order): ?>
    <div class="order">
        <h2>Order ID: <?php echo htmlspecialchars($order['order_id']); ?></h2>
        <p>Product: <?php echo htmlspecialchars($order['product_name']); ?></p>
        <p>Quantity: <?php echo htmlspecialchars($order['quantity']); ?></p>
        <p>Order Date: <?php echo htmlspecialchars($order['order_date']); ?></p>
        <p>Status: <?php echo htmlspecialchars($order['status']); ?></p>
        <p>Total Amount: ksh<?php echo number_format($order['quantity'] * $order['price_ksh'], 2); ?></p>
        <a href="product_details.php?order_id=<?php echo htmlspecialchars($order['order_id']); ?>">View Details</a>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p>No orders found.</p>
    <?php endif; ?>

    <!-- Pagination -->
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($order); ?>"
            class="<?php echo ($i === $page) ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
</body>

</html>
