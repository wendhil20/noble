<?php
// qr_scanner.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get user info
$sessionUser = $_SESSION['noble_user'];
$fullname = is_array($sessionUser) ? 
    ($sessionUser['fullname'] ?? $sessionUser['name'] ?? 'User') : 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Scanner - P.O System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- QR Code Scanner Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74',
                            400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c',
                            800: '#9a3412', 900: '#7c2d12',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        #reader {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }
        
        #reader video {
            border-radius: 12px;
            width: 100% !important;
        }
        
        #reader__scan_region {
            border-radius: 12px !important;
        }
        
        .scanner-overlay {
            position: relative;
        }
        
        .scanner-frame {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 250px;
            height: 250px;
            border: 3px solid #22c55e;
            border-radius: 12px;
            pointer-events: none;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                box-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
            }
            50% {
                opacity: 0.7;
                box-shadow: 0 0 40px rgba(34, 197, 94, 0.8);
            }
        }
        
        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #22c55e, transparent);
            animation: scan 2s linear infinite;
        }
        
        @keyframes scan {
            0% {
                top: 0;
            }
            100% {
                top: 100%;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-transparent">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="bg-green-500 p-3 rounded-lg">
                        <i class="fas fa-camera text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">QR Code Scanner</h1>
                        <p class="text-gray-600 mt-1">Scan item QR codes using your camera</p>
                    </div>
                </div>
                
                <!-- User Info -->
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">
                            <i class="fas fa-user text-primary-600 mr-1"></i>
                            <?php echo htmlspecialchars($fullname); ?>
                        </div>
                    </div>
                    <div class="w-10 h-10 bg-gradient-to-r from-primary-500 to-primary-600 rounded-full flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-sm">
                            <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Scanner Status -->
        <div id="statusAlert" class="mb-6 hidden">
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                    <p class="text-blue-800 font-medium" id="statusMessage"></p>
                </div>
            </div>
        </div>

        <!-- Scanner Container -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-qrcode mr-3"></i>
                    Camera Scanner
                </h2>
            </div>
            
            <div class="p-6">
                <!-- Instructions -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-blue-900 mb-2 flex items-center">
                        <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                        How to use:
                    </h3>
                    <ul class="text-sm text-blue-800 space-y-1 ml-6">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                            <span>Click "Start Scanner" to activate your camera</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                            <span>Point your camera at the QR code</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                            <span>Wait for automatic detection and redirect</span>
                        </li>
                    </ul>
                </div>

                <!-- Scanner Controls -->
                <div class="text-center mb-6">
                    <button id="startBtn" onclick="startScanner()" 
                            class="bg-green-500 hover:bg-green-600 text-white px-8 py-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 mx-auto shadow-lg">
                        <i class="fas fa-play"></i>
                        <span>Start Scanner</span>
                    </button>
                    
                    <button id="stopBtn" onclick="stopScanner()" 
                            class="hidden bg-red-500 hover:bg-red-600 text-white px-8 py-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 mx-auto shadow-lg">
                        <i class="fas fa-stop"></i>
                        <span>Stop Scanner</span>
                    </button>
                </div>

                <!-- Scanner Preview -->
                <div id="reader" class="scanner-overlay"></div>
                
                <!-- Scan Result Display -->
                <div id="scanResult" class="hidden mt-6">
                    <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center flex-1">
                                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                                <div>
                                    <div class="font-semibold text-green-900">QR Code Detected!</div>
                                    <div class="text-sm text-green-700 mt-1">Redirecting to item details...</div>
                                </div>
                            </div>
                            <div class="animate-spin">
                                <i class="fas fa-spinner text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Scans -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-history mr-3"></i>
                    Recent Scans
                </h2>
            </div>
            
            <div class="p-6">
                <div id="recentScans" class="space-y-3">
                    <p class="text-gray-500 text-center py-4">No recent scans yet</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-6 flex flex-col sm:flex-row gap-4">
            <a href="view_po_items.php" 
               class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 shadow-lg">
                <i class="fas fa-list"></i>
                <span>View P.O. Items</span>
            </a>
            
            <a href="warehouse_dashboard.php" 
               class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 shadow-lg">
                <i class="fas fa-warehouse"></i>
                <span>Warehouse Dashboard</span>
            </a>
        </div>
    </div>

    <script>
        let html5QrCode = null;
        let isScanning = false;
        let recentScans = [];

        function showStatus(message, type = 'info') {
            const statusAlert = document.getElementById('statusAlert');
            const statusMessage = document.getElementById('statusMessage');
            
            statusAlert.classList.remove('hidden', 'bg-blue-50', 'bg-green-50', 'bg-red-50', 'bg-yellow-50');
            statusAlert.classList.add(`bg-${type}-50`);
            
            statusAlert.querySelector('.border-l-4').classList.remove('border-blue-400', 'border-green-400', 'border-red-400', 'border-yellow-400');
            statusAlert.querySelector('.border-l-4').classList.add(`border-${type}-400`);
            
            statusMessage.textContent = message;
            
            setTimeout(() => {
                statusAlert.classList.add('hidden');
            }, 5000);
        }

        function startScanner() {
            if (isScanning) return;
            
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');
            
            startBtn.classList.add('hidden');
            stopBtn.classList.remove('hidden');
            
            html5QrCode = new Html5Qrcode("reader");
            
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };
            
            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanError
            ).then(() => {
                isScanning = true;
                showStatus('Scanner is active. Point camera at QR code.', 'green');
            }).catch(err => {
                console.error('Error starting scanner:', err);
                showStatus('Failed to start camera: ' + err, 'red');
                startBtn.classList.remove('hidden');
                stopBtn.classList.add('hidden');
            });
        }

        function stopScanner() {
            if (!html5QrCode || !isScanning) return;
            
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');
            
            html5QrCode.stop().then(() => {
                isScanning = false;
                startBtn.classList.remove('hidden');
                stopBtn.classList.add('hidden');
                showStatus('Scanner stopped', 'yellow');
                document.getElementById('scanResult').classList.add('hidden');
            }).catch(err => {
                console.error('Error stopping scanner:', err);
            });
        }

        function onScanSuccess(decodedText, decodedResult) {
            console.log(`QR Code detected: ${decodedText}`);
            
            // Show scan result
            document.getElementById('scanResult').classList.remove('hidden');
            
            // Add to recent scans
            addToRecentScans(decodedText);
            
            // Stop scanner
            stopScanner();
            
            // Extract item_id from URL or use the full text
            let itemId = null;
            
            // Check if it's a URL with item_id parameter
            if (decodedText.includes('item_id=')) {
                const urlParams = new URLSearchParams(decodedText.split('?')[1]);
                itemId = urlParams.get('item_id');
            }
            
            // Redirect after 1 second
            setTimeout(() => {
                if (itemId) {
                    window.location.href = `scan_item.php?item_id=${itemId}`;
                } else if (decodedText.includes('scan_item.php')) {
                    // If it's already a full URL to scan_item.php
                    window.location.href = decodedText;
                } else {
                    showStatus('Invalid QR code format', 'red');
                    document.getElementById('scanResult').classList.add('hidden');
                    startScanner(); // Restart scanner
                }
            }, 1000);
        }

        function onScanError(errorMessage) {
            // Ignore scan errors (they happen frequently while searching for QR code)
            // console.log(`Scan error: ${errorMessage}`);
        }

        function addToRecentScans(qrCode) {
            const timestamp = new Date().toLocaleString();
            recentScans.unshift({ qrCode, timestamp });
            
            // Keep only last 5 scans
            if (recentScans.length > 5) {
                recentScans = recentScans.slice(0, 5);
            }
            
            updateRecentScansDisplay();
        }

        function updateRecentScansDisplay() {
            const container = document.getElementById('recentScans');
            
            if (recentScans.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center py-4">No recent scans yet</p>';
                return;
            }
            
            container.innerHTML = recentScans.map((scan, index) => `
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">
                    <div class="flex items-center flex-1">
                        <div class="bg-purple-500 w-10 h-10 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white font-bold">${index + 1}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate">${scan.qrCode}</div>
                            <div class="text-xs text-gray-500">${scan.timestamp}</div>
                        </div>
                    </div>
                    <button onclick="rescanQR('${scan.qrCode.replace(/'/g, "\\'")}')" 
                            class="ml-3 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition-colors">
                        <i class="fas fa-redo mr-1"></i>View
                    </button>
                </div>
            `).join('');
        }

        function rescanQR(qrCode) {
            // Extract item_id and redirect
            if (qrCode.includes('item_id=')) {
                const urlParams = new URLSearchParams(qrCode.split('?')[1]);
                const itemId = urlParams.get('item_id');
                if (itemId) {
                    window.location.href = `scan_item.php?item_id=${itemId}`;
                }
            } else if (qrCode.includes('scan_item.php')) {
                window.location.href = qrCode;
            }
        }

        // Auto-start scanner on page load (optional)
        // window.addEventListener('load', () => {
        //     setTimeout(startScanner, 500);
        // });

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (html5QrCode && isScanning) {
                html5QrCode.stop();
            }
        });
    </script>
</body>
</html>