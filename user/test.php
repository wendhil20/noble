<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Expanded Font Showcase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- ✅ Google Fonts (Greatly Expanded) -->
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Pacifico&family=Lobster&family=Quicksand:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Rubik:wght@300;400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Barlow:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- ✅ Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <!-- ✅ Tailwind Config with All Fonts -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            // Sans-serif fonts
            poppins: ['Poppins', 'sans-serif'],
            inter: ['Inter', 'sans-serif'],
            lato: ['Lato', 'sans-serif'],
            opensans: ['"Open Sans"', 'sans-serif'],
            source: ['"Source Sans Pro"', 'sans-serif'],
            raleway: ['Raleway', 'sans-serif'],
            nunito: ['Nunito', 'sans-serif'],
            mont: ['Montserrat', 'sans-serif'],
            roboto: ['Roboto', 'sans-serif'],
            quicksand: ['Quicksand', 'sans-serif'],
            work: ['"Work Sans"', 'sans-serif'],
            rubik: ['Rubik', 'sans-serif'],
            fira: ['"Fira Sans"', 'sans-serif'],
            ubuntu: ['Ubuntu', 'sans-serif'],
            barlow: ['Barlow', 'sans-serif'],
            manrope: ['Manrope', 'sans-serif'],
            dmsans: ['"DM Sans"', 'sans-serif'],
            space: ['"Space Grotesk"', 'sans-serif'],
            
            // Serif fonts
            merri: ['Merriweather', 'serif'],
            playfair: ['"Playfair Display"', 'serif'],
            libre: ['"Libre Baskerville"', 'serif'],
            crimson: ['"Crimson Text"', 'serif'],
            garamond: ['"EB Garamond"', 'serif'],
            lora: ['Lora', 'serif'],
            
            // Display/Decorative fonts
            vibes: ['"Great Vibes"', 'cursive'],
            dancing: ['"Dancing Script"', 'cursive'],
            pacifico: ['Pacifico', 'cursive'],
            lobster: ['Lobster', 'cursive'],
            oswald: ['Oswald', 'sans-serif'],
            bebas: ['"Bebas Neue"', 'sans-serif'],
            anton: ['Anton', 'sans-serif'],
          }
        }
      }
    }
  </script>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 p-8 space-y-12">
  
  <!-- Header -->
  <div class="text-center bg-white rounded-xl shadow-lg p-8 mb-12">
    <h1 class="text-6xl font-mont font-bold text-orange-600 mb-4">🏠 NobleHome Depot</h1>
    <p class="text-xl font-poppins text-gray-700">Font Showcase - Discover the Perfect Typography</p>
  </div>

  <!-- Sans-Serif Fonts Section -->
  <section class="bg-white rounded-xl shadow-lg p-8">
    <h2 class="text-4xl font-playfair font-bold text-gray-800 mb-8 border-b-2 border-orange-200 pb-4">Sans-Serif Fonts</h2>
    
    <div class="grid gap-6">
      <div class="p-6 bg-gray-50 rounded-lg">
        <h3 class="text-3xl font-poppins font-bold text-orange-600 mb-2">Poppins - Modern & Clean</h3>
        <p class="text-lg font-poppins text-gray-700">Perfect for modern websites, headings, and clean body text. Very popular choice.</p>
        <p class="text-sm font-poppins font-light text-gray-600 mt-2">Light weight • Regular • SemiBold • Bold available</p>
      </div>

      <div class="p-6 bg-blue-50 rounded-lg">
        <h3 class="text-3xl font-inter font-bold text-blue-600 mb-2">Inter - Professional & Readable</h3>
        <p class="text-lg font-inter text-gray-700">Designed for computer screens, excellent readability and professional appearance.</p>
        <p class="text-sm font-inter font-light text-gray-600 mt-2">Great for dashboards, apps, and business websites</p>
      </div>

      <div class="p-6 bg-green-50 rounded-lg">
        <h3 class="text-3xl font-lato font-bold text-green-600 mb-2">Lato - Friendly & Approachable</h3>
        <p class="text-lg font-lato text-gray-700">Humanist sans-serif with a friendly feel, perfect for brands that want to appear approachable.</p>
        <p class="text-sm font-lato font-light text-gray-600 mt-2">Warm and inviting personality</p>
      </div>

      <div class="p-6 bg-purple-50 rounded-lg">
        <h3 class="text-3xl font-opensans font-bold text-purple-600 mb-2">Open Sans - Neutral & Versatile</h3>
        <p class="text-lg font-opensans text-gray-700">Highly legible and neutral design, works well in almost any context.</p>
        <p class="text-sm font-opensans font-light text-gray-600 mt-2">The Swiss Army knife of fonts</p>
      </div>

      <div class="p-6 bg-red-50 rounded-lg">
        <h3 class="text-3xl font-raleway font-bold text-red-600 mb-2">Raleway - Elegant & Sophisticated</h3>
        <p class="text-lg font-raleway text-gray-700">Elegant sans-serif with a touch of sophistication, great for luxury brands.</p>
        <p class="text-sm font-raleway font-light text-gray-600 mt-2">Refined and upscale feeling</p>
      </div>

      <div class="p-6 bg-yellow-50 rounded-lg">
        <h3 class="text-3xl font-nunito font-bold text-yellow-600 mb-2">Nunito - Rounded & Friendly</h3>
        <p class="text-lg font-nunito text-gray-700">Rounded sans-serif that feels approachable and modern, perfect for tech companies.</p>
        <p class="text-sm font-nunito font-light text-gray-600 mt-2">Soft corners, modern appeal</p>
      </div>

      <div class="p-6 bg-indigo-50 rounded-lg">
        <h3 class="text-3xl font-quicksand font-bold text-indigo-600 mb-2">Quicksand - Geometric & Modern</h3>
        <p class="text-lg font-quicksand text-gray-700">Geometric sans-serif with a contemporary feel, great for creative projects.</p>
        <p class="text-sm font-quicksand font-light text-gray-600 mt-2">Clean geometric shapes</p>
      </div>

      <div class="p-6 bg-pink-50 rounded-lg">
        <h3 class="text-3xl font-work font-bold text-pink-600 mb-2">Work Sans - Editorial & Clean</h3>
        <p class="text-lg font-work text-gray-700">Optimized for work environments, excellent for long-form reading and interfaces.</p>
        <p class="text-sm font-work font-light text-gray-600 mt-2">Perfect for productivity apps</p>
      </div>

      <div class="p-6 bg-teal-50 rounded-lg">
        <h3 class="text-3xl font-rubik font-bold text-teal-600 mb-2">Rubik - Rounded & Playful</h3>
        <p class="text-lg font-rubik text-gray-700">Slightly rounded sans-serif with a friendly and modern personality.</p>
        <p class="text-sm font-rubik font-light text-gray-600 mt-2">Perfect balance of professional and playful</p>
      </div>

      <div class="p-6 bg-cyan-50 rounded-lg">
        <h3 class="text-3xl font-space font-bold text-cyan-600 mb-2">Space Grotesk - Futuristic & Bold</h3>
        <p class="text-lg font-space text-gray-700">Modern grotesque with a futuristic feel, perfect for tech and startups.</p>
        <p class="text-sm font-space font-light text-gray-600 mt-2">Contemporary and distinctive</p>
      </div>
    </div>
  </section>

  <!-- Serif Fonts Section -->
  <section class="bg-white rounded-xl shadow-lg p-8">
    <h2 class="text-4xl font-playfair font-bold text-gray-800 mb-8 border-b-2 border-orange-200 pb-4">Serif Fonts</h2>
    
    <div class="grid gap-6">
      <div class="p-6 bg-amber-50 rounded-lg">
        <h3 class="text-3xl font-playfair font-bold text-amber-700 mb-2">Playfair Display - Luxury & Elegance</h3>
        <p class="text-lg font-playfair text-gray-700">High-contrast serif perfect for luxury brands, fashion, and elegant headings.</p>
        <p class="text-sm font-playfair font-normal text-gray-600 mt-2">Inspired by 18th century typography</p>
      </div>

      <div class="p-6 bg-stone-50 rounded-lg">
        <h3 class="text-3xl font-merri font-bold text-stone-700 mb-2">Merriweather - Readable & Classic</h3>
        <p class="text-lg font-merri text-gray-700">Designed for excellent screen reading, combines tradition with modern clarity.</p>
        <p class="text-sm font-merri font-light text-gray-600 mt-2">Perfect for blogs and articles</p>
      </div>

      <div class="p-6 bg-slate-50 rounded-lg">
        <h3 class="text-3xl font-libre font-bold text-slate-700 mb-2">Libre Baskerville - Traditional & Scholarly</h3>
        <p class="text-lg font-libre text-gray-700">Web optimization of the classic Baskerville, perfect for academic and literary content.</p>
        <p class="text-sm font-libre font-normal text-gray-600 mt-2">Timeless and authoritative</p>
      </div>

      <div class="p-6 bg-rose-50 rounded-lg">
        <h3 class="text-3xl font-crimson font-bold text-rose-700 mb-2">Crimson Text - Editorial & Refined</h3>
        <p class="text-lg font-crimson text-gray-700">Inspired by old-style serif fonts, perfect for magazines and editorial content.</p>
        <p class="text-sm font-crimson font-semibold text-gray-600 mt-2">Classic book typography feel</p>
      </div>

      <div class="p-6 bg-emerald-50 rounded-lg">
        <h3 class="text-3xl font-garamond font-bold text-emerald-700 mb-2">EB Garamond - Classical & Graceful</h3>
        <p class="text-lg font-garamond text-gray-700">Revival of Claude Garamont's famous humanist typeface from the 16th century.</p>
        <p class="text-sm font-garamond font-medium text-gray-600 mt-2">Renaissance elegance</p>
      </div>

      <div class="p-6 bg-violet-50 rounded-lg">
        <h3 class="text-3xl font-lora font-bold text-violet-700 mb-2">Lora - Modern & Calligraphic</h3>
        <p class="text-lg font-lora text-gray-700">Contemporary serif with calligraphic roots, excellent for both headings and body text.</p>
        <p class="text-sm font-lora font-medium text-gray-600 mt-2">Brushed curves and modern clarity</p>
      </div>
    </div>
  </section>

  <!-- Display & Decorative Fonts Section -->
  <section class="bg-white rounded-xl shadow-lg p-8">
    <h2 class="text-4xl font-playfair font-bold text-gray-800 mb-8 border-b-2 border-orange-200 pb-4">Display & Decorative Fonts</h2>
    
    <div class="grid gap-6">
      <div class="p-6 bg-pink-100 rounded-lg">
        <h3 class="text-4xl font-vibes text-pink-700 mb-2">Great Vibes - Elegant Script</h3>
        <p class="text-lg font-poppins text-gray-700">Beautiful connecting script perfect for signatures, invitations, and luxury branding.</p>
        <p class="text-sm font-poppins text-gray-600 mt-2">Handwritten elegance</p>
      </div>

      <div class="p-6 bg-purple-100 rounded-lg">
        <h3 class="text-4xl font-dancing text-purple-700 mb-2">Dancing Script - Casual & Fun</h3>
        <p class="text-lg font-poppins text-gray-700">Lively casual script that feels friendly and approachable, great for creative brands.</p>
        <p class="text-sm font-poppins text-gray-600 mt-2">Bouncy and energetic</p>
      </div>

      <div class="p-6 bg-blue-100 rounded-lg">
        <h3 class="text-4xl font-pacifico text-blue-700 mb-2">Pacifico - Retro & Playful</h3>
        <p class="text-lg font-poppins text-gray-700">Inspired by 1950s American surf culture, perfect for fun and retro brands.</p>
        <p class="text-sm font-poppins text-gray-600 mt-2">Vintage surf vibes</p>
      </div>

      <div class="p-6 bg-red-100 rounded-lg">
        <h3 class="text-4xl font-lobster text-red-700 mb-2">Lobster - Bold Script</h3>
        <p class="text-lg font-poppins text-gray-700">Bold script font with a vintage feel, great for logos and attention-grabbing headlines.</p>
        <p class="text-sm font-poppins text-gray-600 mt-2">Strong personality</p>
      </div>

      <div class="p-6 bg-gray-100 rounded-lg">
        <h3 class="text-5xl font-oswald font-bold text-gray-800 mb-2">OSWALD - CONDENSED POWER</h3>
        <p class="text-lg font-poppins text-gray-700">Condensed sans-serif perfect for headlines that need to make a strong impact.</p>
        <p class="text-sm font-poppins text-gray-600 mt-2">Newspaper headline style</p>
      </div>

      <div class="p-6 bg-black rounded-lg text-white">
        <h3 class="text-6xl font-bebas text-white mb-2">BEBAS NEUE</h3>
        <p class="text-lg font-poppins text-gray-200">Ultra-condensed display font, perfect for bold statements and modern designs.</p>
        <p class="text-sm font-poppins text-gray-300 mt-2">Maximum impact typography</p>
      </div>

      <div class="p-6 bg-orange-100 rounded-lg">
        <h3 class="text-5xl font-anton font-bold text-orange-800 mb-2">ANTON BOLD</h3>
        <p class="text-lg font-poppins text-gray-700">Single-weight display font inspired by traditional advertising and poster designs.</p>
        <p class="text-sm font-poppins text-gray-600 mt-2">Vintage poster style</p>
      </div>
    </div>
  </section>

  <!-- Font Pairing Examples -->
  <section class="bg-white rounded-xl shadow-lg p-8">
    <h2 class="text-4xl font-playfair font-bold text-gray-800 mb-8 border-b-2 border-orange-200 pb-4">Perfect Font Pairings</h2>
    
    <div class="grid gap-8">
      <div class="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg">
        <h3 class="text-3xl font-playfair font-bold text-indigo-800 mb-2">Playfair Display + Open Sans</h3>
        <p class="text-lg font-opensans text-gray-700">Classic pairing: elegant serif headlines with clean, readable body text. Perfect for luxury brands and editorial content.</p>
      </div>

      <div class="p-6 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg">
        <h3 class="text-3xl font-mont font-bold text-emerald-800 mb-2">Montserrat + Merriweather</h3>
        <p class="text-lg font-merri text-gray-700">Modern sans-serif headlines paired with traditional serif body text creates perfect balance between contemporary and classic.</p>
      </div>

      <div class="p-6 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg">
        <h3 class="text-3xl font-oswald font-bold text-purple-800 mb-2">OSWALD + LATO</h3>
        <p class="text-lg font-lato text-gray-700">Bold condensed headlines with friendly, approachable body text. Great for impactful yet welcoming designs.</p>
      </div>

      <div class="p-6 bg-gradient-to-r from-orange-50 to-red-50 rounded-lg">
        <h3 class="text-3xl font-bebas font-bold text-red-800 mb-2">BEBAS NEUE + NUNITO</h3>
        <p class="text-lg font-nunito text-gray-700">Ultra-bold display font balanced with soft, rounded body text. Perfect for modern, energetic brands.</p>
      </div>
    </div>
  </section>

  <!-- Usage Recommendations -->
  <section class="bg-gradient-to-r from-orange-500 to-red-500 rounded-xl shadow-lg p-8 text-white">
    <h2 class="text-4xl font-playfair font-bold mb-8">Font Usage Guide for NobleHome Depot</h2>
    
    <div class="grid md:grid-cols-2 gap-8">
      <div>
        <h3 class="text-2xl font-mont font-bold mb-4">🏠 For Home & Lifestyle Brands:</h3>
        <ul class="space-y-2 font-poppins">
          <li><strong>Poppins</strong> - Modern, clean, family-friendly</li>
          <li><strong>Lato</strong> - Warm, approachable, trustworthy</li>
          <li><strong>Nunito</strong> - Friendly, contemporary, welcoming</li>
          <li><strong>Merriweather</strong> - Traditional, reliable, established</li>
        </ul>
      </div>
      
      <div>
        <h3 class="text-2xl font-mont font-bold mb-4">📱 For Digital/Web Use:</h3>
        <ul class="space-y-2 font-poppins">
          <li><strong>Inter</strong> - Optimized for screens, professional</li>
          <li><strong>Open Sans</strong> - Highly legible, versatile</li>
          <li><strong>Work Sans</strong> - Great for interfaces</li>
          <li><strong>Source Sans Pro</strong> - Clean, technical</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- Call to Action -->
  <div class="text-center bg-white rounded-xl shadow-lg p-8">
    <h2 class="text-4xl font-playfair font-bold text-gray-800 mb-4">Ready to Choose Your Perfect Font?</h2>
    <p class="text-xl font-poppins text-gray-700 mb-6">Each font tells a different story for your NobleHome Depot brand.</p>
    <div class="flex flex-wrap justify-center gap-4">
      <button class="px-8 py-4 bg-orange-600 text-white font-mont font-bold text-lg rounded-lg shadow-lg hover:bg-orange-700 transition transform hover:scale-105">
        Start Shopping
      </button>
      <button class="px-8 py-4 bg-gray-800 text-white font-poppins font-semibold text-lg rounded-lg shadow-lg hover:bg-gray-900 transition transform hover:scale-105">
        Browse Fonts
      </button>
      <button class="px-8 py-4 bg-blue-600 text-white font-inter font-medium text-lg rounded-lg shadow-lg hover:bg-blue-700 transition transform hover:scale-105">
        Get Started
      </button>
    </div>
  </div>

</body>
</html>