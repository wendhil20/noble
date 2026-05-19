<?php
//view_supplier.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$supplier_id) {
    header("Location: " . BASE_URL . "/supplierlist");
    exit();
}

$supplier_stmt = $conn->prepare("SELECT * FROM supplier_list WHERE id = ?");
$supplier_stmt->bind_param("i", $supplier_id);
$supplier_stmt->execute();
$supplier = $supplier_stmt->get_result()->fetch_assoc();
$supplier_stmt->close();

if (!$supplier) {
    header("Location: " . BASE_URL . "/supplierlist?error=supplier_not_found");
    exit();
}

// AJAX: get variants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_variants') {
    header('Content-Type: application/json');
    $product_id = intval($_POST['product_id']);
    $variants_stmt = $conn->prepare("
        SELECT pv.*,
               CASE WHEN slp.status = 'active' THEN 1 ELSE 0 END as is_linked,
               slp.supplier_type, slp.supplier_price
        FROM product_variants pv
        LEFT JOIN supp_link_products slp ON pv.id = slp.variant_id AND slp.supplier_id = ? AND slp.status = 'active'
        WHERE pv.product_id = ?
        ORDER BY pv.namevariant ASC, pv.color ASC, pv.size ASC
    ");
    $variants_stmt->bind_param("ii", $supplier_id, $product_id);
    $variants_stmt->execute();
    $variants = $variants_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $variants_stmt->close();
    echo json_encode(['success' => true, 'variants' => $variants]);
    exit();
}

// Linked products
$products_stmt = $conn->prepare("
    SELECT p.*,
           COUNT(DISTINCT CASE WHEN slp.status = 'active' AND slp.variant_id IS NOT NULL THEN slp.variant_id END) as linked_variants_count,
           COUNT(DISTINCT pv.id) as total_variants_count,
           MIN(slp.created_at) as linked_date
    FROM products p
    INNER JOIN supp_link_products slp ON p.id = slp.product_id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    WHERE slp.supplier_id = ? AND slp.status = 'active'
    GROUP BY p.id
    ORDER BY p.product_name ASC
");
$products_stmt->bind_param("i", $supplier_id);
$products_stmt->execute();
$linked_products = $products_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$products_stmt->close();

$active_products       = count($linked_products);
$total_linked_variants = array_sum(array_column($linked_products, 'linked_variants_count'));

// ── resolveAsset helper (same as link_products.php) ───────────────────────────
function resolveAsset(string $stored): array {
    $stored = ltrim($stored, '/');
    return [
        'file' => ROOT_PATH . '/' . $stored,
        'url'  => BASE_URL  . '/' . $stored,
    ];
}

// Acronym + color for avatar
$words   = explode(' ', trim($supplier['business_name']));
$acronym = '';
foreach ($words as $word) {
    if (!empty($word)) { $acronym .= strtoupper(substr($word, 0, 1)); if (strlen($acronym) >= 2) break; }
}
if (empty($acronym)) $acronym = strtoupper(substr($supplier['business_name'], 0, 2));
$colors   = ['bg-blue-500','bg-emerald-500','bg-purple-500','bg-pink-500','bg-amber-500','bg-indigo-500','bg-red-500','bg-teal-500'];
$bg_color = $colors[abs(crc32($supplier['business_name'])) % count($colors)];

// ── Supplier logo — using resolveAsset ────────────────────────────────────────
$logo_url    = '';
$logo_exists = false;
if (!empty($supplier['logo_path'])) {
    $raw = ltrim($supplier['logo_path'], '/');
    if (!str_starts_with($raw, 'uploads/')) $raw = 'uploads/supplier_logos/' . basename($raw);
    $la = resolveAsset($raw);
    $logo_exists = file_exists($la['file']);
    $logo_url    = $la['url'];
}

$typeColors = [
    'Manufacturer' => 'bg-blue-100 text-blue-700',
    'Wholesaler'   => 'bg-emerald-100 text-emerald-700',
    'Distributor'  => 'bg-purple-100 text-purple-700',
    'Retailer'     => 'bg-amber-100 text-amber-700',
];
$typeClass = $typeColors[$supplier['business_type']] ?? 'bg-slate-100 text-slate-700';
$isActive  = $supplier['status'] === 'active';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($supplier['business_name']) ?> — Supplier Details</title>
</head>
<body class="bg-slate-50 min-h-screen">

    <?php include ROOT_PATH . "/admin/navbar/top.php"; ?>

    <!-- Top Nav -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-20 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">

                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>/supplierlist"
                       class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors"
                       title="Back to Supplier Directory">
                        <i class="fas fa-arrow-left text-xs"></i>
                    </a>
                    <div class="w-px h-5 bg-slate-200"></div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800 leading-none">Supplier Details</p>
                        <p class="text-xs text-slate-400 leading-none mt-0.5"><?= htmlspecialchars($supplier['business_name']) ?></p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <a href="<?= BASE_URL ?>/editsupplier?edit_id=<?= $supplier['id'] ?>"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium rounded-lg transition-colors">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="<?= BASE_URL ?>/supplierlink?supplier_id=<?= $supplier['id'] ?>"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-medium rounded-lg transition-colors">
                        <i class="fas fa-link"></i> Add Products
                    </a>
                    <button onclick="toggleSupplierStatus(<?= $supplier['id'] ?>, '<?= $supplier['status'] ?>', '<?= htmlspecialchars($supplier['business_name'], ENT_QUOTES) ?>')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                                <?= $isActive ? 'bg-red-50 border border-red-200 text-red-600 hover:bg-red-100' : 'bg-emerald-50 border border-emerald-200 text-emerald-600 hover:bg-emerald-100' ?>">
                        <i class="fas fa-<?= $isActive ? 'pause' : 'play' ?>"></i>
                        <?= $isActive ? 'Deactivate' : 'Activate' ?>
                    </button>
                </div>

            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

        <!-- Flash Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-800 auto-dismiss">
                <i class="fas fa-check-circle text-emerald-500"></i> <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800 auto-dismiss">
                <i class="fas fa-exclamation-circle text-red-500"></i> <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <!-- ─── SUPPLIER INFO CARD ─────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex items-start gap-5">

                <!-- Avatar / Logo -->
                <div class="flex-shrink-0">
                    <?php if ($logo_exists): ?>
                        <img src="<?= htmlspecialchars($logo_url) ?>"
                             alt="<?= htmlspecialchars($supplier['business_name']) ?>"
                             class="w-16 h-16 rounded-xl object-cover border border-slate-200">
                    <?php else: ?>
                        <div class="w-16 h-16 rounded-xl <?= $bg_color ?> flex items-center justify-center text-white font-bold text-lg">
                            <?= htmlspecialchars($acronym) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h1 class="text-xl font-bold text-slate-900"><?= htmlspecialchars($supplier['business_name']) ?></h1>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $typeClass ?>">
                            <?= htmlspecialchars($supplier['business_type']) ?>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                            <?= $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-emerald-500' : 'bg-red-500' ?>"></span>
                            <?= ucfirst($supplier['status']) ?>
                        </span>
                    </div>

                    <!-- Contact Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 mt-3 text-sm">
                        <div class="flex items-center gap-2 text-slate-600">
                            <i class="fas fa-user w-4 text-slate-400 text-center"></i>
                            <span><?= !empty($supplier['primary_contact_name']) ? htmlspecialchars($supplier['primary_contact_name']) : '—' ?></span>
                            <?php if (!empty($supplier['job_title'])): ?>
                                <span class="text-slate-400">· <?= htmlspecialchars($supplier['job_title']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-2 text-slate-600">
                            <i class="fas fa-phone w-4 text-slate-400 text-center"></i>
                            <?php if (!empty($supplier['phone_number'])): ?>
                                <a href="tel:<?= htmlspecialchars($supplier['phone_number']) ?>" class="hover:text-blue-600 transition-colors">
                                    <?= htmlspecialchars($supplier['phone_number']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-slate-400 italic">No phone</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-2 text-slate-600">
                            <i class="fas fa-envelope w-4 text-slate-400 text-center"></i>
                            <?php if (!empty($supplier['email_address'])): ?>
                                <a href="mailto:<?= htmlspecialchars($supplier['email_address']) ?>" class="hover:text-blue-600 transition-colors truncate">
                                    <?= htmlspecialchars($supplier['email_address']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-slate-400 italic">No email</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-2 text-slate-600">
                            <i class="fas fa-map-marker-alt w-4 text-slate-400 text-center"></i>
                            <span><?= !empty($supplier['country_region']) ? htmlspecialchars($supplier['country_region']) : '—' ?></span>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mt-3">
                        <i class="fas fa-calendar mr-1"></i>
                        Added <?= date('F j, Y', strtotime($supplier['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- ─── LINKED PRODUCTS ───────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">

            <!-- Section Header -->
            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">Linked Products</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Products associated with this supplier</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 border border-blue-200 rounded-full text-xs font-medium text-blue-700">
                        <i class="fas fa-box"></i> <?= $active_products ?> Products
                    </span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 border border-emerald-200 rounded-full text-xs font-medium text-emerald-700">
                        <i class="fas fa-layer-group"></i> <?= $total_linked_variants ?> Variants
                    </span>
                    <a href="<?= BASE_URL ?>/supplierlink?supplier_id=<?= $supplier['id'] ?>"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-medium rounded-lg transition-colors">
                        <i class="fas fa-plus"></i> Add Products
                    </a>
                </div>
            </div>

            <div class="p-5">
                <?php if (empty($linked_products)): ?>
                    <!-- Empty State -->
                    <div class="text-center py-12">
                        <div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-box text-slate-400 text-xl"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-700 mb-1">No products linked</h3>
                        <p class="text-xs text-slate-400 mb-4">This supplier doesn't have any linked products yet.</p>
                        <a href="<?= BASE_URL ?>/supplierlink?supplier_id=<?= $supplier['id'] ?>"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition-colors">
                            <i class="fas fa-link text-xs"></i> Link Products
                        </a>
                    </div>

                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <?php foreach ($linked_products as $product): ?>
                            <?php
                            // ── FIX: same resolveAsset logic as link_products.php ──
                            $pimg_url    = '';
                            $image_exists = false;
                            if (!empty($product['main_image'])) {
                                $pa = resolveAsset($product['main_image']);
                                $image_exists = file_exists($pa['file']);
                                $pimg_url     = $pa['url'];
                            }
                            ?>
                            <div class="bg-white rounded-xl border border-slate-200 hover:border-slate-300 hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col">

                                <!-- Image -->
                                <div class="aspect-square bg-slate-50 relative">
                                    <?php if ($image_exists): ?>
                                        <img src="<?= htmlspecialchars($pimg_url) ?>"
                                             alt="<?= htmlspecialchars($product['product_name']) ?>"
                                             class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="fas fa-box text-slate-300 text-3xl"></i>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($product['linked_variants_count'] > 0): ?>
                                        <div class="absolute top-2.5 right-2.5">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-medium rounded-full">
                                                <i class="fas fa-link text-xs"></i> <?= $product['linked_variants_count'] ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Info -->
                                <div class="p-4 flex-1 flex flex-col">
                                    <div class="flex-1">
                                        <h3 class="text-sm font-semibold text-slate-800 line-clamp-2 mb-1"
                                            title="<?= htmlspecialchars($product['product_name']) ?>">
                                            <?= !empty($product['product_name']) ? htmlspecialchars($product['product_name']) : 'Unnamed Product' ?>
                                        </h3>
                                        <p class="text-xs text-slate-400 mb-3">ID: <?= $product['id'] ?></p>

                                        <div class="space-y-1.5 text-xs">
                                            <div class="flex justify-between">
                                                <span class="text-slate-500">Variants</span>
                                                <span class="font-medium text-blue-600">
                                                    <?= $product['linked_variants_count'] ?> / <?= $product['total_variants_count'] ?> linked
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-slate-500">Code</span>
                                                <span class="font-medium text-slate-700 truncate ml-2" title="<?= htmlspecialchars($product['codename']) ?>">
                                                    <?= !empty($product['codename']) ? htmlspecialchars($product['codename']) : '—' ?>
                                                </span>
                                            </div>
                                            <div class="flex justify-between pt-1.5 border-t border-slate-100">
                                                <span class="text-slate-400">Linked</span>
                                                <span class="text-slate-400"><?= date('M j, Y', strtotime($product['linked_date'])) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="mt-4 flex gap-1.5">
                                        <button onclick="showVariantsModal(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['product_name'])) ?>')"
                                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium rounded-lg transition-colors">
                                            <i class="fas fa-list"></i> Variants
                                        </button>
                                        <a href="<?= BASE_URL ?>/supplierlink?supplier_id=<?= $supplier['id'] ?>&product_id=<?= $product['id'] ?>"
                                           class="w-8 h-8 flex items-center justify-center bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg transition-colors"
                                           title="Link More Variants">
                                            <i class="fas fa-link text-xs"></i>
                                        </a>
                                        <button onclick="unlinkProduct(<?= $supplier['id'] ?>, <?= $product['id'] ?>, '<?= htmlspecialchars($product['product_name'], ENT_QUOTES) ?>')"
                                                class="w-8 h-8 flex items-center justify-center bg-red-50 border border-red-200 text-red-600 hover:bg-red-100 rounded-lg transition-colors"
                                                title="Unlink Product">
                                            <i class="fas fa-unlink text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="text-xs text-slate-400 text-center mt-5">
                        Showing <?= count($linked_products) ?> linked product<?= count($linked_products) != 1 ? 's' : '' ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /main -->


    <!-- ─── VARIANTS MODAL ────────────────────────────────────────────── -->
    <div id="variantsModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-layer-group text-blue-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Linked Variants</p>
                        <p class="text-sm font-semibold text-slate-800" id="variantsModalProductName"></p>
                    </div>
                </div>
                <button onclick="closeVariantsModal()"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-6">

                <!-- Loading -->
                <div id="variantsLoadingSpinner" class="text-center py-12">
                    <div class="animate-spin rounded-full h-10 w-10 border-2 border-slate-200 border-t-blue-500 mx-auto mb-3"></div>
                    <p class="text-sm text-slate-400">Loading variants…</p>
                </div>

                <!-- Content -->
                <div id="variantsContent" class="hidden space-y-3"></div>

                <!-- Empty -->
                <div id="variantsEmptyState" class="hidden text-center py-12">
                    <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-layer-group text-slate-400"></i>
                    </div>
                    <p class="text-sm text-slate-500">No variants found for this product.</p>
                </div>
            </div>
        </div>
    </div>


    <script>
        const BASE_URL = '<?= BASE_URL ?>';

        /* ── Supplier status toggle ── */
        function toggleSupplierStatus(supplierId, currentStatus, supplierName) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} "${supplierName}"?`)) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'toggle_supplier_status.php';
            form.innerHTML = `<input type="hidden" name="supplier_id" value="${supplierId}"><input type="hidden" name="new_status" value="${newStatus}">`;
            document.body.appendChild(form);
            form.submit();
        }

        /* ── Unlink product ── */
        function unlinkProduct(supplierId, productId, productName) {
            if (!confirm(`Unlink "${productName}" from this supplier? This cannot be undone.`)) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'unlink_product.php';
            form.innerHTML = `<input type="hidden" name="supplier_id" value="${supplierId}"><input type="hidden" name="product_id" value="${productId}">`;
            document.body.appendChild(form);
            form.submit();
        }

        /* ── Variants Modal ── */
        function showVariantsModal(productId, productName) {
            document.getElementById('variantsModalProductName').textContent = productName;
            document.getElementById('variantsModal').classList.remove('hidden');
            document.getElementById('variantsLoadingSpinner').classList.remove('hidden');
            document.getElementById('variantsContent').classList.add('hidden');
            document.getElementById('variantsEmptyState').classList.add('hidden');

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_variants&product_id=${productId}`
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('variantsLoadingSpinner').classList.add('hidden');
                if (data.success && data.variants && data.variants.length > 0) {
                    displayVariants(data.variants);
                } else {
                    document.getElementById('variantsEmptyState').classList.remove('hidden');
                }
            })
            .catch(() => {
                document.getElementById('variantsLoadingSpinner').classList.add('hidden');
                document.getElementById('variantsEmptyState').classList.remove('hidden');
            });
        }

        function closeVariantsModal() {
            document.getElementById('variantsModal').classList.add('hidden');
        }

        document.getElementById('variantsModal').addEventListener('click', function(e) {
            if (e.target === this) closeVariantsModal();
        });

        function displayVariants(variants) {
            const content  = document.getElementById('variantsContent');
            const linked   = variants.filter(v => v.is_linked == 1);
            const unlinked = variants.filter(v => v.is_linked == 0);
            content.innerHTML = '';

            if (linked.length > 0) {
                content.insertAdjacentHTML('beforeend', `
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="fas fa-link text-emerald-500"></i> Linked Variants (${linked.length})
                    </p>`);
                linked.forEach(v => content.appendChild(createVariantCard(v, true)));
            }

            if (unlinked.length > 0) {
                content.insertAdjacentHTML('beforeend', `
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2 mt-5 flex items-center gap-1.5">
                        <i class="fas fa-unlink text-slate-300"></i> Not Linked (${unlinked.length})
                    </p>`);
                unlinked.forEach(v => content.appendChild(createVariantCard(v, false)));
            }

            content.classList.remove('hidden');
        }

        /* ── FIX: variant image URL using BASE_URL (no extra slash issue) ── */
        function assetUrl(p) {
            return p ? BASE_URL + '/' + p.replace(/^\/+/, '') : '';
        }

        function createVariantCard(variant, isLinked) {
            const card = document.createElement('div');
            card.className = `flex items-start gap-4 p-4 rounded-lg border ${isLinked ? 'bg-emerald-50 border-emerald-200' : 'bg-white border-slate-200'}`;

            // ── FIX: use assetUrl() instead of hardcoded BASE_URL concatenation ──
            const img = variant.image
                ? `<img src="${assetUrl(variant.image)}" alt="Variant" class="w-14 h-14 rounded-lg object-cover border border-slate-200 flex-shrink-0">`
                : `<div class="w-14 h-14 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center flex-shrink-0">
                       <i class="fas fa-image text-slate-300 text-lg"></i>
                   </div>`;

            const typeBadge = isLinked
                ? `<span class="px-1.5 py-0.5 rounded text-xs font-medium ${variant.supplier_type === 'primary' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'}">
                       ${variant.supplier_type}
                   </span>`
                : '';

            const supplierPrice = (isLinked && variant.supplier_price)
                ? `<div><span class="text-blue-600 font-medium text-xs">Supplier Price</span> <span class="font-bold text-blue-900 text-sm">₱${parseFloat(variant.supplier_price).toFixed(2)}</span></div>`
                : '';

            card.innerHTML = `
                ${img}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                        <p class="text-sm font-semibold text-slate-800">${variant.namevariant || 'Unnamed Variant'}</p>
                        ${variant.color ? `<span class="px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700">${variant.color}</span>` : ''}
                        ${variant.size  ? `<span class="px-1.5 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700">${variant.size}</span>` : ''}
                        ${typeBadge}
                    </div>
                    <div class="flex flex-wrap gap-4 text-xs text-slate-600 mt-1">
                        <div><span class="text-slate-400">Original</span> <span class="font-medium">₱${parseFloat(variant.original_price || 0).toFixed(2)}</span></div>
                        <div><span class="text-slate-400">Selling</span> <span class="font-medium text-emerald-600">₱${parseFloat(variant.price || 0).toFixed(2)}</span></div>
                        ${supplierPrice}
                    </div>
                </div>`;
            return card;
        }

        /* ── Auto-dismiss flash messages ── */
        document.querySelectorAll('.auto-dismiss').forEach(el => {
            setTimeout(() => {
                el.style.transition = 'opacity .4s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 400);
            }, 5000);
        });
    </script>

</body>
</html>