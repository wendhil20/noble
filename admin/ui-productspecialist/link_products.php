<?php
//link_products.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

if (!isset($_GET['supplier_id']) || !is_numeric($_GET['supplier_id'])) {
    header("Location: " . BASE_URL . "/supplierlist");
    exit();
}

$supplier_id = intval($_GET['supplier_id']);

$supplier_stmt = $conn->prepare("SELECT * FROM supplier_list WHERE id = ?");
$supplier_stmt->bind_param("i", $supplier_id);
$supplier_stmt->execute();
$supplier = $supplier_stmt->get_result()->fetch_assoc();
$supplier_stmt->close();

if (!$supplier) {
    header("Location: " . BASE_URL . "/supplierlist");
    exit();
}

// ── AJAX handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (isset($_POST['action'])) {

        // link_product
        if ($_POST['action'] === 'link_product' && isset($_POST['product_id'])) {
            $product_id    = intval($_POST['product_id']);
            $variant_id    = isset($_POST['variant_id']) ? intval($_POST['variant_id']) : null;
            $supplier_type = $_POST['supplier_type'] ?? 'secondary';
            $supplier_price= isset($_POST['supplier_price']) && $_POST['supplier_price'] !== ''
                             ? floatval($_POST['supplier_price']) : null;

            try {
                if ($variant_id) {
                    $ck = $conn->prepare("SELECT id,status FROM supp_link_products WHERE supplier_id=? AND variant_id=?");
                    $ck->bind_param("ii", $supplier_id, $variant_id);
                } else {
                    $ck = $conn->prepare("SELECT id,status FROM supp_link_products WHERE supplier_id=? AND product_id=? AND variant_id IS NULL");
                    $ck->bind_param("ii", $supplier_id, $product_id);
                }
                $ck->execute();
                $existing = $ck->get_result()->fetch_assoc();
                $ck->close();

                if ($existing) {
                    if ($existing['status'] === 'active') {
                        if ($supplier_price !== null) {
                            if ($variant_id) {
                                $u = $conn->prepare("UPDATE supp_link_products SET supplier_price=?,updated_at=NOW() WHERE supplier_id=? AND variant_id=?");
                                $u->bind_param("dii", $supplier_price, $supplier_id, $variant_id);
                            } else {
                                $u = $conn->prepare("UPDATE supp_link_products SET supplier_price=?,updated_at=NOW() WHERE supplier_id=? AND product_id=? AND variant_id IS NULL");
                                $u->bind_param("dii", $supplier_price, $supplier_id, $product_id);
                            }
                            $u->execute(); $u->close();
                        }
                        echo json_encode(['success'=>true,'message'=>'Already linked — price updated.']);
                        exit();
                    } else {
                        if ($variant_id) {
                            $u = $conn->prepare("UPDATE supp_link_products SET status='active',supplier_type=?,supplier_price=?,updated_at=NOW() WHERE supplier_id=? AND variant_id=?");
                            $u->bind_param("sdii", $supplier_type, $supplier_price, $supplier_id, $variant_id);
                        } else {
                            $u = $conn->prepare("UPDATE supp_link_products SET status='active',supplier_type=?,supplier_price=?,updated_at=NOW() WHERE supplier_id=? AND product_id=?");
                            $u->bind_param("sdii", $supplier_type, $supplier_price, $supplier_id, $product_id);
                        }
                        $ok = $u->execute(); $u->close();
                        echo json_encode(['success'=>$ok,'message'=>$ok?'Product linked successfully':'Failed: '.$conn->error]);
                        exit();
                    }
                } else {
                    if ($supplier_type === 'primary') {
                        if ($variant_id) {
                            $pc = $conn->prepare("SELECT sp.id,sl.business_name FROM supp_link_products sp LEFT JOIN supplier_list sl ON sp.supplier_id=sl.id WHERE sp.variant_id=? AND sp.supplier_type='primary' AND sp.status='active'");
                            $pc->bind_param("i", $variant_id);
                        } else {
                            $pc = $conn->prepare("SELECT sp.id,sl.business_name FROM supp_link_products sp LEFT JOIN supplier_list sl ON sp.supplier_id=sl.id WHERE sp.product_id=? AND sp.variant_id IS NULL AND sp.supplier_type='primary' AND sp.status='active'");
                            $pc->bind_param("i", $product_id);
                        }
                        $pc->execute();
                        $ep = $pc->get_result()->fetch_assoc();
                        $pc->close();
                        if ($ep) {
                            $sn = $ep['business_name'] ?: 'Another supplier';
                            echo json_encode(['success'=>false,'message'=>"Already has a primary supplier ({$sn})."]);
                            exit();
                        }
                    }
                    if ($variant_id) {
                        $ins = $conn->prepare("INSERT INTO supp_link_products (supplier_id,product_id,variant_id,supplier_type,supplier_price,status,created_at,updated_at) VALUES (?,?,?,?,?,'active',NOW(),NOW())");
                        $ins->bind_param("iiisd", $supplier_id, $product_id, $variant_id, $supplier_type, $supplier_price);
                    } else {
                        $ins = $conn->prepare("INSERT INTO supp_link_products (supplier_id,product_id,supplier_type,supplier_price,status,created_at,updated_at) VALUES (?,?,?,?,'active',NOW(),NOW())");
                        $ins->bind_param("iisd", $supplier_id, $product_id, $supplier_type, $supplier_price);
                    }
                    $ok = $ins->execute(); $ins->close();
                    $msg = $ok
                        ? 'Linked as '.$supplier_type.($supplier_price?' — ₱'.number_format($supplier_price,2):'')
                        : 'Failed: '.$conn->error;
                    echo json_encode(['success'=>$ok,'message'=>$msg]);
                    exit();
                }
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
                exit();
            }
        }

        // unlink_product
        if ($_POST['action'] === 'unlink_product' && isset($_POST['product_id'])) {
            $product_id = intval($_POST['product_id']);
            $variant_id = isset($_POST['variant_id']) ? intval($_POST['variant_id']) : null;
            try {
                if ($variant_id) {
                    $u = $conn->prepare("UPDATE supp_link_products SET status='inactive',updated_at=NOW() WHERE supplier_id=? AND variant_id=?");
                    $u->bind_param("ii", $supplier_id, $variant_id);
                } else {
                    $u = $conn->prepare("UPDATE supp_link_products SET status='inactive',updated_at=NOW() WHERE supplier_id=? AND product_id=? AND variant_id IS NULL");
                    $u->bind_param("ii", $supplier_id, $product_id);
                }
                $ok = $u->execute(); $u->close();
                echo json_encode(['success'=>$ok,'message'=>$ok?'Unlinked successfully':'Failed: '.$conn->error]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
                exit();
            }
        }

        // get_single_variant
        if ($_POST['action'] === 'get_single_variant' && isset($_POST['variant_id'], $_POST['product_id'])) {
            $variant_id = intval($_POST['variant_id']);
            $product_id = intval($_POST['product_id']);
            try {
                $s = $conn->prepare("
                    SELECT pv.*,
                           CASE WHEN sp.status='active' THEN 1 ELSE 0 END as is_linked,
                           sp.supplier_type, sp.supplier_price, sp.created_at as linked_at,
                           (SELECT COUNT(*) FROM supp_link_products s2 WHERE s2.variant_id=pv.id AND s2.supplier_type='primary' AND s2.status='active') as has_primary
                    FROM product_variants pv
                    LEFT JOIN supp_link_products sp ON pv.id=sp.variant_id AND sp.supplier_id=? AND sp.status='active'
                    WHERE pv.id=? AND pv.product_id=?
                ");
                $s->bind_param("iii", $supplier_id, $variant_id, $product_id);
                $s->execute();
                $v = $s->get_result()->fetch_assoc();
                $s->close();
                echo json_encode($v ? ['success'=>true,'variant'=>$v] : ['success'=>false,'message'=>'Not found']);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
                exit();
            }
        }

        // get_product_counts
        if ($_POST['action'] === 'get_product_counts' && isset($_POST['product_id'])) {
            $product_id = intval($_POST['product_id']);
            try {
                $s = $conn->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM supp_link_products sp WHERE sp.product_id=? AND sp.supplier_id=? AND sp.status='active' AND sp.variant_id IS NOT NULL) as linked_count,
                        (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id=?) as total_count
                ");
                $s->bind_param("iii", $product_id, $supplier_id, $product_id);
                $s->execute();
                $c = $s->get_result()->fetch_assoc();
                $s->close();
                echo json_encode(['success'=>true,'linked_count'=>$c['linked_count'],'total_count'=>$c['total_count']]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
                exit();
            }
        }

        // get_variants
        if ($_POST['action'] === 'get_variants' && isset($_POST['product_id'])) {
            $product_id = intval($_POST['product_id']);
            try {
                $s = $conn->prepare("
                    SELECT pv.*,
                           CASE WHEN sp.status='active' THEN 1 ELSE 0 END as is_linked,
                           sp.supplier_type, sp.supplier_price, sp.created_at as linked_at,
                           (SELECT COUNT(*) FROM supp_link_products s2 WHERE s2.variant_id=pv.id AND s2.supplier_type='primary' AND s2.status='active') as has_primary
                    FROM product_variants pv
                    LEFT JOIN supp_link_products sp ON pv.id=sp.variant_id AND sp.supplier_id=? AND sp.status='active'
                    WHERE pv.product_id=?
                    ORDER BY pv.namevariant ASC, pv.color ASC, pv.size ASC
                ");
                $s->bind_param("ii", $supplier_id, $product_id);
                $s->execute();
                $variants = $s->get_result()->fetch_all(MYSQLI_ASSOC);
                $s->close();
                echo json_encode(['success'=>true,'variants'=>$variants]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
                exit();
            }
        }
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action']);
    exit();
}

// ── Page data ─────────────────────────────────────────────────────────────────
$search            = trim($_GET['search'] ?? '');
$filter_product_id = intval($_GET['product_id'] ?? 0);

$where_conditions = [];
$params           = [];
$types            = "";

if ($filter_product_id > 0) {
    $where_conditions[] = "p.id = ?";
    $params[] = $filter_product_id;
    $types   .= "i";
}
if (!empty($search)) {
    $where_conditions[] = "(p.product_name LIKE ? OR p.codename LIKE ? OR p.description LIKE ?)";
    $sp      = "%$search%";
    $params  = array_merge($params, [$sp, $sp, $sp]);
    $types  .= "sss";
}
$where_clause = $where_conditions ? "WHERE ".implode(" AND ", $where_conditions) : "";

$products_sql = "
    SELECT p.*,
           (SELECT COUNT(*) FROM supp_link_products sp2
            WHERE sp2.product_id=p.id AND sp2.status='active'
            AND sp2.supplier_id=? AND sp2.variant_id IS NOT NULL) as linked_variants_count,
           (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id=p.id) as total_variants_count
    FROM products p $where_clause
    ORDER BY p.product_name ASC
";

try {
    $ps = $conn->prepare($products_sql);
    if (!$ps) die("Prepare failed: ".$conn->error);
    !empty($params) ? $ps->bind_param("i".$types, $supplier_id, ...$params) : $ps->bind_param("i", $supplier_id);
    $ps->execute();
    $products = $ps->get_result()->fetch_all(MYSQLI_ASSOC);
    $ps->close();
} catch (Exception $e) {
    die("Database error: ".$e->getMessage());
}

$lc = $conn->prepare("SELECT COUNT(*) as c FROM supp_link_products WHERE supplier_id=? AND status='active'");
$lc->bind_param("i", $supplier_id);
$lc->execute();
$linked_count = $lc->get_result()->fetch_assoc()['c'];
$lc->close();

// ── Helpers ───────────────────────────────────────────────────────────────────
function resolveAsset(string $stored): array {
    $stored = ltrim($stored, '/');
    return ['file' => ROOT_PATH.'/'.$stored, 'url' => BASE_URL.'/'.$stored];
}

// Supplier logo
$logo_url    = '';
$logo_exists = false;
if (!empty($supplier['logo_path'])) {
    $raw = ltrim($supplier['logo_path'], '/');
    if (!str_starts_with($raw, 'uploads/')) $raw = 'uploads/supplier_logos/'.basename($raw);
    $a = resolveAsset($raw);
    $logo_exists = file_exists($a['file']);
    $logo_url    = $a['url'];
}

// Supplier avatar acronym
$words   = explode(' ', trim($supplier['business_name']));
$acronym = '';
foreach ($words as $w) { if (!empty($w)) { $acronym .= strtoupper($w[0]); if (strlen($acronym)>=2) break; } }
if (!$acronym) $acronym = strtoupper(substr($supplier['business_name'],0,2));
$avatar_colors = ['bg-blue-500','bg-emerald-500','bg-violet-500','bg-rose-500'];
$avatar_bg     = $avatar_colors[abs(crc32($supplier['business_name'])) % 4];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Products — <?= htmlspecialchars($supplier['business_name']) ?></title>
    <style>
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .card-enter { animation: fadeUp .28s ease both; }
        .line-clamp-2 { display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-slate-500 mb-6">
        <a href="<?= BASE_URL ?>/supplierlist" class="hover:text-slate-800 transition-colors">Suppliers</a>
        <span class="text-slate-300">/</span>
        <a href="<?= BASE_URL ?>/viewsupplier?id=<?= $supplier_id ?>" class="hover:text-slate-800 transition-colors truncate max-w-[160px]">
            <?= htmlspecialchars($supplier['business_name']) ?>
        </a>
        <span class="text-slate-300">/</span>
        <span class="text-slate-700 font-medium">Link Products</span>
    </nav>

    <!-- Page header -->
    <div class="flex items-start justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Link Products</h1>
            <p class="text-slate-500 text-sm mt-0.5">Choose which products this supplier can supply</p>
        </div>
        <a href="<?= BASE_URL ?>/viewsupplier?id=<?= $supplier_id ?>"
           class="flex-shrink-0 inline-flex items-center gap-2 text-sm font-medium text-slate-600
                  bg-white border border-slate-200 rounded-xl px-4 py-2.5 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="fas fa-arrow-left text-xs"></i> Back
        </a>
    </div>

    <!-- Supplier summary -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-6">
        <div class="flex items-center gap-4">
            <!-- Logo / Avatar -->
            <?php if ($logo_exists): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo"
                     class="w-12 h-12 rounded-xl object-cover border border-slate-200 flex-shrink-0">
            <?php else: ?>
                <div class="w-12 h-12 rounded-xl <?= $avatar_bg ?> flex items-center justify-center text-white font-bold flex-shrink-0">
                    <?= htmlspecialchars($acronym) ?>
                </div>
            <?php endif; ?>

            <div class="flex-1 min-w-0">
                <p class="font-semibold text-slate-800 truncate"><?= htmlspecialchars($supplier['business_name']) ?></p>
                <p class="text-sm text-slate-500">
                    <?= htmlspecialchars($supplier['business_type']) ?> &middot; <?= htmlspecialchars($supplier['country_region']) ?>
                </p>
            </div>

            <!-- Quick stats -->
            <div class="hidden sm:flex items-center gap-3 flex-shrink-0">
                <div class="text-center bg-blue-50 rounded-xl px-4 py-2">
                    <p class="text-xl font-bold text-blue-700 leading-none"><?= $linked_count ?></p>
                    <p class="text-xs text-blue-500 mt-0.5">Linked</p>
                </div>
                <div class="text-center bg-slate-100 rounded-xl px-4 py-2">
                    <p class="text-xl font-bold text-slate-700 leading-none"><?= count($products) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">Products</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <form method="GET" class="flex gap-3">
            <input type="hidden" name="supplier_id" value="<?= $supplier_id ?>">
            <?php if ($filter_product_id > 0): ?>
                <input type="hidden" name="product_id" value="<?= $filter_product_id ?>">
            <?php endif; ?>

            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search by name, code, or description…"
                       class="w-full pl-9 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition-colors">
            </div>

            <button type="submit"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                Search
            </button>

            <?php if (!empty($search) || $filter_product_id > 0): ?>
                <a href="?supplier_id=<?= $supplier_id ?>"
                   class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium rounded-xl transition-colors flex items-center gap-1.5">
                    <i class="fas fa-times text-xs"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Active filter pill -->
    <?php if ($filter_product_id > 0):
       $fp_arr = array_filter($products, fn($p) => $p['id'] == $filter_product_id);
$fp = reset($fp_arr);
    ?>
        <div class="flex items-center justify-between bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 mb-5 text-sm">
            <span class="text-blue-700">
                <i class="fas fa-filter mr-2 opacity-60"></i>
                Filtered to: <strong><?= htmlspecialchars($fp['product_name'] ?? 'Unknown') ?></strong>
            </span>
            <a href="?supplier_id=<?= $supplier_id ?>" class="text-blue-600 hover:text-blue-800 font-medium">Remove</a>
        </div>
    <?php endif; ?>

    <!-- Products grid -->
    <?php if (empty($products)): ?>
        <div class="text-center py-20 bg-white rounded-2xl border border-slate-200">
            <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-box text-slate-400 text-xl"></i>
            </div>
            <p class="font-semibold text-slate-700 mb-1">No products found</p>
            <p class="text-sm text-slate-400">Try a different search term.</p>
        </div>

    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($products as $i => $product):
                $pimg_url = ''; $pimg_exists = false;
                if (!empty($product['main_image'])) {
                    $pa = resolveAsset($product['main_image']);
                    $pimg_exists = file_exists($pa['file']);
                    $pimg_url    = $pa['url'];
                }
                $linked    = intval($product['linked_variants_count']);
                $total     = intval($product['total_variants_count']);
                $all_linked  = $total > 0 && $linked >= $total;
                $some_linked = $linked > 0 && !$all_linked;
            ?>
                <div id="product-card-<?= $product['id'] ?>"
                     class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col card-enter hover:shadow-md hover:border-slate-300 transition-all"
                     style="animation-delay:<?= min($i * 35, 300) ?>ms">

                    <!-- Top section -->
                    <div class="p-5 flex gap-3 items-start">
                        <!-- Thumbnail -->
                        <?php if ($pimg_exists): ?>
                            <img src="<?= htmlspecialchars($pimg_url) ?>"
                                 alt="<?= htmlspecialchars($product['product_name']) ?>"
                                 class="w-14 h-14 rounded-xl object-cover border border-slate-100 flex-shrink-0">
                        <?php else: ?>
                            <div class="w-14 h-14 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0 border border-slate-200">
                                <i class="fas fa-box text-slate-400"></i>
                            </div>
                        <?php endif; ?>

                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-slate-800 line-clamp-2 leading-snug">
                                <?= htmlspecialchars($product['product_name'] ?: 'Unnamed Product') ?>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5 truncate">
                                <?= htmlspecialchars($product['codename'] ?: 'No code') ?>
                            </p>

                            <!-- Status pills -->
                            <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                    <?= $total ?> variant<?= $total !== 1 ? 's' : '' ?>
                                </span>
                                <?php if ($all_linked): ?>
                                    <span class="linked-variants-badge text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">
                                        <i class="fas fa-check-circle mr-0.5 text-[9px]"></i> All linked
                                    </span>
                                <?php elseif ($some_linked): ?>
                                    <span class="linked-variants-badge text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                        <i class="fas fa-link mr-0.5 text-[9px]"></i> <?= $linked ?>/<?= $total ?> linked
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($product['description'])): ?>
                        <div class="px-5 pb-4">
                            <p class="text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars($product['description']) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- CTA -->
                    <div class="mt-auto border-t border-slate-100 p-4">
                        <button onclick="showVariantsModal(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['product_name'])) ?>')"
                                class="w-full flex items-center justify-center gap-2 text-sm font-medium
                                       bg-blue-600 hover:bg-blue-700 active:scale-95
                                       text-white rounded-xl py-2.5 transition-all">
                            <i class="fas fa-list-ul text-xs"></i>
                            View &amp; Link Variants
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div><!-- /container -->


<!-- ════════════════════════════════════════════
     TOAST
════════════════════════════════════════════ -->
<div id="toast"
     class="hidden fixed top-5 right-5 z-[90] flex items-center gap-3
            bg-white border border-slate-200 rounded-2xl shadow-lg px-4 py-3
            min-w-[240px] max-w-xs transition-all">
    <div id="toast-dot" class="w-2 h-2 rounded-full flex-shrink-0 bg-emerald-500"></div>
    <p id="toast-msg" class="text-sm text-slate-700 flex-1 leading-snug"></p>
    <button onclick="hideToast()" class="text-slate-400 hover:text-slate-600 flex-shrink-0">
        <i class="fas fa-times text-xs"></i>
    </button>
</div>


<!-- ════════════════════════════════════════════
     VARIANTS MODAL
════════════════════════════════════════════ -->
<div id="variantsModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(15,23,42,.5);backdrop-filter:blur(4px)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[88vh] flex flex-col">

        <!-- Header -->
        <div class="flex items-start justify-between px-6 py-5 border-b border-slate-100">
            <div>
                <h2 class="font-bold text-slate-800">Product Variants</h2>
                <p id="variantsModalProductName" class="text-sm text-slate-500 mt-0.5"></p>
            </div>
            <button onclick="closeVariantsModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400
                           hover:bg-slate-100 hover:text-slate-600 transition-colors flex-shrink-0 mt-0.5">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-5 space-y-3">
            <div id="variantsLoadingSpinner" class="py-16 flex flex-col items-center gap-3">
                <div class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                <p class="text-sm text-slate-500">Loading variants…</p>
            </div>
            <div id="variantsContent" class="hidden space-y-3"></div>
            <div id="variantsEmptyState" class="hidden py-16 text-center">
                <i class="fas fa-layer-group text-3xl text-slate-300 mb-3 block"></i>
                <p class="font-medium text-slate-600">No variants found</p>
                <p class="text-sm text-slate-400 mt-1">This product has no variants yet.</p>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════
     PRICE MODAL
════════════════════════════════════════════ -->
<div id="priceModal"
     class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4"
     style="background:rgba(15,23,42,.5);backdrop-filter:blur(4px)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <h2 class="font-bold text-slate-800">
                <span id="modalActionLabel">Set</span> Supplier Price
            </h2>
            <button onclick="closePriceModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400
                           hover:bg-slate-100 hover:text-slate-600 transition-colors">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-4">

            <!-- Variant -->
            <div class="bg-slate-50 rounded-xl px-4 py-3">
                <p class="text-xs text-slate-400 mb-0.5">Variant</p>
                <p id="modalProductName" class="font-semibold text-slate-800 text-sm"></p>
            </div>

            <!-- Original price ref -->
            <div id="originalPriceReference"
                 class="flex items-center justify-between bg-blue-50 border border-blue-100 rounded-xl px-4 py-2.5 text-sm">
                <span class="text-blue-600 text-xs"><i class="fas fa-info-circle mr-1.5 opacity-70"></i>Original price</span>
                <span id="modalOriginalPrice" class="font-bold text-blue-800 text-sm"></span>
            </div>

            <!-- Input -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    Supplier Price <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm font-medium">₱</span>
                    <input type="number" id="supplierPriceInput" step="0.01" min="0" placeholder="0.00"
                           class="w-full pl-9 pr-4 py-3 text-sm border border-slate-200 rounded-xl
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <!-- Hidden -->
            <input type="hidden" id="modalProductId">
            <input type="hidden" id="modalVariantId">
            <input type="hidden" id="modalSupplierType">
            <input type="hidden" id="modalOriginalPriceValue">

            <!-- Buttons -->
            <div class="flex gap-3 pt-1">
                <button onclick="closePriceModal()"
                        class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button id="submitPriceBtn" onclick="submitPriceAndLink()"
                        class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium
                               transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-check text-xs"></i>
                    <span id="submitBtnText">Link Variant</span>
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════
     JS
════════════════════════════════════════════ -->
<script>
const BASE_URL = '<?= BASE_URL ?>';

// ── Toast ──────────────────────────────────────────────────────
let _tt;
function showToast(msg, type = 'success') {
    document.getElementById('toast-msg').textContent = msg;
    document.getElementById('toast-dot').className =
        'w-2 h-2 rounded-full flex-shrink-0 ' + (type === 'success' ? 'bg-emerald-500' : 'bg-red-500');
    document.getElementById('toast').classList.remove('hidden');
    clearTimeout(_tt);
    _tt = setTimeout(hideToast, 3500);
}
function hideToast() { document.getElementById('toast').classList.add('hidden'); }

// ── Price modal ────────────────────────────────────────────────
function openPriceModal({ productId, variantId, variantName, supplierType, currentPrice = '', originalPrice = 0, isUpdate = false }) {
    document.getElementById('modalProductId').value          = productId;
    document.getElementById('modalVariantId').value          = variantId;
    document.getElementById('modalProductName').textContent  = variantName;
    document.getElementById('modalSupplierType').value       = supplierType;
    document.getElementById('supplierPriceInput').value      = isUpdate ? currentPrice : '';
    document.getElementById('modalActionLabel').textContent  = isUpdate ? 'Update' : 'Set';
    document.getElementById('submitBtnText').textContent     = isUpdate ? 'Update Price' : 'Link Variant';
    document.getElementById('modalOriginalPriceValue').value = originalPrice;
    document.getElementById('modalOriginalPrice').textContent = '₱' + parseFloat(originalPrice || 0).toFixed(2);
    document.getElementById('originalPriceReference').classList.toggle('hidden', !(originalPrice > 0));
    document.getElementById('priceModal').classList.remove('hidden');
    setTimeout(() => document.getElementById('supplierPriceInput').focus(), 80);
}
function closePriceModal() { document.getElementById('priceModal').classList.add('hidden'); }

function submitPriceAndLink() {
    const productId    = document.getElementById('modalProductId').value;
    const variantId    = document.getElementById('modalVariantId').value;
    const supplierType = document.getElementById('modalSupplierType').value;
    const price        = document.getElementById('supplierPriceInput').value;
    const btn          = document.getElementById('submitPriceBtn');

    if (!price || parseFloat(price) <= 0) { showToast('Please enter a valid price.', 'error'); return; }

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i><span>Processing…</span>';

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=link_product&product_id=${productId}${variantId?'&variant_id='+variantId:''}&supplier_type=${supplierType}&supplier_price=${price}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Done!');
            closePriceModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Something went wrong.', 'error');
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-check text-xs"></i><span id="submitBtnText">Link Variant</span>';
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-check text-xs"></i><span id="submitBtnText">Link Variant</span>';
    });
}

document.getElementById('supplierPriceInput').addEventListener('keypress', e => { if(e.key==='Enter') submitPriceAndLink(); });
document.getElementById('priceModal').addEventListener('click', e => { if(e.target===e.currentTarget) closePriceModal(); });

// ── Variants modal ─────────────────────────────────────────────
function showVariantsModal(productId, productName) {
    document.getElementById('variantsModalProductName').textContent = productName;
    document.getElementById('variantsContent').innerHTML            = '';
    document.getElementById('variantsContent').classList.add('hidden');
    document.getElementById('variantsEmptyState').classList.add('hidden');
    document.getElementById('variantsLoadingSpinner').classList.remove('hidden');
    document.getElementById('variantsModal').classList.remove('hidden');

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_variants&product_id=${productId}`
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('variantsLoadingSpinner').classList.add('hidden');
        if (data.success && data.variants?.length) {
            data.variants.forEach(v => document.getElementById('variantsContent').appendChild(buildVariantCard(v, productId)));
            document.getElementById('variantsContent').classList.remove('hidden');
        } else {
            document.getElementById('variantsEmptyState').classList.remove('hidden');
        }
    })
    .catch(() => {
        document.getElementById('variantsLoadingSpinner').classList.add('hidden');
        document.getElementById('variantsEmptyState').classList.remove('hidden');
    });
}
function closeVariantsModal() { document.getElementById('variantsModal').classList.add('hidden'); }
document.getElementById('variantsModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeVariantsModal(); });

// ── Build variant card ─────────────────────────────────────────
function assetUrl(p) { return p ? BASE_URL + '/' + p.replace(/^\/+/,'') : ''; }

function buildVariantCard(v, productId) {
    const isLinked   = v.is_linked == 1;
    const hasPrimary = v.has_primary > 0;
    const safeName   = (v.namevariant || 'Variant').replace(/"/g,'&quot;');

    const el = document.createElement('div');
    el.id    = `variant-card-${v.id}`;
    el.className = 'flex gap-4 items-start bg-white border border-slate-200 rounded-xl p-4 hover:border-slate-300 transition-colors';

    // Thumbnail
    const thumb = v.image
        ? `<img src="${assetUrl(v.image)}" alt="Variant" class="w-14 h-14 rounded-xl object-cover border border-slate-100 flex-shrink-0">`
        : `<div class="w-14 h-14 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0 border border-slate-200">
               <i class="fas fa-image text-slate-400 text-sm"></i>
           </div>`;

    // Link status badge
    const badge = isLinked
        ? `<span class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0
                        ${v.supplier_type==='primary' ? 'bg-blue-100 text-blue-700':'bg-emerald-100 text-emerald-700'}">
               ${v.supplier_type}
           </span>`
        : `<span class="text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 flex-shrink-0">
               Not linked
           </span>`;

    // Attribute chips
    const chips = [
        v.color ? `<span class="text-xs px-2 py-0.5 bg-purple-50 text-purple-700 rounded-full border border-purple-100">${v.color}</span>` : '',
        v.size  ? `<span class="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100">${v.size}</span>`  : '',
    ].filter(Boolean).join('');

    // Prices
    const prices = `
        <div class="flex flex-wrap gap-3 text-xs text-slate-500">
            <span>Original: <strong class="text-slate-700">₱${parseFloat(v.original_price||0).toFixed(2)}</strong></span>
            <span>Selling: <strong class="text-emerald-700">₱${parseFloat(v.price||0).toFixed(2)}</strong></span>
            ${isLinked && v.supplier_price
                ? `<span>Your price: <strong class="text-blue-700">₱${parseFloat(v.supplier_price).toFixed(2)}</strong></span>`
                : ''}
        </div>`;

    // Action buttons
    let btns;
    if (isLinked) {
        btns = `
            <button data-action="update-price"
                    data-product-id="${productId}" data-variant-id="${v.id}"
                    data-variant-name="${safeName}" data-current-price="${v.supplier_price||0}"
                    data-original-price="${v.original_price||0}"
                    class="variant-action-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                           bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                <i class="fas fa-pen"></i> Update Price
            </button>
            <button data-action="unlink"
                    data-product-id="${productId}" data-variant-id="${v.id}"
                    id="btn-variant-${v.id}"
                    class="variant-action-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                           border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                <i class="fas fa-unlink"></i> Unlink
            </button>`;
    } else {
        const primaryBtn = !hasPrimary
            ? `<button data-action="link"
                       data-product-id="${productId}" data-variant-id="${v.id}"
                       data-variant-name="${safeName}" data-supplier-type="primary"
                       data-original-price="${v.original_price||0}"
                       class="variant-action-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                              bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                   <i class="fas fa-star"></i> Primary
               </button>`
            : `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-100 text-slate-400 cursor-not-allowed">
                   <i class="fas fa-star"></i> Primary taken
               </span>`;

        btns = primaryBtn + `
            <button data-action="link"
                    data-product-id="${productId}" data-variant-id="${v.id}"
                    data-variant-name="${safeName}" data-supplier-type="secondary"
                    data-original-price="${v.original_price||0}"
                    class="variant-action-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                           bg-emerald-600 hover:bg-emerald-700 text-white transition-colors">
                <i class="fas fa-link"></i> Secondary
            </button>`;
    }

    el.innerHTML = `
        ${thumb}
        <div class="flex-1 min-w-0 space-y-2">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-800 text-sm truncate">${v.namevariant || 'Unnamed Variant'}</p>
                    <div class="flex flex-wrap gap-1 mt-1">${chips}</div>
                </div>
                ${badge}
            </div>
            ${prices}
            <div class="flex flex-wrap gap-2">${btns}</div>
        </div>`;

    return el;
}

// ── Event delegation ───────────────────────────────────────────
document.addEventListener('click', e => {
    const btn = e.target.closest('.variant-action-btn');
    if (!btn) return;

    const action    = btn.dataset.action;
    const productId = parseInt(btn.dataset.productId);
    const variantId = parseInt(btn.dataset.variantId);

    if (action === 'link') {
        openPriceModal({
            productId, variantId,
            variantName:   btn.dataset.variantName,
            supplierType:  btn.dataset.supplierType,
            originalPrice: parseFloat(btn.dataset.originalPrice),
            isUpdate: false,
        });
    } else if (action === 'update-price') {
        openPriceModal({
            productId, variantId,
            variantName:   btn.dataset.variantName,
            supplierType:  'update',
            currentPrice:  parseFloat(btn.dataset.currentPrice),
            originalPrice: parseFloat(btn.dataset.originalPrice),
            isUpdate: true,
        });
    } else if (action === 'unlink') {
        if (!confirm('Unlink this variant? The price data will be kept.')) return;

        const b    = document.getElementById(`btn-variant-${variantId}`);
        b.disabled = true;
        b.innerHTML= '<i class="fas fa-spinner fa-spin"></i> Unlinking…';

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=unlink_product&product_id=${productId}&variant_id=${variantId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Unlinked successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to unlink.', 'error');
                b.disabled  = false;
                b.innerHTML = '<i class="fas fa-unlink"></i> Unlink';
            }
        })
        .catch(() => {
            showToast('Network error.', 'error');
            b.disabled  = false;
            b.innerHTML = '<i class="fas fa-unlink"></i> Unlink';
        });
    }
});
</script>
</body>
</html>