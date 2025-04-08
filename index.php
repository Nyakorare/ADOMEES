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
  <title>ADOMee$ - Login</title>
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
</head>

<body class="bg-light dark:bg-dark min-h-screen flex flex-col transition-colors duration-300">
  <!-- Header -->
  <header class="bg-white dark:bg-primary-light shadow-md">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
      <div class="flex items-center">
        <h1 class="text-2xl font-bold text-secondary">ADOMee$</h1>
      </div>
      <div class="flex items-center space-x-4">
        <span class="text-gray-700 dark:text-gray-300">Document Management System</span>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="flex-grow container mx-auto p-6 flex justify-center items-center">
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
        <h2 class="text-2xl font-bold text-secondary mb-6 text-center">Login</h2>
        <form action="auth/login.php" method="POST">
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
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-white dark:bg-primary-light text-center py-6 text-sm border-t border-gray-200 dark:border-gray-700">
    <div class="container mx-auto">
      <p class="text-gray-600 dark:text-gray-300">&copy; 2025 ADOMee$. All rights reserved.</p>
    </div>
  </footer>
</body>

</html>