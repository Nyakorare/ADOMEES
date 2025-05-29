<?php
session_start();
include './php/db.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Display error/success messages if they exist
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error']);
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ADOMee$</title>
  <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: {
              DEFAULT: '#1E293B', // Slate 800
              light: '#334155',   // Slate 700
              dark: '#0F172A',    // Slate 900
            },
            secondary: {
              DEFAULT: '#3B82F6', // Blue 500
              light: '#60A5FA',   // Blue 400
              dark: '#2563EB',    // Blue 600
            },
            accent: {
              DEFAULT: '#8B5CF6', // Violet 500
              light: '#A78BFA',   // Violet 400
              dark: '#7C3AED',    // Violet 600
            },
            light: {
              DEFAULT: '#F8FAFC', // Slate 50
              dark: '#E2E8F0',    // Slate 200
            },
            dark: {
              DEFAULT: '#0F172A', // Slate 900
              light: '#1E293B',   // Slate 800
            },
            success: '#10B981',   // Emerald 500
            warning: '#F59E0B',   // Amber 500
            danger: '#EF4444',    // Red 500
            info: '#3B82F6',      // Blue 500
          },
        }
      }
    }
  </script>
  <style>
    .btn {
      @apply px-4 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-opacity-50;
    }
    .btn-primary {
      @apply bg-secondary text-white hover:bg-secondary-dark focus:ring-secondary;
    }
    .card {
      @apply bg-white dark:bg-primary-light p-6 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border border-gray-200 dark:border-gray-600 backdrop-blur-sm;
    }
  </style>
  <script>
    // Check for saved theme preference, otherwise use system preference
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }

    // Function to toggle dark mode
    function toggleDarkMode() {
      if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark')
        localStorage.theme = 'light'
      } else {
        document.documentElement.classList.add('dark')
        localStorage.theme = 'dark'
      }
    }
  </script>
</head>

<body class="bg-light dark:bg-dark min-h-screen flex flex-col transition-colors duration-300">
  <!-- Header -->
  <header class="bg-white dark:bg-primary-light shadow-md">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
      <div class="flex items-center">
        <img src="assets/logo.png" alt="ADOMee$ Logo" class="h-10 mr-2">
        <h1 class="text-2xl font-bold text-secondary">ADOMee$</h1>
      </div>
      <div class="flex items-center space-x-4">
        <button onclick="toggleDarkMode()" class="p-2 rounded-full bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-800 dark:text-yellow-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="flex-grow container mx-auto p-6 flex justify-between items-center">
    <!-- Logo Section (Left Side) -->
    <div class="hidden md:block w-1/2 flex justify-center">
      <img src="assets/logo.png" alt="ADOMee$ Logo" class="max-w-md">
    </div>
    
    <!-- Login Section (Right Side) -->
    <div class="w-full md:w-1/2 flex justify-center">
      <div class="max-w-md w-full">
        <?php if ($error): ?>
          <div class="bg-danger text-white p-4 rounded-lg mb-6">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="bg-success text-white p-4 rounded-lg mb-6">
            <?php echo htmlspecialchars($success); ?>
          </div>
        <?php endif; ?>

        <div class="card">
          <h2 class="text-2xl font-bold text-secondary mb-6 text-center" id="form-title">Login</h2>
          <form action="auth/login.php" method="POST" id="login-form">
            <div class="mb-4">
              <label for="username" class="block text-gray-700 dark:text-gray-300 mb-2">Username</label>
              <input type="text" id="username" name="username" required
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
            </div>
            <div class="mb-6">
              <label for="password" class="block text-gray-700 dark:text-gray-300 mb-2">Password</label>
              <input type="password" id="password" name="password" required
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
            </div>
            <div class="flex justify-center">
              <button type="submit" class="btn bg-accent hover:bg-accent-dark text-white font-bold py-3 px-8 rounded-lg shadow-lg transform transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-opacity-50">
                Login
              </button>
            </div>
          </form>
          
          <!-- Registration Form (Hidden by default) -->
          <form action="auth/register.php" method="POST" id="register-form" class="hidden">
            <div class="mb-4">
              <label for="reg-username" class="block text-gray-700 dark:text-gray-300 mb-2">Username</label>
              <input type="text" id="reg-username" name="username" required
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
            </div>
            <div class="mb-4">
              <label for="reg-email" class="block text-gray-700 dark:text-gray-300 mb-2">Email</label>
              <input type="email" id="reg-email" name="email" required
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
            </div>
            <div class="mb-6">
              <label for="reg-password" class="block text-gray-700 dark:text-gray-300 mb-2">Password</label>
              <input type="password" id="reg-password" name="password" required
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
              <!-- Password strength meter -->
              <div class="mt-2">
                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                  <div id="password-strength-meter" class="h-full w-0 transition-all duration-300"></div>
                </div>
                <div id="password-requirements" class="mt-2 text-sm">
                  <p class="text-gray-600 dark:text-gray-400">Password must contain:</p>
                  <ul class="list-disc list-inside space-y-1">
                    <li id="length-check" class="text-red-500">At least 8 characters</li>
                    <li id="uppercase-check" class="text-red-500">One uppercase letter</li>
                    <li id="lowercase-check" class="text-red-500">One lowercase letter</li>
                    <li id="number-check" class="text-red-500">One number</li>
                    <li id="special-check" class="text-red-500">One special character (!@#$%^&*()-_=+{};:,<.>)</li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="flex justify-center">
              <button type="submit" class="btn bg-accent hover:bg-accent-dark text-white font-bold py-3 px-8 rounded-lg shadow-lg transform transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-opacity-50">
                Register
              </button>
            </div>
          </form>
          
          <div class="mt-4 text-center">
            <p class="text-gray-600 dark:text-gray-400">
              <span id="toggle-text">Don't have an account?</span>
              <a href="#" id="toggle-form" class="text-accent hover:text-accent-dark font-medium">Sign Up</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-white dark:bg-primary-light text-center py-6 text-sm border-t border-gray-200 dark:border-gray-700">
    <div class="container mx-auto">
      <p class="text-gray-600 dark:text-gray-300">&copy; 2025 ADOMee$. All rights reserved.</p>
    </div>
  </footer>
  
  <script>
    // Form toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
      const loginForm = document.getElementById('login-form');
      const registerForm = document.getElementById('register-form');
      const toggleForm = document.getElementById('toggle-form');
      const formTitle = document.getElementById('form-title');
      const toggleText = document.getElementById('toggle-text');
      
      // Password validation elements
      const passwordInput = document.getElementById('reg-password');
      const strengthMeter = document.getElementById('password-strength-meter');
      const lengthCheck = document.getElementById('length-check');
      const uppercaseCheck = document.getElementById('uppercase-check');
      const lowercaseCheck = document.getElementById('lowercase-check');
      const numberCheck = document.getElementById('number-check');
      const specialCheck = document.getElementById('special-check');
      
      // Password validation function
      function validatePassword(password) {
        const checks = {
          length: password.length >= 8,
          uppercase: /[A-Z]/.test(password),
          lowercase: /[a-z]/.test(password),
          number: /[0-9]/.test(password),
          special: /[!@#$%^&*()\-_=+{};:,<.>]/.test(password)
        };
        
        // Update requirement indicators
        lengthCheck.className = checks.length ? 'text-green-500' : 'text-red-500';
        uppercaseCheck.className = checks.uppercase ? 'text-green-500' : 'text-red-500';
        lowercaseCheck.className = checks.lowercase ? 'text-green-500' : 'text-red-500';
        numberCheck.className = checks.number ? 'text-green-500' : 'text-red-500';
        specialCheck.className = checks.special ? 'text-green-500' : 'text-red-500';
        
        // Calculate strength
        const strength = Object.values(checks).filter(Boolean).length;
        const strengthPercentage = (strength / 5) * 100;
        
        // Update strength meter
        strengthMeter.style.width = `${strengthPercentage}%`;
        
        // Update strength meter color
        if (strengthPercentage <= 20) {
          strengthMeter.className = 'h-full w-0 transition-all duration-300 bg-red-500';
        } else if (strengthPercentage <= 40) {
          strengthMeter.className = 'h-full w-0 transition-all duration-300 bg-orange-500';
        } else if (strengthPercentage <= 60) {
          strengthMeter.className = 'h-full w-0 transition-all duration-300 bg-yellow-500';
        } else if (strengthPercentage <= 80) {
          strengthMeter.className = 'h-full w-0 transition-all duration-300 bg-blue-500';
        } else {
          strengthMeter.className = 'h-full w-0 transition-all duration-300 bg-green-500';
        }
        
        return strength === 5;
      }
      
      // Add password input event listener
      passwordInput.addEventListener('input', function() {
        validatePassword(this.value);
      });
      
      // Form submission validation
      registerForm.addEventListener('submit', function(e) {
        if (!validatePassword(passwordInput.value)) {
          e.preventDefault();
          alert('Please ensure your password meets all requirements.');
        }
      });
      
      toggleForm.addEventListener('click', function(e) {
        e.preventDefault();
        
        if (loginForm.classList.contains('hidden')) {
          // Switch to login form
          loginForm.classList.remove('hidden');
          registerForm.classList.add('hidden');
          formTitle.textContent = 'Login';
          toggleText.textContent = "Don't have an account?";
          toggleForm.textContent = 'Sign Up';
        } else {
          // Switch to register form
          loginForm.classList.add('hidden');
          registerForm.classList.remove('hidden');
          formTitle.textContent = 'Register';
          toggleText.textContent = 'Already have an account?';
          toggleForm.textContent = 'Login';
        }
      });
    });
  </script>
</body>

</html>