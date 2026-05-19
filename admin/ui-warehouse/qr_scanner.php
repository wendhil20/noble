<?php
// qr_scanner.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse', 'logistic']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}

$sessionUser = $_SESSION['noble_user'];
$fullname = is_array($sessionUser) ? ($sessionUser['fullname'] ?? $sessionUser['name'] ?? 'User') : 'User';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QR Scanner — P.O. System</title>
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

        .scanner-frame {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(220px, 65vw);
            height: min(220px, 65vw);
            border: 2.5px solid #22c55e;
            border-radius: 12px;
            pointer-events: none;
            z-index: 10;
            animation: framePulse 2s infinite;
        }

        @keyframes framePulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(34, 197, 94, 0);
            }
        }

        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #22c55e, transparent);
            animation: scanMove 2s linear infinite;
            z-index: 11;
        }

        @keyframes scanMove {
            0% {
                top: 0;
            }

            100% {
                top: 100%;
            }
        }

        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2.5px solid rgba(255, 255, 255, .3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s linear infinite;
        }

        .spinner-lg {
            width: 36px;
            height: 36px;
            border-width: 3px;
            border-color: rgba(99, 102, 241, .2);
            border-top-color: #6366f1;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .fade-in {
            animation: fadeIn .3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- ── Header ───────────────────────────────────────────────── -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 py-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-green-500 flex items-center justify-center shrink-0">
                    <i class="fas fa-camera text-white text-sm"></i>
                </div>
                <div>
                    <h1 class="text-base font-semibold text-gray-900 leading-tight">QR Scanner</h1>
                    <p class="text-xs text-gray-400 mt-0.5">Auto-scanning active</p>
                </div>
                <div id="scanningIndicator"
                    class="ml-auto inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-green-50 border border-green-200 text-xs font-medium text-green-700">
                    <span class="spinner"></span>
                    Scanning
                </div>
            </div>
        </div>
    </div>

    <!-- ── Main ─────────────────────────────────────────────────── -->
    <div class="max-w-2xl mx-auto px-4 sm:px-6 py-5 space-y-4">

        <!-- Status Alert -->
        <div id="statusAlert" class="hidden fade-in">
            <div id="statusInner" class="flex items-center gap-2.5 px-4 py-3 rounded-xl border text-sm font-medium">
                <i id="statusIcon" class="fas fa-info-circle"></i>
                <span id="statusMessage"></span>
            </div>
        </div>

        <!-- Scanner Card -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

            <!-- Card Header -->
            <div class="flex items-center justify-between px-4 py-3 bg-green-600">
                <div class="flex items-center gap-2">
                    <i class="fas fa-qrcode text-white text-sm"></i>
                    <span class="text-sm font-semibold text-white">Camera Scanner</span>
                </div>
                <button onclick="restartScanner()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white text-xs font-medium rounded-lg transition-colors">
                    <i class="fas fa-redo text-[10px]"></i> Restart
                </button>
            </div>

            <div class="p-4">

                <!-- Instructions -->
                <div class="flex items-start gap-3 bg-blue-50 border border-blue-100 rounded-xl px-3 py-2.5 mb-4">
                    <i class="fas fa-lightbulb text-yellow-500 text-sm mt-0.5 shrink-0"></i>
                    <div class="text-xs text-blue-800 space-y-0.5">
                        <p><i class="fas fa-check text-green-600 mr-1"></i>Camera starts automatically</p>
                        <p><i class="fas fa-check text-green-600 mr-1"></i>Point at QR code to scan</p>
                        <p><i class="fas fa-check text-green-600 mr-1"></i>Auto-redirects on detection</p>
                    </div>
                </div>

                <!-- Scanner Viewport -->
                <div id="reader" class="relative rounded-xl overflow-hidden bg-gray-900 min-h-[260px]">
                    <div class="scanner-frame">
                        <div class="scan-line"></div>
                    </div>
                </div>

                <!-- Loading State -->
                <div id="loadingState" class="text-center py-6">
                    <div class="spinner spinner-lg mx-auto"></div>
                    <p class="text-xs text-gray-500 mt-3">Initializing camera...</p>
                </div>

                <!-- Scan Success -->
                <div id="scanResult" class="hidden mt-4 fade-in">
                    <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                        <i class="fas fa-check-circle text-green-500 text-lg shrink-0"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-green-900">QR Code Detected!</p>
                            <p class="text-xs text-green-700">Redirecting you now...</p>
                        </div>
                        <i class="fas fa-spinner animate-spin text-green-500 shrink-0"></i>
                    </div>
                </div>

            </div>
        </div>

        <!-- Recent Scans -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 bg-violet-600">
                <i class="fas fa-history text-white text-sm"></i>
                <span class="text-sm font-semibold text-white">Recent Scans</span>
            </div>
            <div class="p-4">
                <div id="recentScans">
                    <p class="text-xs text-gray-400 text-center py-4">No recent scans yet</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="<?= BASE_URL ?>/receiverlistmain"
                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition-colors">
                <i class="fas fa-list text-xs"></i> Show Assigned Items
            </a>
        </div>

    </div><!-- end max-w-2xl -->

    <script>
        let html5QrCode = null;
        let isScanning = false;
        let recentScans = [];
        let scanCooldown = false;

        // ── Status ────────────────────────────────────────────────
        function showStatus(message, type = 'info') {
            const wrap = document.getElementById('statusAlert');
            const inner = document.getElementById('statusInner');
            const icon = document.getElementById('statusIcon');
            const msg = document.getElementById('statusMessage');

            const styles = {
                info: { wrap: 'bg-blue-50 border-blue-200 text-blue-800', icon: 'fas fa-info-circle text-blue-500' },
                green: { wrap: 'bg-green-50 border-green-200 text-green-800', icon: 'fas fa-check-circle text-green-500' },
                red: { wrap: 'bg-red-50 border-red-200 text-red-800', icon: 'fas fa-exclamation-circle text-red-500' },
                yellow: { wrap: 'bg-yellow-50 border-yellow-200 text-yellow-800', icon: 'fas fa-exclamation-triangle text-yellow-500' },
            };
            const s = styles[type] || styles.info;

            inner.className = `flex items-center gap-2.5 px-4 py-3 rounded-xl border text-sm font-medium ${s.wrap}`;
            icon.className = s.icon;
            msg.textContent = message;

            wrap.classList.remove('hidden');
            wrap.classList.add('fade-in');

            setTimeout(() => wrap.classList.add('hidden'), 5000);
        }

        // ── Scanner ───────────────────────────────────────────────
        function startScanner() {
            if (isScanning) return;

            document.getElementById('loadingState').style.display = 'block';
            html5QrCode = new Html5Qrcode("reader");

            const qrBoxSize = Math.min(220, window.innerWidth * 0.65);

            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: qrBoxSize, height: qrBoxSize }, aspectRatio: 1.0, formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE] },
                onScanSuccess,
                () => { }
            ).then(() => {
                isScanning = true;
                document.getElementById('loadingState').style.display = 'none';
                showStatus('Scanner active. Point at a QR code.', 'green');
            }).catch(err => {
                console.error(err);
                document.getElementById('loadingState').style.display = 'none';
                showStatus('Camera access denied or unavailable.', 'red');
                document.getElementById('reader').innerHTML = `
                <div class="flex flex-col items-center justify-center py-10 text-center px-4">
                    <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center mb-3">
                        <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 mb-1">Camera Access Required</p>
                    <p class="text-xs text-gray-500 mb-4">Please allow camera access to use the scanner.</p>
                    <button onclick="location.reload()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                        <i class="fas fa-redo"></i> Try Again
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
            }).catch(console.error);
        }

        function restartScanner() {
            stopScanner();
            setTimeout(() => location.reload(), 300);
        }

        // ── On Scan ───────────────────────────────────────────────
        function onScanSuccess(decodedText) {
            if (scanCooldown) return;
            scanCooldown = true;

            document.getElementById('scanResult').classList.remove('hidden');
            document.getElementById('scanningIndicator').style.display = 'none';

            addToRecentScans(decodedText);
            stopScanner();

            let redirectUrl = null;

            if (decodedText.includes('replacement_id=')) {
                const p = new URLSearchParams(decodedText.split('?')[1]);
                const id = p.get('replacement_id');
                if (id) redirectUrl = `scan_replacement.php?replacement_id=${id}`;
            } else if (decodedText.includes('item_id=')) {
                const p = new URLSearchParams(decodedText.split('?')[1]);
                const id = p.get('item_id');
                if (id) redirectUrl = `receiver_scan_item_A1.php?item_id=${id}`;
            } else if (decodedText.includes('receiver_scan_replacement_A1.php') || decodedText.includes('scan_item.php')) {
                redirectUrl = decodedText;
            }

            setTimeout(() => {
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                } else {
                    showStatus('Invalid QR code format.', 'red');
                    document.getElementById('scanResult').classList.add('hidden');
                    scanCooldown = false;
                    startScanner();
                }
            }, 1000);
        }

        // ── Recent Scans ──────────────────────────────────────────
        function addToRecentScans(qrCode) {
            recentScans.unshift({ qrCode, timestamp: new Date().toLocaleString() });
            if (recentScans.length > 5) recentScans = recentScans.slice(0, 5);
            updateRecentScansDisplay();
        }

        function updateRecentScansDisplay() {
            const container = document.getElementById('recentScans');

            if (!recentScans.length) {
                container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No recent scans yet</p>';
                return;
            }

            container.innerHTML = recentScans.map((scan, i) => `
            <div class="flex items-center gap-3 px-3 py-2.5 bg-gray-50 border border-gray-100 rounded-xl hover:bg-gray-100 transition-colors mb-2">
                <div class="w-7 h-7 rounded-full bg-violet-500 flex items-center justify-center shrink-0">
                    <span class="text-white text-xs font-bold">${i + 1}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-900 truncate">${scan.qrCode.length > 35 ? scan.qrCode.substring(0, 35) + '…' : scan.qrCode}</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">${scan.timestamp}</p>
                </div>
                <button onclick="rescanQR('${scan.qrCode.replace(/'/g, "\\'")}')"
                    class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-medium rounded-lg transition-colors">
                    <i class="fas fa-redo text-[8px]"></i> View
                </button>
            </div>
        `).join('');
        }

        function rescanQR(qrCode) {
            if (qrCode.includes('replacement_id=')) {
                const p = new URLSearchParams(qrCode.split('?')[1]);
                const id = p.get('replacement_id');
                if (id) window.location.href = `scan_replacement.php?replacement_id=${id}`;
            } else if (qrCode.includes('item_id=')) {
                const p = new URLSearchParams(qrCode.split('?')[1]);
                const id = p.get('item_id');
                if (id) window.location.href = `scan_item.php?item_id=${id}`;
            } else if (qrCode.includes('scan_replacement.php') || qrCode.includes('scan_item.php')) {
                window.location.href = qrCode;
            }
        }

        // ── Init ──────────────────────────────────────────────────
        window.addEventListener('load', () => setTimeout(startScanner, 500));
        window.addEventListener('orientationchange', () => { if (isScanning) restartScanner(); });
        window.addEventListener('beforeunload', () => { if (html5QrCode && isScanning) html5QrCode.stop(); });

        // Prevent pull-to-refresh on mobile
        let touchStartY = 0;
        document.addEventListener('touchstart', e => { touchStartY = e.touches[0].clientY; }, { passive: true });
        document.addEventListener('touchmove', e => {
            if (e.touches[0].clientY - touchStartY > 0 && window.scrollY === 0) e.preventDefault();
        }, { passive: false });
    </script>
</body>

</html>