<?php
// diagnostic.php - Run this to check your database structure and relationships
require_once '../../connection/connect.php';

echo "<h1>Database Diagnostic Report</h1>";
echo "<style>table { border-collapse: collapse; width: 100%; margin: 20px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; } .error { color: red; } .success { color: green; } .warning { color: orange; }</style>";

// 1. Check if all required tables exist
echo "<h2>1. Table Structure Check</h2>";
$requiredTables = ['orders', 'order_items', 'products', 'supplier_list', 'supp_link_products'];
foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p class='success'>✓ Table '$table' exists</p>";
    } else {
        echo "<p class='error'>✗ Table '$table' missing</p>";
    }
}

// 2. Check order_items structure
echo "<h2>2. Order Items Table Structure</h2>";
$result = $conn->query("DESCRIBE order_items");
echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
}
echo "</table>";

// Check if product_id and supplier_id columns exist
$columns = $conn->query("SHOW COLUMNS FROM order_items WHERE Field IN ('product_id', 'supplier_id')");
$foundColumns = [];
while ($col = $columns->fetch_assoc()) {
    $foundColumns[] = $col['Field'];
}

if (in_array('product_id', $foundColumns)) {
    echo "<p class='success'>✓ product_id column exists in order_items</p>";
} else {
    echo "<p class='error'>✗ product_id column missing in order_items</p>";
}

if (in_array('supplier_id', $foundColumns)) {
    echo "<p class='success'>✓ supplier_id column exists in order_items</p>";
} else {
    echo "<p class='error'>✗ supplier_id column missing in order_items</p>";
}

// 3. Check sample data
echo "<h2>3. Sample Data Check</h2>";

// Check orders
$ordersCount = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
echo "<p>Orders in database: <strong>$ordersCount</strong></p>";

// Check order_items
$itemsCount = $conn->query("SELECT COUNT(*) as count FROM order_items")->fetch_assoc()['count'];
echo "<p>Order items in database: <strong>$itemsCount</strong></p>";

// Check order_items with product_id
if (in_array('product_id', $foundColumns)) {
    $itemsWithProductId = $conn->query("SELECT COUNT(*) as count FROM order_items WHERE product_id IS NOT NULL")->fetch_assoc()['count'];
    echo "<p>Order items with product_id: <strong>$itemsWithProductId</strong></p>";
    
    if ($itemsWithProductId == 0) {
        echo "<p class='warning'>⚠ No order items have product_id set. This might be why suppliers aren't showing.</p>";
    }
}

// Check products
$productsCount = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
echo "<p>Products in database: <strong>$productsCount</strong></p>";

// Check suppliers
$suppliersCount = $conn->query("SELECT COUNT(*) as count FROM supplier_list WHERE status = 'active'")->fetch_assoc()['count'];
echo "<p>Active suppliers in database: <strong>$suppliersCount</strong></p>";

// Check supplier-product links
$linksCount = $conn->query("SELECT COUNT(*) as count FROM supp_link_products WHERE status = 'active'")->fetch_assoc()['count'];
echo "<p>Active supplier-product links: <strong>$linksCount</strong></p>";

// 4. Check specific relationships
echo "<h2>4. Relationship Analysis</h2>";

// Sample order items with their product relationships
echo "<h3>Sample Order Items (First 10)</h3>";
$sampleItems = $conn->query("
    SELECT 
        oi.id,
        oi.product_name,
        oi.codename,
        oi.product_id,
        p.product_name as linked_product_name,
        p.codename as linked_product_code
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LIMIT 10
");

echo "<table><tr><th>Item ID</th><th>Item Product Name</th><th>Item Code</th><th>Product ID</th><th>Linked Product Name</th><th>Linked Product Code</th></tr>";
while ($row = $sampleItems->fetch_assoc()) {
    $productIdStatus = $row['product_id'] ? $row['product_id'] : '<span class="error">NULL</span>';
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['product_name']}</td>";
    echo "<td>{$row['codename']}</td>";
    echo "<td>$productIdStatus</td>";
    echo "<td>" . ($row['linked_product_name'] ?: '<span class="warning">No Link</span>') . "</td>";
    echo "<td>" . ($row['linked_product_code'] ?: '<span class="warning">No Link</span>') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check for products that have supplier links
echo "<h3>Products with Supplier Links</h3>";
$productsWithLinks = $conn->query("
    SELECT 
        p.id,
        p.product_name,
        p.codename,
        COUNT(slp.id) as link_count,
        GROUP_CONCAT(
            CONCAT(sl.business_name, ' (', slp.supplier_type, ')')
            SEPARATOR ', '
        ) as suppliers
    FROM products p
    LEFT JOIN supp_link_products slp ON p.id = slp.product_id AND slp.status = 'active'
    LEFT JOIN supplier_list sl ON slp.supplier_id = sl.id AND sl.status = 'active'
    GROUP BY p.id
    HAVING link_count > 0
    ORDER BY link_count DESC
    LIMIT 20
");

if ($productsWithLinks->num_rows > 0) {
    echo "<table><tr><th>Product ID</th><th>Product Name</th><th>Code</th><th>Link Count</th><th>Linked Suppliers</th></tr>";
    while ($row = $productsWithLinks->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['product_name']}</td>";
        echo "<td>{$row['codename']}</td>";
        echo "<td>{$row['link_count']}</td>";
        echo "<td>{$row['suppliers']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠ No products have supplier links. You need to link suppliers to products first.</p>";
}

// 5. Potential Issues and Solutions
echo "<h2>5. Potential Issues and Solutions</h2>";

$issues = [];

// Check if order_items have product_id set
if (in_array('product_id', $foundColumns)) {
    $itemsWithoutProductId = $conn->query("SELECT COUNT(*) as count FROM order_items WHERE product_id IS NULL")->fetch_assoc()['count'];
    if ($itemsWithoutProductId > 0) {
        $issues[] = "Issue: $itemsWithoutProductId order items don't have product_id set.<br>Solution: Update order_items to link them with products table.";
    }
}

// Check if there are supplier links
if ($linksCount == 0) {
    $issues[] = "Issue: No supplier-product links exist.<br>Solution: Create entries in supp_link_products table to link suppliers with products.";
}

// Check if products exist
if ($productsCount == 0) {
    $issues[] = "Issue: No products in database.<br>Solution: Add products to the products table.";
}

if (empty($issues)) {
    echo "<p class='success'>✓ No obvious issues found!</p>";
} else {
    foreach ($issues as $issue) {
        echo "<p class='error'>$issue</p>";
    }
}

// 6. SQL to fix common issues
echo "<h2>6. SQL Fixes (if needed)</h2>";
echo "<h3>If order_items don't have product_id set:</h3>";
echo "<pre>
-- Try to match order items with products by codename
UPDATE order_items oi
JOIN products p ON oi.codename = p.codename
SET oi.product_id = p.id
WHERE oi.product_id IS NULL;

-- Or by product name (if similar)
UPDATE order_items oi
JOIN products p ON oi.product_name = p.product_name
SET oi.product_id = p.id
WHERE oi.product_id IS NULL;
</pre>";

echo "<h3>If you need to create sample supplier links:</h3>";
echo "<pre>
-- Link supplier ID 1 as primary supplier for product ID 1
INSERT INTO supp_link_products (supplier_id, product_id, supplier_type, status)
VALUES (1, 1, 'primary', 'active');

-- Link supplier ID 2 as secondary supplier for product ID 1
INSERT INTO supp_link_products (supplier_id, product_id, supplier_type, status)
VALUES (2, 1, 'secondary', 'active');
</pre>";

echo "<p><em>Diagnostic completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>