<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Load Visualizer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .input-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .input-group {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .input-group h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .input-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .single-input {
            display: block;
        }
        
        label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .calculate-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
            margin-bottom: 30px;
        }
        
        .calculate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .results {
            display: none;
        }
        
        .results.active {
            display: block;
        }
        
        .status-card {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
            font-size: 18px;
        }
        
        .status-card.success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .status-card.warning {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }
        
        .status-card.danger {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-sub {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
            transition: width 0.5s ease;
        }
        
        .progress-fill.danger {
            background: linear-gradient(90deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .views-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .view-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .view-box h3 {
            color: #667eea;
            margin-bottom: 15px;
            text-align: center;
            font-size: 16px;
        }
        
        .canvas-container {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        canvas {
            max-width: 100%;
            height: auto;
        }
        
        @media (max-width: 768px) {
            .input-section,
            .stats,
            .views-container {
                grid-template-columns: 1fr;
            }
            
            .input-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Load Size Visualizer</h1>
        <p class="subtitle">Enter cubic meters and vehicle dimensions to see the fit</p>
        
        <div class="input-section">
            <!-- Load Input -->
            <div class="input-group">
                <h3>🎁 Load Volume</h3>
                <div class="single-input">
                    <label>Total Cubic Meters (m³)</label>
                    <input type="number" id="loadCubicMeters" value="0.48" step="0.01" min="0.01">
                    <div style="font-size: 11px; color: #999; margin-top: 8px;">
                        Example: 0.48 m³ = 80cm × 60cm × 100cm
                    </div>
                </div>
            </div>
            
            <!-- Vehicle Input -->
            <div class="input-group">
                <h3>🚚 Vehicle Dimensions (feet)</h3>
                <div class="input-row">
                    <div>
                        <label>Width (ft)</label>
                        <input type="number" id="vehicleWidth" value="5" step="0.1" min="0.1">
                    </div>
                    <div>
                        <label>Height (ft)</label>
                        <input type="number" id="vehicleHeight" value="4.5" step="0.1" min="0.1">
                    </div>
                    <div>
                        <label>Length (ft)</label>
                        <input type="number" id="vehicleLength" value="8" step="0.1" min="0.1">
                    </div>
                </div>
            </div>
        </div>
        
        <button class="calculate-btn" onclick="calculate()">🔍 Calculate & Visualize</button>
        
        <div class="results" id="results">
            <!-- Status Card -->
            <div class="status-card" id="statusCard"></div>
            
            <!-- Stats -->
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-label">Load Volume</div>
                    <div class="stat-value" id="loadVolume">0 m³</div>
                    <div class="stat-sub" id="loadDimText">Entered volume</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-label">Vehicle Capacity</div>
                    <div class="stat-value" id="vehicleVolume">0 m³</div>
                    <div class="stat-sub" id="vehicleDimText"></div>
                </div>
                
                <div class="stat-box" style="grid-column: 1 / -1;">
                    <div class="stat-label">Space Usage</div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressBar">0%</div>
                    </div>
                </div>
            </div>
            
            <!-- Visual Representation -->
            <div class="views-container">
                <div class="view-box">
                    <h3>👁️ Top View (Width × Length)</h3>
                    <div class="canvas-container">
                        <canvas id="topView" width="300" height="300"></canvas>
                    </div>
                </div>
                
                <div class="view-box">
                    <h3>👁️ Side View (Length × Height)</h3>
                    <div class="canvas-container">
                        <canvas id="sideView" width="300" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function calculate() {
            // Get values
            const loadCubicM = parseFloat(document.getElementById('loadCubicMeters').value) || 0;
            
            const vehicleW_ft = parseFloat(document.getElementById('vehicleWidth').value) || 0;
            const vehicleH_ft = parseFloat(document.getElementById('vehicleHeight').value) || 0;
            const vehicleL_ft = parseFloat(document.getElementById('vehicleLength').value) || 0;
            
            // Convert feet to meters (1 foot = 0.3048 meters)
            const vehicleW_m = vehicleW_ft * 0.3048;
            const vehicleH_m = vehicleH_ft * 0.3048;
            const vehicleL_m = vehicleL_ft * 0.3048;
            
            // Calculate vehicle volume in m³
            const vehicleVolume = vehicleW_m * vehicleH_m * vehicleL_m;
            
            // Calculate percentage
            const percentage = (loadCubicM / vehicleVolume) * 100;
            
            // Update stats
            document.getElementById('loadVolume').textContent = loadCubicM.toFixed(3) + ' m³';
            
            document.getElementById('vehicleVolume').textContent = vehicleVolume.toFixed(3) + ' m³';
            document.getElementById('vehicleDimText').textContent = `${vehicleW_ft} × ${vehicleH_ft} × ${vehicleL_ft} ft`;
            
            // Update progress bar
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = Math.min(percentage, 100) + '%';
            progressBar.textContent = percentage.toFixed(1) + '%';
            
            // Update status card
            const statusCard = document.getElementById('statusCard');
            if (percentage > 100) {
                statusCard.className = 'status-card danger';
                statusCard.innerHTML = '❌ LOAD TOO BIG! Exceeds vehicle capacity by ' + (percentage - 100).toFixed(1) + '%';
                progressBar.classList.add('danger');
            } else if (percentage > 80) {
                statusCard.className = 'status-card warning';
                statusCard.innerHTML = '⚠️ TIGHT FIT! Using ' + percentage.toFixed(1) + '% of vehicle space';
                progressBar.classList.remove('danger');
            } else {
                statusCard.className = 'status-card success';
                statusCard.innerHTML = '✅ FITS PERFECTLY! Using ' + percentage.toFixed(1) + '% of vehicle space';
                progressBar.classList.remove('danger');
            }
            
            // For visualization, we'll create a cube representation
            // Assume load is a cube for simple visualization
            const loadSideLength = Math.pow(loadCubicM, 1/3); // Cube root for equal sides
            
            // Convert to feet for display
            const loadSideLength_ft = loadSideLength / 0.3048;
            
            // Draw visualizations
            drawTopView(loadSideLength_ft, loadSideLength_ft, vehicleW_ft, vehicleL_ft);
            drawSideView(loadSideLength_ft, loadSideLength_ft, vehicleL_ft, vehicleH_ft);
            
            // Show results
            document.getElementById('results').classList.add('active');
        }
        
        function drawTopView(loadW, loadL, vehicleW, vehicleL) {
            const canvas = document.getElementById('topView');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Calculate scale
            const maxDim = Math.max(vehicleW, vehicleL);
            const scale = 250 / maxDim;
            
            const vW = vehicleW * scale;
            const vL = vehicleL * scale;
            const lW = loadW * scale;
            const lL = loadL * scale;
            
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            
            // Draw vehicle (outer box)
            ctx.strokeStyle = '#333';
            ctx.lineWidth = 3;
            ctx.strokeRect(centerX - vW/2, centerY - vL/2, vW, vL);
            ctx.fillStyle = 'rgba(102, 126, 234, 0.1)';
            ctx.fillRect(centerX - vW/2, centerY - vL/2, vW, vL);
            
            // Draw load like water filling from bottom-left
            const vehicleStartX = centerX - vW/2;
            const vehicleStartY = centerY - vL/2;
            
            // Calculate how much of vehicle to fill (like water level)
            const fillW = Math.min(lW, vW);
            const fillL = Math.min(lL, vL);
            
            const fits = loadW <= vehicleW && loadL <= vehicleL;
            
            if (fits) {
                // Green - fits inside (fill from bottom-left corner)
                ctx.fillStyle = 'rgba(34, 197, 94, 0.6)';
                ctx.fillRect(vehicleStartX, vehicleStartY, fillW, fillL);
                ctx.strokeStyle = '#15803d';
                ctx.lineWidth = 2;
                ctx.strokeRect(vehicleStartX, vehicleStartY, fillW, fillL);
                
                // Add wavy water effect on top
                ctx.strokeStyle = '#15803d';
                ctx.lineWidth = 2;
                ctx.beginPath();
                for (let x = 0; x <= fillW; x += 10) {
                    const y = vehicleStartY + Math.sin(x / 10) * 3;
                    if (x === 0) ctx.moveTo(vehicleStartX + x, y);
                    else ctx.lineTo(vehicleStartX + x, y);
                }
                ctx.stroke();
            } else {
                // Red - fills up to vehicle capacity (like overflowing water)
                ctx.fillStyle = 'rgba(239, 68, 68, 0.6)';
                ctx.fillRect(vehicleStartX, vehicleStartY, fillW, fillL);
                
                // Add wavy water effect on top edge
                ctx.strokeStyle = '#b91c1c';
                ctx.lineWidth = 2;
                ctx.beginPath();
                for (let x = 0; x <= fillW; x += 10) {
                    const y = vehicleStartY + Math.sin(x / 10) * 3;
                    if (x === 0) ctx.moveTo(vehicleStartX + x, y);
                    else ctx.lineTo(vehicleStartX + x, y);
                }
                ctx.stroke();
                
                // Show overflow amount
                const overflowW = Math.max(0, loadW - vehicleW);
                const overflowL = Math.max(0, loadL - vehicleL);
                
                if (overflowW > 0 || overflowL > 0) {
                    // Draw overflow text
                    ctx.fillStyle = '#b91c1c';
                    ctx.font = 'bold 14px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText('⚠️ OVERFLOW', centerX, centerY - vL/2 - 30);
                    
                    if (overflowW > 0) {
                        ctx.fillText(`+${overflowW.toFixed(1)}ft wider`, centerX + vW/2 + 40, centerY);
                    }
                    if (overflowL > 0) {
                        ctx.fillText(`+${overflowL.toFixed(1)}ft longer`, centerX, centerY + vL/2 + 30);
                    }
                }
            }
            
            // Add percentage label inside filled area
            ctx.fillStyle = fits ? '#15803d' : '#b91c1c';
            ctx.font = 'bold 16px Arial';
            ctx.textAlign = 'center';
            const fillPercentW = (fillW / vW) * 100;
            const fillPercentL = (fillL / vL) * 100;
            const avgPercent = (fillPercentW + fillPercentL) / 2;
            ctx.fillText(`${avgPercent.toFixed(0)}%`, vehicleStartX + fillW/2, vehicleStartY + fillL/2);
            
            // Labels
            ctx.fillStyle = '#333';
            ctx.font = 'bold 12px Arial';
            ctx.textAlign = 'center';
            
            // Vehicle label
            ctx.fillText('Vehicle', centerX, centerY - vL/2 - 10);
            ctx.fillText(`${vehicleW.toFixed(1)} × ${vehicleL.toFixed(1)} ft`, centerX, centerY + vL/2 + 20);
            
            // Load label
            ctx.fillStyle = fits ? '#15803d' : '#b91c1c';
            ctx.fillText('Load', centerX, centerY);
            ctx.fillText(`~${loadW.toFixed(1)} × ${loadL.toFixed(1)} ft`, centerX, centerY + 15);
        }
        
        function drawSideView(loadL, loadH, vehicleL, vehicleH) {
            const canvas = document.getElementById('sideView');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Calculate scale
            const maxDim = Math.max(vehicleL, vehicleH);
            const scale = 250 / maxDim;
            
            const vL = vehicleL * scale;
            const vH = vehicleH * scale;
            const lL = loadL * scale;
            const lH = loadH * scale;
            
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            
            // Draw vehicle (outer box)
            ctx.strokeStyle = '#333';
            ctx.lineWidth = 3;
            ctx.strokeRect(centerX - vL/2, centerY - vH/2, vL, vH);
            ctx.fillStyle = 'rgba(102, 126, 234, 0.1)';
            ctx.fillRect(centerX - vL/2, centerY - vH/2, vL, vH);
            
            // Draw load like water filling from bottom-left
            const vehicleStartX = centerX - vL/2;
            const vehicleStartY = centerY + vH/2; // Start from bottom
            
            // Calculate how much of vehicle to fill (like water level)
            const fillL = Math.min(lL, vL);
            const fillH = Math.min(lH, vH);
            
            const fits = loadL <= vehicleL && loadH <= vehicleH;
            
            if (fits) {
                // Green - fits inside (fill from bottom-left corner upward)
                ctx.fillStyle = 'rgba(34, 197, 94, 0.6)';
                ctx.fillRect(vehicleStartX, vehicleStartY - fillH, fillL, fillH);
                ctx.strokeStyle = '#15803d';
                ctx.lineWidth = 2;
                ctx.strokeRect(vehicleStartX, vehicleStartY - fillH, fillL, fillH);
                
                // Add wavy water effect on top
                ctx.strokeStyle = '#15803d';
                ctx.lineWidth = 2;
                ctx.beginPath();
                for (let x = 0; x <= fillL; x += 10) {
                    const y = (vehicleStartY - fillH) + Math.sin(x / 10) * 3;
                    if (x === 0) ctx.moveTo(vehicleStartX + x, y);
                    else ctx.lineTo(vehicleStartX + x, y);
                }
                ctx.stroke();
            } else {
                // Red - fills up to vehicle capacity (like overflowing water)
                ctx.fillStyle = 'rgba(239, 68, 68, 0.6)';
                ctx.fillRect(vehicleStartX, vehicleStartY - fillH, fillL, fillH);
                
                // Add wavy water effect on top edge
                ctx.strokeStyle = '#b91c1c';
                ctx.lineWidth = 2;
                ctx.beginPath();
                for (let x = 0; x <= fillL; x += 10) {
                    const y = (vehicleStartY - fillH) + Math.sin(x / 10) * 3;
                    if (x === 0) ctx.moveTo(vehicleStartX + x, y);
                    else ctx.lineTo(vehicleStartX + x, y);
                }
                ctx.stroke();
                
                // Show overflow amount
                const overflowL = Math.max(0, loadL - vehicleL);
                const overflowH = Math.max(0, loadH - vehicleH);
                
                if (overflowL > 0 || overflowH > 0) {
                    // Draw overflow text
                    ctx.fillStyle = '#b91c1c';
                    ctx.font = 'bold 14px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText('⚠️ OVERFLOW', centerX, centerY - vH/2 - 30);
                    
                    if (overflowL > 0) {
                        ctx.fillText(`+${overflowL.toFixed(1)}ft longer`, centerX + vL/2 + 45, centerY);
                    }
                    if (overflowH > 0) {
                        ctx.fillText(`+${overflowH.toFixed(1)}ft taller`, centerX, centerY - vH/2 - 45);
                    }
                }
            }
            
            // Add percentage label inside filled area
            ctx.fillStyle = fits ? '#15803d' : '#b91c1c';
            ctx.font = 'bold 16px Arial';
            ctx.textAlign = 'center';
            const fillPercentL = (fillL / vL) * 100;
            const fillPercentH = (fillH / vH) * 100;
            const avgPercent = (fillPercentL + fillPercentH) / 2;
            ctx.fillText(`${avgPercent.toFixed(0)}%`, vehicleStartX + fillL/2, vehicleStartY - fillH/2);
            
            // Labels
            ctx.fillStyle = '#333';
            ctx.font = 'bold 12px Arial';
            ctx.textAlign = 'center';
            
            // Vehicle label
            ctx.fillText('Vehicle', centerX, centerY - vH/2 - 10);
            ctx.fillText(`${vehicleL.toFixed(1)} × ${vehicleH.toFixed(1)} ft`, centerX, centerY + vH/2 + 20);
            
            // Load label
            ctx.fillStyle = fits ? '#15803d' : '#b91c1c';
            ctx.fillText('Load', centerX, centerY);
            ctx.fillText(`~${loadL.toFixed(1)} × ${loadH.toFixed(1)} ft`, centerX, centerY + 15);
        }
        
        // Auto-calculate on page load
        calculate();
    </script>
</body>
</html>