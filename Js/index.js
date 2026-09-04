sessionStorage.setItem("baseAPIUrl", "../../api");

let captchaAnswer = 0;
const Toast = Swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 1500,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.addEventListener("mouseenter", Swal.stopTimer);
    toast.addEventListener("mouseleave", Swal.resumeTimer);
  },
});

// Password validation function
function validatePassword(password) {
  const errors = [];

  if (password.length < 8) {
    errors.push("At least 8 characters");
  }

  if (!/[A-Z]/.test(password)) {
    errors.push("At least one uppercase letter");
  }

  if (!/[a-z]/.test(password)) {
    errors.push("At least one lowercase letter");
  }

  if (!/\d/.test(password)) {
    errors.push("At least one number");
  }

  if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
    errors.push("At least one special character");
  }

  return {
    isValid: errors.length === 0,
    errors: errors,
  };
}
document.addEventListener("DOMContentLoaded", function () {
  window.Swal = Swal;
  setupPasswordToggle();
  setupForgotPassword();
  setupAccountStatus();
  setupCaptcha();
  setupFormValidation();

  // Focus the school ID field on page load
  const schoolIdInput = document.getElementById("school_id");
  if (schoolIdInput) {
    setTimeout(() => schoolIdInput.focus(), 500);
  }
});

const setupPasswordToggle = () => {
  const togglePassword = document.querySelector("#togglePassword");
  const password = document.querySelector("#password");

  if (!togglePassword || !password) return;

  togglePassword.addEventListener("click", function () {
    const type =
      password.getAttribute("type") === "password" ? "text" : "password";
    password.setAttribute("type", type);

    // Update icon
    const icon = this.querySelector("i");
    if (icon) {
      if (type === "password") {
        icon.className = "bi bi-eye-slash-fill text-muted";
      } else {
        icon.className = "bi bi-eye-fill text-muted";
      }
    }
  });
};

const setupForgotPassword = () => {
  const forgotPasswordLink = document.getElementById("forgotPasswordLink");
  if (!forgotPasswordLink) return;

  forgotPasswordLink.addEventListener("click", function (e) {
    e.preventDefault();
    window.location.href = "forgot_password.html";
  });
};

const setupAccountStatus = () => {
  const accountStatusBtn = document.getElementById("accountStatusBtn");
  const checkStatusBtn = document.getElementById("checkStatusBtn");
  const statusSchoolId = document.getElementById("statusSchoolId");
  const accountStatusPopup = document.getElementById("accountStatusPopup");
  const closeStatusPopup = document.getElementById("closeStatusPopup");

  if (!accountStatusBtn) return;

  // Toggle popup when info button is clicked
  accountStatusBtn.addEventListener("click", function (e) {
    e.stopPropagation();

    if (accountStatusPopup.classList.contains("d-none")) {
      // Show popup
      accountStatusPopup.classList.remove("d-none");

      // Clear previous results
      const resultDiv = document.getElementById("accountStatusResult");
      const statusContent = document.getElementById("statusContent");
      if (resultDiv) resultDiv.classList.add("d-none");
      if (statusContent) statusContent.innerHTML = "";
      if (statusSchoolId) statusSchoolId.value = "";

      // Focus on input
      setTimeout(() => {
        if (statusSchoolId) statusSchoolId.focus();
      }, 100);
    } else {
      // Hide popup
      accountStatusPopup.classList.add("d-none");
    }
  });

  // Close popup when close button is clicked
  if (closeStatusPopup) {
    closeStatusPopup.addEventListener("click", function () {
      accountStatusPopup.classList.add("d-none");
    });
  }

  // Close popup when clicking outside
  document.addEventListener("click", function (e) {
    if (
      !accountStatusPopup.contains(e.target) &&
      !accountStatusBtn.contains(e.target)
    ) {
      accountStatusPopup.classList.add("d-none");
    }
  });

  // Handle check status button
  if (checkStatusBtn) {
    checkStatusBtn.addEventListener("click", checkAccountStatus);
  }

  // Handle Enter key in school ID input
  if (statusSchoolId) {
    statusSchoolId.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        checkAccountStatus();
      }
    });
  }
};

const setupCaptcha = () => {
  const refreshCaptchaBtn = document.getElementById("refreshCaptcha");
  if (!refreshCaptchaBtn) return;

  generateCaptcha();
  refreshCaptchaBtn.addEventListener("click", generateCaptcha);
};

const generateCaptcha = () => {
  // Use simpler numbers (1-5) to make it easier
  const num1 = Math.floor(Math.random() * 5) + 1;
  const num2 = Math.floor(Math.random() * 5) + 1;
  captchaAnswer = num1 + num2;

  // Update the display
  const captchaNum1 = document.getElementById("captchaNum1");
  const captchaNum2 = document.getElementById("captchaNum2");
  const captchaInput = document.getElementById("captchaInput");

  if (captchaNum1) captchaNum1.textContent = num1;
  if (captchaNum2) captchaNum2.textContent = num2;
  if (captchaInput) {
    captchaInput.value = "";
    captchaInput.style.borderColor = ""; // Reset border color
  }

  // Focus the input field for better UX
  setTimeout(() => {
    if (captchaInput) captchaInput.focus();
  }, 100);
};

const setupFormValidation = () => {
  const loginForm = document.getElementById("loginForm");
  const captchaInput = document.getElementById("captchaInput");

  if (!loginForm || !captchaInput) return;

  // Only allow numbers in CAPTCHA input
  captchaInput.addEventListener("input", function (e) {
    // Remove any non-digit characters
    this.value = this.value.replace(/[^0-9]/g, "");

    // Validate CAPTCHA in real-time
    validateCaptcha();
  });

  loginForm.addEventListener("submit", async function (e) {
    e.preventDefault();
    // console.log('Form submitted, checking captcha...'); // Debug log

    if (validateCaptcha()) {
      // console.log('Captcha valid, proceeding to login...'); // Debug log
      await loginUser();
    } else {
      // console.log('Captcha invalid, showing error...'); // Debug log
      // Show SweetAlert for wrong captcha
      if (captchaInput.value.trim() !== "") {
        showAlert("Incorrect Answer", "Please try again.", "error");
        generateCaptcha();
      } else {
        showAlert(
          "Security Check",
          "Please complete the simple math problem to continue.",
          "info",
        );
      }
    }
  });
};

const validateCaptcha = () => {
  const captchaInput = document.getElementById("captchaInput");

  if (!captchaInput) return false;

  // Reset border and clear any previous error messages
  captchaInput.style.borderColor = "";

  // If empty, just return false without showing error
  if (captchaInput.value.trim() === "") {
    return false;
  }

  const userAnswer = parseInt(captchaInput.value.trim());

  // Validate if it's a number
  if (isNaN(userAnswer)) {
    captchaInput.style.borderColor = "#dc3545"; // Red for invalid
    return false;
  }

  // Check if answer is correct (exact match required)
  if (userAnswer === captchaAnswer) {
    captchaInput.style.borderColor = "#198754"; // Green for correct
    return true;
  } else {
    captchaInput.style.borderColor = "#dc3545"; // Red for wrong
    return false;
  }
};

const loginUser = async () => {
  // Get form values
  const schoolId = document.getElementById("school_id")?.value.trim();
  const password = document.getElementById("password")?.value;
  const captchaInput = document.getElementById("captchaInput");
  const userAnswer = parseInt(captchaInput?.value.trim());

  // Validate inputs
  if (!schoolId || !password) {
    showAlert(
      "Missing Information",
      "Please enter both school ID and password to continue.",
      "warning",
    );
    return;
  }

  // Captcha is already validated in form submission, so we can proceed

  try {
    setLoading(true);
    hideError();

    const loginData = {
      school_id: schoolId,
      password: password,
    };

    // console.log('Making login request to:', ${sessionStorage.getItem('baseAPIUrl')}/auth/login.php);
    // console.log('Login data:', loginData);

    const response = await axios({
      url: `${sessionStorage.getItem("baseAPIUrl")}/auth/login.php`,
      method: "POST",
      data: loginData,
      headers: {
        "Content-Type": "application/json",
      },
      withCredentials: true, // CRITICAL: Enable cookies for session management
    });

    // console.log('Login response:', response);
    // console.log('Login response data:', response.data);

    if (response.data && response.data.success) {
      // Store user info in sessionStorage
      sessionStorage.setItem("user", JSON.stringify(response.data.user));

      // console.log('Login response data:', response.data);
      // console.log('Requires password change:', response.data.requires_password_change);
      // console.log('Type of requires_password_change:', typeof response.data.requires_password_change);
      // console.log('User role:', response.data.user.role_name);
      // console.log('User ID:', response.data.user.user_id);

      // Check if user needs to change password (first-time login)
      if (response.data.requires_password_change === true) {
        // console.log('Showing password change prompt');
        await showPasswordChangePrompt(response.data.user);
      } else {
        // console.log('No password change required, proceeding with normal login');
        // Show success toast and redirect
        await Toast.fire({
          icon: "success",
          title: `Welcome, ${response.data.user.full_name}!`,
          timer: 1500,
          didClose: () => {
            // Convert relative path to absolute path
            const redirectUrl = response.data.redirect.startsWith("/")
              ? response.data.redirect
              : "/csdl_student_loan/" +
                response.data.redirect.replace("../../", "");
            window.location.href = redirectUrl;
          },
        });
        // Redirect after a short delay in case the toast is not shown
        setTimeout(() => {
          // Convert relative path to absolute path
          const redirectUrl = response.data.redirect.startsWith("/")
            ? response.data.redirect
            : "/csdl_student_loan/" +
              response.data.redirect.replace("../../", "");
          window.location.href = redirectUrl;
        }, 1600);
      }
    } else {
      // console.error('Login failed. Response data:', response.data);
      throw new Error(
        response.data?.message || "Login failed - no success flag"
      );
    }
  } catch (error) {
    handleLoginError(error);
  } finally {
    setLoading(false);
  }
};

const showAlert = (title, message, type = "error") => {
  return Swal.fire({
    title: title,
    text: message,
    icon: type,
    confirmButtonColor: "#198754",
    confirmButtonText: "OK",
    allowOutsideClick: false,
  });
};

const handleLoginError = (error) => {
  let errorTitle = "Login Failed";
  let errorMessage = "An error occurred during login. Please try again.";
  let errorType = "error";

  if (error.response) {
    // Only log unexpected errors, not normal failed login attempts
    if (error.response.status >= 500) {
      // console.error('Server error during login:', error.response.status, error.response.data);
    }

    if (error.response.status === 401) {
      errorMessage =
        error.response.data?.message ||
        "Invalid school ID or password. Please check your credentials and try again.";
      errorType = "warning";
    } else if (error.response.status === 403) {
      errorMessage =
        error.response.data?.message ||
        "Account is inactive. Please contact support for assistance.";
      errorType = "warning";
    } else if (error.response.status === 423) {
      errorMessage =
        error.response.data?.message ||
        "Account is temporarily locked due to too many failed attempts.";
      errorType = "warning";
    } else if (error.response.status === 404) {
      errorMessage =
        error.response.data?.message ||
        "School ID not found. Please check your School ID and try again.";
      errorType = "warning";
    } else if (error.response.data?.message) {
      errorMessage = error.response.data.message;
      if (error.response.data.message.toLowerCase().includes("captcha")) {
        errorType = "info";
      } else if (
        error.response.data.message.toLowerCase().includes("attempts remaining")
      ) {
        errorType = "warning";
      }
    }
  } else if (error.request) {
    errorMessage =
      "No response from server. Please check your internet connection and try again.";
    // console.error('Network error during login:', error.request);
  } else if (error.message) {
    errorMessage = error.message;
    // console.error('Unexpected error during login:', error.message);
  }

  // Show error message using our helper function
  showAlert(errorTitle, errorMessage, errorType);

  // Generate new captcha for security
  generateCaptcha();

  // Clear password field for security
  const passwordInput = document.getElementById("password");
  if (passwordInput) {
    passwordInput.value = "";
  }
};

const showError = (message) => {
  const errorDiv = document.getElementById("errorMessage");
  const errorText = document.getElementById("errorText");

  if (errorDiv && errorText) {
    errorText.textContent = message;
    errorDiv.classList.remove("d-none");

    setTimeout(() => {
      hideError();
    }, 5000);
  } else {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: message,
      confirmButtonText: "OK",
    });
  }
};

const hideError = () => {
  const errorDiv = document.getElementById("errorMessage");
  if (errorDiv) {
    errorDiv.classList.add("d-none");
  }
};

const setLoading = (isLoading) => {
  const button = document.getElementById("loginButton");
  const buttonText = document.getElementById("loginButtonText");
  const spinner = document.getElementById("loginSpinner");

  if (button && buttonText && spinner) {
    if (isLoading) {
      button.disabled = true;
      buttonText.textContent = "Signing in...";
      spinner.classList.remove("d-none");
    } else {
      button.disabled = false;
      buttonText.textContent = "Sign In";
      spinner.classList.add("d-none");
    }
  }
};

// Function to show password change prompt for first-time users
const showPasswordChangePrompt = async (user) => {
  const result = await Swal.fire({
    title: "Change Your Password",
    html: `
            <div class="text-start">
                <p class="mb-3">Welcome, <strong>${user.full_name}</strong>!</p>
                <p class="mb-3">For security reasons, you must change your default password before proceeding.</p>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <input id="swalNewPassword" type="password" class="form-control" placeholder="Enter new password" />
                        <button class="btn btn-outline-secondary" type="button" id="swalToggleNewPassword" onclick="toggleSwalPassword('swalNewPassword', 'swalToggleNewPassword')">
                            <i class="bi bi-eye" id="swalNewPasswordIcon"></i>
                        </button>
                    </div>
                    <div class="form-text">
                        <small class="text-muted">Password must contain: 8+ characters, uppercase, lowercase, number, and special character</small>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input id="swalConfirmPassword" type="password" class="form-control" minlength="6" placeholder="Confirm new password" />
                        <button class="btn btn-outline-secondary" type="button" id="swalToggleConfirmPassword" onclick="toggleSwalPassword('swalConfirmPassword', 'swalToggleConfirmPassword')">
                            <i class="bi bi-eye" id="swalConfirmPasswordIcon"></i>
                        </button>
                    </div>
                </div>
                <div id="swalPasswordValidation" class="mt-2" style="display: none;">
                    <div class="alert alert-warning alert-sm mb-0">
                        <small id="swalValidationMessage"></small>
                    </div>
                </div>
            </div>
        `,
    focusConfirm: false,
    showCancelButton: false,
    confirmButtonText: "Change Password",
    customClass: {
      popup: "swal2-custom-popup",
      actions: "d-flex gap-2",
      confirmButton: "btn btn-primary px-4",
    },
    buttonsStyling: false,
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      // Add real-time validation
      const newPasswordInput = document.getElementById("swalNewPassword");
      const confirmPasswordInput = document.getElementById(
        "swalConfirmPassword",
      );
      const validationDiv = document.getElementById("swalPasswordValidation");
      const validationMessage = document.getElementById(
        "swalValidationMessage",
      );

      const validatePasswords = () => {
        const newPassword = newPasswordInput.value.trim();
        const confirmPassword = confirmPasswordInput.value.trim();

        if (newPassword.length > 0) {
          const passwordValidation = validatePassword(newPassword);
          if (!passwordValidation.isValid) {
            validationDiv.style.display = "block";
            validationMessage.textContent =
              "Password requirements: " + passwordValidation.errors.join(", ");
            return false;
          }
        }

        if (confirmPassword.length > 0 && newPassword !== confirmPassword) {
          validationDiv.style.display = "block";
          validationMessage.textContent = "Passwords do not match";
          return false;
        } else if (
          newPassword.length > 0 &&
          confirmPassword.length > 0 &&
          newPassword === confirmPassword
        ) {
          const passwordValidation = validatePassword(newPassword);
          if (passwordValidation.isValid) {
            validationDiv.style.display = "none";
            return true;
          }
        } else {
          validationDiv.style.display = "none";
          return false;
        }
      };

      newPasswordInput.addEventListener("input", validatePasswords);
      confirmPasswordInput.addEventListener("input", validatePasswords);
    },
    preConfirm: async () => {
      const newPassword = (
        document.getElementById("swalNewPassword")?.value || ""
      ).trim();
      const confirmPassword = (
        document.getElementById("swalConfirmPassword")?.value || ""
      ).trim();

      // Enhanced validation
      if (!newPassword) {
        Swal.showValidationMessage("Please enter a new password");
        return false;
      }

      // Validate password strength
      const passwordValidation = validatePassword(newPassword);
      if (!passwordValidation.isValid) {
        Swal.showValidationMessage(
          "Password requirements: " + passwordValidation.errors.join(", "),
        );
        return false;
      }

      if (!confirmPassword) {
        Swal.showValidationMessage("Please confirm your password");
        return false;
      }

      if (newPassword !== confirmPassword) {
        Swal.showValidationMessage("Passwords do not match");
        return false;
      }

      // Check for common weak passwords
      const weakPasswords = [
        "password",
        "123456",
        "password123",
        "admin",
        "user",
        "csdlstaff123",
        "student123",
      ];
      if (weakPasswords.includes(newPassword.toLowerCase())) {
        Swal.showValidationMessage("Please choose a stronger password");
        return false;
      }

      // Call password change API (different endpoints for staff vs students)
      try {
        // console.log('Changing password for user:', user.user_id, 'Role:', user.role_name);

        let apiUrl, requestData;

        if (user.role_name === "Student") {
          // Use student API endpoint
          apiUrl = `${sessionStorage.getItem("baseAPIUrl")}/csdl/students.php?action=change_password`;
          requestData = {
            student_id: user.user_id,
            new_password: newPassword,
          };
        } else {
          // Use staff API endpoint
          apiUrl = `${sessionStorage.getItem("baseAPIUrl")}/csdl/employees.php?action=change_password`;
          requestData = {
            employee_id: user.user_id,
            password: newPassword,
          };
        }

        const response = await axios({
          url: apiUrl,
          method: "POST",
          data: requestData,
          withCredentials: true, // CRITICAL: Enable cookies for session management
        });

        // console.log('Password change response:', response);

        if (response.data && response.data.success) {
          return true;
        } else {
          Swal.showValidationMessage(
            response.data?.message || "Failed to change password",
          );
          return false;
        }
      } catch (error) {
        // console.error('Password change error:', error);
        Swal.showValidationMessage(
          "Error changing password. Please try again.",
        );
        return false;
      }
    },
  });

  if (result.isConfirmed) {
    // Show success message and redirect
    await Swal.fire({
      icon: "success",
      title: "Password Changed!",
      text: "Your password has been updated successfully. Please log in again.",
      timer: 2000,
      showConfirmButton: false,
    });

    // Redirect back to login page
    window.location.href = "/csdl_student_loan/views/auth/login.html";
  }
};

// Function to toggle password visibility in SweetAlert
function toggleSwalPassword(inputId, buttonId) {
  const passwordInput = document.getElementById(inputId);
  const toggleIcon = document.getElementById(inputId + "Icon");

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    toggleIcon.className = "bi bi-eye-slash";
  } else {
    passwordInput.type = "password";
    toggleIcon.className = "bi bi-eye";
  }
}

// Function to check account status
const checkAccountStatus = async () => {
  const statusSchoolId = document.getElementById("statusSchoolId");
  const checkStatusBtn = document.getElementById("checkStatusBtn");
  const resultDiv = document.getElementById("accountStatusResult");
  const statusContent = document.getElementById("statusContent");

  if (!statusSchoolId || !checkStatusBtn || !resultDiv || !statusContent)
    return;

  const schoolId = statusSchoolId.value.trim();

  if (!schoolId) {
    showAlert(
      "Missing Information",
      "Please enter a Student ID to check loan status.",
      "warning",
    );
    return;
  }

  try {
    // Set loading state
    checkStatusBtn.disabled = true;
    checkStatusBtn.innerHTML =
      '<i class="bi bi-hourglass-split me-1"></i>Checking...';

    const response = await axios({
      url: `${sessionStorage.getItem("baseAPIUrl")}/auth/check-account-status.php`,
      method: "POST",
      data: { school_id: schoolId },
      headers: {
        "Content-Type": "application/json",
      },
    });

    // console.log('Account status response:', response.data);

    if (response.data && response.data.found) {
      const data = response.data;
      let loanStatusText = "No loan applications found";
      let loanBadgeClass = "bg-secondary";
      if (data.account_type === "student" && data.loans) {
        if (Array.isArray(data.loans.recent) && data.loans.recent.length > 0) {
          const latest = data.loans.recent[0];
          loanStatusText = latest?.status || "Unknown";
          loanBadgeClass = /approved/i.test(loanStatusText)
            ? "bg-success"
            : /rejected|denied/i.test(loanStatusText)
              ? "bg-danger"
              : "bg-warning";
        } else if (data.loans.count > 0) {
          loanStatusText = `${data.loans.count} application(s)`;
          loanBadgeClass = "bg-info";
        }
      }
      let html = `
                <div class="text-center">
                    <div class="small text-muted mb-1">Loan Status</div>
                    <span class="badge ${loanBadgeClass} px-3 py-2" style="font-size:0.95rem;">${loanStatusText}</span>
                </div>
            `;
      if (
        data.account_type === "student" &&
        data.loans &&
        data.loans.count > 0
      ) {
        html += `
                    <div class="mt-3">
                        <div class="small text-muted mb-1">Applications: <strong>${data.loans.count}</strong></div>
                `;
        if (Array.isArray(data.loans.recent) && data.loans.recent.length > 0) {
          html += `<div class="list-group list-group-flush">`;
          data.loans.recent.forEach((loan) => {
            const amt = (Number(loan?.amount) || 0).toLocaleString("en-PH", {
              minimumFractionDigits: 2,
            });
            const date = loan?.date
              ? new Date(loan.date).toLocaleDateString("en-PH", {
                  year: "numeric",
                  month: "short",
                  day: "numeric",
                })
              : "N/A";
            const status = loan?.status || "Unknown";
            const badgeClass = /approved/i.test(status)
              ? "bg-success"
              : /rejected|denied/i.test(status)
                ? "bg-danger"
                : "bg-warning";
            html += `
                            <div class="list-group-item px-2 py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-success">₱ ${amt}</div>
                                    <div class="text-muted small">${date}</div>
                                </div>
                                <span class="badge ${badgeClass}">${status}</span>
                            </div>
                        `;
          });
          html += `</div>`;
        }
        html += `</div>`;
      }
      statusContent.innerHTML = html;
      resultDiv.classList.remove("d-none");
    } else if (response.data && response.data.error) {
      statusContent.innerHTML = `
                <div class="text-center text-danger">
                    <i class="bi bi-exclamation-triangle fs-4 mb-2"></i>
                    <p class="mb-0">${response.data.error}</p>
                </div>
            `;
      resultDiv.classList.remove("d-none");
    } else {
      statusContent.innerHTML = `
                <div class="text-center text-muted">
                    <i class="bi bi-exclamation-circle fs-4 mb-2"></i>
                    <p class="mb-0">${response.data?.message || "No account found with this Student ID"}</p>
                </div>
            `;
      resultDiv.classList.remove("d-none");
    }
  } catch (error) {
    // console.error('Account status check error:', error);
    // console.error('Error response:', error.response);
    // console.error('Error message:', error.message);

    let errorMessage = "Failed to check account status. Please try again.";

    if (error.response?.data?.error) {
      errorMessage = error.response.data.error;
    } else if (error.message) {
      errorMessage = error.message;
    }

    statusContent.innerHTML = `
            <div class="text-center text-danger">
                <i class="bi bi-exclamation-triangle fs-4 mb-2"></i>
                <p class="mb-0">${errorMessage}</p>
            </div>
        `;
    resultDiv.classList.remove("d-none");
  } finally {
    // Reset button state
    checkStatusBtn.disabled = false;
    checkStatusBtn.innerHTML = '<i class="bi bi-search me-1"></i>Check';
  }
};