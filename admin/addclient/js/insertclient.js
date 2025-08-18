function deleteClient(id) {
  if (confirm("Are you sure you want to delete this client?")) {
    window.location.href = "delete_client.php?id=" + id;
  }
}

function openEditModal(clientId) {
  const modal = document.getElementById("editModal");
  const iframe = document.getElementById("editIframe");
  iframe.src = "edit_client.php?id=" + clientId; // adjust to your file path
  modal.classList.remove("hidden");
}

function closeEditModal() {
  const modal = document.getElementById("editModal");
  const iframe = document.getElementById("editIframe");
  iframe.src = ""; // Clear src when closing
  modal.classList.add("hidden");
}

// Enhanced form validation with visual feedback
const form = document.querySelector("form");
const inputs = form.querySelectorAll("input[required]");

inputs.forEach((input) => {
  input.addEventListener("blur", function () {
    if (this.value.trim() === "") {
      this.classList.add(
        "border-red-300",
        "focus:border-red-500",
        "focus:ring-red-500/20"
      );
      this.classList.remove(
        "border-gray-200",
        "focus:border-blue-500",
        "focus:ring-blue-500/20"
      );
      showFieldError(this, "This field is required");
    } else {
      this.classList.remove(
        "border-red-300",
        "focus:border-red-500",
        "focus:ring-red-500/20"
      );
      this.classList.add(
        "border-green-300",
        "focus:border-green-500",
        "focus:ring-green-500/20"
      );
      hideFieldError(this);
    }
  });

  input.addEventListener("input", function () {
    if (this.value.trim() !== "") {
      this.classList.remove(
        "border-red-300",
        "focus:border-red-500",
        "focus:ring-red-500/20"
      );
      this.classList.add(
        "border-green-300",
        "focus:border-green-500",
        "focus:ring-green-500/20"
      );
      hideFieldError(this);
    }
  });
});

// Email validation
const emailInput = document.getElementById("email");
emailInput.addEventListener("blur", function () {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (this.value && !emailRegex.test(this.value)) {
    this.classList.add(
      "border-red-300",
      "focus:border-red-500",
      "focus:ring-red-500/20"
    );
    this.classList.remove(
      "border-green-300",
      "focus:border-green-500",
      "focus:ring-green-500/20"
    );
    showFieldError(this, "Please enter a valid email address");
  }
});

// Phone validation
const contactInput = document.getElementById("contact");
contactInput.addEventListener("input", function () {
  // Remove non-numeric characters
  this.value = this.value.replace(/\D/g, "");

  if (this.value.length > 11) {
    this.value = this.value.slice(0, 11);
  }
});

contactInput.addEventListener("blur", function () {
  if (this.value && this.value.length !== 11) {
    this.classList.add(
      "border-red-300",
      "focus:border-red-500",
      "focus:ring-red-500/20"
    );
    this.classList.remove(
      "border-green-300",
      "focus:border-green-500",
      "focus:ring-green-500/20"
    );
    showFieldError(this, "Contact number must be exactly 11 digits");
  }
});

// Show field error
function showFieldError(input, message) {
  hideFieldError(input); // Remove existing error first

  const errorDiv = document.createElement("div");
  errorDiv.className =
    "error-message text-red-500 text-xs mt-1 ml-1 animate-shake";
  errorDiv.innerHTML = `
                <span class="flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    ${message}
                </span>
            `;

  input.parentNode.appendChild(errorDiv);
}

// Hide field error
function hideFieldError(input) {
  const existingError = input.parentNode.querySelector(".error-message");
  if (existingError) {
    existingError.remove();
  }
}

// Form submission with enhanced feedback
form.addEventListener("submit", function (e) {
  e.preventDefault();

  let isValid = true;
  inputs.forEach((input) => {
    if (input.value.trim() === "") {
      input.classList.add("animate-shake");
      showFieldError(input, "This field is required");
      isValid = false;

      setTimeout(() => {
        input.classList.remove("animate-shake");
      }, 500);
    }
  });

  // Additional email validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (emailInput.value && !emailRegex.test(emailInput.value)) {
    isValid = false;
  }

  // Additional phone validation
  if (contactInput.value && contactInput.value.length !== 11) {
    isValid = false;
  }

  if (isValid) {
    showSubmissionLoader();

    // Submit form data
    const formData = new FormData(this);

    fetch("submit_form.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.text())
      .then((data) => {
        hideSubmissionLoader();
        showSuccessMessage();
        form.reset();

        // Reset input styles
        inputs.forEach((input) => {
          input.classList.remove(
            "border-green-300",
            "focus:border-green-500",
            "focus:ring-green-500/20"
          );
          input.classList.add(
            "border-gray-200",
            "focus:border-blue-500",
            "focus:ring-blue-500/20"
          );
        });

        // Reload page after 2 seconds to show updated client list
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      })
      .catch((error) => {
        hideSubmissionLoader();
        showErrorMessage("An error occurred. Please try again.");
        console.error("Error:", error);
      });
  }
});

// Show submission loader
function showSubmissionLoader() {
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;

  submitBtn.disabled = true;
  submitBtn.innerHTML = `
                <span class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Adding Client...
                </span>
            `;
}

// Hide submission loader
function hideSubmissionLoader() {
  const submitBtn = form.querySelector('button[type="submit"]');
  submitBtn.disabled = false;
  submitBtn.innerHTML = `
                <span class="flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Client
                </span>
            `;
}

// Show success message
function showSuccessMessage() {
  const successDiv = document.createElement("div");
  successDiv.className =
    "fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-2xl z-50 animate-slide-up";
  successDiv.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="font-semibold">Client added successfully!</span>
                </div>
            `;

  document.body.appendChild(successDiv);

  setTimeout(() => {
    successDiv.remove();
  }, 4000);
}

// Show error message
function showErrorMessage(message) {
  const errorDiv = document.createElement("div");
  errorDiv.className =
    "fixed top-4 right-4 bg-red-500 text-white px-6 py-4 rounded-lg shadow-2xl z-50 animate-slide-up";
  errorDiv.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="font-semibold">${message}</span>
                </div>
            `;

  document.body.appendChild(errorDiv);

  setTimeout(() => {
    errorDiv.remove();
  }, 4000);
}

// Keyboard shortcuts
document.addEventListener("keydown", function (e) {
  // Focus on name input with Ctrl+N
  if (e.ctrlKey && e.key === "n") {
    e.preventDefault();
    document.getElementById("name").focus();
  }
});

// Enhanced table interactions
document.addEventListener("DOMContentLoaded", function () {
  // Add hover effects to table rows
  const tableRows = document.querySelectorAll(".table-row");
  tableRows.forEach((row) => {
    row.addEventListener("mouseenter", function () {
      this.style.transform = "scale(1.01)";
    });

    row.addEventListener("mouseleave", function () {
      this.style.transform = "scale(1)";
    });
  });

  // Auto-focus first input
  const firstInput = document.getElementById("name");
  if (firstInput) {
    firstInput.focus();
  }

  // Add loading animation to buttons
  const buttons = document.querySelectorAll("button");
  buttons.forEach((button) => {
    button.addEventListener("click", function () {
      if (!this.disabled) {
        this.style.transform = "scale(0.98)";
        setTimeout(() => {
          this.style.transform = "scale(1)";
        }, 150);
      }
    });
  });
});

// Format phone number display
function formatPhoneNumber(number) {
  if (number.length === 11) {
    return `${number.slice(0, 4)} ${number.slice(4, 7)} ${number.slice(7)}`;
  }
  return number;
}

// Auto-save form data to prevent data loss
let autoSaveTimeout;
inputs.forEach((input) => {
  input.addEventListener("input", function () {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
      const formData = {};
      inputs.forEach((inp) => {
        formData[inp.name] = inp.value;
      });

      // Store in memory (since localStorage is not available)
      window.tempFormData = formData;
    }, 1000);
  });
});

// Restore form data if available
if (window.tempFormData) {
  inputs.forEach((input) => {
    if (window.tempFormData[input.name]) {
      input.value = window.tempFormData[input.name];
    }
  });
}
