<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['superadmin', 'logistic']); // allow only admin and superadmin

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

$success = '';
$error = '';

$conn->query("ALTER TABLE variant_tracking AUTO_INCREMENT = 1");

if (!isset($_SESSION['selected_items'])) $_SESSION['selected_items'] = [];
if (!isset($_SESSION['selected_order'])) $_SESSION['selected_order'] = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? '';
    $selected_items = $_POST['order_item_ids'] ?? [];
    $driver_id = $_POST['driver_id'] ?? '';
    $places = $_POST['place'] ?? [];

    $_SESSION['selected_order'] = $order_id;
    $_SESSION['selected_items'] = $selected_items;

    if ($order_id && !empty($selected_items) && $driver_id) {
        $stmt = $conn->prepare("INSERT INTO variant_tracking (
            order_id, order_item_id, place, timestamp, driver_id,
            customer_name, customer_address, mobile, email,
            product_name, variant_color, size,
            price, quantity, subtotal, discount, vat_amount, shipping_fee, delivery_fee, final_total,
            mode_payment, description1, description2, status, reference_no
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $system_places = ['Pending', 'Ongoing', 'Arrival', 'Customs'];
        $timestamp = date('Y-m-d H:i:s');

        foreach ($selected_items as $order_item_id) {
            $item = $conn->query("SELECT * FROM order_items WHERE id = $order_item_id")->fetch_assoc();
            $order = $conn->query("SELECT * FROM orders WHERE id = $order_id")->fetch_assoc();

            $mobile = $order['mobile'] ?? '';
            $email = $order['email'] ?? '';
            $mode_payment = $order['mode_payment'] ?? '';
            $description1 = $item['descrip6'] ?? '';
            $description2 = $item['descrip7'] ?? '';
            $reference_no = $order['reference_no'] ?? '';

            $vat_amount = round($item['subtotal'] * 0.12, 2);
            $all_places = array_merge($system_places, $places);

            foreach ($all_places as $place) {
                $place = trim($place);
                if ($place === '') continue;

                $status = 'active';

                $stmt->bind_param(
                    "iississssssssdiiddddsssss",
                    $order_id, $order_item_id, $place, $timestamp, $driver_id,
                    $order['customer_name'], $order['address'], $mobile, $email,
                    $item['product_name'], $item['variant_color'], $item['size'],
                    $item['price'], $item['quantity'], $item['subtotal'],
                    $order['discount'], $vat_amount, $order['shipping_fee'], $order['delivery_fee'], $order['final_total'],
                    $mode_payment, $description1, $description2, $status, $reference_no
                );
                $stmt->execute();
            }
        }

        $stmt->close();
        $success = "Tracking added successfully.";
        $_SESSION['selected_items'] = [];
    } else {
        $error = "Please complete all required fields.";
    }
}

// Load untracked items
$filtered_items = [];
$order_info = null;

if (!empty($_SESSION['selected_order'])) {
    $oid = (int)$_SESSION['selected_order'];

    // Load order info including reference number
    $order_stmt = $conn->prepare("SELECT reference_no, customer_name, address FROM orders WHERE id = ?");
    $order_stmt->bind_param("i", $oid);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order_info = $order_result->fetch_assoc();
    $order_stmt->close();

    $stmt = $conn->prepare("SELECT id, product_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7 
        FROM order_items 
        WHERE order_id = ? AND id NOT IN (
            SELECT order_item_id FROM variant_tracking WHERE order_id = ?
        )");
    $stmt->bind_param("ii", $oid, $oid);
    $stmt->execute();
    $filtered_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$order_results = $conn->query("SELECT id FROM orders ORDER BY id DESC");
$driver_results = $conn->query("SELECT id, name, plate_number FROM drivers");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Tracking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #f97316 0%, #f59e0b 100%);
        }
        .item-card {
            transition: all 0.2s ease;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <?php include '../navbar/top.php'; ?>

<div class="">
    <!-- Header Card -->
    <div class="gradient-bg text-white  p-6 ">
        <h1 class="text-2xl font-bold">Tracking System</h1>
        <p class="opacity-90 mt-1">Track multiple product variants in a single order</p>
    </div>
    <!-- Main Form Card -->
    <div class="bg-white shadow-md rounded-b-lg p-6 border-t-0">
        <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="font-medium"><?= $success ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="font-medium"><?= $error ?></span>
                </div>
            </div>
        <?php endif; ?>
        <form method="POST" id="trackingForm" class="space-y-6">
            <!-- Order Selection -->
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Select Order</label>
                <div class="flex space-x-3">
                    <select name="order_id" onchange="document.getElementById('trackingForm').submit()" required 
                            class="flex-1 border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <option value="">-- Select Order ID --</option>
                        <?php while ($row = $order_results->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= ($_SESSION['selected_order'] ?? '') == $row['id'] ? 'selected' : '' ?>>
                                Order #<?= $row['id'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="button" onclick="document.getElementById('trackingForm').submit()" 
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md transition">
                        Refresh
                    </button>
                </div>
            </div>
            <?php if (!empty($_SESSION['selected_order'])):
                $oid = (int)$_SESSION['selected_order'];
                $order = $conn->query("SELECT discount, shipping_fee, delivery_fee, final_total, mode_payment FROM orders WHERE id = $oid")->fetch_assoc();
                if ($order): ?>
                <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-md">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Discount</p>
                            <p class="font-medium"><?= $order['discount'] ?>%</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Shipping</p>
                            <p class="font-medium">₱<?= number_format($order['shipping_fee'], 2) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500">Delivery</p>
                            <p class="font-medium">₱<?= number_format($order['delivery_fee'], 2) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500">Total</p>
                            <p class="font-bold text-orange-600">₱<?= number_format($order['final_total'], 2) ?></p>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-amber-100">
                        <p class="text-sm text-gray-600">Payment Method: <span class="font-medium text-gray-800"><?= htmlspecialchars($order['mode_payment']) ?></span></p>
                    </div>
                </div>
            <?php endif; endif; ?>
            <?php if (!empty($filtered_items)): ?>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Add Items to Track</label>
                    <select id="variantSelect" class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <option value="">-- Select Product Variant --</option>
                        <?php foreach ($filtered_items as $item): ?>
                            <?php if (!in_array($item['id'], $_SESSION['selected_items'] ?? [])): ?>
                                <option value="<?= $item['id'] ?>">
                                    <?= htmlspecialchars($item['product_name']) ?> (<?= $item['variant_color'] ?>, <?= $item['size'] ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="selectedItems" class="space-y-3">
                    <?php foreach ($_SESSION['selected_items'] ?? [] as $item_id): 
                        $itm = $conn->query("SELECT * FROM order_items WHERE id = $item_id")->fetch_assoc(); ?>
                        <div class="item-card flex justify-between items-center bg-blue-50 border border-blue-100 rounded-lg p-4">
                            <div>
                                <h3 class="font-medium text-gray-800"><?= htmlspecialchars($itm['product_name']) ?></h3>
                                <p class="text-sm text-gray-600"><?= $itm['variant_color'] ?>, <?= $itm['size'] ?></p>
                                <p class="text-sm mt-1">
                                    <span class="font-medium">₱<?= number_format($itm['price'], 2) ?></span>
                                    <span class="mx-1">×</span>
                                    <span><?= $itm['quantity'] ?></span>
                                    <span class="mx-1">=</span>
                                    <span class="font-medium text-blue-600">₱<?= number_format($itm['subtotal'], 2) ?></span>
                                </p>
                                <input type="hidden" name="order_item_ids[]" value="<?= $item_id ?>">
                            </div>
                            <button type="button" onclick="removeItem(this, <?= $item_id ?>)" 
                                    class="text-red-500 hover:text-red-700 p-1 rounded-full hover:bg-red-50 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <!-- Driver Selection -->
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Assign Driver</label>
                <select name="driver_id" required class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">-- Select Driver --</option>
                    <?php while ($d = $driver_results->fetch_assoc()): ?>
                        <option value="<?= $d['id'] ?>"><?= $d['name'] ?> (<?= $d['plate_number'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <!-- Tracking Locations -->
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tracking Locations</label>
                    <div class="bg-gray-50 border border-gray-200 rounded-md p-3 text-sm text-gray-600">
                        <p class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            System will automatically add: <span class="font-medium ml-1">Pending, Ongoing, Arrival, Customs</span>
                        </p>
                    </div>
                </div>
                
                <div id="trackingFields" class="space-y-3"></div>
                
                <button type="button" onclick="addTrackingField()" 
                        class="flex items-center text-orange-600 hover:text-orange-700 text-sm font-medium">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Custom Location
                </button>
            </div>
            <!-- Submit Button -->
            <div class="pt-4">
                <button type="submit" class="gradient-bg hover:opacity-90 text-white font-medium px-6 py-3 rounded-md shadow transition w-full md:w-auto">
                    Create Tracking Records
                </button>
            </div>
        </form>
    </div>
</div>
<script>
// Improved version of your JavaScript with better UX
function getOrdinal(n) {
    const s = ["th", "st", "nd", "rd"];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]) + " Location";
}
function addTrackingField() {
    const container = document.getElementById('trackingFields');
    const count = container.querySelectorAll('.tracking-group').length + 1;
    const group = document.createElement('div');
    group.className = 'tracking-group flex items-start space-x-3';
    const inputWrapper = document.createElement('div');
    inputWrapper.className = 'flex-1';
    const label = document.createElement('label');
    label.className = 'block text-sm font-medium text-gray-700 mb-1';
    label.innerText = getOrdinal(count);
    const inputGroup = document.createElement('div');
    inputGroup.className = 'flex rounded-md shadow-sm';
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'place[]';
    input.placeholder = 'e.g. Warehouse - 1';
    input.className = 'flex-1 block w-full rounded-md border-gray-300 focus:ring-orange-500 focus:border-orange-500 sm:text-sm';
    input.dataset.placeIndex = count;
    input.addEventListener('blur', function() {
        const trimmed = this.value.trim();
        if (trimmed !== '' && !trimmed.match(/ - \d+$/)) {
            this.value = `${trimmed} - ${count}`;
        }
    });
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'text-gray-400 hover:text-gray-500 mt-7 ml-2';
    removeBtn.innerHTML = `
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    `;
    removeBtn.onclick = function() {
        group.remove();
        // Renumber remaining fields
        const groups = container.querySelectorAll('.tracking-group');
        groups.forEach((g, index) => {
            const label = g.querySelector('label');
            label.innerText = getOrdinal(index + 1);
            const input = g.querySelector('input');
            input.dataset.placeIndex = index + 1;
        });
    };
    inputGroup.appendChild(input);
    inputWrapper.appendChild(label);
    inputWrapper.appendChild(inputGroup);
    group.appendChild(inputWrapper);
    group.appendChild(removeBtn);
    container.appendChild(group);
}
document.addEventListener("DOMContentLoaded", function() {
    addTrackingField();
});
document.getElementById('variantSelect')?.addEventListener('change', function() {
    const selectedId = this.value;
    if (!selectedId) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    const orderInput = document.createElement('input');
    orderInput.name = 'order_id';
    orderInput.value = "<?= $_SESSION['selected_order'] ?? '' ?>";
    form.appendChild(orderInput);
    const oldItems = <?= json_encode($_SESSION['selected_items'] ?? []) ?>;
    if (!oldItems.includes(selectedId)) oldItems.push(selectedId);
    oldItems.forEach(id => {
        const input = document.createElement('input');
        input.name = 'order_item_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
});
function removeItem(button, idToRemove) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    const orderInput = document.createElement('input');
    orderInput.name = 'order_id';
    orderInput.value = "<?= $_SESSION['selected_order'] ?? '' ?>";
    form.appendChild(orderInput);
    const oldItems = <?= json_encode($_SESSION['selected_items'] ?? []) ?>;
    const newItems = oldItems.filter(id => id != idToRemove);
    newItems.forEach(id => {
        const input = document.createElement('input');
        input.name = 'order_item_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>