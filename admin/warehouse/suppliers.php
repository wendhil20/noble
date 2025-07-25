<?php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse', 'superadmin']);


if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}


$_SESSION['last_activity'] = time();

// Get selected supplier from URL parameter
$selected_supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
$selected_supplier_name = '';
$supplier_products = [];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
   
</head>

<body class="bg-gray-100">

    <div class="container  p-4">
        <div class="mb-6">
            <a href="../supplier/suppliers.php"
                class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4"></path>
                </svg>
                Manage Suppliers
            </a>
            <?php if ($selected_supplier_id > 0): ?>
                <a href="?"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 ml-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to All Suppliers
                </a>
            <?php endif; ?>
        </div>

        <section class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Supplier Management</h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- LEFT COLUMN (Supplier List) -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-700">Suppliers with Products</h3>
                    <div class="overflow-x-auto bg-white border border-gray-300 rounded-lg shadow">
                        <table class="min-w-full">
                            <thead class="bg-gray-100">
                                <tr>
                                  
                                    <th class="py-3 px-4 border text-left">Supplier Name</th>
                                    <th class="py-3 px-4 border text-left">Email</th>
                                    <th class="py-3 px-4 border text-left">Products</th>
                                    <th class="py-3 px-4 border text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Check if tables exist first
                                $table_check = $conn->query("SHOW TABLES LIKE 'suppliers'");
                                $suppliers_table_exists = $table_check && $table_check->num_rows > 0;

                                $product_table_check = $conn->query("SHOW TABLES LIKE 'supplier_products'");
                                $products_table_exists = $product_table_check && $product_table_check->num_rows > 0;

                                if ($suppliers_table_exists && $products_table_exists) {
                                    // Updated query to work with your actual database structure
                                    $query = "SELECT 
                                        n.id,
                                        n.email,
                                        n.fullname,
                                        n.supplier_id,
                                        s.company_name,
                                        s.contact_person,
                                        COUNT(sp.id) as product_count
                                    FROM nobleaccount n
                                    LEFT JOIN suppliers s ON n.supplier_id = s.supplier_id
                                    LEFT JOIN supplier_products sp ON n.supplier_id = sp.supplier_id
                                    WHERE n.supplier_id IS NOT NULL
                                    GROUP BY n.id, n.email, n.fullname, n.supplier_id, s.company_name, s.contact_person
                                    ORDER BY n.id ASC";
                                } else {
                                    // Simplified query for accounts with supplier_id
                                    $query = "SELECT 
                                        n.id,
                                        n.email,
                                        n.fullname,
                                        n.supplier_id,
                                        '' as company_name,
                                        '' as contact_person,
                                        (SELECT COUNT(*) FROM supplier_products sp WHERE sp.supplier_id = n.supplier_id) as product_count
                                    FROM nobleaccount n
                                    WHERE n.supplier_id IS NOT NULL
                                    ORDER BY n.id ASC";
                                }
                                
                                $result = $conn->query($query);

                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $supplier_name = !empty($row['company_name']) ? $row['company_name'] : $row['fullname'];
                                        
                                        // Set selected supplier name if this is the selected one
                                        if ($selected_supplier_id == $row['supplier_id']) {
                                            $selected_supplier_name = $supplier_name;
                                        }
                                        
                                        // Highlight selected row
                                        $row_class = ($selected_supplier_id == $row['supplier_id']) ? 'bg-blue-50 border-blue-200' : 'hover:bg-gray-50';
                                        
                                        echo "<tr class='$row_class hover:bg-gray-50'>";
                                        echo "<td class='py-3 px-4 border font-medium'>" . htmlspecialchars($supplier_name) . "</td>";
                                        echo "<td class='py-3 px-4 border'>" . htmlspecialchars($row['email']) . "</td>";
                                        echo "<td class='py-3 px-4 border'>";
                                        if ($row['product_count'] > 0) {
                                            echo "<span class='bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm'>" . $row['product_count'] . " products</span>";
                                        } else {
                                            echo "<span class='bg-gray-100 text-gray-500 px-2 py-1 rounded-full text-sm'>No products</span>";
                                        }
                                        echo "</td>";
                                        echo "<td class='py-3 px-4 border'>";
                                        echo "<a href='../suppliermain/supplier_catalog.php?supplier_id=" . $row['supplier_id'] . "&supplier_name=" . urlencode($supplier_name) . "' class='bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition-colors inline-block'>View Products</a>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='py-4 px-4 border text-center text-gray-500'>No suppliers found with supplier_id. Please assign supplier IDs in the nobleaccount table.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

               
            </div>
        </section>
    </div>



</body>

</html>