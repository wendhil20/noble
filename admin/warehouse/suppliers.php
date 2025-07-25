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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>

    <a href="../supplier/supplier.php"
        class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M12 4v16m8-8H4"></path>
        </svg>
        Manage Suppliers
    </a>
    <section class="p-4">
        <h2 class="text-xl font-bold mb-4">List of Suppliers</h2>

        <div class="flex flex-wrap gap-4">

            <!-- LEFT COLUMN (Supplier List) -->
            <div class="w-full lg:w-1/2">
                <div class="overflow-x-auto bg-white border border-gray-300 rounded shadow">
                    <table class="min-w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border">ID</th>
                                <th class="py-2 px-4 border">Company</th>
                                <th class="py-2 px-4 border">Address</th>
                                <th class="py-2 px-4 border">Mobile</th>
                                <th class="py-2 px-4 border">Email</th>
                                <th class="py-2 px-4 border">Status</th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT * FROM adminsuppliers ORDER BY id ASC";
                            $result = $conn->query($query);

                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td class='py-2 px-4 border'>" . htmlspecialchars($row['id']) . "</td>";
                                    echo "<td class='py-2 px-4 border'>" . htmlspecialchars($row['company']) . "</td>";
                                    echo "<td class='py-2 px-4 border'>" . htmlspecialchars($row['address']) . "</td>";
                                    echo "<td class='py-2 px-4 border'>" . htmlspecialchars($row['mobile']) . "</td>";
                                    echo "<td class='py-2 px-4 border'>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "<td class='py-2 px-4 border'>" . htmlspecialchars($row['status']) . "</td>";

                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' class='py-2 px-4 border text-center'>No suppliers found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
          
        </div>
    </section>

<!-- RIGHT COLUMN (Product Catalog) -->
<div class="w-full lg:w-1/2" id="catalog-container">
    <div class="h-full bg-gray-50 border border-dashed border-gray-300 rounded shadow p-6 text-gray-500 text-center">
        <p>Select a supplier to view their product catalog.</p>
    </div>
</div>

<script>
function loadCatalog(supplierId) {
    fetch('get_supplier_catalog.php?supplier_id=' + supplierId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('catalog-container').innerHTML = data;
        })
        .catch(error => {
            console.error('Error fetching catalog:', error);
            document.getElementById('catalog-container').innerHTML =
                '<div class="text-red-500 p-4">Error loading catalog.</div>';
        });
}
</script>

</body>

</html>