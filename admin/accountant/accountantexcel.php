<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['accountant']);

$tables = ['accountantrecord'];

foreach ($tables as $table) {
    // Get the current highest ID that exists
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];

    // Reset AUTO_INCREMENT to max_id + 1
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

// Handle DELETE request
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // Get record details first for confirmation
    $check_stmt = $conn->prepare("SELECT project_name, amount FROM accountantrecord WHERE id = ?");
    $check_stmt->bind_param("i", $delete_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM accountantrecord WHERE id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $message = "Record deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting record: " . $delete_stmt->error;
            $message_type = "error";
        }
        $delete_stmt->close();
    } else {
        $message = "Record not found!";
        $message_type = "error";
    }
    $check_stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['filter'])) {
    $project_name = $_POST['project_name'];
    $date = $_POST['date'];
    $particular = $_POST['particular'];
    $amount = $_POST['amount'];
    $forms = $_POST['forms'];
    
    $stmt = $conn->prepare("INSERT INTO accountantrecord (project_name, date, particular, amount, forms) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssds", $project_name, $date, $particular, $amount, $forms);
    
    if ($stmt->execute()) {
        $message = "Record inserted successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Build query with filters
$sql = "SELECT * FROM accountantrecord WHERE 1=1";
$params = [];
$types = "";

// Get filter values
$filter_project = isset($_GET['project']) ? $_GET['project'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_forms = isset($_GET['forms']) ? $_GET['forms'] : '';

// Apply filters
if (!empty($filter_project)) {
    $sql .= " AND project_name LIKE ?";
    $params[] = "%" . $filter_project . "%";
    $types .= "s";
}

if (!empty($filter_date_from)) {
    $sql .= " AND date >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $sql .= " AND date <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

if (!empty($filter_forms)) {
    $sql .= " AND forms = ?";
    $params[] = $filter_forms;
    $types .= "s";
}

$sql .= " ORDER BY date DESC LIMIT 50";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $records = $stmt->get_result();
} else {
    $records = $conn->query($sql);
}

// Get all unique project names for dropdown
$projects_sql = "SELECT DISTINCT project_name FROM accountantrecord ORDER BY project_name";
$projects_result = $conn->query($projects_sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Records</title>
    <style>
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert {
            animation: slideIn 0.3s ease-out;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            animation: slideIn 0.3s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../navbar/top.php'; ?>
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-file-invoice-dollar text-green-600 mr-2"></i>
                        Accountant Records
                    </h1>
                    <p class="text-gray-600 mt-2">Manage and export financial records</p>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border text-red-700'; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Add Record Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>
                        Add New Record
                    </h2>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-project-diagram text-gray-500 mr-1"></i>
                                Project Name
                            </label>
                            <input type="text" name="project_name" required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar text-gray-500 mr-1"></i>
                                Date
                            </label>
                            <input type="date" name="date" required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left text-gray-500 mr-1"></i>
                                Particular
                            </label>
                            <textarea name="particular" rows="3" required 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-peso-sign text-gray-500 mr-1"></i>
                                Amount
                            </label>
                            <input type="number" step="0.01" name="amount" required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tags text-gray-500 mr-1"></i>
                                Type
                            </label>
                            <select name="forms" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                                <option value="">Select Type</option>
                                <option value="Expense">Expense</option>
                                <option value="Sale">Sale</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors duration-200 shadow-md hover:shadow-lg">
                            <i class="fas fa-save mr-2"></i>
                            Save Record
                        </button>
                    </form>
                </div>
            </div>

            <!-- Records Table -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <!-- Filter Section -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-700">
                                <i class="fas fa-filter mr-2"></i>Filters
                            </h3>
                            <button onclick="clearFilters()" class="text-sm text-red-600 hover:text-red-700">
                                <i class="fas fa-times mr-1"></i>Clear All
                            </button>
                        </div>
                        
                        <form method="GET" id="filterForm" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Project Name</label>
                                <select name="project" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                    <option value="">All Projects</option>
                                    <?php while ($proj = $projects_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($proj['project_name']); ?>" 
                                                <?php echo $filter_project === $proj['project_name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($proj['project_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                                <select name="forms" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                    <option value="">All Types</option>
                                    <option value="Expense" <?php echo $filter_forms === 'Expense' ? 'selected' : ''; ?>>Expense</option>
                                    <option value="Sale" <?php echo $filter_forms === 'Sale' ? 'selected' : ''; ?>>Sale</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Date From</label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Date To</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="md:col-span-2 flex gap-2">
                                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                    <i class="fas fa-search mr-2"></i>Apply Filters
                                </button>
                                <button type="button" onclick="exportToExcel()" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                    <i class="fas fa-file-excel mr-2"></i>Export Filtered Data
                                </button>
                            </div>
                        </form>
                    </div>

                    <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">
                        <i class="fas fa-list text-purple-600 mr-2"></i>
                        Records 
                        <?php if ($records): ?>
                            <span class="text-sm text-gray-500">(<?php echo $records->num_rows; ?> results)</span>
                        <?php endif; ?>
                    </h2>

                    <?php if ($records && $records->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-100 text-gray-700">
                                        <th class="px-4 py-3 text-left text-sm font-semibold">Project</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold">Date</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold">Particular</th>
                                        <th class="px-4 py-3 text-right text-sm font-semibold">Amount</th>
                                        <th class="px-4 py-3 text-center text-sm font-semibold">Type</th>
                                        <th class="px-4 py-3 text-center text-sm font-semibold">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php while ($row = $records->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($row['project_name']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                <?php echo date('M d, Y', strtotime($row['date'])); ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">
                                                <?php echo htmlspecialchars(substr($row['particular'], 0, 50)) . (strlen($row['particular']) > 50 ? '...' : ''); ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-semibold text-right <?php echo $row['forms'] === 'Expense' ? 'text-red-600' : 'text-green-600'; ?>">
                                                ₱<?php echo number_format($row['amount'], 2); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $row['forms'] === 'Expense' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo htmlspecialchars($row['forms']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['project_name'], ENT_QUOTES); ?>', <?php echo $row['amount']; ?>)" 
                                                        class="text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded transition-colors">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
                            <p class="text-gray-500 text-lg">No records found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Record</h3>
                <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete this record? This action cannot be undone.</p>
                
                <div class="bg-gray-50 p-3 rounded-lg mb-4 text-left">
                    <p class="text-sm"><strong>Project:</strong> <span id="deleteProject"></span></p>
                    <p class="text-sm"><strong>Amount:</strong> <span id="deleteAmount"></span></p>
                </div>

                <div class="flex gap-3">
                    <button onclick="closeDeleteModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button onclick="executeDelete()" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let deleteId = null;

        function confirmDelete(id, project, amount) {
            deleteId = id;
            document.getElementById('deleteProject').textContent = project;
            document.getElementById('deleteAmount').textContent = '₱' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            deleteId = null;
        }

        function executeDelete() {
            if (deleteId) {
                const params = new URLSearchParams(window.location.search);
                params.set('delete_id', deleteId);
                window.location.href = window.location.pathname + '?' + params.toString();
            }
        }

        function exportToExcel() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = 'accountantexport_excel.php?' + params.toString();
        }

        function clearFilters() {
            window.location.href = window.location.pathname;
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>