<?php
// update_process-page-2-A.php - UPDATED WITH DESCRIP1/6/7 SUPPORT
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require_once '../notification/main-handler-notif-page-2.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['noble_user'])) {
  header("Location: ../../loginpage/index.php");
  exit();
}

$product_id = $_POST['product_id'] ?? null;

// ── Current user info ─────────────────────────────────────────────────────────
$current_user  = $_SESSION['noble_user'];
$current_role  = $_SESSION['noble_role'] ?? '';
$is_superadmin = ($current_role === 'superadmin');

// ── Ownership check ───────────────────────────────────────────────────────────
if (!$is_superadmin) {
  $own_check = $conn->prepare("SELECT id FROM products WHERE id = ? AND added_by = ?");
  $own_check->bind_param("is", $product_id, $current_user);
  $own_check->execute();
  if ($own_check->get_result()->num_rows === 0) {
    echo "<script>alert('Access denied. You can only edit your own products.'); window.location.href='main-adminupdateshop-page-2.php';</script>";
    exit();
  }
  $own_check->close();
}

if (!$product_id) die("Invalid product ID");

function convertDatetimeLocalToMySql($datetimeLocal) {
  if (empty($datetimeLocal)) return null;
  try {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $datetimeLocal, new DateTimeZone('Asia/Manila'));
    if (!$dt) { error_log("❌ Failed to parse datetime-local: $datetimeLocal"); return null; }
    return $dt->format('Y-m-d H:i:s');
  } catch (Exception $e) { error_log("❌ DateTime conversion error: " . $e->getMessage()); return null; }
}

function calculateDurationSeconds($startDatetime, $endDatetime) {
  if (empty($startDatetime) || empty($endDatetime)) return 0;
  try {
    $start = new DateTime($startDatetime, new DateTimeZone('Asia/Manila'));
    $end   = new DateTime($endDatetime,   new DateTimeZone('Asia/Manila'));
    $interval = $end->diff($start);
    $seconds = ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    return $seconds < 0 ? 0 : (int)$seconds;
  } catch (Exception $e) { return 0; }
}

function formatDuration($seconds) {
  if ($seconds <= 0) return "No duration";
  $days    = intdiv($seconds, 86400); $remaining = $seconds % 86400;
  $hours   = intdiv($remaining, 3600); $remaining = $remaining % 3600;
  $minutes = intdiv($remaining, 60);   $secs = $remaining % 60;
  $parts = [];
  if ($days > 0)    $parts[] = "$days day"    . ($days > 1 ? 's' : '');
  if ($hours > 0)   $parts[] = "$hours hour"  . ($hours > 1 ? 's' : '');
  if ($minutes > 0) $parts[] = "$minutes minute" . ($minutes > 1 ? 's' : '');
  if ($secs > 0 && $days === 0 && $hours === 0) $parts[] = "$secs second" . ($secs > 1 ? 's' : '');
  return !empty($parts) ? implode(', ', $parts) : "Less than a second";
}

try {
  $conn->begin_transaction();

  $productNameResult = $conn->query("SELECT product_name FROM products WHERE id = $product_id");
  $productNameRow    = $productNameResult->fetch_assoc();
  $product_name      = $productNameRow['product_name'] ?? 'Unknown Product';

  // ── 1. UPDATE BASIC PRODUCT INFO + EXTENDED DESCRIPTIONS ──────
  $product_name_new = $conn->real_escape_string($_POST['product_name']);
  $quantity         = (int)$_POST['quantity'];
  $description      = $conn->real_escape_string($_POST['description']);
  $category         = $conn->real_escape_string($_POST['category']);

  // ✅ EXTENDED DESCRIPTIONS
  $descrip1 = $conn->real_escape_string($_POST['descrip1'] ?? '');
  $descrip6 = $conn->real_escape_string($_POST['descrip6'] ?? '');
  $descrip7 = $conn->real_escape_string($_POST['descrip7'] ?? '');

  $updateProductSQL = "UPDATE products 
                       SET product_name = '$product_name_new',
                           quantity     = $quantity,
                           description  = '$description',
                           codename     = '$category',
                           descrip1     = '$descrip1',
                           descrip6     = '$descrip6',
                           descrip7     = '$descrip7'
                       WHERE id = $product_id";

  if (!$conn->query($updateProductSQL)) {
    throw new Exception("Failed to update product: " . $conn->error);
  }

  if ($product_name_new !== $product_name) $product_name = $product_name_new;

  // ── 2. MAIN IMAGE ──────────────────────────────────────────────
  if (!empty($_FILES['main_image']['name'])) {
    $oldMainImageResult = $conn->query("SELECT main_image FROM products WHERE id = $product_id");
    if ($oldMainImageResult) {
      $oldMainRow       = $oldMainImageResult->fetch_assoc();
      $oldMainImagePath = $oldMainRow['main_image'] ?? null;
      if (!empty($oldMainImagePath)) { $fullOldPath = '../../' . $oldMainImagePath; if (file_exists($fullOldPath)) unlink($fullOldPath); }
    }
    $mainImageName = time() . '_main_' . basename($_FILES['main_image']['name']);
    $uploadDir     = '../../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $mainImagePath = $uploadDir . $mainImageName;
    if (move_uploaded_file($_FILES['main_image']['tmp_name'], $mainImagePath)) {
      $mainImagePath = 'uploads/' . $mainImageName;
      $conn->query("UPDATE products SET main_image = '$mainImagePath' WHERE id = $product_id");
    } else {
      throw new Exception("Failed to upload main image");
    }
  }

  // ── 3. SUB IMAGES ──────────────────────────────────────────────
  $subImagesKeepArray = $_POST['keep_sub_image'] ?? [];
  $product            = $conn->query("SELECT sub_images FROM products WHERE id = $product_id")->fetch_assoc();
  $existingSubImages  = [];
  if (!empty($product['sub_images'])) {
    $decoded = json_decode($product['sub_images'], true);
    if (is_array($decoded)) $existingSubImages = $decoded;
  }

  $subImagesToKeep = [];
  foreach ($existingSubImages as $index => $subImageFilename) {
    $shouldKeep = isset($subImagesKeepArray[$index]) && $subImagesKeepArray[$index] == '1';
    if (!$shouldKeep) {
      $fullPath = '../../sub_images/' . $subImageFilename;
      if (file_exists($fullPath)) unlink($fullPath);
    } else {
      $subImagesToKeep[] = $subImageFilename;
    }
  }
  $updatedSubImagesJson = json_encode($subImagesToKeep);
  $conn->query("UPDATE products SET sub_images = '$updatedSubImagesJson' WHERE id = $product_id");

  if (!empty($_FILES['new_sub_images']['name'][0])) {
    $newSubImages = $subImagesToKeep;
    foreach ($_FILES['new_sub_images']['name'] as $fileIndex => $fileName) {
      if (!empty($fileName)) {
        $subImageName = time() . '_' . $fileIndex . '_' . basename($fileName);
        $uploadDir    = '../../sub_images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $uploadPath = $uploadDir . $subImageName;
        if (move_uploaded_file($_FILES['new_sub_images']['tmp_name'][$fileIndex], $uploadPath)) {
          $newSubImages[] = $subImageName;
        } else {
          throw new Exception("Failed to upload sub image at index $fileIndex");
        }
      }
    }
    $newSubImagesJson = json_encode($newSubImages);
    $conn->query("UPDATE products SET sub_images = '$newSubImagesJson' WHERE id = $product_id");
  }

  // ── 4. PRODUCT COLORS ──────────────────────────────────────────
  if (isset($_POST['color_id'])) {
    $colorIds    = $_POST['color_id']    ?? [];
    $colorNames  = $_POST['color_name']  ?? [];
    $colorCodes  = $_POST['color_code']  ?? [];
    $colorPrices = $_POST['color_price'] ?? [];
    $colorStocks = $_POST['color_stock'] ?? [];
    $fileIndex   = 0;

    foreach ($colorIds as $index => $colorId) {
      $colorName  = $conn->real_escape_string($colorNames[$index]);
      $colorCode  = $conn->real_escape_string($colorCodes[$index]);
      $colorPrice = (float)$colorPrices[$index];
      $colorStock = (int)($colorStocks[$index] ?? 0);

      if (isset($_POST['delete_color']) && in_array($colorId, $_POST['delete_color'])) {
        $getColorImagesResult = $conn->query("SELECT image, image2 FROM product_colors WHERE id = $colorId");
        if ($getColorImagesResult) {
          $colorRow = $getColorImagesResult->fetch_assoc();
          if (!empty($colorRow['image']))  { $fp = '../../' . $colorRow['image'];  if (file_exists($fp)) unlink($fp); }
          if (!empty($colorRow['image2'])) { $fp = '../../' . $colorRow['image2']; if (file_exists($fp)) unlink($fp); }
        }
        $conn->query("DELETE FROM product_variant_colors WHERE color_id = $colorId");
        $conn->query("DELETE FROM product_colors WHERE id = $colorId");
        $fileIndex++; continue;
      }

      $colorImagePath = ''; $colorImage2Path = '';

      if ($colorId === 'new') {
        if (!empty($_FILES['color_image']['name'][$fileIndex])) {
          $colorImageName = time() . '_' . basename($_FILES['color_image']['name'][$fileIndex]);
          $uploadDir = '../../uploads/';
          if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
          $colorImagePath = $uploadDir . $colorImageName;
          if (move_uploaded_file($_FILES['color_image']['tmp_name'][$fileIndex], $colorImagePath)) { $colorImagePath = 'uploads/' . $colorImageName; } else { throw new Exception("Failed to upload main color image"); }
        }
        if (!empty($_FILES['color_image2']['name'][$fileIndex])) {
          $colorImage2Name = time() . '_secondary_' . basename($_FILES['color_image2']['name'][$fileIndex]);
          $uploadDir = '../../uploads/';
          if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
          $colorImage2Path = $uploadDir . $colorImage2Name;
          if (move_uploaded_file($_FILES['color_image2']['tmp_name'][$fileIndex], $colorImage2Path)) { $colorImage2Path = 'uploads/' . $colorImage2Name; } else { throw new Exception("Failed to upload secondary color image"); }
        }
        $insertColorSQL = "INSERT INTO product_colors (product_id, color_name, color_code, price, image, image2, stock) VALUES ($product_id, '$colorName', '$colorCode', $colorPrice, '$colorImagePath', '$colorImage2Path', $colorStock)";
        if (!$conn->query($insertColorSQL)) throw new Exception("Failed to insert color: " . $conn->error);
      } else {
        $updateSQL = "UPDATE product_colors SET color_name = '$colorName', color_code = '$colorCode', price = $colorPrice, stock = $colorStock";
        if (!empty($_FILES['color_image']['name'][$fileIndex])) {
          $old = $conn->query("SELECT image FROM product_colors WHERE id = $colorId")->fetch_assoc();
          if (!empty($old['image'])) { $fp = '../../' . $old['image']; if (file_exists($fp)) unlink($fp); }
          $colorImageName = time() . '_' . basename($_FILES['color_image']['name'][$fileIndex]);
          $uploadDir = '../../uploads/'; if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
          $colorImagePath = $uploadDir . $colorImageName;
          if (move_uploaded_file($_FILES['color_image']['tmp_name'][$fileIndex], $colorImagePath)) { $colorImagePath = 'uploads/' . $colorImageName; $updateSQL .= ", image = '$colorImagePath'"; }
        }
        if (!empty($_FILES['color_image2']['name'][$fileIndex])) {
          $old2 = $conn->query("SELECT image2 FROM product_colors WHERE id = $colorId")->fetch_assoc();
          if (!empty($old2['image2'])) { $fp = '../../' . $old2['image2']; if (file_exists($fp)) unlink($fp); }
          $colorImage2Name = time() . '_secondary_' . basename($_FILES['color_image2']['name'][$fileIndex]);
          $uploadDir = '../../uploads/'; if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
          $colorImage2Path = $uploadDir . $colorImage2Name;
          if (move_uploaded_file($_FILES['color_image2']['tmp_name'][$fileIndex], $colorImage2Path)) { $colorImage2Path = 'uploads/' . $colorImage2Name; $updateSQL .= ", image2 = '$colorImage2Path'"; }
        }
        $updateSQL .= " WHERE id = $colorId";
        if (!$conn->query($updateSQL)) throw new Exception("Failed to update color: " . $conn->error);
      }
      $fileIndex++;
    }
  }

  // ── 5. TYPES AND VARIANTS ──────────────────────────────────────
  if (isset($_POST['type_id'])) {
    $typeIds = $_POST['type_id'] ?? [];

    foreach ($typeIds as $typeIndex => $typeId) {
      $typeName = $conn->real_escape_string($_POST['type_name'][$typeIndex] ?? '');

      if (isset($_POST['delete_type']) && in_array($typeId, $_POST['delete_type'])) {
        $getTypeImageResult = $conn->query("SELECT type_image FROM product_types WHERE id = $typeId");
        if ($getTypeImageResult) { $typeRow = $getTypeImageResult->fetch_assoc(); if (!empty($typeRow['type_image'])) { $fp = '../../' . $typeRow['type_image']; if (file_exists($fp)) unlink($fp); } }
        $variantCheck = $conn->query("SELECT id FROM product_variants WHERE type_id = $typeId");
        if ($variantCheck && $variantCheck->num_rows > 0) { while ($vrow = $variantCheck->fetch_assoc()) { $conn->query("DELETE FROM product_variant_colors WHERE variant_id = " . $vrow['id']); } }
        $conn->query("DELETE FROM product_variants WHERE type_id = $typeId");
        $conn->query("DELETE FROM product_types WHERE id = $typeId");
        continue;
      }

      $typeImagePath = null;
      if ($typeId === 'new') {
        if (!empty($_FILES['type_image']['name'][$typeIndex])) {
          $typeImageName = time() . '_' . basename($_FILES['type_image']['name'][$typeIndex]);
          $uploadDir = '../../uploads/type_images/'; if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
          $uploadPath = $uploadDir . $typeImageName;
          if (move_uploaded_file($_FILES['type_image']['tmp_name'][$typeIndex], $uploadPath)) $typeImagePath = 'uploads/type_images/' . $typeImageName;
        }
        $insertTypeSQL = "INSERT INTO product_types (product_id, type_name, type_image) VALUES ($product_id, '$typeName', " . ($typeImagePath ? "'$typeImagePath'" : "NULL") . ")";
        if (!$conn->query($insertTypeSQL)) throw new Exception("Failed to insert type: " . $conn->error);
        $typeId = $conn->insert_id;
      } else {
        if (!empty($_FILES['type_image']['name'][$typeIndex])) {
          $oldType = $conn->query("SELECT type_image FROM product_types WHERE id = $typeId")->fetch_assoc();
          if (!empty($oldType['type_image'])) { $fp = '../../' . $oldType['type_image']; if (file_exists($fp)) unlink($fp); }
          $typeImageName = time() . '_' . basename($_FILES['type_image']['name'][$typeIndex]);
          $uploadDir = '../../uploads/type_images/'; if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
          $uploadPath = $uploadDir . $typeImageName;
          if (move_uploaded_file($_FILES['type_image']['tmp_name'][$typeIndex], $uploadPath)) { $typeImagePath = 'uploads/type_images/' . $typeImageName; $conn->query("UPDATE product_types SET type_name = '$typeName', type_image = '$typeImagePath' WHERE id = $typeId"); }
        } else {
          $conn->query("UPDATE product_types SET type_name = '$typeName' WHERE id = $typeId");
        }
      }

      if (isset($_POST['variant_id'][$typeIndex])) {
        $variantIds             = $_POST['variant_id'][$typeIndex]             ?? [];
        $variantSizes           = $_POST['variant_size'][$typeIndex]           ?? [];
        $variantNamevariants    = $_POST['variant_namevariant'][$typeIndex]    ?? [];
        $variantOriginalPrices  = $_POST['variant_original_price'][$typeIndex] ?? [];
        $variantPercents        = $_POST['variant_percent'][$typeIndex]        ?? [];
        $variantDiscounts       = $_POST['variant_discount'][$typeIndex]       ?? [];
        $variantPrices          = $_POST['variant_price'][$typeIndex]          ?? [];
        $variantWidths          = $_POST['variant_width'][$typeIndex]          ?? [];
        $variantHeights         = $_POST['variant_height'][$typeIndex]         ?? [];
        $variantLengths         = $_POST['variant_length'][$typeIndex]         ?? [];
        $variantDimensionUnits  = $_POST['variant_dimension_unit'][$typeIndex] ?? [];
        $variantWeights         = $_POST['variant_weight'][$typeIndex]         ?? [];
        $variantWeightUnits     = $_POST['variant_weight_unit'][$typeIndex]    ?? [];
        $variantTimerDiscounts  = $_POST['variant_timer_discount'][$typeIndex] ?? [];
        $variantTimerActives    = $_POST['variant_timer_active'][$typeIndex]   ?? [];
        $variantTimerStarts     = $_POST['variant_timer_start'][$typeIndex]    ?? [];
        $variantTimerEnds       = $_POST['variant_timer_end'][$typeIndex]      ?? [];

        foreach ($variantIds as $variantIndex => $variantId) {
          $variantSize           = $conn->real_escape_string($variantSizes[$variantIndex]          ?? '');
          $variantNamevariant    = $conn->real_escape_string($variantNamevariants[$variantIndex]   ?? '');
          $variantOriginalPrice  = (float)($variantOriginalPrices[$variantIndex]                  ?? 0);
          $variantPercent        = (float)($variantPercents[$variantIndex]                         ?? 0);
          $variantDiscount       = (float)($variantDiscounts[$variantIndex]                        ?? 0);
          $variantWidth          = !empty($variantWidths[$variantIndex])  ? (float)$variantWidths[$variantIndex]  : null;
          $variantHeight         = !empty($variantHeights[$variantIndex]) ? (float)$variantHeights[$variantIndex] : null;
          $variantLength         = !empty($variantLengths[$variantIndex]) ? (float)$variantLengths[$variantIndex] : null;
          $variantDimensionUnit  = $conn->real_escape_string($variantDimensionUnits[$variantIndex] ?? 'cm');
          $variantWeight         = !empty($variantWeights[$variantIndex]) ? (float)$variantWeights[$variantIndex] : null;
          $variantWeightUnit     = $conn->real_escape_string($variantWeightUnits[$variantIndex]    ?? 'kg');

          $priceAfterMarkup          = $variantOriginalPrice + ($variantOriginalPrice * $variantPercent / 100);
          $priceAfterRegularDiscount = $priceAfterMarkup - ($priceAfterMarkup * $variantDiscount / 100);

          $timerDiscount    = (float)($variantTimerDiscounts[$variantIndex] ?? 0);
          $timerActive      = isset($variantTimerActives[$variantIndex]) ? 1 : 0;

          $timerStartFormatted = null; $timerStart = "NULL";
          if (!empty($variantTimerStarts[$variantIndex])) {
            $timerStartFormatted = convertDatetimeLocalToMySql($variantTimerStarts[$variantIndex]);
            if ($timerStartFormatted) $timerStart = "'" . $conn->real_escape_string($timerStartFormatted) . "'";
          }

          $timerEndFormatted = null; $timerEnd = "NULL";
          if (!empty($variantTimerEnds[$variantIndex])) {
            $timerEndFormatted = convertDatetimeLocalToMySql($variantTimerEnds[$variantIndex]);
            if ($timerEndFormatted) $timerEnd = "'" . $conn->real_escape_string($timerEndFormatted) . "'";
          }

          $timerDurationSeconds  = 0; $timerDurationFormatted = "No duration";
          if ($timerStartFormatted && $timerEndFormatted) {
            $timerDurationSeconds  = calculateDurationSeconds($timerStartFormatted, $timerEndFormatted);
            $timerDurationFormatted = formatDuration($timerDurationSeconds);
          }

          $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
          $finalPrice = $priceAfterRegularDiscount; $finalTimerActive = $timerActive;

          if ($timerEndFormatted && $timerStartFormatted) {
            $startDateTime = new DateTime($timerStartFormatted, new DateTimeZone('Asia/Manila'));
            $endDateTime   = new DateTime($timerEndFormatted,   new DateTimeZone('Asia/Manila'));
            if ($endDateTime < $now) {
              $finalTimerActive = 0;
            } elseif ($now >= $startDateTime && $now < $endDateTime && $finalTimerActive) {
              $finalPrice = $priceAfterRegularDiscount - ($priceAfterRegularDiscount * $timerDiscount / 100);
            }
          }

          if (isset($_POST['delete_variant'][$typeIndex]) && in_array($variantId, $_POST['delete_variant'][$typeIndex])) {
            $conn->query("DELETE FROM product_variant_colors WHERE variant_id = $variantId");
            $conn->query("DELETE FROM product_variants WHERE id = $variantId");
            continue;
          }

          $oldVariantId = $variantId;

          if ($variantId === 'new') {
            $insertVariantSQL = "INSERT INTO product_variants 
                                (product_id, type_id, size, namevariant, original_price, percent, discount, price,
                                 width, height, length, dimension_unit, weight, weight_unit,
                                 timer_discount_percent, timer_discount_active, timer_discount_start, timer_discount_end,
                                 timer_discount_duration_seconds, timer_discount_duration_formatted)
                                VALUES ($product_id, $typeId, '$variantSize', '$variantNamevariant', $variantOriginalPrice,
                                        $variantPercent, $variantDiscount, $finalPrice,
                                        " . ($variantWidth  !== null ? $variantWidth  : "NULL") . ",
                                        " . ($variantHeight !== null ? $variantHeight : "NULL") . ",
                                        " . ($variantLength !== null ? $variantLength : "NULL") . ",
                                        '$variantDimensionUnit',
                                        " . ($variantWeight !== null ? $variantWeight : "NULL") . ",
                                        '$variantWeightUnit',
                                        $timerDiscount, $finalTimerActive, $timerStart, $timerEnd,
                                        $timerDurationSeconds, '$timerDurationFormatted')";
            if (!$conn->query($insertVariantSQL)) throw new Exception("Failed to insert variant: " . $conn->error);
            $variantId = $conn->insert_id;
          } else {
            $updateVariantSQL = "UPDATE product_variants SET
                                size = '$variantSize', namevariant = '$variantNamevariant',
                                original_price = $variantOriginalPrice, percent = $variantPercent,
                                discount = $variantDiscount, price = $finalPrice,
                                width  = " . ($variantWidth  !== null ? $variantWidth  : "NULL") . ",
                                height = " . ($variantHeight !== null ? $variantHeight : "NULL") . ",
                                length = " . ($variantLength !== null ? $variantLength : "NULL") . ",
                                dimension_unit = '$variantDimensionUnit',
                                weight = " . ($variantWeight !== null ? $variantWeight : "NULL") . ",
                                weight_unit = '$variantWeightUnit',
                                timer_discount_percent = $timerDiscount,
                                timer_discount_active  = $finalTimerActive,
                                timer_discount_start   = $timerStart,
                                timer_discount_end     = $timerEnd,
                                timer_discount_duration_seconds  = $timerDurationSeconds,
                                timer_discount_duration_formatted = '$timerDurationFormatted'
                                WHERE id = $variantId";
            if (!$conn->query($updateVariantSQL)) throw new Exception("Failed to update variant: " . $conn->error);
          }

          // Variant-color junction
          if (isset($_POST['delete_variant_color'][$typeIndex][$oldVariantId])) {
            foreach ($_POST['delete_variant_color'][$typeIndex][$oldVariantId] as $vcId) {
              $conn->query("DELETE FROM product_variant_colors WHERE id = $vcId");
            }
          }
          if (isset($_POST['variant_color_id'][$typeIndex][$oldVariantId])) {
            $variantColorIds    = $_POST['variant_color_id'][$typeIndex][$oldVariantId]    ?? [];
            $variantColorStocks = $_POST['variant_color_stock'][$typeIndex][$oldVariantId] ?? [];
            foreach ($variantColorIds as $vcIndex => $vcId) {
              $stock = (int)($variantColorStocks[$vcIndex] ?? 0);
              $conn->query("UPDATE product_variant_colors SET stock_quantity = $stock WHERE id = $vcId");
            }
          }

          $colorKeysToCheck = [$oldVariantId];
          if ($oldVariantId === 'new') {
            foreach ($_POST['new_variant_color'][$typeIndex] ?? [] as $key => $value) {
              if (strpos($key, 'new-') === 0) $colorKeysToCheck[] = $key;
            }
          }

          foreach ($colorKeysToCheck as $checkKey) {
            if (isset($_POST['new_variant_color'][$typeIndex][$checkKey])) {
              $newColorIds    = $_POST['new_variant_color'][$typeIndex][$checkKey]       ?? [];
              $newColorStocks = $_POST['new_variant_color_stock'][$typeIndex][$checkKey] ?? [];
              foreach ($newColorIds as $colorIndex => $newColorId) {
                if (!empty($newColorId)) {
                  $stock = (int)($newColorStocks[$colorIndex] ?? 0);
                  $checkResult = $conn->query("SELECT id FROM product_variant_colors WHERE variant_id = $variantId AND color_id = $newColorId");
                  if (!$checkResult || $checkResult->num_rows === 0) {
                    $insertJunctionSQL = "INSERT INTO product_variant_colors (variant_id, color_id, stock_quantity) VALUES ($variantId, $newColorId, $stock)";
                    if (!$conn->query($insertJunctionSQL)) throw new Exception("Failed to insert variant-color: " . $conn->error);
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

  // Notification
  $notification_title   = "Product Updated";
  $notification_message = "'" . htmlspecialchars($product_name) . "' (ID: #$product_id) has been updated";
  $style = getNotificationStyle('product_update');
  createNotification($conn, 'product_update', $notification_title, $notification_message, $style['icon'], $style['color']);

  $_SESSION['success_message'] = "Product updated successfully!";
  header("Location: update_product-page-2-A.php?id=$product_id&success=1");
  exit();

} catch (Exception $e) {
  $conn->rollback();
  error_log("❌ Update Product Error: " . $e->getMessage());
  $_SESSION['error_message'] = "Error: " . $e->getMessage();
  header("Location: update_product-page-2-A.php?id=$product_id&error=1");
  exit();
}
?>