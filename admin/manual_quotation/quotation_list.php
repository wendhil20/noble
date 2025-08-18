<?php
// quotations_list.php
session_name("nobleadmin");
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

// Set noble_name and noble_lvl from DB if not already set
if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

$_SESSION['last_activity'] = time();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'get_quotations') {
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $search = isset($_POST['search']) ? '%' . $_POST['search'] . '%' : '%%';
            
            // Get total count
            $count_stmt = $conn->prepare("SELECT COUNT(*) FROM quotations WHERE quotation_for LIKE ? OR quotation_no LIKE ?");
            $count_stmt->bind_param("ss", $search, $search);
            $count_stmt->execute();
            $count_stmt->bind_result($total);
            $count_stmt->fetch();
            $count_stmt->close();
            
            // Get quotations
            $stmt = $conn->prepare("
                SELECT id, quotation_no, quotation_for, quotation_date, contact_person, 
                       grand_total, status, created_at 
                FROM quotations 
                WHERE quotation_for LIKE ? OR quotation_no LIKE ?
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("ssii", $search, $search, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $quotations = [];
            while ($row = $result->fetch_assoc()) {
                $quotations[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'data' => $quotations,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ]);
            exit();
        }
        
        if ($_POST['action'] === 'delete_quotation') {
            $quotation_id = (int)$_POST['quotation_id'];
            
            $stmt = $conn->prepare("DELETE FROM quotations WHERE id = ?");
            $stmt->bind_param("i", $quotation_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Quotation deleted successfully']);
            } else {
                throw new Exception('Failed to delete quotation');
            }
            $stmt->close();
            exit();
        }
        
        if ($_POST['action'] === 'update_status') {
            $quotation_id = (int)$_POST['quotation_id'];
            $status = $_POST['status'];
            
            $allowed_statuses = ['draft', 'sent', 'approved', 'cancelled'];
            if (!in_array($status, $allowed_statuses)) {
                throw new Exception('Invalid status');
            }
            
            $stmt = $conn->prepare("UPDATE quotations SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $quotation_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                throw new Exception('Failed to update status');
            }
            $stmt->close();
            exit();
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotations List</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Quotations List</h1>
                    <p class="text-gray-600">Manage all your quotations</p>
                </div>
                <a href="orders.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition duration-200">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    New Quotation
                </a>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-4 items-center">
                <div class="flex-1">
                    <input type="text" id="searchInput" placeholder="Search by quotation number or client name..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <button id="searchBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Search
                </button>
            </div>
        </div>

        <!-- Quotations Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Quotation No.</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Client</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Date</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Contact Person</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Total</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="quotationsTableBody" class="divide-y divide-gray-200">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> results
                </div>
                <div class="flex space-x-2" id="paginationControls">
                    <!-- Pagination buttons will be added here -->
                </div>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
            <div class="bg-white p-6 rounded-lg">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                <p class="mt-4 text-gray-700">Loading...</p>
            </div>
        </div>

        <!-- Status Update Modal -->
        <div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
            <div class="bg-white p-6 rounded-lg w-96">
                <h3 class="text-lg font-bold mb-4">Update Status</h3>
                <select id="statusSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-4">
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="approved">Approved</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <div class="flex justify-end space-x-4">
                    <button id="cancelStatusBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">Cancel</button>
                    <button id="updateStatusBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">Update</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let currentPage = 1;
            let currentQuotationId = null;

            // Load quotations on page load
            loadQuotations();

            // Search functionality
            $('#searchBtn').on('click', function() {
                currentPage = 1;
                loadQuotations();
            });

            $('#searchInput').on('keypress', function(e) {
                if (e.which === 13) {
                    currentPage = 1;
                    loadQuotations();
                }
            });

            function loadQuotations() {
                $('#loadingSpinner').removeClass('hidden');
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'get_quotations',
                        page: currentPage,
                        search: $('#searchInput').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        $('#loadingSpinner').addClass('hidden');
                        
                        if (response.success) {
                            displayQuotations(response.data);
                            updatePagination(response);
                        } else {
                            alert('Error loading quotations: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#loadingSpinner').addClass('hidden');
                        alert('Error: ' + error);
                    }
                });
            }

            function displayQuotations(quotations) {
                const tbody = $('#quotationsTableBody');
                tbody.empty();

                if (quotations.length === 0) {
                    tbody.append(`
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                No quotations found
                            </td>
                        </tr>
                    `);
                    return;
                }

                quotations.forEach(function(quotation) {
                    const statusColor = getStatusColor(quotation.status);
                    const formattedDate = new Date(quotation.quotation_date).toLocaleDateString();
                    const formattedTotal = '₱' + parseFloat(quotation.grand_total).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });

                    const row = `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">${quotation.quotation_no}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${quotation.quotation_for}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${formattedDate}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">${quotation.contact_person}</td>
                            <td class="px-6 py-4 text-sm text-gray-700 font-medium">${formattedTotal}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusColor}">
                                    ${quotation.status.charAt(0).toUpperCase() + quotation.status.slice(1)}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex space-x-2">
                                    <button class="view-btn text-blue-600 hover:text-blue-800" data-id="${quotation.id}">View</button>
                                    <button class="status-btn text-green-600 hover:text-green-800" data-id="${quotation.id}" data-status="${quotation.status}">Status</button>
                                    <button class="delete-btn text-red-600 hover:text-red-800" data-id="${quotation.id}">Delete</button>
                                </div>
                            </td>
                        </tr>
                    `;
                    tbody.append(row);
                });

                // Bind events
                bindTableEvents();
            }

            function getStatusColor(status) {
                const colors = {
                    'draft': 'bg-gray-100 text-gray-800',
                    'sent': 'bg-blue-100 text-blue-800',
                    'approved': 'bg-green-100 text-green-800',
                    'cancelled': 'bg-red-100 text-red-800'
                };
                return colors[status] || 'bg-gray-100 text-gray-800';
            }

            function bindTableEvents() {
                $('.view-btn').on('click', function() {
                    const quotationId = $(this).data('id');
                    window.open(`view_quotation.php?id=${quotationId}`, '_blank');
                });

                $('.status-btn').on('click', function() {
                    currentQuotationId = $(this).data('id');
                    const currentStatus = $(this).data('status');
                    $('#statusSelect').val(currentStatus);
                    $('#statusModal').removeClass('hidden');
                });

                $('.delete-btn').on('click', function() {
                    const quotationId = $(this).data('id');
                    if (confirm('Are you sure you want to delete this quotation?')) {
                        deleteQuotation(quotationId);
                    }
                });
            }

            function updatePagination(response) {
                const { page, total_pages, total } = response;
                const limit = 10;
                const from = ((page - 1) * limit) + 1;
                const to = Math.min(page * limit, total);

                $('#showingFrom').text(from);
                $('#showingTo').text(to);
                $('#totalRecords').text(total);

                const paginationControls = $('#paginationControls');
                paginationControls.empty();

                if (total_pages > 1) {
                    // Previous button
                    if (page > 1) {
                        paginationControls.append(`
                            <button class="pagination-btn px-3 py-1 bg-white border border-gray-300 rounded-lg hover:bg-gray-50" data-page="${page - 1}">
                                Previous
                            </button>
                        `);
                    }

                    // Page numbers
                    for (let i = 1; i <= total_pages; i++) {
                        const activeClass = i === page ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50';
                        paginationControls.append(`
                            <button class="pagination-btn px-3 py-1 border border-gray-300 rounded-lg ${activeClass}" data-page="${i}">
                                ${i}
                            </button>
                        `);
                    }

                    // Next button
                    if (page < total_pages) {
                        paginationControls.append(`
                            <button class="pagination-btn px-3 py-1 bg-white border border-gray-300 rounded-lg hover:bg-gray-50" data-page="${page + 1}">
                                Next
                            </button>
                        `);
                    }

                    // Bind pagination events
                    $('.pagination-btn').on('click', function() {
                        currentPage = parseInt($(this).data('page'));
                        loadQuotations();
                    });
                }
            }

            // Modal events
            $('#cancelStatusBtn').on('click', function() {
                $('#statusModal').addClass('hidden');
            });

            $('#updateStatusBtn').on('click', function() {
                const newStatus = $('#statusSelect').val();
                updateQuotationStatus(currentQuotationId, newStatus);
            });

            function updateQuotationStatus(quotationId, status) {
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'update_status',
                        quotation_id: quotationId,
                        status: status
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#statusModal').addClass('hidden');
                            loadQuotations();
                            alert('Status updated successfully!');
                        } else {
                            alert('Error updating status: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error: ' + error);
                    }
                });
            }

            function deleteQuotation(quotationId) {
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        action: 'delete_quotation',
                        quotation_id: quotationId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadQuotations();
                            alert('Quotation deleted successfully!');
                        } else {
                            alert('Error deleting quotation: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error: ' + error);
                    }
                });
            }
        });
    </script>
</body>
</html>