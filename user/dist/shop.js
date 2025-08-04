  // Initialize AOS
    AOS.init({
      duration: 800,
      easing: 'ease-in-out',
      once: true,
      offset: 100
    });

     // Bubble background animation
      (function() {
      const canvas = document.getElementById('bubble-bg-canvas');
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      let width = 0, height = 0;
      function resize() {
        width = canvas.width = canvas.offsetWidth;
        height = canvas.height = canvas.offsetHeight;
      }
      window.addEventListener('resize', resize);
      resize();

      // Bubble properties
      const bubbleCount = 18;
      const bubbles = [];
      for (let i = 0; i < bubbleCount; i++) {
        const radius = Math.random() * 32 + 18;
        bubbles.push({
        x: Math.random() * width,
        y: Math.random() * height,
        r: radius,
        dx: (Math.random() - 0.5) * 1.2,
        dy: (Math.random() - 0.5) * 1.2,
        color: [
          'rgba(255,179,71,0.18)', // orange
          'rgba(255,255,255,0.13)', // white
          'rgba(255,204,128,0.15)', // light orange
          'rgba(255,255,255,0.09)', // white
          'rgba(255,179,71,0.12)' // orange
        ][Math.floor(Math.random() * 5)]
        });
      }

      function animate() {
        ctx.clearRect(0, 0, width, height);
        for (let b of bubbles) {
        // Move
        b.x += b.dx;
        b.y += b.dy;

        // Bounce on edges
        if (b.x - b.r < 0) { b.x = b.r; b.dx *= -1; }
        if (b.x + b.r > width) { b.x = width - b.r; b.dx *= -1; }
        if (b.y - b.r < 0) { b.y = b.r; b.dy *= -1; }
        if (b.y + b.r > height) { b.y = height - b.r; b.dy *= -1; }

        // Draw
        ctx.beginPath();
        ctx.arc(b.x, b.y, b.r, 0, Math.PI * 2);
        ctx.fillStyle = b.color;
        ctx.shadowColor = b.color;
        ctx.shadowBlur = 16;
        ctx.fill();
        ctx.shadowBlur = 0;
        }
        requestAnimationFrame(animate);
      }
      animate();
      })();

    // ✅ Mobile Filter Toggle System
    const mobileFilterToggle = document.getElementById('mobileFilterToggle');
    const mobileFilterPanel = document.getElementById('mobileFilterPanel');
    const closeMobileFilter = document.getElementById('closeMobileFilter');

    // Open mobile filter
    mobileFilterToggle?.addEventListener('click', function() {
      mobileFilterPanel.classList.add('active');
      document.body.style.overflow = 'hidden'; // Prevent background scrolling
    });

    // Close mobile filter
    function closeMobileFilterPanel() {
      mobileFilterPanel.classList.remove('active');
      document.body.style.overflow = ''; // Restore scrolling
    }

    closeMobileFilter?.addEventListener('click', closeMobileFilterPanel);

    // Close on overlay click
    mobileFilterPanel?.addEventListener('click', function(e) {
      if (e.target === mobileFilterPanel) {
        closeMobileFilterPanel();
      }
    });

    // Close on escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && mobileFilterPanel.classList.contains('active')) {
        closeMobileFilterPanel();
      }
    });

    // ✅ Enhanced Rating System
    document.querySelectorAll('.rate-stars').forEach(function(starsContainer) {
      const productId = starsContainer.dataset.productId;
      const stars = starsContainer.querySelectorAll('.star-button');
      const avgRatingElement = starsContainer.querySelector('.avg-rating');

      stars.forEach(function(star, index) {
        star.addEventListener('click', function() {
          const rating = parseInt(star.dataset.rating);
          
          // Send rating to server
          fetch('../rate/rate_product.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              product_id: productId,
              rating: rating
            })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Update stars display
              updateStarsDisplay(stars, data.new_average);
              avgRatingElement.textContent = `(${data.new_average}/5)`;
              
              // Show success message
              showNotification('Rating submitted successfully!', 'success');
            } else {
              showNotification(data.message || 'Failed to submit rating', 'error');
            }
          })
          .catch(error => {
            console.error('Rating error:', error);
            showNotification('Failed to submit rating', 'error');
          });
        });

        // Hover effect
        star.addEventListener('mouseenter', function() {
          const rating = parseInt(star.dataset.rating);
          highlightStars(stars, rating);
        });
      });

      // Reset on mouse leave
      starsContainer.addEventListener('mouseleave', function() {
        const currentRating = parseFloat(avgRatingElement.textContent.match(/\(([\d.]+)/)?.[1] || 0);
        updateStarsDisplay(stars, currentRating);
      });
    });

    function highlightStars(stars, rating) {
      stars.forEach(function(star, index) {
        const starRating = index + 1;
        const icon = star.querySelector('i');
        
        if (starRating <= rating) {
          icon.className = 'fas fa-star';
        } else {
          icon.className = 'far fa-star';
        }
      });
    }

    function updateStarsDisplay(stars, average) {
      stars.forEach(function(star, index) {
        const starRating = index + 1;
        const icon = star.querySelector('i');
        
        if (starRating <= Math.floor(average)) {
          icon.className = 'fas fa-star';
        } else if (starRating - average < 1) {
          icon.className = 'fas fa-star-half-alt';
        } else {
          icon.className = 'far fa-star';
        }
      });
    }

    // ✅ Quick Sort Function
    function quickSort(sortValue) {
      const url = new URL(window.location);
      url.searchParams.set('sort', sortValue);
      url.searchParams.set('page', '1'); // Reset to first page
      window.location.href = url.toString();
    }

    // ✅ Filter Management Functions
    function removeFilter(type, value) {
      const url = new URL(window.location);
      
      if (type === 'category') {
        const categories = url.searchParams.getAll('category[]');
        const filteredCategories = categories.filter(cat => cat !== value);
        
        url.searchParams.delete('category[]');
        filteredCategories.forEach(cat => url.searchParams.append('category[]', cat));
      } else if (type === 'search') {
        url.searchParams.delete('search');
      }
      
      url.searchParams.set('page', '1'); // Reset to first page
      window.location.href = url.toString();
    }

    function clearAllFilters() {
      const url = new URL(window.location);
      url.searchParams.delete('category[]');
      url.searchParams.delete('search');
      url.searchParams.delete('sort');
      url.searchParams.delete('page');
      window.location.href = url.toString();
    }

    // ✅ Clear Filters Button for Mobile
    document.getElementById('clearFilters')?.addEventListener('click', function() {
      clearAllFilters();
    });

    // ✅ Enhanced Notification System
    function showNotification(message, type = 'info') {
      // Remove existing notifications
      const existingNotifications = document.querySelectorAll('.notification');
      existingNotifications.forEach(notif => notif.remove());

      // Create notification element
      const notification = document.createElement('div');
      notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
      
      // Set notification style based on type
      switch(type) {
        case 'success':
          notification.classList.add('bg-green-500', 'text-white');
          break;
        case 'error':
          notification.classList.add('bg-red-500', 'text-white');
          break;
        case 'warning':
          notification.classList.add('bg-yellow-500', 'text-white');
          break;
        default:
          notification.classList.add('bg-blue-500', 'text-white');
      }

      notification.innerHTML = `
        <div class="flex items-center space-x-3">
          <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
          <span>${message}</span>
          <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
          </button>
        </div>
      `;

      document.body.appendChild(notification);

      // Animate in
      setTimeout(() => {
        notification.classList.remove('translate-x-full');
      }, 100);

      // Auto remove after 5 seconds
      setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => notification.remove(), 300);
      }, 5000);
    }

    // ✅ Enhanced Image Loading with Error Handling
    document.querySelectorAll('img[loading="lazy"]').forEach(function(img) {
      img.addEventListener('error', function() {
        this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgODBDOTQuNDc3MiA4MCA5MCA4NC40NzcyIDkwIDkwVjExMEM5MCA5NS41MjI4IDk0LjQ3NzIgMTAwIDEwMCAxMDBIMTIwQzEyNS41MjMgMTAwIDEzMCA5NS41MjI4IDEzMCA5MFY4MEMxMzAgNzQuNDc3MiAxMjUuNTIzIDcwIDEyMCA3MEgxMDBDOTQuNDc3MiA3MCA5MCA3NC40NzcyIDkwIDgwWiIgZmlsbD0iIzlDQTNBRiIvPgo8L3N2Zz4K';
        this.alt = 'Image not available';
      });
    });

    // ✅ Smooth Scroll for Pagination
    document.querySelectorAll('a[href*="page="]').forEach(function(link) {
      link.addEventListener('click', function(e) {
        // Small delay to allow page load, then scroll to top
        setTimeout(() => {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 100);
      });
    });

    // ✅ Enhanced Search Input
    const searchInputs = document.querySelectorAll('input[name="search"]');
    searchInputs.forEach(function(input) {
      let searchTimeout;
      
      input.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        // Auto-submit after user stops typing for 1 second (optional)
        searchTimeout = setTimeout(() => {
          // Uncomment the line below for auto-search
          // this.closest('form').submit();
        }, 1000);
      });

      // Clear search button
      if (input.value) {
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600';
        clearBtn.innerHTML = '<i class="fas fa-times"></i>';
        clearBtn.addEventListener('click', function() {
          input.value = '';
          input.focus();
        });
        
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(clearBtn);
      }
    });

    // ✅ Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
      // Ctrl/Cmd + K to focus search
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
          searchInput.focus();
          searchInput.select();
        }
      }
      
      // Escape to clear search
      if (e.key === 'Escape') {
        const searchInput = document.querySelector('input[name="search"]:focus');
        if (searchInput) {
          searchInput.value = '';
          searchInput.blur();
        }
      }
    });

