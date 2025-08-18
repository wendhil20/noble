<?php
session_start();
include '../../connection/connect.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Client Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-slow': 'pulse 3s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': {
                                opacity: '0',
                                transform: 'translateY(10px)'
                            },
                            '100%': {
                                opacity: '1',
                                transform: 'translateY(0)'
                            }
                        },
                        slideUp: {
                            '0%': {
                                transform: 'translateY(10px)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'translateY(0)',
                                opacity: '1'
                            }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            backdrop-filter: blur(16px) saturate(180%);
            background-color: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(209, 213, 219, 0.3);
        }

        .gradient-bg {
            background: linear-gradient(135deg, rgb(213, 110, 14) 0%, rgb(201, 114, 14) 100%);
        }

        .card-shadow {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .input-glow:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 0 20px rgba(59, 130, 246, 0.2);
        }

        .btn-gradient {
            background: linear-gradient(135deg, rgb(214, 116, 10) 0%, rgb(223, 170, 25) 100%);
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, rgb(218, 162, 9) 0%, rgb(205, 141, 13) 100%);
            transform: translateY(-2px);
        }

        .table-row:hover {
            transform: scale(1.002);
            transition: all 0.2s ease;
        }
    </style>
</head>

<body class=" bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">


    <?php include '../navbar/top.php'; ?>

    <!-- Floating Particles Background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-10 left-10 w-4 h-4 bg-blue-200 rounded-full opacity-30 animate-pulse-slow"></div>
        <div class="absolute top-32 right-20 w-6 h-6 bg-purple-200 rounded-full opacity-20 animate-pulse-slow" style="animation-delay: 1s;"></div>
        <div class="absolute bottom-20 left-1/4 w-8 h-8 bg-indigo-200 rounded-full opacity-25 animate-pulse-slow" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-40 right-1/3 w-5 h-5 bg-blue-300 rounded-full opacity-30 animate-pulse-slow" style="animation-delay: 0.5s;"></div>
    </div>

    <div class="relative z-10 mt-8 px-4 sm:px-3 lg:px-5">
        <!-- Enhanced Header with Gradient -->
        <div class="glass-effect shadow-2xl border border-white/20 mb-5 overflow-hidden animate-fade-in">
            <div class="gradient-bg px-4 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-white mb-2 tracking-tight">Client Management</h1>
                        <p class="text-blue-100 text-md font-medium">Manage your client database with ease</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm p-4 rounded-2xl border border-white/30">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-3">
            <!-- Enhanced Form Container -->
            <div class="xl:col-span-2">
                <div class="glass-effect card-shadow border border-white/20 overflow-hidden animate-slide-up">
                    <div class="bg-gradient-to-r from-orange-400 to-orange-500 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add New Client
                        </h2>
                    </div>

                    <form action="submit_form.php" method="POST" class="p-8 space-y-6">

                        <!-- Full Name -->
                        <div class="group">
                            <label for="name" class="block text-sm font-semibold text-gray-700 mb-3 group-hover:text-blue-600 transition-colors">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Full Name *
                                </span>
                            </label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                required
                                class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 input-glow bg-white/80 backdrop-blur-sm hover:bg-white"
                                placeholder="Enter client's full name">
                        </div>

                        <!-- Address -->
                        <div class="group">
                            <label for="address" class="block text-sm font-semibold text-gray-700 mb-3 group-hover:text-blue-600 transition-colors">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Address *
                                </span>
                            </label>
                            <input
                                type="text"
                                id="address"
                                name="address"
                                required
                                class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 input-glow bg-white/80 backdrop-blur-sm hover:bg-white"
                                placeholder="Enter complete address">
                        </div>

                        <!-- Email -->
                        <div class="group">
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-3 group-hover:text-blue-600 transition-colors">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 7.89a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    Email Address *
                                </span>
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                required
                                class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 input-glow bg-white/80 backdrop-blur-sm hover:bg-white"
                                placeholder="client@example.com">
                        </div>

                        <!-- Contact Number -->
                        <div class="group">
                            <label for="contact" class="block text-sm font-semibold text-gray-700 mb-3 group-hover:text-blue-600 transition-colors">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    Contact Number *
                                </span>
                            </label>
                            <input
                                type="tel"
                                id="contact"
                                name="contact"
                                pattern="[0-9]{11}"
                                required
                                class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 input-glow bg-white/80 backdrop-blur-sm hover:bg-white"
                                placeholder="09171234567">
                            <p class="text-xs text-gray-500 mt-2 ml-1">Format: 11 digits (e.g., 09171234567)</p>
                        </div>

                        <!-- Country -->
                        <div class="group">
                            <label for="country" class="block text-sm font-semibold text-gray-700 mb-3 group-hover:text-blue-600 transition-colors">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Country *
                                </span>
                            </label>
                            <input
                                type="text"
                                id="country"
                                name="country"
                                required
                                class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 input-glow bg-white/80 backdrop-blur-sm hover:bg-white"
                                placeholder="Enter country">
                        </div>

                        <!-- Client Type -->
                        <div class="group">
                            <label for="client_type" class="block text-sm font-semibold text-gray-700 mb-3 group-hover:text-blue-600 transition-colors">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 17v-2a4 4 0 014-4h5V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2h4z" />
                                    </svg>
                                    Client Type *
                                </span>
                            </label>
                            <select
                                id="client_type"
                                name="client_type"
                                required
                                class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 bg-white/80 backdrop-blur-sm hover:bg-white">
                                <option value="">Select client type</option>
                                <option value="vip">VIP</option>
                                <option value="walkin">Walk-In</option>
                                <option value="new">New Client</option>
                                <option value="old">Old Client</option>
                            </select>
                        </div>


                        <!-- Sex -->
                        <div class="group">
                            <label for="sex" class="block text-sm font-semibold text-gray-700 mb-3 group-hover:text-blue-600 transition-colors">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5.121 17.804A7 7 0 0112 5a7 7 0 016.879 12.804M15 11h.01M9 11h.01M8 15h8" />
                                    </svg>
                                    Sex
                                </span>
                            </label>
                            <select id="sex" name="sex" required
                                class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 input-glow bg-white/80 backdrop-blur-sm hover:bg-white">
                                <option value="" disabled selected>Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                        </div>

                        <!-- Enhanced Submit Button -->
                        <div class="pt-6">
                            <button
                                type="submit"
                                class="w-full btn-gradient text-white font-bold py-5 px-8 rounded-xl transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-blue-500/50 focus:ring-offset-2 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 active:scale-95">
                                <span class="flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Add Client
                                </span>
                            </button>
                        </div>

                        <!-- Required Fields Note -->
                        <div class="text-center pt-1">
                            <p class="text-xs text-gray-500 bg-gray-50 px-4 py-2 rounded-full inline-block">* Required fields</p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Enhanced Client List Table -->
            <div class="xl:col-span-3 ">
                <div class="glass-effect card-shadow border border-white/20 overflow-hidden animate-slide-up p-2" style="animation-delay: 0.2s;">

                    <?php if (isset($_GET['deleted'])): ?>
                        <div id="deleteMessage" class="bg-green-50 border border-green-300 text-green-700 text-sm px-3 py-2 rounded-md mb-3 w-fit">
                            Client deleted successfully!
                        </div>

                        <script>
                            setTimeout(() => {
                                const msg = document.getElementById('deleteMessage');
                                if (msg) msg.style.display = 'none';

                                // Remove "deleted" from the URL without reloading
                                const url = new URL(window.location.href);
                                url.searchParams.delete('deleted');
                                window.history.replaceState({}, document.title, url);
                            }, 2000); // 2 seconds
                        </script>
                    <?php endif; ?>



                    <div class="bg-gradient-to-r from-orange-400 to-orange-400 px-6 py-4 rounded">
                        <h2 class="text-xl font-bold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            Client
                        </h2>
                    </div>

                    <div class="p-6">
                        <?php
 $sql = "
        SELECT *
        FROM client_info ci
        WHERE ci.id IN (
            SELECT MAX(id)
            FROM client_info
            GROUP BY email
        )
        ORDER BY ci.created_at DESC
    ";

    $result = $conn->query($sql);
                        if ($result && $result->num_rows > 0) {
                        ?>

                            <div class="overflow-x-auto rounded-xl border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Client Info</th>
                                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Contact</th>
                                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Location</th>
                                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white/50 backdrop-blur-sm divide-y divide-gray-200">
                                        <?php while ($client = $result->fetch_assoc()): ?>
                                            <tr class="table-row hover:bg-white/80 hover:shadow-lg">
                                                <td class="px-6 py-5 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="bg-gradient-to-br from-orange-400 to-orange-500 rounded-full p-3 mr-4 shadow-lg">
                                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                            </svg>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($client['name']); ?></div>
                                                            <div class="text-sm text-gray-500 flex items-center mt-1">
                                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 7.89a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                                </svg>
                                                                <?php echo htmlspecialchars($client['email']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-5 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded-lg inline-flex items-center">
                                                        <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                        </svg>
                                                        <?php echo htmlspecialchars($client['contact']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-5 whitespace-nowrap">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900 flex items-center">
                                                            <svg class="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                            <?php echo htmlspecialchars($client['country']); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($client['address']); ?></div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-5 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <!-- Edit Button -->
                                                        <button
                                                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:scale-95 flex items-center"
                                                            onclick="openEditModal(<?php echo $client['id']; ?>)">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 
                                                                       002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 
                                                                       15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                            Edit
                                                        </button>


                                                        <button
                                                            class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:scale-95 flex items-center"
                                                            onclick="deleteClient(<?php echo $client['id']; ?>)">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862
                                                                       a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6
                                                                       m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            Delete
                                                        </button>

                                                    </div>
                                                </td>

                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php
                        } else {
                        ?>
                            <div class="text-center py-16">
                                <div class="bg-gray-100 rounded-full p-6 mx-auto w-24 h-24 flex items-center justify-center mb-6">
                                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">No clients found</h3>
                                <p class="text-gray-500 mb-6">Get started by adding your first client to the database.</p>
                                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 inline-block">
                                    <p class="text-blue-700 text-sm">👈 Use the form on the left to add a new client</p>
                                </div>
                            </div>
                        <?php
                        }
                        // Close connection
                        $conn->close();
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Edit Modal (Iframe Version) -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white w-[90%] max-w-2xl rounded-xl overflow-hidden shadow-lg relative">
            <!-- Modal Header -->
            <div class="flex justify-between items-center bg-orange-600 text-white px-4 py-2">
                <h2 class="text-lg font-semibold">Edit Client</h2>
                <button onclick="closeEditModal()" class="text-white text-xl font-bold hover:text-gray-300">&times;</button>
            </div>
            <!-- Iframe -->
            <iframe id="editIframe" src="" class="w-full h-[500px] border-none"></iframe>
        </div>
    </div>


    <script src="js/insertclient.js" defer></script>

</body>

</html>