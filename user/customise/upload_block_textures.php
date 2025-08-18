<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Upload Block Textures</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

  <div class="bg-white shadow-lg rounded-lg p-8 max-w-xl w-full">
    <h2 class="text-2xl font-bold text-orange-600 mb-6 text-center">🧱 Upload Block Textures</h2>

    <form action="save_block.php" method="POST" enctype="multipart/form-data" class="space-y-6">

      <!-- Block name input -->
      <div>
        <label for="block_name" class="block text-sm font-medium text-gray-700">Block Name</label>
        <input type="text" name="block_name" id="block_name" required
               class="mt-1 w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:ring-orange-500 focus:border-orange-500">
      </div>

      <!-- Side image uploads -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Right</label>
          <input type="file" name="side_right" required
                 class="mt-1 w-full text-sm text-gray-600 file:border file:border-gray-300 file:rounded file:px-3 file:py-1 file:bg-white">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Left</label>
          <input type="file" name="side_left" required
                 class="mt-1 w-full text-sm text-gray-600 file:border file:border-gray-300 file:rounded file:px-3 file:py-1 file:bg-white">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Top</label>
          <input type="file" name="side_top" required
                 class="mt-1 w-full text-sm text-gray-600 file:border file:border-gray-300 file:rounded file:px-3 file:py-1 file:bg-white">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Bottom</label>
          <input type="file" name="side_bottom" required
                 class="mt-1 w-full text-sm text-gray-600 file:border file:border-gray-300 file:rounded file:px-3 file:py-1 file:bg-white">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Front</label>
          <input type="file" name="side_front" required
                 class="mt-1 w-full text-sm text-gray-600 file:border file:border-gray-300 file:rounded file:px-3 file:py-1 file:bg-white">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Back</label>
          <input type="file" name="side_back" required
                 class="mt-1 w-full text-sm text-gray-600 file:border file:border-gray-300 file:rounded file:px-3 file:py-1 file:bg-white">
        </div>
      </div>

      <!-- Submit -->
      <div class="text-center">
        <button type="submit"
                class="bg-orange-600 hover:bg-orange-700 text-white font-semibold px-6 py-2 rounded-lg shadow transition">
          Save Block
        </button>
      </div>
    </form>
  </div>

</body>
</html>