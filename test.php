<?php
// delivery_calculator.php
// Simple PHP-only delivery fee calculator (weight + volume aware).
// No JavaScript. Two-step form: choose number of items -> fill item fields -> compute.

function clean_num($v) {
    // Remove commas and trim
    $v = str_replace(',', '', trim($v));
    return is_numeric($v) ? (float)$v : 0.0;
}

function format_currency($n) {
    return '₱' . number_format($n, 2);
}

$step = $_POST['step'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Delivery Fee Calculator (Weight + Volume)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; margin:20px; max-width:1000px; }
    input[type="number"] { width:110px; }
    table { border-collapse:collapse; width:100%; margin-bottom:1rem;}
    th, td { border:1px solid #ddd; padding:8px; text-align:left; }
    th { background:#f4f4f4; }
    .muted { color:#666; font-size:0.9rem; }
    .result-box { padding:12px; border:1px solid #2d8; background:#f7fff7; }
  </style>
</head>
<body>

<h1>Delivery Fee Calculator (Weight + Volume)</h1>
<p class="muted">Two-step form — walang JavaScript. Pwede mong baguhin ang vehicle/fee defaults sa form.</p>

<?php if (!$step): ?>
  <!-- STEP 1: Choose number of items -->
  <form method="post">
    <label for="items_count">How many different items in the order?</label>
    <input type="number" id="items_count" name="items_count" value="3" min="1" max="50" required>
    <input type="hidden" name="step" value="fill">
    <div style="margin-top:10px;">
      <button type="submit">Next → Fill items</button>
    </div>
  </form>

<?php elseif ($step === 'fill' && isset($_POST['items_count'])): ?>
  <!-- STEP 2: Show item fields -->
  <?php
    $items_count = (int)$_POST['items_count'];
    if ($items_count < 1) $items_count = 1;
    if ($items_count > 50) $items_count = 50;
  ?>
  <form method="post">
    <input type="hidden" name="step" value="compute">
    <input type="hidden" name="items_count" value="<?php echo $items_count; ?>">

    <h2>Items (enter per-item data)</h2>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Item name</th>
          <th>Qty</th>
          <th>Weight (kg each)</th>
          <th>Length (cm)</th>
          <th>Width (cm)</th>
          <th>Height (cm)</th>
          <th class="muted">Volume (m³ each)</th>
        </tr>
      </thead>
      <tbody>
      <?php for ($i=0; $i < $items_count; $i++): ?>
        <tr>
          <td><?php echo $i+1; ?></td>
          <td><input type="text" name="item_name[]" value="Item <?php echo $i+1; ?>"></td>
          <td><input type="number" name="qty[]" value="1" min="1" required></td>
          <td><input type="number" step="0.01" name="weight[]" value="10.00" min="0"></td>
          <td><input type="number" step="0.1" name="length[]" value="50.0" min="0"></td>
          <td><input type="number" step="0.1" name="width[]" value="50.0" min="0"></td>
          <td><input type="number" step="0.1" name="height[]" value="50.0" min="0"></td>
          <td class="muted">auto</td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>

    <h3>Delivery & Vehicle Settings</h3>
    <table>
      <tr>
        <td>Distance (km)</td>
        <td><input type="number" step="0.1" name="distance_km" value="10.0" min="0" required></td>
      </tr>
      <tr>
        <td>Base fee (covers base km)</td>
        <td>
          <input type="number" step="0.01" name="base_fee" value="300.00" min="0"> &nbsp;
          covers
          <input type="number" step="0.1" name="base_km" value="5.0" min="0"> km
        </td>
      </tr>
      <tr>
        <td>Extra per km (beyond base km)</td>
        <td><input type="number" step="0.01" name="per_km_fee" value="15.00" min="0"></td>
      </tr>
      <tr>
        <td>Vehicle weight capacity (kg)</td>
        <td><input type="number" step="0.1" name="vehicle_weight_capacity" value="200.0" min="0.1" required></td>
      </tr>
      <tr>
        <td>Vehicle volume capacity (m³)</td>
        <td><input type="number" step="0.001" name="vehicle_volume_capacity" value="4.0" min="0.001" required>
           <span class="muted"> (e.g., a small truck ~3-6 m³)</span></td>
      </tr>
      <tr>
        <td>Discount per 2nd+ trip? (optional)</td>
        <td>
          <input type="number" step="0.01" name="extra_trip_discount_percent" value="0.00" min="0" max="100">
          <span class="muted"> (e.g., enter 10 for 10% off on trips after the first)</span>
        </td>
      </tr>
    </table>

    <div style="margin-top:12px;">
      <button type="submit">Compute delivery fee</button>
      <button type="submit" formaction="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Cancel</button>
    </div>
  </form>

<?php elseif ($step === 'compute'): ?>
  <!-- STEP 3: Compute and show results -->
  <?php
    // Read arrays safely
    $names = $_POST['item_name'] ?? [];
    $qtys  = $_POST['qty'] ?? [];
    $weights = $_POST['weight'] ?? [];
    $lengths = $_POST['length'] ?? [];
    $widths  = $_POST['width'] ?? [];
    $heights = $_POST['height'] ?? [];

    $distance_km = clean_num($_POST['distance_km'] ?? 0);
    $base_fee = clean_num($_POST['base_fee'] ?? 0);
    $base_km = clean_num($_POST['base_km'] ?? 0);
    $per_km_fee = clean_num($_POST['per_km_fee'] ?? 0);
    $vehicle_weight_capacity = clean_num($_POST['vehicle_weight_capacity'] ?? 1);
    $vehicle_volume_capacity = clean_num($_POST['vehicle_volume_capacity'] ?? 1);
    $extra_trip_discount_percent = clean_num($_POST['extra_trip_discount_percent'] ?? 0);

    // Totals
    $total_weight = 0.0; // kg
    $total_volume = 0.0; // m^3

    $items = [];
    $count_items = max(count($weights), count($qtys)); // safety
    for ($i = 0; $i < $count_items; $i++) {
        $name = trim($names[$i] ?? "Item " . ($i+1));
        $qty = max(0, (int)($qtys[$i] ?? 0));
        if ($qty < 1) $qty = 1;
        $w = clean_num($weights[$i] ?? 0);
        $l = clean_num($lengths[$i] ?? 0);
        $wi= clean_num($widths[$i] ?? 0);
        $h = clean_num($heights[$i] ?? 0);

        // Convert dimensions cm -> meters for m^3 (L/100 * W/100 * H/100)
        $volume_each = ($l/100.0) * ($wi/100.0) * ($h/100.0); // m^3
        if ($volume_each < 0) $volume_each = 0;
        $weight_total_item = $w * $qty;
        $volume_total_item = $volume_each * $qty;

        $total_weight += $weight_total_item;
        $total_volume += $volume_total_item;

        $items[] = [
            'name' => $name,
            'qty' => $qty,
            'weight_each' => $w,
            'weight_total' => $weight_total_item,
            'volume_each' => $volume_each,
            'volume_total' => $volume_total_item,
        ];
    }

    // Determine trips required by weight and by volume
    $trips_by_weight = ($vehicle_weight_capacity > 0) ? ceil($total_weight / $vehicle_weight_capacity) : PHP_INT_MAX;
    $trips_by_volume = ($vehicle_volume_capacity > 0) ? ceil($total_volume / $vehicle_volume_capacity) : PHP_INT_MAX;
    $trips_needed = max(1, $trips_by_weight, $trips_by_volume);

    // Compute per-trip fee:
    $extra_km = max(0, $distance_km - $base_km);
    $per_trip_cost = $base_fee + ($extra_km * $per_km_fee);

    // If user set a discount for additional trips, apply it:
    $total_cost = 0.0;
    if ($trips_needed == 1) {
        $total_cost = $per_trip_cost;
    } else {
        // First trip full price, subsequent trips discounted by percent
        $discount = max(0, min(100, $extra_trip_discount_percent));
        $total_cost = $per_trip_cost; // first trip
        for ($t = 2; $t <= $trips_needed; $t++) {
            $trip_price = $per_trip_cost * (1 - $discount / 100.0);
            $total_cost += $trip_price;
        }
    }

    // Per-trip average load (just for info): naive equal split
    $avg_weight_per_trip = $total_weight / $trips_needed;
    $avg_volume_per_trip = $total_volume / $trips_needed;
  ?>

  <h2>Computation Result</h2>

  <div class="result-box">
    <p><strong>Total weight:</strong> <?php echo number_format($total_weight,2); ?> kg</p>
    <p><strong>Total volume:</strong> <?php echo number_format($total_volume,4); ?> m³</p>
    <p><strong>Vehicle capacity:</strong> <?php echo number_format($vehicle_weight_capacity,2); ?> kg, <?php echo number_format($vehicle_volume_capacity,3); ?> m³</p>
    <p><strong>Trips required (by weight):</strong> <?php echo $trips_by_weight; ?> &nbsp; <strong>by volume:</strong> <?php echo $trips_by_volume; ?> &nbsp; → <strong>used:</strong> <?php echo $trips_needed; ?> trip(s)</p>
    <hr>
    <p><strong>Distance:</strong> <?php echo number_format($distance_km,2); ?> km</p>
    <p><strong>Base fee:</strong> <?php echo format_currency($base_fee); ?> (covers <?php echo number_format($base_km,2); ?> km)</p>
    <p><strong>Extra km:</strong> <?php echo number_format($extra_km,2); ?> km × <?php echo format_currency($per_km_fee); ?> = <?php echo format_currency($extra_km * $per_km_fee); ?></p>
    <p><strong>Per trip cost:</strong> <?php echo format_currency($per_trip_cost); ?></p>
    <?php if ($trips_needed > 1 && $extra_trip_discount_percent > 0): ?>
      <p class="muted">Note: additional trips after the first get <?php echo number_format($extra_trip_discount_percent,2); ?>% discount.</p>
    <?php endif; ?>
    <h3>Total delivery cost: <?php echo format_currency($total_cost); ?></h3>
    <p class="muted">Average per trip (naive equal split): <?php echo format_currency($total_cost / $trips_needed); ?> per trip.</p>
  </div>

  <h3>Item breakdown</h3>
  <table>
    <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Weight total (kg)</th><th>Volume total (m³)</th></tr></thead>
    <tbody>
      <?php foreach ($items as $idx => $it): ?>
        <tr>
          <td><?php echo $idx+1; ?></td>
          <td><?php echo htmlspecialchars($it['name']); ?></td>
          <td><?php echo $it['qty']; ?></td>
          <td><?php echo number_format($it['weight_total'],2); ?></td>
          <td><?php echo number_format($it['volume_total'],4); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3>What to do next / notes</h3>
  <ul>
    <li>If you want a smarter splitter (which decides exactly which items go on each trip) we can implement a bin-packing heuristic — helpful for minimizing number of trips. (That is more advanced but doable.)</li>
    <li>If the vehicle's volume or weight capacity is very small or set to zero, trips will be forced high — make sure capacity fields are correct.</li>
    <li>To integrate into checkout, call this script on server-side with the cart items and show the computed fee as part of order total.</li>
  </ul>

  <div style="margin-top:12px;">
    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Start new calculation</a>
  </div>

<?php else: ?>
  <p>Invalid step. <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Start over</a></p>
<?php endif; ?>



</body>
</html>
