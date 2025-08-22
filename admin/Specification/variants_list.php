<?php 
session_name("nobleadmin"); 
session_start(); 
include '../role/roleaccount.php'; 
require_role(['productspecialist', 'superadmin']); // allow only productspecialist and superadmin  

if (!isset($_SESSION['noble_user'])) {     
    header("Location: ../../loginpage/index.php");     
    exit(); 
}  

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 86400) {     
    session_unset();     
    session_destroy();     
    header("Location: ../../loginpage/index.php?timeout=true");     
    exit(); 
}  

include '../../connection/connect.php'; 

// Updated query to get products instead of variants since descrip6 and descrip7 are now in products table
$result = $conn->query("SELECT id, product_name, codename, descrip6, descrip7 FROM products ORDER BY id ASC"); 
?>  

<!DOCTYPE html> 
<html lang="en"> 
<head>   
    <meta charset="UTF-8">   
    <title>Product Descriptions</title>   
    <script src="https://cdn.tailwindcss.com"></script> 
</head> 
<body class="bg-gray-100 min-h-screen font-sans">  

<?php include '../navbar/top.php'; ?>   

<div class="py-10 px-4">   
    <h2 class="text-3xl font-bold text-orange-700 mb-6">Product Descriptions Management</h2>    
    
    <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">     
        <?php while ($row = $result->fetch_assoc()): ?>       
            <div class="bg-white p-5 rounded-xl shadow hover:shadow-lg transition">         
                <div class="text-sm text-gray-500 mb-2">Product ID: <?= $row['id'] ?></div>
                <div class="text-xs text-blue-500 mb-2 font-mono"><?= htmlspecialchars($row['codename'] ?? '-') ?></div>         
                <h3 class="text-lg font-semibold text-gray-800 mb-3">           
                    <?= htmlspecialchars($row['product_name'] ?? '-') ?>         
                </h3>         
                
                <!-- Show current descriptions status -->
                <div class="mb-4 space-y-2">
                    <div class="text-xs">
                        <span class="text-gray-600">Descrip6:</span>
                        <span class="<?= !empty($row['descrip6']) ? 'text-green-600' : 'text-red-500' ?>">
                            <?= !empty($row['descrip6']) ? '✓ Set' : '✗ Not Set' ?>
                        </span>
                    </div>
                    <div class="text-xs">
                        <span class="text-gray-600">Descrip7:</span>
                        <span class="<?= !empty($row['descrip7']) ? 'text-green-600' : 'text-red-500' ?>">
                            <?= !empty($row['descrip7']) ? '✓ Set' : '✗ Not Set' ?>
                        </span>
                    </div>
                </div>
                
                <a href="set_description.php?id=<?= $row['id'] ?>&type=product"            
                   class="inline-block bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm font-medium transition">           
                   Manage Descriptions         
                </a>       
            </div>     
        <?php endwhile; ?>   
    </div> 
</div>  

</body> 
</html>