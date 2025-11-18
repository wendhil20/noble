<?php
include '../../connection/connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

function validateCSVFile($file)
{
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        throw new Exception("No file uploaded");
    }

    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("File too large. Maximum size is 5MB");
    }

    // Check file extension
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        throw new Exception("Only CSV files are allowed");
    }

    // Check if file can be read
    if (!is_readable($file['tmp_name'])) {
        throw new Exception("Cannot read uploaded file");
    }

    return true;
}

// Reset AUTO_INCREMENT for all tables
$tables = ['products', 'product_types', 'product_variants', 'product_colors'];

foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

if (isset($_FILES['csv_file'])) {
    try {
        // Validate CSV file
        validateCSVFile($_FILES['csv_file']);

        $conn->begin_transaction();

        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$file) {
            throw new Exception("Cannot open CSV file");
        }

        // Read and validate header
        $header = fgetcsv($file);
        $expectedHeaders = [
            'product_name',
            'codename',
            'quantity',
            'description',
            'type_name',
            'color_name',
            'color_code',
            'color_price',
            'variant_size',
            'variant_price',
            'variant_namevariant',
            'variant_percent',
            'variant_discount',
            'variant_color'
        ];

        if (count($header) < count($expectedHeaders)) {
            throw new Exception("CSV file must have at least " . count($expectedHeaders) . " columns");
        }

        $rowNumber = 1; // Start from 1 (header is row 0)
        $processedCount = 0;
        $skippedCount = 0;

        while (($row = fgetcsv($file)) !== FALSE) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($row, function ($value) {
                return trim($value) !== '';
            }))) {
                $skippedCount++;
                continue;
            }

            // Ensure we have enough columns
            $row = array_pad($row, count($expectedHeaders), '');

            try {
                // Sanitize and prepare data
                $product_name = trim($row[0] ?? '');
                $codename = trim($row[1] ?? '');
                $quantity = max(0, (int)($row[2] ?? 0));
                $description = trim($row[3] ?? '');
                $type_name = trim($row[4] ?? '');
                $color_name = trim($row[5] ?? '');
                $color_code = trim($row[6] ?? '');
                $color_price = max(0, (float)($row[7] ?? 0));
                $variant_size = trim($row[8] ?? '');
                $variant_price = max(0, (float)($row[9] ?? 0));
                $variant_namevariant = trim($row[10] ?? '');
                $variant_percent = (float)($row[11] ?? 0);
                $variant_discount = max(0, (float)($row[12] ?? 0));
                $variant_color = trim($row[13] ?? '');

                // Skip if essential fields are empty
                if (empty($product_name) || empty($codename)) {
                    $skippedCount++;
                    continue;
                }

                // 1. Insert or get product
                $stmt = $conn->prepare("SELECT id FROM products WHERE product_name = ? AND codename = ?");
                $stmt->bind_param("ss", $product_name, $codename);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $product = $result->fetch_assoc();
                    $product_id = $product['id'];

                    // Update quantity
                    $stmt2 = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
                    $stmt2->bind_param("ii", $quantity, $product_id);
                    $stmt2->execute();
                    $stmt2->close();
                } else {
                    // Insert new product
                    $stmt2 = $conn->prepare("INSERT INTO products (product_name, codename, quantity, main_image, description) VALUES (?, ?, ?, NULL, ?)");
                    $stmt2->bind_param("ssis", $product_name, $codename, $quantity, $description);
                    $stmt2->execute();
                    $product_id = $conn->insert_id;
                    $stmt2->close();
                }
                $stmt->close();

                $type_id = null;

                // 2. Insert or get product type (if type_name provided)
                if (!empty($type_name)) {
                    $stmt = $conn->prepare("SELECT id FROM product_types WHERE product_id = ? AND type_name = ?");
                    $stmt->bind_param("is", $product_id, $type_name);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $type = $result->fetch_assoc();
                        $type_id = $type['id'];
                    } else {
                        $stmt2 = $conn->prepare("INSERT INTO product_types (product_id, type_name, type_image) VALUES (?, ?, NULL)");
                        $stmt2->bind_param("is", $product_id, $type_name);
                        $stmt2->execute();
                        $type_id = $conn->insert_id;
                        $stmt2->close();
                    }
                    $stmt->close();
                }

                // 3. Insert product color (if color data provided)
                if (!empty($color_name)) {
                    $stmt = $conn->prepare("SELECT id FROM product_colors WHERE product_id = ? AND color_name = ?");
                    $stmt->bind_param("is", $product_id, $color_name);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 0) {
                        $stmt2 = $conn->prepare("INSERT INTO product_colors (product_id, color_name, color_code, price, image) VALUES (?, ?, ?, ?, NULL)");
                        $stmt2->bind_param("issd", $product_id, $color_name, $color_code, $color_price);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                    $stmt->close();
                }

                // 4. Insert product variant (if variant data provided and type exists)
                if ($type_id && (!empty($variant_size) || !empty($variant_namevariant))) {
                    $final_price = $variant_price + ($variant_price * $variant_percent / 100);

                    // Check if variant already exists
                    $stmt = $conn->prepare("SELECT id FROM product_variants WHERE type_id = ? AND size = ? AND namevariant = ? AND color = ?");
                    $stmt->bind_param("isss", $type_id, $variant_size, $variant_namevariant, $variant_color);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 0) {
                        // Modify your variant insertion to include product_id
                        $stmt2 = $conn->prepare("INSERT INTO product_variants (product_id, type_id, color, size, price, percent, discount, namevariant, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)");
                        $stmt2->bind_param("iissddds", $product_id, $type_id, $variant_color, $variant_size, $final_price, $variant_percent, $variant_discount, $variant_namevariant);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                    $stmt->close();
                }

                $processedCount++;
            } catch (Exception $rowError) {
                // Log row-specific error but continue processing
                error_log("Error processing row $rowNumber: " . $rowError->getMessage());
                $skippedCount++;
                continue;
            }
        }

        fclose($file);
        $conn->commit();

        $message = "CSV import completed! Processed: $processedCount rows, Skipped: $skippedCount rows";
        echo "<script>alert('" . addslashes($message) . "'); window.location.href='adminshop.php';</script>";
    } catch (Exception $e) {
        if (isset($file)) fclose($file);
        $conn->rollback();
        echo "<script>alert('Error during CSV import: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    }
} else {
    echo "<script>alert('No file uploaded.'); history.back();</script>";
}
