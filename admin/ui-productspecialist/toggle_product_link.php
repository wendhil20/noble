<?php
// toggle_product_link.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';
    
    if ($supplier_id && $product_id && in_array($new_status, ['active', 'inactive'])) {
        $sql = "UPDATE supp_link_products SET status = ?, updated_at = NOW() WHERE supplier_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $new_status, $supplier_id, $product_id);
        
        if ($stmt->execute()) {
            $message = "Product link status updated successfully";
        } else {
            $message = "Error updating product link status";
        }
        $stmt->close();
    }
}

header("Location: view_supplier.php?id=" . $supplier_id . "&message=" . urlencode($message));
exit();
?>

---

<?php
// unlink_product.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if ($supplier_id && $product_id) {
        $sql = "DELETE FROM supp_link_products WHERE supplier_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $supplier_id, $product_id);
        
        if ($stmt->execute()) {
            $message = "Product unlinked successfully";
        } else {
            $message = "Error unlinking product";
        }
        $stmt->close();
    }
}

header("Location: view_supplier.php?id=" . $supplier_id . "&message=" . urlencode($message));
exit();
?>