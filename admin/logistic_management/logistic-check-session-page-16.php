<?php
// check_session.php
session_name("nobleadmin");
session_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Checker - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-900">Session Debug Information</h1>
            
            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-2 text-blue-600">Is User Logged In?</h2>
                <p class="text-xl font-bold <?php echo isset($_SESSION['noble_user']) ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo isset($_SESSION['noble_user']) ? 'YES ✓' : 'NO ✗'; ?>
                </p>
            </div>
            
            <?php if (isset($_SESSION['noble_user'])): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-2 text-blue-600">User Information</h2>
                    <div class="bg-gray-50 p-4 rounded border">
                        <table class="w-full text-sm">
                            <tr class="border-b">
                                <td class="py-2 font-semibold">User ID:</td>
                                <td class="py-2"><?php echo isset($_SESSION['noble_id']) ? $_SESSION['noble_id'] : 'NOT SET'; ?></td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-2 font-semibold">Email:</td>
                                <td class="py-2"><?php echo isset($_SESSION['noble_user']) ? $_SESSION['noble_user'] : 'NOT SET'; ?></td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-2 font-semibold">Name:</td>
                                <td class="py-2"><?php echo isset($_SESSION['noble_name']) ? $_SESSION['noble_name'] : 'NOT SET'; ?></td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-2 font-semibold">Level (Department):</td>
                                <td class="py-2 font-bold text-purple-600">
                                    <?php echo isset($_SESSION['noble_lvl']) ? $_SESSION['noble_lvl'] : 'NOT SET'; ?>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-2 font-semibold">Subrole:</td>
                                <td class="py-2 font-bold text-green-600">
                                    <?php echo isset($_SESSION['noble_subrole']) ? $_SESSION['noble_subrole'] : 'NOT SET'; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-2 text-blue-600">Access Check for Dispatcher Pages</h2>
                    <div class="bg-gray-50 p-4 rounded border">
                        <?php 
                        $lvl = $_SESSION['noble_lvl'] ?? '';
                        $subrole = $_SESSION['noble_subrole'] ?? '';
                        
                        $isLogistic = ($lvl === 'logistic');
                        $isDispatcher = ($subrole === 'dispatcher');
                        $canAccessDispatcherPages = $isLogistic && $isDispatcher;
                        ?>
                        
                        <div class="space-y-2">
                            <p class="<?php echo $isLogistic ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $isLogistic ? '✓' : '✗'; ?> 
                                Is Logistic Department: <strong><?php echo $isLogistic ? 'YES' : 'NO'; ?></strong>
                                (Current: <?php echo $lvl ? $lvl : 'EMPTY'; ?>)
                            </p>
                            
                            <p class="<?php echo $isDispatcher ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $isDispatcher ? '✓' : '✗'; ?> 
                                Is Dispatcher: <strong><?php echo $isDispatcher ? 'YES' : 'NO'; ?></strong>
                                (Current: <?php echo $subrole ? $subrole : 'EMPTY'; ?>)
                            </p>
                            
                            <div class="mt-4 p-3 rounded <?php echo $canAccessDispatcherPages ? 'bg-green-100 border border-green-300' : 'bg-red-100 border border-red-300'; ?>">
                                <p class="font-bold <?php echo $canAccessDispatcherPages ? 'text-green-800' : 'text-red-800'; ?>">
                                    <?php if ($canAccessDispatcherPages): ?>
                                        ✓ CAN ACCESS DISPATCHER PAGES
                                    <?php else: ?>
                                        ✗ CANNOT ACCESS DISPATCHER PAGES
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="bg-red-50 border border-red-200 rounded p-4">
                    <p class="text-red-800 font-semibold">No user session found. Please log in first.</p>
                </div>
            <?php endif; ?>
            
            <div class="mt-6">
                <h2 class="text-lg font-semibold mb-2 text-blue-600">Complete $_SESSION Array</h2>
                <div class="bg-gray-900 text-green-400 p-4 rounded overflow-auto">
                    <pre><?php print_r($_SESSION); ?></pre>
                </div>
            </div>
            
            <div class="mt-6 flex gap-4">
                <a href="logistic-dispatcher-dashboard-page-13.php" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                    Try Dispatcher Dashboard
                </a>
                <a href="../../loginpage/index.php" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
                    Go to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>