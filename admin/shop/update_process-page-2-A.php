<?php
//update_process-page-2-A.php - FIXED VERSION WITH AUTO-DELETE
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

  // ✅ UPDATE MAIN PRODUCT IMAGE WITH AUTO-DELETE
  if (!empty($_FILES['main_image']['name'])) {
    // Get old main image
    $oldMainImageResult = $conn->query("SELECT main_image FROM products WHERE id = $product_id");
    if ($oldMainImageResult) {
      $oldMainRow = $oldMainImageResult->fetch_assoc();
      $oldMainImagePath = $oldMainRow['main_image'] ?? null;
      
      // Delete old file if exists
      if (!empty($oldMainImagePath)) {
        $fullOldPath = '../../' . $oldMainImagePath;
        if (file_exists($fullOldPath)) {
          unlink($fullOldPath);
        }
      }
    }
    
    // Upload new main image
    $mainImageName = time() . '_main_' . basename($_FILES['main_image']['name']);
    $uploadDir = '../../uploads/';
    
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    
    $mainImagePath = $uploadDir . $mainImageName;
    
    if (move_uploaded_file($_FILES['main_image']['tmp_name'], $mainImagePath)) {
      $mainImagePath = 'uploads/' . $mainImageName;
      $conn->query("UPDATE products SET main_image = '$mainImagePath' WHERE id = $product_id");
    } else {
      throw new Exception("Failed to upload main image");
    }
  }

  // 2. HANDLE PRODUCT COLORS WITH AUTO-DELETE
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
        // Get images first
        $getColorImagesResult = $conn->query("SELECT image, image2 FROM product_colors WHERE id = $colorId");
        if ($getColorImagesResult) {
          $colorRow = $getColorImagesResult->fetch_assoc();
          
          // Delete image1
          if (!empty($colorRow['image'])) {
            $fullPath = '../../' . $colorRow['image'];
            if (file_exists($fullPath)) {
              unlink($fullPath);
            }
          }
          
          // Delete image2
          if (!empty($colorRow['image2'])) {
            $fullPath = '../../' . $colorRow['image2'];
            if (file_exists($fullPath)) {
              unlink($fullPath);
            }
          }
        }
        
        $conn->query("DELETE FROM product_variant_colors WHERE color_id = $colorId");
        $conn->query("DELETE FROM product_colors WHERE id = $colorId");
        $fileIndex++;
        continue;
      }

      $colorImagePath = '';
      $colorImage2Path = '';

      if ($colorId === 'new') {
        // Insert new color

        // Handle main image
        if (!empty($_FILES['color_image']['name'][$fileIndex])) {
          $colorImageName = time() . '_' . basename($_FILES['color_image']['name'][$fileIndex]);
          $uploadDir = '../../uploads/';

          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }

          $colorImagePath = $uploadDir . $colorImageName;

          if (move_uploaded_file($_FILES['color_image']['tmp_name'][$fileIndex], $colorImagePath)) {
            $colorImagePath = 'uploads/' . $colorImageName;
          } else {
            throw new Exception("Failed to upload main color image");
          }
        }

        // Handle secondary image
        if (!empty($_FILES['color_image2']['name'][$fileIndex])) {
          $colorImage2Name = time() . '_secondary_' . basename($_FILES['color_image2']['name'][$fileIndex]);
          $uploadDir = '../../uploads/';

          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }

          $colorImage2Path = $uploadDir . $colorImage2Name;

          if (move_uploaded_file($_FILES['color_image2']['tmp_name'][$fileIndex], $colorImage2Path)) {
            $colorImage2Path = 'uploads/' . $colorImage2Name;
          } else {
            throw new Exception("Failed to upload secondary color image");
          }
        }

        $insertColorSQL = "INSERT INTO product_colors (product_id, color_name, color_code, price, image, image2, stock) 
                       VALUES ($product_id, '$colorName', '$colorCode', $colorPrice, '$colorImagePath', '$colorImage2Path', $colorStock)";

        if (!$conn->query($insertColorSQL)) {
          throw new Exception("Failed to insert color: " . $conn->error);
        }
      } else {
        // Update existing color
        $updateSQL = "UPDATE product_colors SET color_name = '$colorName', color_code = '$colorCode', price = $colorPrice, stock = $colorStock";

        // ✅ Handle main image update WITH AUTO-DELETE
        if (!empty($_FILES['color_image']['name'][$fileIndex])) {
          // Get old image
          $getOldColorImageResult = $conn->query("SELECT image FROM product_colors WHERE id = $colorId");
          if ($getOldColorImageResult) {
            $oldColorRow = $getOldColorImageResult->fetch_assoc();
            $oldImagePath = $oldColorRow['image'] ?? null;
            
            // Delete old file if exists
            if (!empty($oldImagePath)) {
              $fullOldPath = '../../' . $oldImagePath;
              if (file_exists($fullOldPath)) {
                unlink($fullOldPath);
              }
            }
          }
          
          // Upload new main image
          $colorImageName = time() . '_' . basename($_FILES['color_image']['name'][$fileIndex]);
          $uploadDir = '../../uploads/';

          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }

          $colorImagePath = $uploadDir . $colorImageName;

          if (move_uploaded_file($_FILES['color_image']['tmp_name'][$fileIndex], $colorImagePath)) {
            $colorImagePath = 'uploads/' . $colorImageName;
            $updateSQL .= ", image = '$colorImagePath'";
          }
        }

        // ✅ Handle secondary image update WITH AUTO-DELETE
        if (!empty($_FILES['color_image2']['name'][$fileIndex])) {
          // Get old image2
          $getOldColorImage2Result = $conn->query("SELECT image2 FROM product_colors WHERE id = $colorId");
          if ($getOldColorImage2Result) {
            $oldColorRow2 = $getOldColorImage2Result->fetch_assoc();
            $oldImage2Path = $oldColorRow2['image2'] ?? null;
            
            // Delete old file if exists
            if (!empty($oldImage2Path)) {
              $fullOldPath2 = '../../' . $oldImage2Path;
              if (file_exists($fullOldPath2)) {
                unlink($fullOldPath2);
              }
            }
          }
          
          // Upload new secondary image
          $colorImage2Name = time() . '_secondary_' . basename($_FILES['color_image2']['name'][$fileIndex]);
          $uploadDir = '../../uploads/';

          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }

          $colorImage2Path = $uploadDir . $colorImage2Name;

          if (move_uploaded_file($_FILES['color_image2']['tmp_name'][$fileIndex], $colorImage2Path)) {
            $colorImage2Path = 'uploads/' . $colorImage2Name;
            $updateSQL .= ", image2 = '$colorImage2Path'";
          }
        }

        $updateSQL .= " WHERE id = $colorId";

        if (!$conn->query($updateSQL)) {
          throw new Exception("Failed to update color: " . $conn->error);
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
        // Get type image first
        $getTypeImageResult = $conn->query("SELECT type_image FROM product_types WHERE id = $typeId");
        if ($getTypeImageResult) {
          $typeRow = $getTypeImageResult->fetch_assoc();
          if (!empty($typeRow['type_image'])) {
            $fullPath = '../../' . $typeRow['type_image'];
            if (file_exists($fullPath)) {
              unlink($fullPath);
            }
          }
        }
        
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

      // ✅ Handle type image WITH AUTO-DELETE
      $typeImagePath = null;
      if ($typeId === 'new') {
        if (!empty($_FILES['type_image']['name'][$typeIndex])) {
          $typeImageName = time() . '_' . basename($_FILES['type_image']['name'][$typeIndex]);
          $uploadDir = '../../uploads/type_images/';
          
          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }
          
          $uploadPath = $uploadDir . $typeImageName;
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
          // Get old type image
          $getOldTypeImageResult = $conn->query("SELECT type_image FROM product_types WHERE id = $typeId");
          if ($getOldTypeImageResult) {
            $oldTypeRow = $getOldTypeImageResult->fetch_assoc();
            $oldTypeImagePath = $oldTypeRow['type_image'] ?? null;
            
            // Delete old file if exists
            if (!empty($oldTypeImagePath)) {
              $fullOldPath = '../../' . $oldTypeImagePath;
              if (file_exists($fullOldPath)) {
                unlink($fullOldPath);
              }
            }
          }
          
          // Upload new type image
          $typeImageName = time() . '_' . basename($_FILES['type_image']['name'][$typeIndex]);
          $uploadDir = '../../uploads/type_images/';
          
          if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }
          
          $uploadPath = $uploadDir . $typeImageName;
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

          $oldVariantId = $variantId;

          // Insert or update variant
          if ($variantId === 'new') {
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
            $variantId = $conn->insert_id;
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

          // Add new variant-color combinations
          $colorKeysToCheck = [$oldVariantId];

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