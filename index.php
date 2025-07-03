<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <meta http-equiv="refresh" content="0;url=index.php">
    <script>
        window.location.href = "user/index.php";
    </script>
</head>
<body>
    

 <div id="chat-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;">

        <!-- ✅ CHAT TOGGLE BUTTON -->
        <button id="chat-toggle" onclick="openChat()"
            style="background-color:rgb(0, 0, 0); color: white; border: 1px solid white; padding: 12px 18px; border-radius: 50px; box-shadow: 0 0 10px rgba(0,0,0,0.3); font-size: 14px; cursor: pointer;">
            Chat with us
        </button>

        <!-- ✅ CHATBOX IFRAME (Initially hidden) -->
        <div id="chat-box" style="display: none; position: relative; margin-top: 10px;">
            <!-- ❌ Close Button -->
            <button onclick="closeChat()"
                style="position: absolute; top: -10px; right: -10px; background: red; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; font-size: 16px; cursor: pointer; z-index: 10000;">
                ×
            </button>

            <!-- 🧩 IFRAME CHATBOX -->
            <iframe
                src="chatbot/login.php"
                style="width: 360px; height: 540px; border: none; border-radius: 14px; box-shadow: 0 0 15px rgba(0,0,0,0.2);"
                allow="clipboard-write">
            </iframe>
        </div>
    </div>
    
</body>
</html>