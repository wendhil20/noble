<?php
// view_companies.php - View All Companies with Search
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get user info from session or database
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl']) || !isset($_SESSION['noble_id'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT id, fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_id'] = $id;
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_id'] = null;
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

// Set user variables
$user_id = $_SESSION['noble_id'];
$fullname = $_SESSION['noble_name'];
$user_level = $_SESSION['noble_lvl'];

// Fetch all companies (shared across all sales)
$companies = [];
$stmt = $conn->prepare("SELECT c.id, c.company_name, c.company_address, c.logo_path, c.created_at, c.sales_user_id, n.fullname as added_by FROM companies c LEFT JOIN nobleaccount n ON c.sales_user_id = n.id ORDER BY c.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $companies[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies - Noble Admin</title>
    
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
            <div class="py-3 sm:py-4">
                <!-- Mobile Layout -->
<div class="flex flex-col space-y-3 sm:hidden">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-2 rounded-lg">
                <i class="fas fa-building text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Companies</h1>
                <p class="text-xs text-gray-600">Browse all profiles</p>
            </div>
        </div>
        <div class="w-10 h-10 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                            <span class="text-white font-bold text-sm">
                                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                            </span>
                        </div>
                    </div>
                    <!-- Add Company Button for Mobile -->
    <a href="manage_clients.php" 
        class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-2 rounded-lg transition-all duration-200 shadow-lg flex items-center justify-center space-x-2">
        <i class="fas fa-plus"></i>
        <span>Add Company</span>
    </a>
                </div>

                <!-- Desktop Layout -->
<div class="hidden sm:flex justify-between items-center">
    <div class="flex items-center space-x-4">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-3 rounded-lg">
            <i class="fas fa-building text-white text-2xl"></i>
        </div>
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">List of Companies</h1>
            <p class="text-gray-600 mt-1">Browse and manage company profiles</p>
        </div>
    </div>
    
    <div class="flex items-center space-x-4">
        <!-- Add Company Button -->
        <a href="manage_clients.php" 
            class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center space-x-2">
            <i class="fas fa-plus"></i>
            <span>Add Company</span>
        </a>

                    <div class="text-right">
            <div class="text-sm font-medium text-gray-900">
                <i class="fas fa-user text-primary-600 mr-1"></i>
                <?php echo htmlspecialchars($fullname); ?>
            </div>
            <div class="text-xs text-gray-500">
                <i class="fas fa-shield-alt mr-1"></i>
                <?php echo htmlspecialchars(ucfirst($user_level)); ?>
            </div>
        </div>
        <div class="w-12 h-12 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
            <span class="text-white font-bold text-lg">
                <?php echo strtoupper(substr($fullname, 0, 1)); ?>
            </span>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8 py-4 sm:py-8">
        
        <!-- Companies List -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-gray-600 to-gray-700 px-4 sm:px-6 py-3 sm:py-4">
                <h2 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-list mr-2 sm:mr-3"></i>
                    All Companies (<?php echo count($companies); ?>)
                </h2>
            </div>
            
            <div class="p-4 sm:p-6">
                <!-- Search Bar -->
                <div class="mb-6">
                    <div class="relative">
                        <input type="text" id="searchInput" onkeyup="searchCompanies()" 
                            placeholder="Search by company name..."
                            class="w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
                    </div>
                </div>
                
                <?php if (!empty($companies)): ?>
                    <div id="companiesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($companies as $company): ?>
                            <div class="company-card bg-white border-2 border-gray-200 rounded-lg p-4 hover:shadow-lg transition-shadow" data-company-name="<?php echo htmlspecialchars($company['company_name']); ?>">
                                <!-- Logo -->
                                <div class="flex items-center justify-center mb-4">
                                    <?php if (!empty($company['logo_path']) && file_exists($company['logo_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($company['logo_path']); ?>" 
                                            alt="<?php echo htmlspecialchars($company['company_name']); ?>"
                                            class="h-20 w-20 object-contain rounded-lg border border-gray-200">
                                    <?php else: ?>
                                        <div class="h-20 w-20 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-building text-blue-600 text-3xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Company Name -->
                                <h3 class="text-lg font-bold text-gray-900 text-center mb-2">
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                </h3>

                                <!-- Address -->
                                <div class="bg-gray-50 rounded-lg p-3 mb-3">
                                    <div class="flex items-start space-x-2">
                                        <i class="fas fa-map-marker-alt text-gray-600 mt-1"></i>
                                        <p class="text-sm text-gray-700">
                                            <?php echo nl2br(htmlspecialchars($company['company_address'])); ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Purchase Order Button -->
                                <a href="purchase_orders.php?company_id=<?php echo $company['id']; ?>" 
                                    class="block w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white text-center px-4 py-2 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-md mb-3">
                                    <i class="fas fa-file-invoice mr-2"></i>Purchase Orders
                                </a>

                                <!-- Added Date -->
                                <div class="text-xs text-gray-500 text-center">
                                    <i class="fas fa-calendar mr-1"></i>
                                    Added on <?php echo date('M j, Y', strtotime($company['created_at'])); ?>
                                </div>
                                
                                <!-- Added By -->
                                <div class="text-xs text-gray-400 text-center mt-1">
                                    <i class="fas fa-user mr-1"></i>
                                    By <?php echo htmlspecialchars($company['added_by'] ?? 'Unknown'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- No Results Message -->
                    <div id="noResults" class="hidden text-center py-12 text-gray-500">
                        <i class="fas fa-search text-5xl mb-4 text-gray-300"></i>
                        <p class="text-lg font-medium">No companies found</p>
                        <p class="text-sm">Try a different search term</p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-building text-5xl mb-4 text-gray-300"></i>
                        <p class="text-lg font-medium">No companies yet</p>
                        <p class="text-sm">Add your first company profile</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function searchCompanies() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const cards = document.getElementsByClassName('company-card');
            const noResults = document.getElementById('noResults');
            let visibleCount = 0;
            
            for (let i = 0; i < cards.length; i++) {
                const companyName = cards[i].getAttribute('data-company-name').toLowerCase();
                
                if (companyName.includes(filter)) {
                    cards[i].style.display = '';
                    visibleCount++;
                } else {
                    cards[i].style.display = 'none';
                }
            }
            
            // Show/hide no results message
            if (visibleCount === 0 && filter !== '') {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }
    </script>
</body>
</html>