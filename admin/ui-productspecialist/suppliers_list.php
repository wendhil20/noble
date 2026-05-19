<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$business_type_filter = isset($_GET['business_type']) ? $_GET['business_type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(business_name LIKE ? OR primary_contact_name LIKE ? OR email_address LIKE ? OR country_region LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}
if (!empty($business_type_filter)) {
    $where_conditions[] = "business_type = ?";
    $params[] = $business_type_filter;
    $types .= "s";
}
if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$count_sql = "SELECT COUNT(*) as total FROM supplier_list $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
}

$sql = "SELECT id, business_name, business_type, country_region, primary_contact_name,
               job_title, phone_number, email_address, status, created_at, logo_path
        FROM supplier_list $where_clause ORDER BY business_name ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $suppliers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $suppliers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$types_result = $conn->query("SELECT DISTINCT business_type FROM supplier_list ORDER BY business_type");
$business_types = [];
while ($row = $types_result->fetch_assoc()) {
    $business_types[] = $row['business_type'];
}

// Helper: badge color per business type
function typeBadgeClass($type)
{
    return match ($type) {
        'Manufacturer' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
        'Wholesaler' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'Distributor' => 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',
        'Retailer' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        default => 'bg-gray-100 text-gray-600 ring-1 ring-gray-200',
    };
}

// Helper: avatar bg color
function avatarColor($name)
{
    $colors = [
        'bg-sky-500',
        'bg-emerald-500',
        'bg-violet-500',
        'bg-rose-500',
        'bg-amber-500',
        'bg-indigo-500',
        'bg-teal-500',
        'bg-pink-500'
    ];
    return $colors[abs(crc32($name)) % count($colors)];
}

// Helper: get acronym
function getAcronym($name)
{
    $words = array_filter(explode(' ', trim($name)));
    $acronym = '';
    foreach ($words as $w) {
        $acronym .= strtoupper($w[0]);
        if (strlen($acronym) >= 2)
            break;
    }
    return $acronym ?: strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Directory</title>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- ── Page Header ── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Supplier Directory</h1>
                <p class="text-sm text-gray-500 mt-1">
                    <span class="font-medium text-gray-700"><?= number_format($total_records) ?></span>
                    supplier<?= $total_records !== 1 ? 's' : '' ?> in your network
                </p>
            </div>
            <a href="<?= BASE_URL ?>/suppliermanagement" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                  text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors shadow-sm">
                <i class="fas fa-plus text-xs"></i>
                Add Supplier
            </a>
        </div>

        <!-- ── Filters ── -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
            <form method="GET">
                <div class="flex flex-col md:flex-row gap-3">

                    <!-- Search -->
                    <div class="relative flex-1">
                        <i
                            class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search name, contact, email, region…" class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Business Type -->
                    <select name="business_type" class="text-sm border border-gray-300 rounded-lg px-3 py-2.5 bg-white
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-40">
                        <option value="">All Types</option>
                        <?php foreach ($business_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $business_type_filter === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Status -->
                    <select name="status" class="text-sm border border-gray-300 rounded-lg px-3 py-2.5 bg-white
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-36">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>

                    <!-- Buttons -->
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white
                                   text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                            <i class="fas fa-filter text-xs"></i>Filter
                        </button>
                        <?php if ($search || $business_type_filter || $status_filter): ?>
                            <a href="?" class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600
                                  text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                                <i class="fas fa-times text-xs"></i>Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- ── Empty State ── -->
        <?php if (empty($suppliers)): ?>
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm py-20 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-box-open text-gray-400 text-xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800 mb-1">No suppliers found</h3>
                <p class="text-sm text-gray-500 mb-5">Try a different search or filter, or add a new supplier.</p>
                <a href="supplier_management.php" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white
                      text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    <i class="fas fa-plus text-xs"></i>Add Supplier
                </a>
            </div>

        <?php else: ?>

            <!-- ── Supplier Cards ── -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <?php foreach ($suppliers as $s):
                    $logo_path = !empty($s['logo_path']) ? '../../uploads/supplier_logos/' . basename($s['logo_path']) : '';
                    $logo_exists = $logo_path && file_exists($logo_path);
                    $isActive = $s['status'] === 'active';
                    ?>
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col
                    hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">

                        <!-- Card Top -->
                        <div class="p-5 flex items-start gap-4">

                            <!-- Avatar / Logo -->
                            <?php if ($logo_exists): ?>
                                <img src="<?= htmlspecialchars($logo_path) ?>" alt="<?= htmlspecialchars($s['business_name']) ?>"
                                    class="w-12 h-12 rounded-lg object-cover border border-gray-200 flex-shrink-0">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-lg flex-shrink-0 flex items-center justify-center
                                text-white text-sm font-bold <?= avatarColor($s['business_name']) ?>">
                                    <?= getAcronym($s['business_name']) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Name + badges -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-semibold text-gray-900 leading-snug truncate mb-1.5">
                                    <?= htmlspecialchars($s['business_name']) ?>
                                </h3>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <!-- Business type -->
                                    <span
                                        class="text-xs font-medium px-2 py-0.5 rounded-full <?= typeBadgeClass($s['business_type']) ?>">
                                        <?= htmlspecialchars($s['business_type']) ?>
                                    </span>
                                    <!-- Status dot -->
                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full
                                     <?= $isActive ? 'bg-green-50 text-green-700 ring-1 ring-green-200' : 'bg-red-50 text-red-600 ring-1 ring-red-200' ?>">
                                        <span
                                            class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-green-500' : 'bg-red-400' ?>"></span>
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="border-t border-gray-100 mx-5"></div>

                        <!-- Details -->
                        <div class="p-5 space-y-2.5 flex-1">
                            <div class="flex items-center gap-3 text-sm text-gray-600">
                                <i class="fas fa-user w-4 text-center text-gray-400 text-xs"></i>
                                <div>
                                    <span
                                        class="font-medium text-gray-800"><?= htmlspecialchars($s['primary_contact_name']) ?></span>
                                    <?php if ($s['job_title']): ?>
                                        <span class="text-gray-400"> · </span>
                                        <span class="text-gray-500"><?= htmlspecialchars($s['job_title']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 text-sm text-gray-600">
                                <i class="fas fa-map-marker-alt w-4 text-center text-gray-400 text-xs"></i>
                                <span><?= htmlspecialchars($s['country_region']) ?></span>
                            </div>

                            <div class="flex items-center gap-3 text-sm">
                                <i class="fas fa-envelope w-4 text-center text-gray-400 text-xs"></i>
                                <a href="mailto:<?= htmlspecialchars($s['email_address']) ?>"
                                    class="text-blue-600 hover:underline truncate">
                                    <?= htmlspecialchars($s['email_address']) ?>
                                </a>
                            </div>

                            <div class="flex items-center gap-3 text-sm text-gray-600">
                                <i class="fas fa-phone w-4 text-center text-gray-400 text-xs"></i>
                                <a href="tel:<?= htmlspecialchars($s['phone_number']) ?>"
                                    class="hover:text-blue-600 transition-colors">
                                    <?= htmlspecialchars($s['phone_number']) ?>
                                </a>
                            </div>

                            <div class="flex items-center gap-3 text-xs text-gray-400 pt-1 border-t border-gray-100">
                                <i class="fas fa-calendar w-4 text-center text-xs"></i>
                                <span>Added <?= date('M j, Y', strtotime($s['created_at'])) ?></span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="px-5 pb-5 pt-0 flex gap-2">
                            <a href="<?= BASE_URL ?>/viewsupplier?id=<?= $s['id'] ?>" class="flex-1 inline-flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-700
                          text-white text-xs font-medium py-2 px-3 rounded-lg transition-colors">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="<?= BASE_URL ?>/editsupplier?edit_id=<?= $s['id'] ?>" class="flex-1 inline-flex items-center justify-center gap-1.5 bg-gray-100 hover:bg-gray-200
                          text-gray-700 text-xs font-medium py-2 px-3 rounded-lg transition-colors">
                                <i class="fas fa-pen"></i> Edit
                            </a>
                            <a href="<?= BASE_URL ?>/supplierlink?supplier_id=<?= $s['id'] ?>" class="inline-flex items-center justify-center gap-1.5 bg-gray-100 hover:bg-gray-200
                          text-gray-700 text-xs font-medium py-2 px-3 rounded-lg transition-colors"
                                title="Link Products">
                                <i class="fas fa-link"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer count -->
            <p class="text-center text-xs text-gray-400 mt-6">
                Showing <?= count($suppliers) ?> of <?= number_format($total_records) ?> suppliers
            </p>

        <?php endif; ?>
    </div>

    <script>
        // Auto-submit on dropdown change
        document.querySelectorAll('select[name="business_type"], select[name="status"]').forEach(el => {
            el.addEventListener('change', () => el.form.submit());
        });
    </script>
</body>

</html>