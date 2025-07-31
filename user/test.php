<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Login Test</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js" defer></script>
    <style>
        @import url('https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css');
        .debug-info { background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 8px; padding: 16px; margin: 16px 0; }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-center mb-6">Mobile Login Test</h1>
        
        <div x-data="mobileLoginTest()">
            <!-- Debug Info -->
            <div class="debug-info">
                <h3 class="font-bold text-sm mb-2">Debug Info:</h3>
                <p class="text-xs">Input: <span x-text="loginInput || 'empty'"></span></p>
                <p class="text-xs">Is Mobile: <span x-text="isMobile ? 'Yes' : 'No'"></span></p>
                <p class="text-xs">Is Email: <span x-text="isEmail ? 'Yes' : 'No'"></span></p>
                <p class="text-xs">Password: <span x-text="password ? '***filled***' : 'empty'"></span></p>
            </div>

            <!-- Mobile Input -->
            <div class="mb-4">
                <label for="login" class="block text-sm font-medium text-gray-600 mb-2">Mobile Number</label>
                <input type="text" id="login" name="login" x-model="loginInput" @input="checkLoginType"
                    placeholder="09123456789" 
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Format: 09 followed by 9 digits</p>
            </div>

            <!-- Password Input -->
            <div x-show="isMobile" class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-600 mb-2">Password</label>
                <input type="password" id="password" name="password" x-model="password"
                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Test Buttons -->
            <div class="space-y-2 mb-4">
                <button @click="testMobileFormat" 
                    class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg">
                    Test Mobile Format
                </button>
                
                <button @click="testLogin" :disabled="!isMobile || !password"
                    class="w-full bg-green-500 hover:bg-green-600 disabled:bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg">
                    Test Login Request
                </button>
            </div>

            <!-- Results -->
            <div x-show="testResults" x-transition class="debug-info">
                <h3 class="font-bold text-sm mb-2">Test Results:</h3>
                <pre x-text="testResults" class="text-xs whitespace-pre-wrap"></pre>
            </div>

            <!-- Form Action Test -->
            <div class="debug-info">
                <h3 class="font-bold text-sm mb-2">Form Action Path:</h3>
                <p class="text-xs" x-text="formAction"></p>
                <button @click="testPath" class="mt-2 bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-3 py-1 rounded">
                    Test Path
                </button>
            </div>

            <!-- Sample Users for Testing -->
            <div class="debug-info">
                <h3 class="font-bold text-sm mb-2">Sample Test Data:</h3>
                <div class="text-xs space-y-1">
                    <p><strong>Your existing user:</strong></p>
                    <p>Mobile: 09081031241</p>
                    <p>Email: wendhil08@gmail.com</p>
                    <p class="text-red-600">Note: Password hash is in database</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function mobileLoginTest() {
            return {
                loginInput: '',
                password: '',
                isMobile: false,
                isEmail: false,
                testResults: '',
                formAction: '../login.php',

                checkLoginType() {
                    const input = this.loginInput.trim();
                    this.isMobile = /^09\d{9}$/.test(input);
                    this.isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input);
                    this.testResults = '';
                },

                testMobileFormat() {
                    const input = this.loginInput.trim();
                    const regex = /^09\d{9}$/;
                    
                    this.testResults = `
Input: "${input}"
Length: ${input.length}
Starts with 09: ${input.startsWith('09')}
Is all digits after 09: ${/^\d+$/.test(input.substring(2))}
Regex match: ${regex.test(input)}
Expected format: 09 + 9 digits = 11 total characters

Examples:
✅ 09123456789 (valid)
✅ 09081031241 (valid - your number)
❌ 9123456789 (missing 0)
❌ 09123456 (too short)
❌ 091234567890 (too long)
                    `;
                },

                async testLogin() {
                    if (!this.isMobile || !this.password) {
                        this.testResults = 'Error: Mobile number and password required';
                        return;
                    }

                    this.testResults = 'Testing login request...\n';

                    try {
                        const formData = new FormData();
                        formData.append('login', this.loginInput);
                        formData.append('password', this.password);

                        this.testResults += `
Sending POST to: ${this.formAction}
Data being sent:
- login: ${this.loginInput}
- password: [hidden]

Making request...
                        `;

                        const response = await fetch(this.formAction, {
                            method: 'POST',
                            body: formData
                        });

                        this.testResults += `
Response Status: ${response.status}
Response URL: ${response.url}
Is Redirected: ${response.redirected}

                        `;

                        if (response.redirected) {
                            this.testResults += `✅ Login appears successful - redirected to: ${response.url}`;
                        } else {
                            const text = await response.text();
                            this.testResults += `Response body: ${text.substring(0, 500)}`;
                        }

                    } catch (error) {
                        this.testResults += `❌ Error: ${error.message}`;
                    }
                },

                testPath() {
                    this.testResults = `
Testing path: ${this.formAction}

Current page: ${window.location.href}
Calculated full path: ${new URL(this.formAction, window.location.href).href}

Path analysis:
- '../' means go up one directory
- If current page is in '/pages/' folder
- Then '../login.php' points to '/login.php'

Make sure your folder structure is:
/your-project/
  ├── login.php          ← Target file
  ├── pages/
  │   └── current-page   ← Your current location
                    `;
                }
            };
        }
    </script>
</body>
</html>