<?php
// qr_scanner.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QR Code Scanner - P.O System</title>


    <style>
        * {
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            overflow-x: hidden;
        }
        
        #reader {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            position: relative;
        }
        
        #reader video {
            border-radius: 12px;
            width: 100% !important;
            height: auto !important;
            object-fit: cover;
        }
        
        #reader__scan_region {
            border-radius: 12px !important;
        }
        
        #reader__dashboard_section {
            display: none !important;
        }
        
        .scanner-overlay {
            position: relative;
            min-height: 300px;
        }
        
        .scanner-frame {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(250px, 70vw);
            height: min(250px, 70vw);
            border: 3px solid #22c55e;
            border-radius: 12px;
            pointer-events: none;
            animation: pulse 2s infinite;
            z-index: 10;
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
            z-index: 11;
        }
        
        @keyframes scan {
            0% {
                top: 0;
            }
            100% {
                top: 100%;
            }
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Mobile optimizations */
        @media (max-width: 640px) {
            .container-padding {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            #reader video {
                max-height: 60vh;
            }
        }
        
        /* Smooth transitions */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Header -->
    <div class="bg-transparent">
        <div class="max-w-4xl mx-auto container-padding">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-4 gap-4">
                <div class="flex items-center space-x-3 sm:space-x-4">
                    <div class="bg-green-500 p-2 sm:p-3 rounded-lg shadow-lg">
                        <i class="fas fa-camera text-white text-xl sm:text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">QR Scanner</h1>
                        <p class="text-sm sm:text-base text-gray-600 mt-1">Auto-scanning active</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto container-padding py-4 sm:py-8">
        
        <!-- Scanner Status -->
        <div id="statusAlert" class="mb-4 sm:mb-6 hidden fade-in">
            <div class="bg-blue-50 border-l-4 border-blue-400 p-3 sm:p-4 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2 sm:mr-3"></i>
                    <p class="text-sm sm:text-base text-blue-800 font-medium" id="statusMessage"></p>
                </div>
            </div>
        </div>

        <!-- Scanner Container -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-4 sm:mb-6">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 sm:px-6 py-3 sm:py-4">
                <h2 class="text-lg sm:text-xl font-bold text-white flex items-center">
                    <i class="fas fa-qrcode mr-2 sm:mr-3"></i>
                    <span>Camera Scanner</span>
                    <span id="scanningIndicator" class="ml-auto flex items-center text-sm">
                        <span class="loading-spinner mr-2"></span>
                        Scanning...
                    </span>
                </h2>
            </div>
            
            <div class="p-4 sm:p-6">
                <!-- Instructions - Collapsible on mobile -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                    <h3 class="font-semibold text-sm sm:text-base text-blue-900 mb-2 flex items-center">
                        <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                        Instructions:
                    </h3>
                    <ul class="text-xs sm:text-sm text-blue-800 space-y-1 ml-5 sm:ml-6">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-2 mt-0.5 sm:mt-1"></i>
                            <span>Camera starts automatically</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-2 mt-0.5 sm:mt-1"></i>
                            <span>Point at QR code to scan</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 mr-2 mt-0.5 sm:mt-1"></i>
                            <span>Auto-redirect on detection</span>
                        </li>
                    </ul>
                </div>

                <!-- Scanner Controls -->
                <div class="text-center mb-4 sm:mb-6">
                    <button id="restartBtn" onclick="restartScanner()" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-6 sm:px-8 py-2.5 sm:py-3 rounded-lg transition-all duration-200 inline-flex items-center justify-center space-x-2 shadow-lg text-sm sm:text-base active:scale-95">
                        <i class="fas fa-redo"></i>
                        <span>Restart Scanner</span>
                    </button>
                </div>

                <!-- Scanner Preview -->
                <div id="reader" class="scanner-overlay rounded-lg overflow-hidden">
                    <div class="scanner-frame">
                        <div class="scan-line"></div>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div id="loadingState" class="text-center py-8">
                    <div class="inline-block">
                        <div class="loading-spinner" style="width: 40px; height: 40px; border-width: 4px;"></div>
                    </div>
                    <p class="text-gray-600 mt-4 text-sm sm:text-base">Initializing camera...</p>
                </div>
                
                <!-- Scan Result Display -->
                <div id="scanResult" class="hidden mt-4 sm:mt-6 fade-in">
                    <div class="bg-green-50 border-2 border-green-300 rounded-lg p-3 sm:p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center flex-1">
                                <i class="fas fa-check-circle text-green-600 text-xl sm:text-2xl mr-2 sm:mr-3"></i>
                                <div>
                                    <div class="font-semibold text-sm sm:text-base text-green-900">QR Code Detected!</div>
                                    <div class="text-xs sm:text-sm text-green-700 mt-1">Redirecting...</div>
                                </div>
                            </div>
                            <div class="animate-spin">
                                <i class="fas fa-spinner text-green-600 text-lg sm:text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Scans -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-4 sm:px-6 py-3 sm:py-4">
                <h2 class="text-lg sm:text-xl font-bold text-white flex items-center">
                    <i class="fas fa-history mr-2 sm:mr-3"></i>
                    Recent Scans
                </h2>
            </div>
            
            <div class="p-4 sm:p-6">
                <div id="recentScans" class="space-y-2 sm:space-y-3">
                    <p class="text-gray-500 text-center py-4 text-sm sm:text-base">No recent scans yet</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-4 sm:mt-6 flex flex-col sm:flex-row gap-3 sm:gap-4">
            <a href="receiver_po_list_main.php" 
               class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 shadow-lg text-sm sm:text-base active:scale-95">
                <i class="fas fa-list"></i>
                <span>Show Assigned Items</span>
            </a>
            
            <a href="warehouse_dashboard.php" 
               class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 shadow-lg text-sm sm:text-base active:scale-95">
                <i class="fas fa-warehouse"></i>
                <span>Dashboard</span>
            </a>
        </div>
    </div>

    <script>
        let html5QrCode = null;
        let isScanning = false;
        let recentScans = [];
        let scanCooldown = false;

        function showStatus(message, type = 'info') {
            const statusAlert = document.getElementById('statusAlert');
            const statusMessage = document.getElementById('statusMessage');
            
            statusAlert.classList.remove('hidden', 'bg-blue-50', 'bg-green-50', 'bg-red-50', 'bg-yellow-50');
            statusAlert.classList.add(`bg-${type}-50`, 'fade-in');
            
            const border = statusAlert.querySelector('.border-l-4');
            border.classList.remove('border-blue-400', 'border-green-400', 'border-red-400', 'border-yellow-400');
            border.classList.add(`border-${type}-400`);
            
            statusMessage.textContent = message;
            
            setTimeout(() => {
                statusAlert.classList.add('hidden');
            }, 5000);
        }

        function startScanner() {
            if (isScanning) return;
            
            const loadingState = document.getElementById('loadingState');
            loadingState.style.display = 'block';
            
            html5QrCode = new Html5Qrcode("reader");
            
            // Responsive QR box size
            const isMobile = window.innerWidth < 640;
            const qrBoxSize = isMobile ? Math.min(250, window.innerWidth * 0.7) : 250;
            
            const config = {
                fps: 10,
                qrbox: { width: qrBoxSize, height: qrBoxSize },
                aspectRatio: 1.0,
                formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ]
            };
            
            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanError
            ).then(() => {
                isScanning = true;
                loadingState.style.display = 'none';
                showStatus('Scanner active. Point at QR code.', 'green');
            }).catch(err => {
                console.error('Error starting scanner:', err);
                loadingState.style.display = 'none';
                showStatus('Camera access denied or unavailable', 'red');
                
                // Show error message in reader area
                document.getElementById('reader').innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                        <p class="text-gray-700 font-medium mb-2">Camera Access Required</p>
                        <p class="text-gray-600 text-sm">Please allow camera access to use the scanner</p>
                        <button onclick="location.reload()" class="mt-4 bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg text-sm">
                            <i class="fas fa-redo mr-2"></i>Try Again
                        </button>
                    </div>
                `;
            });
        }

        function stopScanner() {
            if (!html5QrCode || !isScanning) return;
            
            html5QrCode.stop().then(() => {
                isScanning = false;
                document.getElementById('scanResult').classList.add('hidden');
            }).catch(err => {
                console.error('Error stopping scanner:', err);
            });
        }

        function restartScanner() {
            stopScanner();
            setTimeout(() => {
                location.reload();
            }, 300);
        }

        function onScanSuccess(decodedText, decodedResult) {
    if (scanCooldown) return;
    
    console.log(`QR Code detected: ${decodedText}`);
    scanCooldown = true;
    
    // Show scan result
    document.getElementById('scanResult').classList.remove('hidden');
    document.getElementById('scanningIndicator').style.display = 'none';
    
    // Add to recent scans
    addToRecentScans(decodedText);
    
    // Stop scanner
    stopScanner();
    
    // Check if it's a replacement or original item
    let itemId = null;
    let replacementId = null;
    let redirectUrl = null;
    
    // Check if it's a replacement QR code
    if (decodedText.includes('replacement_id=')) {
        const urlParams = new URLSearchParams(decodedText.split('?')[1]);
        replacementId = urlParams.get('replacement_id');
        if (replacementId) {
            redirectUrl = `scan_replacement.php?replacement_id=${replacementId}`;
        }
    }
    // Check if it's an original item QR code
    else if (decodedText.includes('item_id=')) {
        const urlParams = new URLSearchParams(decodedText.split('?')[1]);
        itemId = urlParams.get('item_id');
        if (itemId) {
            redirectUrl = `receiver_scan_item_A1.php?item_id=${itemId}`;
        }
    }
    // Check if it's a direct URL
    else if (decodedText.includes('receiver_scan_replacement_A1.php')) {
        redirectUrl = decodedText;
    }
    else if (decodedText.includes('scan_item.php')) {
        redirectUrl = decodedText;
    }
    
    // Redirect after 1 second
    setTimeout(() => {
        if (redirectUrl) {
            window.location.href = redirectUrl;
        } else {
            showStatus('Invalid QR code format', 'red');
            document.getElementById('scanResult').classList.add('hidden');
            scanCooldown = false;
            startScanner();
        }
    }, 1000);
}

        function onScanError(errorMessage) {
            // Silently ignore scan errors
        }

        function addToRecentScans(qrCode) {
            const timestamp = new Date().toLocaleString();
            recentScans.unshift({ qrCode, timestamp });
            
            if (recentScans.length > 5) {
                recentScans = recentScans.slice(0, 5);
            }
            
            updateRecentScansDisplay();
        }

        function updateRecentScansDisplay() {
            const container = document.getElementById('recentScans');
            
            if (recentScans.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center py-4 text-sm sm:text-base">No recent scans yet</p>';
                return;
            }
            
            container.innerHTML = recentScans.map((scan, index) => `
                <div class="flex items-center justify-between p-2 sm:p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">
                    <div class="flex items-center flex-1 min-w-0">
                        <div class="bg-purple-500 w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                            <span class="text-white font-bold text-xs sm:text-sm">${index + 1}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs sm:text-sm font-medium text-gray-900 truncate">${scan.qrCode.length > 30 ? scan.qrCode.substring(0, 30) + '...' : scan.qrCode}</div>
                            <div class="text-xs text-gray-500">${scan.timestamp}</div>
                        </div>
                    </div>
                    <button onclick="rescanQR('${scan.qrCode.replace(/'/g, "\\'")}')" 
                            class="ml-2 sm:ml-3 bg-blue-500 hover:bg-blue-600 text-white px-2 sm:px-3 py-1 rounded text-xs sm:text-sm transition-colors flex-shrink-0 active:scale-95">
                        <i class="fas fa-redo mr-1"></i><span class="hidden sm:inline">View</span>
                    </button>
                </div>
            `).join('');
        }

        function rescanQR(qrCode) {
    // Check for replacement QR codes first
    if (qrCode.includes('replacement_id=')) {
        const urlParams = new URLSearchParams(qrCode.split('?')[1]);
        const replacementId = urlParams.get('replacement_id');
        if (replacementId) {
            window.location.href = `scan_replacement.php?replacement_id=${replacementId}`;
        }
    }
    // Then check for original item QR codes
    else if (qrCode.includes('item_id=')) {
        const urlParams = new URLSearchParams(qrCode.split('?')[1]);
        const itemId = urlParams.get('item_id');
        if (itemId) {
            window.location.href = `scan_item.php?item_id=${itemId}`;
        }
    }
    // Check for direct URLs
    else if (qrCode.includes('scan_replacement.php')) {
        window.location.href = qrCode;
    }
    else if (qrCode.includes('scan_item.php')) {
        window.location.href = qrCode;
    }
}

        // Auto-start scanner on page load
        window.addEventListener('load', () => {
            setTimeout(startScanner, 500);
        });

        // Handle orientation changes on mobile
        window.addEventListener('orientationchange', () => {
            if (isScanning) {
                restartScanner();
            }
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (html5QrCode && isScanning) {
                html5QrCode.stop();
            }
        });

        // Prevent page refresh on pull-down (mobile)
        let touchStartY = 0;
        document.addEventListener('touchstart', (e) => {
            touchStartY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchmove', (e) => {
            const touchY = e.touches[0].clientY;
            const touchDiff = touchY - touchStartY;
            if (touchDiff > 0 && window.scrollY === 0) {
                e.preventDefault();
            }
        }, { passive: false });
    </script>
</body>
</html>