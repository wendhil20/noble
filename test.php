<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Snake Game</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #0f172a;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
        sans-serif;
      color: #e5e7eb;
    }

    .game-wrapper {
      display: flex;
      flex-direction: column;
      gap: 12px;
      align-items: center;
      padding: 20px;
      background: #020617;
      border-radius: 16px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7);
    }

    h1 {
      margin: 0;
      font-size: 24px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #38bdf8;
    }

    .info-row {
      display: flex;
      gap: 16px;
      font-size: 14px;
    }

    .badge {
      background: #020617;
      border-radius: 999px;
      padding: 4px 10px;
      border: 1px solid #1f2937;
    }

    .badge span {
      font-weight: 600;
      color: #38bdf8;
    }

    canvas {
      border-radius: 8px;
      background: #020617;
      border: 2px solid #1f2937;
    }

    .controls {
      font-size: 13px;
      opacity: 0.8;
      text-align: center;
      margin-top: 4px;
    }

    .btn {
      margin-top: 8px;
      padding: 6px 14px;
      border-radius: 999px;
      border: none;
      cursor: pointer;
      background: #38bdf8;
      color: #020617;
      font-weight: 600;
      font-size: 13px;
    }

    .btn:hover {
      filter: brightness(1.1);
    }

    .game-over {
      font-size: 14px;
      color: #f97373;
      min-height: 18px;
    }
  </style>
</head>
<body>
  <div class="game-wrapper">
    <h1>SNAKE</h1>

    <div class="info-row">
      <div class="badge">Score: <span id="score">0</span></div>
      <div class="badge">High Score: <span id="highScore">0</span></div>
      <div class="badge">Speed: <span id="speedLabel">Normal</span></div>
    </div>

    <canvas id="game" width="400" height="400"></canvas>

    <div class="game-over" id="gameOverMsg"></div>

    <button class="btn" id="restartBtn">Restart</button>

    <div class="controls">
      Use <strong>Arrow Keys</strong> (or W/A/S/D) to move. <br />
      Don’t hit the walls or yourself!
    </div>
  </div>

  <script>
    // Canvas and context
    const canvas = document.getElementById("game");
    const ctx = canvas.getContext("2d");

    // UI elements
    const scoreEl = document.getElementById("score");
    const highScoreEl = document.getElementById("highScore");
    const gameOverMsg = document.getElementById("gameOverMsg");
    const restartBtn = document.getElementById("restartBtn");
    const speedLabel = document.getElementById("speedLabel");

    // Grid settings
    const tileSize = 20; // each cell size
    const tileCount = canvas.width / tileSize; // 400/20 = 20x20 grid

    // Game state
    let snake;
    let food;
    let velocity;
    let score;
    let highScore = parseInt(localStorage.getItem("snakeHighScore") || "0");
    let gameLoop;
    let speed = 130; // lower = faster

    highScoreEl.textContent = highScore;

    function resetGame() {
      snake = [
        { x: 10, y: 10 },
        { x: 9, y: 10 },
        { x: 8, y: 10 },
      ];
      velocity = { x: 1, y: 0 }; // moving right
      score = 0;
      scoreEl.textContent = score;
      gameOverMsg.textContent = "";
      speed = 130;
      updateSpeedLabel();
      placeFood();
      if (gameLoop) clearInterval(gameLoop);
      gameLoop = setInterval(gameTick, speed);
    }

    function updateSpeedLabel() {
      if (speed <= 90) {
        speedLabel.textContent = "Fast";
      } else if (speed <= 120) {
        speedLabel.textContent = "Normal+";
      } else {
        speedLabel.textContent = "Normal";
      }
    }

    function placeFood() {
      // place food in random grid cell
      food = {
        x: Math.floor(Math.random() * tileCount),
        y: Math.floor(Math.random() * tileCount),
      };

      // make sure food is not on the snake
      for (let part of snake) {
        if (part.x === food.x && part.y === food.y) {
          return placeFood();
        }
      }
    }

    function gameTick() {
      update();
      draw();
    }

    function update() {
      // create new head
      const head = {
        x: snake[0].x + velocity.x,
        y: snake[0].y + velocity.y,
      };

      // collision with walls
      if (
        head.x < 0 ||
        head.x >= tileCount ||
        head.y < 0 ||
        head.y >= tileCount
      ) {
        return gameOver();
      }

      // collision with self
      for (let part of snake) {
        if (part.x === head.x && part.y === head.y) {
          return gameOver();
        }
      }

      // add new head to the front
      snake.unshift(head);

      // check food collision
      if (head.x === food.x && head.y === food.y) {
        score += 1;
        scoreEl.textContent = score;

        // update high score
        if (score > highScore) {
          highScore = score;
          highScoreEl.textContent = highScore;
          localStorage.setItem("snakeHighScore", highScore);
        }

        // increase speed a little
        if (speed > 70) {
          speed -= 5;
          updateSpeedLabel();
          clearInterval(gameLoop);
          gameLoop = setInterval(gameTick, speed);
        }

        // place new food
        placeFood();
      } else {
        // remove tail (normal movement)
        snake.pop();
      }
    }

    function draw() {
      // clear board
      ctx.fillStyle = "#020617";
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      // draw grid (optional, faint)
      ctx.strokeStyle = "#0b1120";
      for (let i = 0; i < tileCount; i++) {
        ctx.beginPath();
        ctx.moveTo(i * tileSize, 0);
        ctx.lineTo(i * tileSize, canvas.height);
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(0, i * tileSize);
        ctx.lineTo(canvas.width, i * tileSize);
        ctx.stroke();
      }

      // draw snake
      for (let i = 0; i < snake.length; i++) {
        const part = snake[i];
        if (i === 0) {
          // head
          ctx.fillStyle = "#38bdf8";
        } else {
          ctx.fillStyle = "#0ea5e9";
        }
        ctx.fillRect(
          part.x * tileSize,
          part.y * tileSize,
          tileSize - 2,
          tileSize - 2
        );
      }

      // draw food
      ctx.fillStyle = "#22c55e";
      ctx.fillRect(
        food.x * tileSize,
        food.y * tileSize,
        tileSize - 2,
        tileSize - 2
      );
    }

    function gameOver() {
      clearInterval(gameLoop);
      gameOverMsg.textContent = "Game Over – press Restart to play again.";
    }

    // handle keyboard
    window.addEventListener("keydown", (e) => {
      const key = e.key.toLowerCase();

      // prevent reverse direction
      if ((key === "arrowup" || key === "w") && velocity.y !== 1) {
        velocity = { x: 0, y: -1 };
      } else if ((key === "arrowdown" || key === "s") && velocity.y !== -1) {
        velocity = { x: 0, y: 1 };
      } else if ((key === "arrowleft" || key === "a") && velocity.x !== 1) {
        velocity = { x: -1, y: 0 };
      } else if ((key === "arrowright" || key === "d") && velocity.x !== -1) {
        velocity = { x: 1, y: 0 };
      }
    });

    restartBtn.addEventListener("click", resetGame);

    // start
    resetGame();
  </script>
</body>
</html>
