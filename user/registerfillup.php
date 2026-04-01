<form action="register.php" method="POST" class="space-y-3">
  <div>
    <label class="block text-sm font-medium text-gray-600">Name</label>
    <input type="text" name="name" required
      class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-orange-500" />
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-600">Email</label>
    <input type="email" name="email" required
      class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-orange-500" />
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-600">Password</label>
    <input type="password" name="password" required autocomplete="new-password"
      class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-orange-500" />
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-600">Confirm Password</label>
    <input type="password" name="confirm_password" required
      class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-orange-500" />
  </div>

  <button type="submit"
    class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded-lg">
    Register
  </button>

  <div class="text-sm text-center mt-2">
    Already have an account? <a href="login_form.php" class="text-orange-500 hover:underline">Log in</a>
  </div>
</form>
