<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['superadmin']); // allow only admin and superadmin


$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$order_id) die("Invalid order ID");

// Fetch order
$order = $conn->query("SELECT * FROM orders WHERE id = $order_id")->fetch_assoc();
if (!$order) die("Order not found");

// Fetch items
$items = $conn->query("SELECT * FROM order_items WHERE order_id = $order_id")->fetch_all(MYSQLI_ASSOC);

// Fetch unique tracking logs per place (plus color, quantity, descrip6/7)
$tracking_logs = $conn->query("
    SELECT vt.place, vt.status, vt.timestamp,
           vt.variant_color, vt.quantity,
           vt.description1, vt.description2,
           d.name AS driver_name, d.plate_number 
    FROM variant_tracking vt
    LEFT JOIN drivers d ON vt.driver_id = d.id
    WHERE vt.order_id = $order_id
    GROUP BY vt.place
    ORDER BY MIN(vt.id)
")->fetch_all(MYSQLI_ASSOC);

// Define step labels
$default_steps = ['Pending', 'Ongoing', 'Arrival', 'Customs'];
$extra_steps = array_slice(array_column($tracking_logs, 'place'), count($default_steps));
$steps = array_merge($default_steps, $extra_steps);
$total_logs = count($tracking_logs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - #<?= $order_id ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-in': 'slideIn 0.3s ease-out',
                        'pulse-slow': 'pulse 3s infinite',
                        'bounce-slow': 'bounce 2s infinite'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideIn: {
                            '0%': { opacity: '0', transform: 'translateX(-20px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .tracking-line {
            position: relative;
        }
        .tracking-line::before {
            content: '';
            position: absolute;
            left: 24px;
            top: 60px;
            bottom: -20px;
            width: 2px;
            background: linear-gradient(to bottom, #10b981, #d1d5db);
        }
        .tracking-line:last-child::before {
            display: none;
        }
        .step-circle {
            transition: all 0.3s ease;
        }
        .step-circle:hover {
            transform: scale(1.1);
        }
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">

<?php include '../navbar/top.php'; ?>

    <div class="container  px-4 py-8 ">
        <!-- Header -->
        <div class="glass-effect rounded-2xl shadow-xl border border-white/20 p-5 mb-8 animate-fade-in">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold bg-orange-500 bg-clip-text text-transparent">
                        Order Tracking
                    </h1>
                    <p class="text-xs text-gray-600 mt-2">Order #<?= $order_id ?></p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                        <?= count($items) ?> Items
                    </div>
                    <div class="px-4 py-2 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                        <?= $total_logs ?> Checkpoints
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Order Items -->
            <div class="lg:col-span-1">
                <div class="glass-effect rounded-2xl shadow-xl border border-white/20 p-6 animate-slide-in">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                        <span class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center text-white text-sm mr-3">
                          
                        </span>
                        Package Contents
                    </h2>
                    
                    <?php if (empty($items)): ?>
                        <p class="text-gray-500 italic text-center py-8">No items found</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($items as $index => $item): ?>
                                <div class="bg-white/60 backdrop-blur-sm rounded-xl p-4 border border-white/30 hover:shadow-md transition-all duration-300 animate-fade-in" style="animation-delay: <?= $index * 0.1 ?>s">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center text-white font-bold">
                                            <?= $item['quantity'] ?>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-orange-500 mb-1"><?= htmlspecialchars($item['product_name']) ?></h3>
                                            <div class="flex flex-wrap gap-2 mb-2">
                                                <span class="px-2 py-1 text-blue-800 rounded-full text-xs font-medium">Color :
                                                    <?= htmlspecialchars($item['variant_color']) ?>
                                                </span>
                                                <span class="px-2 py-1  text-xs font-medium">
                                                    <?= htmlspecialchars($item['size']) ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($item['descrip6'])): ?>
                                                <p class="text-xs text-gray-600 mb-1">
                                                    <span class="font-medium"></span> <?= htmlspecialchars($item['descrip6']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['descrip7'])): ?>
                                                <p class="text-xs text-gray-600">
                                                    <span class="font-medium"></span> <?= htmlspecialchars($item['descrip7']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tracking Timeline -->
            <div class="lg:col-span-2">
                <div class="glass-effect rounded-2xl shadow-xl border border-white/20 p-6 animate-slide-in">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                        <span class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center text-white text-sm mr-3">
                            
                        </span>
                        Delivery Journey
                    </h2>

                    <?php if (empty($tracking_logs)): ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 bg-orange-500 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse-slow">
                                <span class="text-2xl"></span>
                            </div>
                            <p class="text-gray-500 italic">No tracking data available yet.</p>
                            <p class="text-sm text-gray-400 mt-2">Your package tracking will appear here once processing begins.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-0">
                            <?php foreach ($tracking_logs as $index => $log):
                                $label = $steps[$index] ?? 'Step ' . ($index + 1);
                                $reached = in_array($log['status'], ['reached', 'complete']);
                                $is_complete = ($log['status'] === 'complete');
                                $is_last = ($index === $total_logs - 1);
                                $is_system_step = in_array($log['place'], ['Pending', 'Ongoing', 'Arrival', 'Customs']);
                                $button_label = $is_last ? 'Mark as Complete Delivery' : 'Mark as Reached';
                                
                                // Dynamic colors based on status
                                $circle_color = $is_complete ? 'bg-green-500' : ($reached ? 'bg-blue-500' : 'bg-gray-300');
                                $border_color = $is_complete ? 'border-green-200' : ($reached ? 'border-blue-200' : 'border-gray-200');
                                $bg_color = $is_complete ? 'bg-green-50/80' : ($reached ? 'bg-blue-50/80' : 'bg-gray-50/80');
                            ?>
                                <div class="tracking-line relative animate-fade-in" style="animation-delay: <?= $index * 0.2 ?>s">
                                    <div class="bg-white/60 backdrop-blur-sm rounded-xl p-6 border <?= $border_color ?> <?= $bg_color ?> hover:shadow-lg transition-all duration-300 ml-12">
                                        <!-- Step Circle -->
                                        <div class="absolute -left-6 top-6 w-12 h-12 <?= $circle_color ?> rounded-full flex items-center justify-center text-white font-bold shadow-lg step-circle">
                                            <?php if ($is_complete): ?>
                                                ✓
                                            <?php elseif ($reached): ?>
                                                <?= $index + 1 ?>
                                            <?php else: ?>
                                                <?= $index + 1 ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3 mb-3">
                                                    <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($log['place']) ?></h3>
                                                    <span class="px-3 py-1 bg-white/80 text-gray-600 rounded-full text-sm font-medium">
                                                        <?= $label ?>
                                                    </span>
                                                </div>

                                                <div class="grid md:grid-cols-2 gap-4 mb-4">
                                                    <?php if (!$is_system_step && $log['driver_name']): ?>
                                                        <div class="flex items-center space-x-2">
                                                            <span class="w-5 h-5 bg-blue-100 rounded-full flex items-center justify-center text-xs"></span>
                                                            <span class="text-sm text-gray-700">
                                                                <span class="font-medium">Driver:</span> <?= htmlspecialchars($log['driver_name']) ?>
                                                            </span>
                                                        </div>
                                                        <div class="flex items-center space-x-2">
                                                            <span class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center text-xs"></span>
                                                            <span class="text-sm text-gray-700">
                                                                <span class="font-medium">Plate:</span> <?= htmlspecialchars($log['plate_number']) ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="flex items-center space-x-2">
                                                        <span class="w-5 h-5 bg-orange-500 rounded-full flex items-center justify-center text-xs"></span>
                                                        <span class="text-sm text-gray-700">
                                                            <span class="font-medium">Color:</span> <?= htmlspecialchars($log['variant_color']) ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="flex items-center space-x-2">
                                                        <span class="w-5 h-5 bg-orange-500 rounded-full flex items-center justify-center text-xs"></span>
                                                        <span class="text-sm text-gray-700">
                                                            <span class="font-medium">Qty:</span> <?= htmlspecialchars($log['quantity']) ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <?php if ($log['description1'] || $log['description2']): ?>
                                                    <div class="bg-white/60 rounded-lg p-3 mb-4 border border-white/30">
                                                        <?php if ($log['description1']): ?>
                                                            <p class="text-sm text-gray-600 mb-1">
                                                                <span class="font-medium"></span> <?= htmlspecialchars($log['description1']) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <?php if ($log['description2']): ?>
                                                            <p class="text-sm text-gray-600">
                                                                <span class="font-medium"></span> <?= htmlspecialchars($log['description2']) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($reached): ?>
                                                    <div class="flex items-center space-x-2 mb-3">
                                                        <span class="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center text-white text-xs">✓</span>
                                                        <span class="text-sm font-medium <?= $is_complete ? 'text-green-600' : 'text-blue-600' ?>">
                                                            <?= $is_complete ? 'Completed' : 'Reached' ?> on <?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($log['place'] === 'Arrival'): ?>
                                                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-400 p-4 rounded-r-lg">
                                                        <div class="flex items-start space-x-3">
                                                            <span class="text-2xl animate-bounce-slow">🇵🇭</span>
                                                            <div>
                                                                <p class="font-semibold text-blue-800 mb-1">Package Arrived in Philippines!</p>
                                                                <p class="text-sm text-blue-700">
                                                                    Your item has successfully arrived from China. Typical delivery time is <strong>1–2 months</strong>. Thank you for your patience!
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!$reached): ?>
                                                <form method="POST" action="mark_tracking_status.php" class="ml-4">
                                                    <input type="hidden" name="place" value="<?= htmlspecialchars($log['place']) ?>">
                                                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                                    <input type="hidden" name="complete_delivery" value="<?= $is_last ? '1' : '0' ?>">
                                                    <button type="submit" class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-2 rounded-lg font-medium transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl">
                                                        <?= $button_label ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add smooth scrolling and enhanced interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate elements on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe all tracking items
            document.querySelectorAll('.tracking-line').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });

            // Add button loading state
            document.querySelectorAll('button[type="submit"]').forEach(button => {
                button.addEventListener('click', function() {
                    this.innerHTML = '<span class="inline-block animate-spin mr-2">⟳</span>Processing...';
                    this.disabled = true;
                });
            });
        });
    </script>
</body>
</html>