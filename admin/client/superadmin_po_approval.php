<?php
// superadmin_po_approval.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

$current_user_id = $_SESSION['noble_id'] ?? 0;

// Handle file download BEFORE any HTML output
if (isset($_GET['download']) && isset($_GET['file_id'])) {
    $file_id = (int)$_GET['file_id'];
    $downloadSql = "SELECT * FROM po_attachments WHERE id = ? LIMIT 1";
    $downloadStmt = $conn->prepare($downloadSql);
    $downloadStmt->bind_param("i", $file_id);
    $downloadStmt->execute();
    $file = $downloadStmt->get_result()->fetch_assoc();
    $downloadStmt->close();
    
    if ($file && file_exists($file['file_path'])) {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
        header('Content-Length: ' . filesize($file['file_path']));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        readfile($file['file_path']);
        exit();
    } else {
        $_SESSION['error_message'] = "File not found or has been deleted.";
        header("Location: superadmin_po_approval.php");
        exit();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle approval
if (isset($_POST['approve_po'])) {
    $file_id = (int)$_POST['file_id'];
    
    $approveSql = "UPDATE po_attachments 
                   SET superadmin_approval_status = 'approved',
                       superadmin_approved_by = ?,
                       superadmin_approved_at = NOW()
                   WHERE id = ?";
    $stmt = $conn->prepare($approveSql);
    $stmt->bind_param("ii", $current_user_id, $file_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "P.O. file approved successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to approve P.O. file.";
    }
    $stmt->close();
    header("Location: superadmin_po_approval.php");
    exit();
}

// Handle rejection
if (isset($_POST['reject_po'])) {
    $file_id = (int)$_POST['file_id'];
    $rejection_reason = trim($_POST['rejection_reason']);
    
    $rejectSql = "UPDATE po_attachments 
                  SET superadmin_approval_status = 'rejected',
                      superadmin_approved_by = ?,
                      superadmin_approved_at = NOW(),
                      superadmin_rejection_reason = ?
                  WHERE id = ?";
    $stmt = $conn->prepare($rejectSql);
    $stmt->bind_param("isi", $current_user_id, $rejection_reason, $file_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "P.O. file rejected.";
    } else {
        $_SESSION['error_message'] = "Failed to reject P.O. file.";
    }
    $stmt->close();
    header("Location: superadmin_po_approval.php");
    exit();
}

// Build query for P.O. files
$whereConditions = ["1=1"];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $whereConditions[] = "pa.superadmin_approval_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search !== '') {
    $whereConditions[] = "(pa.supplier_name LIKE ? OR pa.original_filename LIKE ? OR o.id LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

$whereClause = implode(' AND ', $whereConditions);

// Get P.O. files with order details
$sql = "SELECT pa.*, 
        o.customer_name, o.email, o.total, o.status as order_status,
        requester.fullname as requested_by_name,
        sa.fullname as superadmin_name
        FROM po_attachments pa
        INNER JOIN orders o ON pa.order_id = o.id
        LEFT JOIN nobleaccount requester ON pa.approval_requested_by = requester.id
        LEFT JOIN nobleaccount sa ON pa.superadmin_approved_by = sa.id
        WHERE {$whereClause}
        ORDER BY 
            CASE pa.superadmin_approval_status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
            END,
            pa.uploaded_at DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$poFiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get status counts
$countSql = "SELECT 
                superadmin_approval_status,
                COUNT(*) as count
             FROM po_attachments
             GROUP BY superadmin_approval_status";
$countResult = $conn->query($countSql);
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
while ($row = $countResult->fetch_assoc()) {
    $statusCounts[$row['superadmin_approval_status']] = (int)$row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P.O. Approval - Superadmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>

<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="w-full px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-shield-alt text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">P.O. File Approval</h1>
                        <p class="text-sm text-gray-600">Review and approve purchase order files</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="w-full px-6 py-8">
        
        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="text-green-800"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                    <span class="text-red-800"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Approval</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $statusCounts['pending']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Approved</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $statusCounts['approved']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-red-500">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Rejected</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $statusCounts['rejected']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <form method="GET" class="flex gap-4">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by Order ID, Supplier, or File name..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </form>
        </div>

        <!-- P.O. Files List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <?php if (!empty($poFiles)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($poFiles as $file): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="font-medium text-gray-900">#<?php echo $file['order_id']; ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($file['customer_name']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($file['supplier_name']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-file-pdf text-red-600 mr-2"></i>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($file['original_filename']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($file['uploaded_at'])); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($file['requested_by_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusClass = $statusColors[$file['superadmin_approval_status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($file['superadmin_approval_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-2">
    <a href="?download=1&file_id=<?php echo $file['id']; ?>" 
   target="_blank"
   class="text-blue-600 hover:text-blue-900" title="View PDF">
    <i class="fas fa-file-pdf"></i>
</a>
                                            <?php if ($file['superadmin_approval_status'] === 'pending'): ?>
                                                <button onclick="approveFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                                                        class="text-green-600 hover:text-green-900">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button onclick="rejectFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['original_filename'], ENT_QUOTES); ?>')"
                                                        class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No P.O. files found</h3>
                    <p class="text-sm text-gray-500">Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Approve Modal -->
    <div id="approveModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <form method="POST">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="bg-green-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-check text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold">Approve P.O. File</h3>
                        </div>
                        <p class="text-gray-600 mb-6">Approve <strong id="approveFileName"></strong>?</p>
                        <input type="hidden" name="file_id" id="approveFileId">
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('approveModal')" 
                                    class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">
                                Cancel
                            </button>
                            <button type="submit" name="approve_po" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                <i class="fas fa-check mr-2"></i>Approve
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <form method="POST">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="bg-red-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-times text-red-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold">Reject P.O. File</h3>
                        </div>
                        <p class="text-gray-600 mb-4">Reject <strong id="rejectFileName"></strong>?</p>
                        <input type="hidden" name="file_id" id="rejectFileId">
                        <textarea name="rejection_reason" rows="3" required
                                  placeholder="Reason for rejection..."
                                  class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500 mb-4"></textarea>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('rejectModal')" 
                                    class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">
                                Cancel
                            </button>
                            <button type="submit" name="reject_po" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                <i class="fas fa-times mr-2"></i>Reject
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function approveFile(id, name) {
            document.getElementById('approveFileId').value = id;
            document.getElementById('approveFileName').textContent = name;
            document.getElementById('approveModal').classList.remove('hidden');
        }

        function rejectFile(id, name) {
            document.getElementById('rejectFileId').value = id;
            document.getElementById('rejectFileName').textContent = name;
            document.getElementById('rejectModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal(modal.id);
            });
        });
    </script>
</body>
</html>