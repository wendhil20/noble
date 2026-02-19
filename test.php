<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Map</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .map-container {
            padding: 20px;
        }
        
        iframe {
            width: 100%;
            height: 600px;
            border: none;
            border-radius: 6px;
        }
        
        .footer {
            background-color: #f9f9f9;
            padding: 15px 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📍 Interactive Map</h1>
            <p>Powered by Scribble Maps</p>
        </div>
        
        <div class="map-container">
            <iframe src="https://www.scribblemaps.com/maps/view/?utm_source=embed&lat=14.6571315&lng=121.0033292&z=15&t=hybrid" allowfullscreen="" loading="lazy"></iframe>
        </div>
        
        <div class="footer">
            <p>Free version of Scribble Maps - Create and share your own maps at scribblemaps.com</p>
        </div>
    </div>
</body>
</html>