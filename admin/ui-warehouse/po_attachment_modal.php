<?php
// po_attachment_modal.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get order_id from POST
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

// Use the same logic as generate_po.php to get suppliers
$itemStmt = $conn->prepare("
    SELECT 
        oi.supplier_id,
        oi.manual_supplier_name,
        sl.business_name,
        sl.primary_contact_name,
        sl.email_address,
        sl.phone_number,
        sl.business_address
    FROM order_items oi
    LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND oi.supplier_id > 0
    WHERE oi.order_id = ? AND (
        (oi.supplier_id IS NOT NULL AND oi.supplier_id > 0) OR 
        (oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '' AND TRIM(oi.manual_supplier_name) != '')
    )
    ORDER BY COALESCE(sl.business_name, oi.manual_supplier_name)
");

$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();
$allItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

// Group items by supplier using the same logic as generate_po.php
$supplierGroups = [];
foreach ($allItems as $item) {
    // Handle different supplier assignment scenarios
    if (!empty($item['supplier_id']) && $item['supplier_id'] > 0) {
        // Linked supplier (supplier_id > 0)
        $supplierKey = strval($item['supplier_id']);
        $supplierName = $item['business_name'];
        $isManual = false;
    } else if (($item['supplier_id'] === 0 || $item['supplier_id'] === '0' || is_null($item['supplier_id'])) && !empty($item['manual_supplier_name'])) {
        // Manual supplier (supplier_id is 0/NULL, name is in manual_supplier_name)
        $supplierKey = 'manual_' . $item['manual_supplier_name'];
        $supplierName = $item['manual_supplier_name'];
        $isManual = true;
    } else {
        // Skip items without proper supplier assignment
        continue;
    }
    
    // Make sure we have a valid supplier name
    if (empty($supplierName)) {
        continue;
    }
    
    if (!isset($supplierGroups[$supplierKey])) {
        $supplierGroups[$supplierKey] = [
            'supplier_info' => [
                'name' => $supplierName,
                'contact' => $item['primary_contact_name'] ?? '',
                'email' => $item['email_address'] ?? '',
                'phone' => $item['phone_number'] ?? '',
                'address' => $item['business_address'] ?? '',
                'is_manual' => $isManual
            ]
        ];
    }
}

// Convert supplierGroups to the format expected by the modal
$suppliers = [];
foreach ($supplierGroups as $supplierKey => $supplierData) {
    $suppliers[] = [
        'supplier_name' => $supplierData['supplier_info']['name'],
        'supplier_id' => $supplierData['supplier_info']['is_manual'] ? 0 : (int)str_replace('manual_', '', $supplierKey),
        'manual_supplier_name' => $supplierData['supplier_info']['is_manual'] ? $supplierData['supplier_info']['name'] : null,
        'supplier_type' => $supplierData['supplier_info']['is_manual'] ? 'manual' : 'linked',
        'supplier_key' => $supplierKey // Add the key for form submission
    ];
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['po_files'])) {
    $upload_dir = '../../uploads/p.o_files/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_files = [];
    $errors = [];
    
    // Process each uploaded file
    for ($i = 0; $i < count($_FILES['po_files']['name']); $i++) {
        if ($_FILES['po_files']['error'][$i] === UPLOAD_ERR_OK) {
            $supplier_key = $_POST['supplier_keys'][$i] ?? '';
            $original_name = $_FILES['po_files']['name'][$i];
            $tmp_name = $_FILES['po_files']['tmp_name'][$i];
            
            // Skip empty files
            if (empty($original_name) || empty($supplier_key)) {
                continue;
            }
            
            // Validate file type (Excel files only)
            $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            if (!in_array($file_extension, ['xlsx', 'xls'])) {
                $errors[] = "File {$original_name} is not a valid Excel file";
                continue;
            }
            
            // Find the supplier name from our supplier groups
            $supplier_name = 'Unknown Supplier';
            if (isset($supplierGroups[$supplier_key])) {
                $supplier_name = $supplierGroups[$supplier_key]['supplier_info']['name'];
            }
            
            // Generate unique filename
            $new_filename = "order_{$order_id}_supplier_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $supplier_key) . "_" . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($tmp_name, $file_path)) {
                // Save to database
                $insertSql = "INSERT INTO po_attachments (order_id, supplier_name, original_filename, stored_filename, file_path, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())";
                if ($insertStmt = $conn->prepare($insertSql)) {
                    $insertStmt->bind_param("issss", $order_id, $supplier_name, $original_name, $new_filename, $file_path);
                    if ($insertStmt->execute()) {
                        $uploaded_files[] = [
                            'supplier' => $supplier_name,
                            'filename' => $original_name,
                            'stored_as' => $new_filename
                        ];
                    } else {
                        $errors[] = "Failed to save file record for {$original_name}";
                        unlink($file_path); // Remove uploaded file if DB insert fails
                    }
                    $insertStmt->close();
                } else {
                    $errors[] = "Database error for {$original_name}";
                    unlink($file_path);
                }
            } else {
                $errors[] = "Failed to upload {$original_name}";
            }
        } else if ($_FILES['po_files']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = "Upload error for file " . ($_FILES['po_files']['name'][$i] ?? 'unknown');
        }
    }
    
    // If files were successfully uploaded, change order status to "processing"
    if (count($uploaded_files) > 0) {
        $statusUpdateSql = "UPDATE orders SET status = 'processing' WHERE id = ?";
        if ($statusStmt = $conn->prepare($statusUpdateSql)) {
            $statusStmt->bind_param("i", $order_id);
            $statusStmt->execute();
            $statusStmt->close();
        }
        
        // Also initialize tracking status for all items in this order
        $initTrackingSql = "
            UPDATE order_items 
            SET tracking_status = 'processing' 
            WHERE order_id = ? AND tracking_status IS NULL
        ";
        if ($trackingStmt = $conn->prepare($initTrackingSql)) {
            $trackingStmt->bind_param("i", $order_id);
            $trackingStmt->execute();
            $trackingStmt->close();
        }
        
        if (empty($errors)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Files uploaded successfully and order status changed to Processing',
                'files' => $uploaded_files
            ]);
        } else {
            echo json_encode([
                'success' => true, 
                'message' => 'Some files uploaded successfully, order status changed to Processing, but some failed',
                'errors' => $errors,
                'files' => $uploaded_files
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No files were uploaded',
            'errors' => !empty($errors) ? $errors : ['No valid files were selected']
        ]);
    }
    exit();
}

// Return suppliers list for modal
echo json_encode([
    'success' => true,
    'suppliers' => $suppliers,
    'order_id' => $order_id,
    'supplier_count' => count($suppliers),
    'message' => "Found " . count($suppliers) . " suppliers for order #{$order_id}"
]);
?>