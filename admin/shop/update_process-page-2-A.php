<?php
//update_process-page-2-A.php - FIXED VERSION
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['noble_user'])) {
  header("Location: ../../loginpage/index.php");
  exit();
}

$product_id = $_POST['product_id'] ?? null;

if (!$product_id) {
  die("Invalid product ID");
}

try {
  $conn->begin_transaction();

  // 1. UPDATE BASIC PRODUCT INFO
  $product_name = $conn->real_escape_string($_POST['product_name']);
  $quantity = (int)$_POST['quantity'];
  $description = $conn->real_escape_string($_POST['description']);
  $category = $conn->real_escape_string($_POST['category']);

  $updateProductSQL = "UPDATE products 
                      SET product_name = '$product_name',
                          quantity = $quantity,
                          description = '$description',
                          codename = '$category'
                      WHERE id = $product_id";
  
  if (!$conn->query($updateProductSQL)) {
    throw new Exception("Failed to update product: " . $conn->error);
  }

  // 2. HANDLE PRODUCT COLORS
  if (isset($_POST['color_id'])) {
    $colorIds = $_POST['color_id'] ?? [];
    $colorNames = $_POST['color_name'] ?? [];
    $colorCodes = $_POST['color_code'] ?? [];
    $colorPrices = $_POST['color_price'] ?? [];
    $colorStocks = $_POST['color_stock'] ?? [];

    $fileIndex = 0;

    foreach ($colorIds as $index => $colorId) {
      $colorName = $conn->real_escape_string($colorNames[$index]);
      $colorCode = $conn->real_escape_string($colorCodes[$index]);
      $colorPrice = (float)$colorPrices[$index];
      $colorStock = (int)($colorStocks[$index] ?? 0);

      // Delete color
      if (isset($_POST['delete_color']) && in_array($colorId, $_POST['delete_color'])) {
        $conn->query("DELETE FROM product_variant_colors WHERE color_id = $colorId");
        $conn->query("DELETE FROM product_colors WHERE id = $colorId");
        $fileIndex++;
        continue;
      }

      $colorImagePath = '';

      if ($colorId === 'new') {
        // Insert new color
        if (!empty($_FILES['color_image']['name'][$fileIndex])) {
          $colorImageName = time() . '_' . basename($_FILES['color_image']['name'][$fileIndex]);
          $uploadDir = '../../uploads/color_images/';
          
          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }
          
          $colorImagePath = $uploadDir . $colorImageName;
          
          if (move_uploaded_file($_FILES['color_image']['tmp_name'][$fileIndex], $colorImagePath)) {
            $colorImagePath = 'uploads/color_images/' . $colorImageName;
          } else {
            throw new Exception("Failed to upload color image");
          }
        }
        
        $insertColorSQL = "INSERT INTO product_colors (product_id, color_name, color_code, price, image, stock) 
                         VALUES ($product_id, '$colorName', '$colorCode', $colorPrice, '$colorImagePath', $colorStock)";
        
        if (!$conn->query($insertColorSQL)) {
          throw new Exception("Failed to insert color: " . $conn->error);
        }
      } else {
        // Update existing color
        if (!empty($_FILES['color_image']['name'][$fileIndex])) {
          $colorImageName = time() . '_' . basename($_FILES['color_image']['name'][$fileIndex]);
          $uploadDir = '../../uploads/color_images/';
          
          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }
          
          $colorImagePath = $uploadDir . $colorImageName;
          
          if (move_uploaded_file($_FILES['color_image']['tmp_name'][$fileIndex], $colorImagePath)) {
            $colorImagePath = 'uploads/color_images/' . $colorImageName;
            $conn->query("UPDATE product_colors 
                         SET color_name = '$colorName', color_code = '$colorCode', price = $colorPrice, image = '$colorImagePath', stock = $colorStock
                         WHERE id = $colorId");
          }
        } else {
          $conn->query("UPDATE product_colors 
                       SET color_name = '$colorName', color_code = '$colorCode', price = $colorPrice, stock = $colorStock
                       WHERE id = $colorId");
        }
      }

      $fileIndex++;
    }
  }

  // 3. HANDLE TYPES AND VARIANTS
  if (isset($_POST['type_id'])) {
    $typeIds = $_POST['type_id'] ?? [];

    foreach ($typeIds as $typeIndex => $typeId) {
      $typeName = $conn->real_escape_string($_POST['type_name'][$typeIndex] ?? '');

      // Delete type
      if (isset($_POST['delete_type']) && in_array($typeId, $_POST['delete_type'])) {
        $variantCheck = $conn->query("SELECT id FROM product_variants WHERE type_id = $typeId");
        if ($variantCheck && $variantCheck->num_rows > 0) {
          while ($vrow = $variantCheck->fetch_assoc()) {
            $conn->query("DELETE FROM product_variant_colors WHERE variant_id = " . $vrow['id']);
          }
        }
        $conn->query("DELETE FROM product_variants WHERE type_id = $typeId");
        $conn->query("DELETE FROM product_types WHERE id = $typeId");
        continue;
      }

      // Handle type image
      $typeImagePath = null;
      if ($typeId === 'new') {
        if (!empty($_FILES['type_image']['name'][$typeIndex])) {
          $typeImageName = time() . '_' . basename($_FILES['type_image']['name'][$typeIndex]);
          $uploadPath = '../../uploads/type_images/' . $typeImageName;
          if (move_uploaded_file($_FILES['type_image']['tmp_name'][$typeIndex], $uploadPath)) {
            $typeImagePath = 'uploads/type_images/' . $typeImageName;
          }
        }

        $insertTypeSQL = "INSERT INTO product_types (product_id, type_name, type_image) 
                         VALUES ($product_id, '$typeName', " . ($typeImagePath ? "'$typeImagePath'" : "NULL") . ")";
        if (!$conn->query($insertTypeSQL)) {
          throw new Exception("Failed to insert type: " . $conn->error);
        }
        $typeId = $conn->insert_id;
      } else {
        if (!empty($_FILES['type_image']['name'][$typeIndex])) {
          $typeImageName = time() . '_' . basename($_FILES['type_image']['name'][$typeIndex]);
          $uploadPath = '../../uploads/type_images/' . $typeImageName;
          if (move_uploaded_file($_FILES['type_image']['tmp_name'][$typeIndex], $uploadPath)) {
            $typeImagePath = 'uploads/type_images/' . $typeImageName;
            $conn->query("UPDATE product_types SET type_name = '$typeName', type_image = '$typeImagePath' WHERE id = $typeId");
          }
        } else {
          $conn->query("UPDATE product_types SET type_name = '$typeName' WHERE id = $typeId");
        }
      }

      // Handle variants for this type
      if (isset($_POST['variant_id'][$typeIndex])) {
        $variantIds = $_POST['variant_id'][$typeIndex] ?? [];
        $variantSizes = $_POST['variant_size'][$typeIndex] ?? [];
        $variantNamevariants = $_POST['variant_namevariant'][$typeIndex] ?? [];
        $variantOriginalPrices = $_POST['variant_original_price'][$typeIndex] ?? [];
        $variantPercents = $_POST['variant_percent'][$typeIndex] ?? [];
        $variantDiscounts = $_POST['variant_discount'][$typeIndex] ?? [];
        $variantPrices = $_POST['variant_price'][$typeIndex] ?? [];
        $variantWidths = $_POST['variant_width'][$typeIndex] ?? [];
        $variantHeights = $_POST['variant_height'][$typeIndex] ?? [];
        $variantLengths = $_POST['variant_length'][$typeIndex] ?? [];
        $variantDimensionUnits = $_POST['variant_dimension_unit'][$typeIndex] ?? [];
        $variantWeights = $_POST['variant_weight'][$typeIndex] ?? [];
        $variantWeightUnits = $_POST['variant_weight_unit'][$typeIndex] ?? [];
        
        // Timer discount data
        $variantTimerDiscounts = $_POST['variant_timer_discount'][$typeIndex] ?? [];
        $variantTimerActives = $_POST['variant_timer_active'][$typeIndex] ?? [];
        $variantTimerStarts = $_POST['variant_timer_start'][$typeIndex] ?? [];
        $variantTimerEnds = $_POST['variant_timer_end'][$typeIndex] ?? [];

        foreach ($variantIds as $variantIndex => $variantId) {
          $variantSize = $conn->real_escape_string($variantSizes[$variantIndex] ?? '');
          $variantNamevariant = $conn->real_escape_string($variantNamevariants[$variantIndex] ?? '');
          $variantOriginalPrice = (float)($variantOriginalPrices[$variantIndex] ?? 0);
          $variantPercent = (float)($variantPercents[$variantIndex] ?? 0);
          $variantDiscount = (float)($variantDiscounts[$variantIndex] ?? 0);
          $variantPrice = (float)($variantPrices[$variantIndex] ?? 0);
          $variantWidth = !empty($variantWidths[$variantIndex]) ? (float)$variantWidths[$variantIndex] : null;
          $variantHeight = !empty($variantHeights[$variantIndex]) ? (float)$variantHeights[$variantIndex] : null;
          $variantLength = !empty($variantLengths[$variantIndex]) ? (float)$variantLengths[$variantIndex] : null;
          $variantDimensionUnit = $conn->real_escape_string($variantDimensionUnits[$variantIndex] ?? 'cm');
          $variantWeight = !empty($variantWeights[$variantIndex]) ? (float)$variantWeights[$variantIndex] : null;
          $variantWeightUnit = $conn->real_escape_string($variantWeightUnits[$variantIndex] ?? 'kg');
          
          // Process timer discount data
          $timerDiscount = (float)($variantTimerDiscounts[$variantIndex] ?? 0);
          $timerActive = isset($variantTimerActives[$variantIndex]) ? 1 : 0;
          $timerStart = !empty($variantTimerStarts[$variantIndex]) 
              ? "'" . $conn->real_escape_string($variantTimerStarts[$variantIndex]) . "'" 
              : "NULL";
          $timerEnd = !empty($variantTimerEnds[$variantIndex]) 
              ? "'" . $conn->real_escape_string($variantTimerEnds[$variantIndex]) . "'" 
              : "NULL";

          // Delete variant
          if (isset($_POST['delete_variant'][$typeIndex]) && in_array($variantId, $_POST['delete_variant'][$typeIndex])) {
            $conn->query("DELETE FROM product_variant_colors WHERE variant_id = $variantId");
            $conn->query("DELETE FROM product_variants WHERE id = $variantId");
            continue;
          }

          // 🔧 FIX: Store old variant ID before updating
          $oldVariantId = $variantId;

          // Insert or update variant
          if ($variantId === 'new') {
            // ✅ FIXED: Added product_id to INSERT
            $insertVariantSQL = "INSERT INTO product_variants 
                                (product_id, type_id, size, namevariant, original_price, percent, discount, price, 
                                 width, height, length, dimension_unit, weight, weight_unit,
                                 timer_discount_percent, timer_discount_active, timer_discount_start, timer_discount_end)
                                VALUES ($product_id, $typeId, '$variantSize', '$variantNamevariant', $variantOriginalPrice, 
                                        $variantPercent, $variantDiscount, $variantPrice, 
                                        " . ($variantWidth !== null ? $variantWidth : "NULL") . ", " .
                                        ($variantHeight !== null ? $variantHeight : "NULL") . ", " .
                                        ($variantLength !== null ? $variantLength : "NULL") . ", " .
                                        "'$variantDimensionUnit', " .
                                        ($variantWeight !== null ? $variantWeight : "NULL") . ", " .
                                        "'$variantWeightUnit',
                                        $timerDiscount, $timerActive, $timerStart, $timerEnd)";
            if (!$conn->query($insertVariantSQL)) {
              throw new Exception("Failed to insert variant: " . $conn->error);
            }
            $variantId = $conn->insert_id; // 🔧 Get the NEW variant ID
          } else {
            $updateVariantSQL = "UPDATE product_variants 
                                SET size = '$variantSize', 
                                    namevariant = '$variantNamevariant', 
                                    original_price = $variantOriginalPrice,
                                    percent = $variantPercent, 
                                    discount = $variantDiscount, 
                                    price = $variantPrice,
                                    width = " . ($variantWidth !== null ? $variantWidth : "NULL") . ", " .
                                    "height = " . ($variantHeight !== null ? $variantHeight : "NULL") . ", " .
                                    "length = " . ($variantLength !== null ? $variantLength : "NULL") . ", " .
                                    "dimension_unit = '$variantDimensionUnit',
                                    weight = " . ($variantWeight !== null ? $variantWeight : "NULL") . ", " .
                                    "weight_unit = '$variantWeightUnit',
                                    timer_discount_percent = $timerDiscount,
                                    timer_discount_active = $timerActive,
                                    timer_discount_start = $timerStart,
                                    timer_discount_end = $timerEnd
                                WHERE id = $variantId";
            if (!$conn->query($updateVariantSQL)) {
              throw new Exception("Failed to update variant: " . $conn->error);
            }
          }

          // 4. HANDLE VARIANT-COLOR JUNCTION
          
          // Delete variant-color relationships
          if (isset($_POST['delete_variant_color'][$typeIndex][$oldVariantId])) {
            foreach ($_POST['delete_variant_color'][$typeIndex][$oldVariantId] as $vcId) {
              $conn->query("DELETE FROM product_variant_colors WHERE id = $vcId");
            }
          }

          // Update existing variant-color stock
          if (isset($_POST['variant_color_id'][$typeIndex][$oldVariantId])) {
            $variantColorIds = $_POST['variant_color_id'][$typeIndex][$oldVariantId] ?? [];
            $variantColorStocks = $_POST['variant_color_stock'][$typeIndex][$oldVariantId] ?? [];
            
            foreach ($variantColorIds as $vcIndex => $vcId) {
              $stock = (int)($variantColorStocks[$vcIndex] ?? 0);
              $conn->query("UPDATE product_variant_colors SET stock_quantity = $stock WHERE id = $vcId");
            }
          }

          // 🔧 FIX: Add new variant-color combinations using CORRECT variant ID
          // Check for both old ID (for existing variants) and generated IDs (for new variants)
          $colorKeysToCheck = [$oldVariantId];
          
          // Also check for timestamp-based keys (e.g., "new-1234567890")
          if ($oldVariantId === 'new') {
            foreach ($_POST['new_variant_color'][$typeIndex] ?? [] as $key => $value) {
              if (strpos($key, 'new-') === 0) {
                $colorKeysToCheck[] = $key;
              }
            }
          }

          foreach ($colorKeysToCheck as $checkKey) {
            if (isset($_POST['new_variant_color'][$typeIndex][$checkKey])) {
              $newColorIds = $_POST['new_variant_color'][$typeIndex][$checkKey] ?? [];
              $newColorStocks = $_POST['new_variant_color_stock'][$typeIndex][$checkKey] ?? [];
              
              foreach ($newColorIds as $colorIndex => $newColorId) {
                if (!empty($newColorId)) {
                  $stock = (int)($newColorStocks[$colorIndex] ?? 0);
                  
                  // Check if already exists
                  $checkSQL = "SELECT id FROM product_variant_colors 
                              WHERE variant_id = $variantId AND color_id = $newColorId";
                  $checkResult = $conn->query($checkSQL);
                  
                  if (!$checkResult || $checkResult->num_rows === 0) {
                    $insertJunctionSQL = "INSERT INTO product_variant_colors (variant_id, color_id, stock_quantity) 
                                         VALUES ($variantId, $newColorId, $stock)";
                    if (!$conn->query($insertJunctionSQL)) {
                      throw new Exception("Failed to insert variant-color: " . $conn->error);
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  $conn->commit();

  $_SESSION['success_message'] = "Product updated successfully with timer discount!";
  header("Location: update_product-page-2-A.php?id=$product_id&success=1");
  exit();

} catch (Exception $e) {
  $conn->rollback();
  error_log("Update Product Error: " . $e->getMessage());
  
  $_SESSION['error_message'] = "Error: " . $e->getMessage();
  header("Location: update_product-page-2-A.php?id=$product_id&error=1");
  exit();
}
?>