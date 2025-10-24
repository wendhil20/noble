<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include '../navbar/top.php' ?>
    <!-- Not Available Message -->
    <div class="flex items-center justify-center min-h-[calc(100vh-64px)] p-4">

        <div class="text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-200 rounded-full mb-4">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-semibold text-gray-800 mb-2">Not Available For Now</h2>
            <p class="text-gray-600">This content is currently unavailable. Please check back later.</p>
        </div>
    </div>
    
</body>
</html>