<?php 
include 'connection/connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>NobleHome Loading</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Corporate white background */
    body {
      background: #ffffff;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    /* Professional logo container */
    .logo-container {
      position: relative;
      animation: logoEntry 2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    
    @keyframes logoEntry {
      0% { 
        opacity: 0; 
        transform: translateY(-30px) scale(0.8); 
      }
      100% { 
        opacity: 1; 
        transform: translateY(0) scale(1); 
      }
    }
    
    /* Subtle professional ring */
    .logo-ring {
      position: absolute;
      top: -20px;
      left: -20px;
      right: -20px;
      bottom: -20px;
      border: 1px solid rgba(249, 115, 22, 0.2);
      border-radius: 50%;
      animation: subtleRotate 20s linear infinite;
    }
    
    @keyframes subtleRotate {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Corporate text styling */
    .brand-text {
      
      font-weight: 700;
      letter-spacing: -0.02em;
      animation: textEntry 2.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      position: relative;
    }
    
    .brand-text::after {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 50%;
      transform: translateX(-50%);
      width: 0;
      height: 2px;
      background: linear-gradient(90deg, transparent, #f97316, transparent);
      animation: underlineGrow 3s ease-out 1s forwards;
    }
    
    @keyframes textEntry {
      0% { 
        opacity: 0; 
        transform: translateY(20px); 
        letter-spacing: 0.1em;
      }
      100% { 
        opacity: 1; 
        transform: translateY(0); 
        letter-spacing: -0.02em;
      }
    }
    
    @keyframes underlineGrow {
      0% { width: 0; }
      100% { width: 80px; }
    }
    
    /* Professional tagline */
    .tagline {
      color: #4a5568;
      font-weight: 500;
      animation: fadeInUp 3s ease-out 0.5s both;
    }
    
    @keyframes fadeInUp {
      0% { 
        opacity: 0; 
        transform: translateY(15px); 
      }
      100% { 
        opacity: 1; 
        transform: translateY(0); 
      }
    }
    
    /* Corporate loading indicator */
    .loading-bar-container {
      background: #f7fafc;
      border: 1px solid #e2e8f0;
      position: relative;
      overflow: hidden;
    }
    
    .loading-bar {
      background: linear-gradient(90deg, #f97316, #e65100, #f97316);
      background-size: 200% 100%;
      animation: progressFill 5s cubic-bezier(0.4, 0, 0.2, 1) forwards,
                 shimmer 2s ease-in-out infinite;
      height: 100%;
    }
    
    @keyframes progressFill {
      0% { width: 0%; }
      100% { width: 100%; }
    }
    
    @keyframes shimmer {
      0%, 100% { background-position: 200% 0; }
      50% { background-position: -200% 0; }
    }
    
    /* Professional dots indicator */
    .loading-dots {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #cbd5e0;
      animation: corporatePulse 1.5s ease-in-out infinite;
    }
    
    .dot:nth-child(1) { animation-delay: 0s; }
    .dot:nth-child(2) { animation-delay: 0.3s; }
    .dot:nth-child(3) { animation-delay: 0.6s; }
    
    @keyframes corporatePulse {
      0%, 60%, 100% { 
        background: #cbd5e0;
        transform: scale(1);
      }
      30% { 
        background: #f97316;
        transform: scale(1.2);
      }
    }
    
    /* Status text */
    .status-text {
      color: #718096;
      font-weight: 400;
      animation: textBreathe 3s ease-in-out infinite;
    }
    
    @keyframes textBreathe {
      0%, 100% { opacity: 0.7; }
      50% { opacity: 1; }
    }
    
    /* Professional fade out */
    .fade-out {
      animation: corporateFadeOut 1.5s cubic-bezier(0.4, 0, 1, 1) forwards;
    }
    
    @keyframes corporateFadeOut {
      0% { 
        opacity: 1; 
        transform: scale(1) translateY(0);
      }
      100% { 
        opacity: 0; 
        transform: scale(0.95) translateY(-20px);
      }
    }
    
    /* Percentage counter */
    .percentage {
      font-variant-numeric: tabular-nums;
      font-weight: 600;
      color: #2d3748;
      min-width: 45px;
      text-align: center;
    }
    
    /* Main container professional styling */
    .main-container {
      animation: containerEntry 2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    
    @keyframes containerEntry {
      0% { 
        opacity: 0; 
        transform: translateY(40px);
      }
      100% { 
        opacity: 1; 
        transform: translateY(0);
      }
    }
  </style>
</head>
<body class="flex items-center justify-center h-screen bg-white">
  
  <!-- Professional loading container -->
  <div id="loader" class="main-container flex flex-col items-center space-y-8 max-w-md mx-auto px-8">
    
    <!-- Logo with corporate styling -->
    <div class="logo-container relative">
      <div class="logo-ring"></div>
      <img src="user/img/logo.png" alt="NobleHome Logo" class="w-20 h-20 object-contain">
    </div>
    
    <!-- Brand name -->
    <div class="text-center">
      <h1 class="brand-text text-4xl mb-3 text-orange-400">NobleHome</h1>
      <p class="tagline text-base">Premium Real Estate Solutions</p>
    </div>
    
    <!-- Progress section -->
    <div class="w-full space-y-4">
      <!-- Progress bar -->
      <div class="loading-bar-container w-full h-1 rounded-full">
        <div class="loading-bar rounded-full"></div>
      </div>
      
      <!-- Progress info -->
      <div class="flex items-center justify-between text-sm">
        <div class="loading-dots">
          <div class="dot"></div>
          <div class="dot"></div>
          <div class="dot"></div>
        </div>
        <div class="percentage" id="percentage">0%</div>
      </div>
    </div>
    
    <!-- Status text -->
    <p class="status-text text-sm text-center" id="statusText">
      Initializing application...
    </p>
    
  </div>

  <script>
    // Professional loading sequence
    let progress = 0;
    const percentageEl = document.getElementById('percentage');
    const statusTextEl = document.getElementById('statusText');
    
    const loadingStages = [
      { text: "Initializing application...", duration: 1000 },
      { text: "Loading core modules...", duration: 1200 },
      { text: "Preparing interface...", duration: 1000 },
      { text: "Finalizing setup...", duration: 800 },
      { text: "Ready to proceed", duration: 1000 }
    ];
    
    let currentStage = 0;
    let currentProgress = 0;
    
    // Update progress smoothly
    const progressInterval = setInterval(() => {
      if (currentProgress < 100) {
        currentProgress += Math.random() * 3 + 1;
        currentProgress = Math.min(currentProgress, 100);
        percentageEl.textContent = Math.floor(currentProgress) + '%';
      }
    }, 100);
    
    // Update status text through stages
    const updateStatus = () => {
      if (currentStage < loadingStages.length) {
        statusTextEl.textContent = loadingStages[currentStage].text;
        
        setTimeout(() => {
          currentStage++;
          if (currentStage < loadingStages.length) {
            updateStatus();
          }
        }, loadingStages[currentStage].duration);
      }
    };
    
    updateStatus();
    
    // Complete loading sequence
    setTimeout(() => {
      clearInterval(progressInterval);
      percentageEl.textContent = '100%';
      statusTextEl.textContent = 'Loading complete';
      
      setTimeout(() => {
        document.getElementById("loader").classList.add("fade-out");
        setTimeout(() => {
          window.location.href = "user/otherpage/index.php";
        }, 1500);
      }, 500);
    }, 4500);
  </script>
</body>
</html>