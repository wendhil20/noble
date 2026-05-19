<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$products_query = "SELECT id, product_name, min_order_qty FROM products WHERE is_archived = 0 ORDER BY product_name ASC";
$products_result = mysqli_query($conn, $products_query);
$products = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minimum Order Quantity | Noble Home</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.07);
        }

        .qty-input {
            font-family: 'DM Mono', monospace;
            width: 90px;
            padding: 6px 10px;
            border: 1.5px solid #e5e5e5;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #111;
            text-align: center;
            transition: border-color 0.15s, box-shadow 0.15s;
            background: #fafafa;
        }

        .qty-input:focus {
            outline: none;
            border-color: #111;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.06);
        }

        .qty-input.changed {
            border-color: #f97316;
            background: #fff7f0;
        }

        .btn-save-all {
            background: #111;
            color: #fff;
            border-radius: 10px;
            padding: 11px 28px;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.15s, transform 0.1s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save-all:hover {
            background: #333;
            transform: translateY(-1px);
        }

        .btn-apply-all {
            background: #fff;
            color: #111;
            border: 1.5px solid #e5e5e5;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-apply-all:hover {
            border-color: #111;
            background: #f9f9f9;
        }

        .search-input {
            padding: 10px 16px 10px 40px;
            border: 1.5px solid #e5e5e5;
            border-radius: 10px;
            font-size: 14px;
            width: 100%;
            max-width: 320px;
            transition: border-color 0.15s;
            background: #fff;
            font-family: 'DM Sans', sans-serif;
        }

        .search-input:focus {
            outline: none;
            border-color: #111;
        }

        .table-row {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.12s;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-row:hover {
            background: #fafafa;
        }

        .badge-default {
            background: #f0f0f0;
            color: #666;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 11px;
            font-family: 'DM Mono', monospace;
            font-weight: 500;
        }

        .badge-custom {
            background: #fff3e0;
            color: #f97316;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 11px;
            font-family: 'DM Mono', monospace;
            font-weight: 600;
        }

        .changed-count {
            display: inline-flex;
            align-items: center;
            background: #fff3e0;
            color: #f97316;
            border-radius: 20px;
            padding: 3px 12px;
            font-size: 12px;
            font-weight: 600;
            gap: 5px;
            transition: opacity 0.2s;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.07);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.3s ease both;
        }
    </style>
</head>

<body>
    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="container mx-auto px-6 py-8 max-w-5xl">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-black rounded-lg flex items-center justify-center">
                        <i class='bx bx-package text-white text-xl'></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Minimum Order Quantity</h1>
                        <p class="text-xs text-gray-500">Set the minimum pcs required per product before adding to cart
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-6 fade-in">
            <div class="stat-card">
                <p class="text-xs text-gray-500 mb-1">Total Products</p>
                <p class="text-2xl font-bold text-gray-900" id="statTotal"><?php echo count($products); ?></p>
            </div>
            <div class="stat-card">
                <p class="text-xs text-gray-500 mb-1">With Custom Min Qty</p>
                <p class="text-2xl font-bold text-orange-500" id="statCustom">
                    <?php echo count(array_filter($products, fn($p) => $p['min_order_qty'] > 1)); ?>
                </p>
            </div>
            <div class="stat-card">
                <p class="text-xs text-gray-500 mb-1">Default (Min = 1)</p>
                <p class="text-2xl font-bold text-gray-400" id="statDefault">
                    <?php echo count(array_filter($products, fn($p) => $p['min_order_qty'] == 1)); ?>
                </p>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card fade-in" style="animation-delay:0.05s">
            <!-- Toolbar -->
            <div class="p-5 border-b border-gray-100 flex flex-wrap items-center gap-3 justify-between">
                <div class="flex items-center gap-3 flex-wrap">
                    <!-- Search -->
                    <div class="relative">
                        <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg'></i>
                        <input type="text" id="searchInput" placeholder="Search products..." class="search-input">
                    </div>
                    <!-- Apply to All -->
                    <div class="flex items-center gap-2">
                        <input type="number" id="applyAllValue" min="1" value="1" placeholder="Qty" class="qty-input"
                            style="width:80px;">
                        <button onclick="applyToAll()" class="btn-apply-all">
                            <i class='bx bx-select-multiple'></i> Apply to All
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="changed-count" id="changedCount" style="opacity:0">
                        <i class='bx bx-edit-alt'></i>
                        <span id="changedNum">0</span> unsaved
                    </span>
                    <button onclick="saveAll()" class="btn-save-all">
                        <i class='bx bx-save text-lg'></i> Save All Changes
                    </button>
                </div>
            </div>

            <!-- Table Header -->
            <div
                class="grid grid-cols-12 gap-4 px-5 py-3 bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                <div class="col-span-1">#</div>
                <div class="col-span-7">Product Name</div>
                <div class="col-span-2 text-center">Status</div>
                <div class="col-span-2 text-center">Min Qty (pcs)</div>
            </div>

            <!-- Table Body -->
            <div id="productTable">
                <?php foreach ($products as $i => $product): ?>
                    <div class="table-row grid grid-cols-12 gap-4 px-5 py-3 items-center product-row"
                        data-name="<?php echo strtolower(htmlspecialchars($product['product_name'])); ?>"
                        data-id="<?php echo $product['id']; ?>">
                        <div class="col-span-1 text-xs text-gray-400 font-mono"><?php echo $i + 1; ?></div>
                        <div class="col-span-7">
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($product['product_name']); ?></p>
                        </div>
                        <div class="col-span-2 text-center">
                            <?php if ($product['min_order_qty'] > 1): ?>
                                <span class="badge-custom">Custom</span>
                            <?php else: ?>
                                <span class="badge-default">Default</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-2 flex justify-center">
                            <input type="number" class="qty-input product-qty" min="1"
                                value="<?php echo (int) $product['min_order_qty']; ?>"
                                data-original="<?php echo (int) $product['min_order_qty']; ?>"
                                data-id="<?php echo $product['id']; ?>" onchange="onQtyChange(this)"
                                oninput="onQtyChange(this)">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty state -->
            <div id="noResults" class="hidden py-16 text-center text-gray-400">
                <i class='bx bx-search-alt text-4xl mb-3 block'></i>
                <p class="text-sm">No products found</p>
            </div>
        </div>
    </div>

    <script>
        const originalData = {};
        let changedIds = new Set();

        // Track originals
        document.querySelectorAll('.product-qty').forEach(input => {
            originalData[input.dataset.id] = parseInt(input.dataset.original);
        });

        function onQtyChange(input) {
            const id = input.dataset.id;
            const val = parseInt(input.value) || 1;
            const orig = originalData[id];

            if (val !== orig) {
                input.classList.add('changed');
                changedIds.add(id);
            } else {
                input.classList.remove('changed');
                changedIds.delete(id);
            }

            // Update status badge
            const row = input.closest('.product-row');
            const badge = row.querySelector('.badge-custom, .badge-default');
            if (val > 1) {
                badge.className = 'badge-custom';
                badge.textContent = 'Custom';
            } else {
                badge.className = 'badge-default';
                badge.textContent = 'Default';
            }

            updateChangedCount();
        }

        function updateChangedCount() {
            const countEl = document.getElementById('changedCount');
            const numEl = document.getElementById('changedNum');
            numEl.textContent = changedIds.size;
            countEl.style.opacity = changedIds.size > 0 ? '1' : '0';
        }

        function applyToAll() {
            const val = parseInt(document.getElementById('applyAllValue').value) || 1;
            document.querySelectorAll('.product-qty').forEach(input => {
                if (!input.closest('.product-row').classList.contains('hidden')) {
                    input.value = val;
                    onQtyChange(input);
                }
            });
        }

        // Search
        document.getElementById('searchInput').addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const name = row.dataset.name;
                const match = !q || name.includes(q);
                row.classList.toggle('hidden', !match);
                if (match) visible++;
            });
            document.getElementById('noResults').classList.toggle('hidden', visible > 0);
        });

        async function saveAll() {
            if (changedIds.size === 0) {
                Swal.fire({ icon: 'info', title: 'No Changes', text: 'Nothing to save yet.', confirmButtonColor: '#111' });
                return;
            }

            const updates = [];
            document.querySelectorAll('.product-qty').forEach(input => {
                if (changedIds.has(input.dataset.id)) {
                    updates.push({ id: input.dataset.id, min_order_qty: parseInt(input.value) || 1 });
                }
            });

            Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            try {
                const res = await fetch('<?= BASE_URL; ?>/quantitysave', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ updates })
                });
                const data = await res.json();

                if (data.success) {
                    // Update originals
                    updates.forEach(u => { originalData[u.id] = u.min_order_qty; });
                    document.querySelectorAll('.product-qty').forEach(input => {
                        input.classList.remove('changed');
                        input.dataset.original = input.value;
                    });
                    changedIds.clear();
                    updateChangedCount();

                    // Update stats
                    updateStats();

                    Swal.fire({ icon: 'success', title: 'Saved!', text: `${updates.length} product(s) updated.`, timer: 2000, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to save.', confirmButtonColor: '#111' });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.', confirmButtonColor: '#111' });
            }
        }

        function updateStats() {
            let custom = 0, def = 0;
            document.querySelectorAll('.product-qty').forEach(input => {
                if (parseInt(input.value) > 1) custom++;
                else def++;
            });
            document.getElementById('statCustom').textContent = custom;
            document.getElementById('statDefault').textContent = def;
        }
    </script>
</body>

</html>