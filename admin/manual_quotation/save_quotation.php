<?php
// save_quotation.php - Debug Version
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_name("nobleadmin");
session_start();

// Basic error logging
function logError($message) {
    error_log("Quotation Debug: " . $message);
}

logError("Script started");

try {
    // Include connection
    include '../../connection/connect.php';
    logError("Connection included");
    
    // Include role check
    require_once '../role/roleaccount.php';
    logError("Role account included");
    
    require_role(['sales', 'superadmin']);
    logError("Role check passed");
    
} catch (Exception $e) {
    logError("Include error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Include error: ' . $e->getMessage()]);
    exit();
}

// Set headers
header('Content-Type: application/json');

// Set noble_name and noble_lvl from DB if not already set
try {
    if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl'])) {
        $email = $_SESSION['noble_user'];
        $stmt = $conn->prepare("SELECT fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($name, $lvl);
        if ($stmt->fetch()) {
            $_SESSION['noble_name'] = $name;
            $_SESSION['noble_lvl'] = $lvl;
        } else {
            $_SESSION['noble_name'] = "Unknown User";
            $_SESSION['noble_lvl'] = "guest";
        }
        $stmt->close();
    }
    logError("User info set: " . $_SESSION['noble_name']);
} catch (Exception $e) {
    logError("User info error: " . $e->getMessage());
}

// Check authentication
if (!isset($_SESSION['noble_user'])) {
    logError("No user session");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    logError("Session expired");
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit();
}

$_SESSION['last_activity'] = time();

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logError("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate action parameter
if (!isset($_POST['action'])) {
    logError("No action provided");
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

$action = $_POST['action'];
logError("Action: " . $action);

try {
    if ($action === 'get_user_info') {
        logError("Getting user info");
        echo json_encode([
            'success' => true,
            'name' => $_SESSION['noble_name'] ?? 'Unknown User',
            'level' => $_SESSION['noble_lvl'] ?? 'guest'
        ]);
        exit();
    }
    
    if ($action === 'save_quotation') {
        logError("Starting save quotation");
        
        // Validate quotation data
        if (!isset($_POST['quotation_data'])) {
            throw new Exception('No quotation data provided');
        }
        
        logError("Quotation data received");
        
        $quotation_data = json_decode($_POST['quotation_data'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data: ' . json_last_error_msg());
        }
        
        if (!$quotation_data) {
            throw new Exception('Empty quotation data');
        }
        
        logError("JSON decoded successfully");
        
        // Validate required fields
        $required_fields = ['quotationNo', 'quotationFor', 'address', 'quotationDate', 
                           'contactPerson', 'preparedBy', 'validGap', 'validUntil', 
                           'grandTotal', 'items'];
        
        foreach ($required_fields as $field) {
            if (!isset($quotation_data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        if (empty($quotation_data['items']) || !is_array($quotation_data['items'])) {
            throw new Exception('No items provided');
        }
        
        logError("Validation passed. Items count: " . count($quotation_data['items']));
        
        // Validate items
        foreach ($quotation_data['items'] as $index => $item) {
            if (!isset($item['name']) || empty(trim($item['name']))) {
                throw new Exception("Item " . ($index + 1) . " is missing a name");
            }
            
            if (!isset($item['unitMaterialPrice']) || $item['unitMaterialPrice'] <= 0) {
                throw new Exception("Item " . ($index + 1) . " must have a unit material price greater than 0");
            }
        }
        
        logError("Item validation passed");
        
        // Start transaction
        $conn->autocommit(FALSE);
        logError("Transaction started");
        
        try {
            // Check if quotation number already exists
            $stmt = $conn->prepare("SELECT id FROM quotations WHERE quotation_no = ?");
            $stmt->bind_param("s", $quotation_data['quotationNo']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception('Quotation number already exists. Please generate a new one.');
            }
            $stmt->close();
            
            logError("Quotation number check passed");
            
            // Insert quotation header
            $stmt = $conn->prepare("
                INSERT INTO quotations (
                    quotation_no, quotation_for, address, quotation_date, 
                    contact_person, prepared_by, valid_gap, valid_until, 
                    employee, grand_total, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $employee = isset($quotation_data['employee']) ? $quotation_data['employee'] : '';
            
            $stmt->bind_param(
                "ssssssissds",
                $quotation_data['quotationNo'],
                $quotation_data['quotationFor'],
                $quotation_data['address'],
                $quotation_data['quotationDate'],
                $quotation_data['contactPerson'],
                $quotation_data['preparedBy'],
                $quotation_data['validGap'],
                $quotation_data['validUntil'],
                $employee,
                $quotation_data['grandTotal'],
                $_SESSION['noble_user']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to save quotation: ' . $stmt->error);
            }
            
            $quotation_id = $conn->insert_id;
            $stmt->close();
            
            logError("Quotation header saved. ID: " . $quotation_id);
            
            // Insert quotation items
            $stmt = $conn->prepare("
                INSERT INTO quotation_items (
                    quotation_id, item_identifier, item_name, description, width_mm, height_mm,
                    size_display, unit, quantity, unit_material_price, unit_total_material,
                    labor_percentage, unit_labor, unit_total, total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($quotation_data['items'] as $index => $item) {
                // Set default values
                $identifier = isset($item['identifier']) ? $item['identifier'] : 'Custom';
                $description = isset($item['description']) ? $item['description'] : '';
                $width = isset($item['width']) ? floatval($item['width']) : 0;
                $height = isset($item['height']) ? floatval($item['height']) : 0;
                $size = isset($item['size']) ? $item['size'] : '';
                $unit = isset($item['unit']) ? $item['unit'] : 'pcs';
                $quantity = isset($item['quantity']) ? floatval($item['quantity']) : 1;
                $unitMaterialPrice = floatval($item['unitMaterialPrice']);
                $unitTotalMaterial = isset($item['unitTotalMaterial']) ? floatval($item['unitTotalMaterial']) : 0;
                $laborPercentage = isset($item['laborPercentage']) ? floatval($item['laborPercentage']) : 0;
                $unitLabor = isset($item['unitLabor']) ? floatval($item['unitLabor']) : 0;
                $unitTotal = isset($item['unitTotal']) ? floatval($item['unitTotal']) : 0;
                $total = isset($item['total']) ? floatval($item['total']) : 0;
                
                $stmt->bind_param(
                    "isssddssddddddd",
                    $quotation_id,
                    $identifier,
                    $item['name'],
                    $description,
                    $width,
                    $height,
                    $size,
                    $unit,
                    $quantity,
                    $unitMaterialPrice,
                    $unitTotalMaterial,
                    $laborPercentage,
                    $unitLabor,
                    $unitTotal,
                    $total
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to save quotation item ' . ($index + 1) . ': ' . $stmt->error);
                }
                
                logError("Item " . ($index + 1) . " saved");
            }
            
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            $conn->autocommit(TRUE);
            
            logError("Transaction committed successfully");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Quotation saved successfully',
                'quotation_id' => $quotation_id
            ]);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $conn->autocommit(TRUE);
            logError("Transaction rolled back due to error: " . $e->getMessage());
            throw $e;
        }
        
    } else {
        throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    logError("Exception caught: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    logError("Fatal error caught: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
}

logError("Script ended");
?>