<?php
// Initialize variables
$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $base_fee = (float) $_POST['base_fee'];
    $per_km_rate = (float) $_POST['per_km_rate'];
    $total_km_for_base = (float) $_POST['total_km_for_base'];
    $distance_km = (float) $_POST['distance_km'];

    $items = $_POST['items'];
    $subtotal = 0;
    $total_delivery_cost = 0;

    foreach ($items as $item) {
        $name = $item['name'];
        $unit_price = (float) $item['unit_price'];
        $qty = (int) $item['qty'];

        // Compute delivery fee per item
        if ($distance_km <= $total_km_for_base) {
            $delivery_fee_per_item = $base_fee;
        } else {
            $extra_km = $distance_km - $total_km_for_base;
            $delivery_fee_per_item = $base_fee + ($extra_km * $per_km_rate);
        }

        $item_total_price = $unit_price * $qty;
        $item_total_delivery = $delivery_fee_per_item * $qty;

        $subtotal += $item_total_price;
        $total_delivery_cost += $item_total_delivery;

        $results[] = [
            'name' => $name,
            'unit_price' => $unit_price,
            'qty' => $qty,
            'item_total_price' => $item_total_price,
            'delivery_fee_per_item' => $delivery_fee_per_item,
            'item_total_delivery' => $item_total_delivery
        ];
    }

    $grand_total = $subtotal + $total_delivery_cost;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Delivery Fee Calculator</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f6f8;
        margin: 0;
        padding: 20px;
    }
    .container {
        max-width: 900px;
        margin: auto;
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    h1 {
        text-align: center;
        color: #333;
    }
    label {
        font-weight: bold;
    }
    input {
        padding: 8px;
        width: 100%;
        margin: 5px 0 15px;
        border-radius: 5px;
        border: 1px solid #ccc;
    }
    .item-group {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 10px;
        margin-bottom: 15px;
    }
    button {
        background-color: #007BFF;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    button:hover {
        background-color: #0056b3;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    table, th, td {
        border: 1px solid #ddd;
    }
    th, td {
        padding: 10px;
        text-align: center;
    }
    th {
        background-color: #007BFF;
        color: white;
    }
    .total {
        font-weight: bold;
        background-color: #f1f1f1;
    }
</style>
</head>
<body>

<div class="container">
    <h1>Delivery Fee Calculator</h1>
    <form method="POST">
        <label>Base Fee (₱):</label>
        <input type="number" name="base_fee" step="0.01" required>

        <label>Per KM Rate (₱):</label>
        <input type="number" name="per_km_rate" step="0.01" required>

        <label>Total KM for Base Fee:</label>
        <input type="number" name="total_km_for_base" step="0.01" required>

        <label>Total Distance KM:</label>
        <input type="number" name="distance_km" step="0.01" required>

        <h3>Items</h3>
        <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="item-group">
            <input type="text" name="items[<?php echo $i; ?>][name]" placeholder="Item Name" required>
            <input type="number" name="items[<?php echo $i; ?>][unit_price]" placeholder="Unit Price" step="0.01" required>
            <input type="number" name="items[<?php echo $i; ?>][qty]" placeholder="Quantity" required>
        </div>
        <?php endfor; ?>

        <button type="submit">Calculate</button>
    </form>

    <?php if (!empty($results)): ?>
        <h2>Calculation Results</h2>
        <table>
            <tr>
                <th>Item Name</th>
                <th>Unit Price</th>
                <th>Qty</th>
                <th>Item Total Price</th>
                <th>Delivery Fee per Item</th>
                <th>Total Delivery for Item</th>
            </tr>
            <?php foreach ($results as $res): ?>
            <tr>
                <td><?php echo htmlspecialchars($res['name']); ?></td>
                <td>₱<?php echo number_format($res['unit_price'], 2); ?></td>
                <td><?php echo $res['qty']; ?></td>
                <td>₱<?php echo number_format($res['item_total_price'], 2); ?></td>
                <td>₱<?php echo number_format($res['delivery_fee_per_item'], 2); ?></td>
                <td>₱<?php echo number_format($res['item_total_delivery'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total">
                <td colspan="3">Total</td>
                <td>₱<?php echo number_format($subtotal, 2); ?></td>
                <td>-</td>
                <td>₱<?php echo number_format($total_delivery_cost, 2); ?></td>
            </tr>
            <tr class="total">
                <td colspan="5">Grand Total (Items + Delivery)</td>
                <td>₱<?php echo number_format($grand_total, 2); ?></td>
            </tr>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
