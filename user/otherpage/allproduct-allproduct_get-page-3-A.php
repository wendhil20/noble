<?php
// allproduct-allproduct_get-page-3-A.php

include ROOT_PATH . '/connection/connect.php';
header('Content-Type: application/json');


// Allow both authenticated users and guests
$is_guest = !isset($_SESSION['user_id']);

try {
    // ✅ Handle sub-subcategories request
    if (isset($_GET['subcategory_id'])) {
        $subcategory_id = (int)$_GET['subcategory_id'];
        
        if ($subcategory_id <= 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid subcategory ID',
                'is_guest' => $is_guest
            ]);
            exit;
        }
        
        $stmt = $conn->prepare("
            SELECT 
                id, 
                subcategory_id,
                sub_subcategory_name, 
                sub_subcategory_slug, 
                image_path 
            FROM product_sub_subcategories 
            WHERE subcategory_id = ? 
            ORDER BY sub_subcategory_name ASC
        ");
        
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $subcategory_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sub_subcategories = [];
        while ($row = $result->fetch_assoc()) {
            $sub_subcategories[] = [
                'id' => $row['id'],
                'subcategory_id' => $row['subcategory_id'],
                'sub_subcategory_name' => $row['sub_subcategory_name'],
                'sub_subcategory_slug' => $row['sub_subcategory_slug'],
                'image_path' => $row['image_path']
            ];
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'subsubcategories' => $sub_subcategories,
            'count' => count($sub_subcategories),
            'is_guest' => $is_guest
        ]);
        exit;
    }
    
    // ✅ Handle subcategories request
    if (isset($_GET['category_id'])) {
        $category_id = (int)$_GET['category_id'];
        
        if ($category_id <= 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid category ID',
                'is_guest' => $is_guest
            ]);
            exit;
        }
        
        // Fetch subcategories for the selected category with all fields including image_path
        $stmt = $conn->prepare("
            SELECT 
                id, 
                category_id, 
                subcategory_name, 
                subcategory_slug, 
                image_path 
            FROM product_subcategories 
            WHERE category_id = ? 
            ORDER BY subcategory_name ASC
        ");
        
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $subcategories = [];
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = [
                'id' => $row['id'],
                'category_id' => $row['category_id'],
                'subcategory_name' => $row['subcategory_name'],
                'subcategory_slug' => $row['subcategory_slug'],
                'image_path' => $row['image_path']
            ];
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'subcategories' => $subcategories,
            'count' => count($subcategories),
            'is_guest' => $is_guest
        ]);
        exit;
    }
    
    // No valid parameters provided
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters (category_id or subcategory_id)',
        'is_guest' => $is_guest
    ]);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching data',
        'error' => $e->getMessage(),
        'is_guest' => $is_guest
    ]);
}
?>