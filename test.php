<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scroll Into Picture</title>
  <style>
    body, html {
      margin: 0;
      padding: 0;
      height: 200vh; /* para may scroll space */
      background: #111;
      overflow-x: hidden;
    }

    .hero {
      position: relative;
      height: 100vh;
      overflow: hidden;
    }

    .hero img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transform: scale(1); /* start normal size */
      transition: transform 0.1s linear;
    }

    .content {
      height: 100vh;
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
    }
  </style>
</head>
<body>

  <div class="hero">
    <img id="bg" src="https://picsum.photos/id/1018/1920/1080" alt="Background">
  </div>

  <div class="content">
    <p>Now you are inside the picture, Papalicious 😎</p>
  </div>

  <script>
    const bg = document.getElementById("bg");

    window.addEventListener("scroll", () => {
      let scrollY = window.scrollY;
      let scale = 1 + scrollY / 1000; // adjust zoom speed
      bg.style.transform = `scale(${scale})`;
    });
  </script>

</body>
</html>
