<?php
session_start();
include './php/db.php';
include './php/auth_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to access the dashboard.";
    header("Location: index.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id, $conn);

// If user data not found, log them out
if (!$user) {
    session_destroy();
    $_SESSION['error'] = "Your account could not be found. Please log in again.";
    header("Location: index.php");
    exit();
}

// Get current tab from URL parameter or default to appropriate tab based on role
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : '';

// Define role-specific tabs
$role_tabs = [
    'admin' => [
        ['id' => 'users', 'name' => 'Users']
    ],
    'sales' => [
        ['id' => 'available_clients', 'name' => 'Available Clients'],
        ['id' => 'current_clients', 'name' => 'Current Clients'],
        ['id' => 'editors', 'name' => 'Editors']
    ],
    'editor' => [
        ['id' => 'tasks', 'name' => 'Tasks'],
        ['id' => 'sales_agents', 'name' => 'Sales Agents'],
        ['id' => 'printing_operators', 'name' => 'Printing Operators']
    ],
    'operator' => [
        ['id' => 'tasks', 'name' => 'Tasks']
    ],
    'client' => []
];

// Get tabs for current user role
$user_tabs = $role_tabs[$user['role']] ?? [];

// If no tab is selected, default to the first tab for the user's role
if (empty($current_tab) && !empty($user_tabs)) {
    $current_tab = $user_tabs[0]['id'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | ADOMee$</title>
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
    .notification {
      @apply fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-all duration-300 translate-x-full;
    }
    .notification.show {
      @apply translate-x-0;
    }
    .notification-success {
      @apply bg-success text-white;
    }
    .notification-error {
      @apply bg-danger text-white;
    }
    .tab {
      @apply px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-secondary dark:hover:text-secondary-light transition-colors;
    }
    .tab-active {
      @apply text-secondary dark:text-secondary-light border-b-2 border-secondary dark:border-secondary-light;
    }
    .tab {
  @apply px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-secondary dark:hover:text-secondary-light transition-colors relative;
}
.tab-active {
  @apply text-secondary dark:text-secondary-light font-medium;
}

/* Improved card styling */
.card {
  @apply bg-white dark:bg-primary-light p-6 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 border border-gray-200 dark:border-gray-700;
}

/* Better table styling */
table {
  @apply min-w-full divide-y divide-gray-200 dark:divide-gray-700;
}

th {
  @apply px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider;
}

td {
  @apply px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300;
}

/* Improved button styling */
.btn-danger {
  @apply bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center;
}
  /* Add these to your existing styles */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

.animate-fade-in {
  animation: fadeIn 0.5s ease-out forwards;
}

.animate-pulse-slow {
  animation: pulse 3s ease-in-out infinite;
}

.hover-grow {
  @apply transition-transform duration-300 hover:scale-105;
}

.hover-rotate {
  @apply transition-transform duration-500 hover:rotate-12;
}

.hover-shake:hover {
  animation: shake 0.5s;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20%, 60% { transform: translateX(-3px); }
  40%, 80% { transform: translateX(3px); }
}

.card-hover {
  @apply transition-all duration-500 hover:shadow-xl hover:-translate-y-1 hover:scale-[1.01];
}

.button-pop {
  @apply transition-all duration-300 transform hover:scale-105 active:scale-95;
}

.gradient-text {
  @apply bg-clip-text text-transparent bg-gradient-to-r from-secondary to-accent;
}

/* Enhanced tab animations */
.tab {
  @apply relative overflow-hidden;
}

.tab::after {
  content: '';
  @apply absolute bottom-0 left-0 w-0 h-0.5 bg-accent transition-all duration-500;
}

.tab:hover::after {
  @apply w-full;
}

.tab-active::after {
  @apply w-full bg-secondary dark:bg-secondary-light;
}
.ripple-effect {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.4);
  transform: scale(0);
  animation: ripple 0.6s linear;
  pointer-events: none;
}

@keyframes ripple {
  to {
    transform: scale(4);
    opacity: 0;
  }
}

.btn-ripple {
  position: relative;
  overflow: hidden;
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
    
    // Profile Modal Functions
    function openProfileModal() {
      const modal = document.getElementById('profile-modal');
      const modalContent = document.getElementById('profile-modal-content');
      
      // Show modal with animation
      modal.classList.remove('hidden');
      setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
      }, 10);
      
      // Set dark mode toggle state
      const darkModeToggle = document.getElementById('dark-mode-toggle');
      if (darkModeToggle) {
        darkModeToggle.checked = document.documentElement.classList.contains('dark');
      }
    }
    
    function closeProfileModal() {
      const modal = document.getElementById('profile-modal');
      const modalContent = document.getElementById('profile-modal-content');
      
      modalContent.classList.remove('scale-100', 'opacity-100');
      modalContent.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 300);
    }
    
    // Settings Modal Functions
    function openSettingsModal() {
      const modal = document.getElementById('settings-modal');
      const modalContent = document.getElementById('settings-modal-content');
      
      // Show modal with animation
      modal.classList.remove('hidden');
      setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
      }, 10);
      
      // Set dark mode toggle state
      const darkModeToggle = document.getElementById('dark-mode-toggle');
      if (darkModeToggle) {
        darkModeToggle.checked = document.documentElement.classList.contains('dark');
      }
    }
    
    function closeSettingsModal() {
      const modal = document.getElementById('settings-modal');
      const modalContent = document.getElementById('settings-modal-content');
      
      modalContent.classList.remove('scale-100', 'opacity-100');
      modalContent.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 300);
    }
    
    // Handle profile form submission
    document.addEventListener('DOMContentLoaded', function() {
      const profileForm = document.getElementById('profile-form');
      if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const username = document.getElementById('profile-username').value;
          const password = document.getElementById('profile-password').value;
          const confirmPassword = document.getElementById('profile-confirm-password').value;
          
          // Validate passwords match if both are provided
          if (password && password !== confirmPassword) {
            showNotification('Passwords do not match', 'error');
            return;
          }
          
          // Create form data
          const formData = new FormData();
          formData.append('username', username);
          if (password) {
            formData.append('password', password);
          }
          
          // Send AJAX request to update profile
          fetch('./php/update_profile.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showNotification(data.message || 'Profile updated successfully', 'success');
              closeProfileModal();
              
              // Update username in header if changed
              if (data.username) {
                const usernameElements = document.querySelectorAll('.user-username');
                usernameElements.forEach(el => {
                  el.textContent = data.username;
                });
              }
            } else {
              showNotification(data.message || 'Failed to update profile', 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while updating your profile', 'error');
          });
        });
      }
    });
  </script>
</head>

<body class="bg-light dark:bg-dark min-h-screen flex flex-col transition-colors duration-300">
  <!-- Header -->
<header class="bg-white dark:bg-primary-light shadow-md transition-all duration-300">
  <div class="container mx-auto px-4 py-4">
    <div class="flex justify-between items-center mb-4">
      <div class="flex items-center space-x-4 group">
        <img src="assets/logo.png" alt="ADOMee$ Logo" class="h-10 transition-transform duration-500 group-hover:rotate-12">
        <h1 class="text-2xl font-bold text-secondary transition-all duration-300 group-hover:text-accent">ADOMee$</h1>
      </div>
      
      <div class="flex items-center space-x-6">
        <!-- Theme Toggle Button -->
        <button onclick="toggleDarkMode()" class="p-2 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all duration-300 transform hover:scale-110 hover:rotate-12 group">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-800 dark:text-yellow-300 group-hover:text-accent transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </button>
        
        <!-- User Profile Button -->
        <div class="relative group">
          <div class="flex items-center space-x-2 bg-gray-100 dark:bg-gray-700 px-4 py-2 rounded-full cursor-pointer transition-all duration-300 hover:bg-accent hover:text-white hover:shadow-lg hover:-translate-y-0.5">
            <span class="text-gray-700 dark:text-gray-300 group-hover:text-white transition-colors duration-300">
              <?php echo htmlspecialchars($user['username']); ?>
            </span>
            <span class="px-2 py-1 text-xs rounded-full bg-accent text-white transition-all duration-300 group-hover:bg-white group-hover:text-accent">
              <?php echo ucfirst($user['role']); ?>
            </span>
          </div>
          <!-- Dropdown Menu (hidden until hover) -->
          <div class="absolute right-0 mt-2 w-48 bg-white dark:bg-primary rounded-md shadow-lg py-1 z-50 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-1 group-hover:translate-y-0">
            <a href="#" onclick="openProfileModal(); return false;" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary-light transition-colors duration-200">Profile</a>
            <a href="#" onclick="openSettingsModal(); return false;" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary-light transition-colors duration-200">Settings</a>
          </div>
        </div>
        
        <!-- Logout Button -->
        <div class="relative group">
          <a href="auth/logout.php" class="flex items-center space-x-2 bg-gradient-to-r from-red-500 to-red-600 px-4 py-2 rounded-lg text-white shadow-md transition-all duration-300 hover:shadow-xl hover:-translate-y-0.5 hover:from-red-600 hover:to-red-700 transform hover:scale-105">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform duration-300 group-hover:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span class="hidden md:inline-block">Logout</span>
          </a>
          <span class="absolute -bottom-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap md:hidden">
            Logout
          </span>
        </div>
      </div>
    </div>
    
    <!-- Navigation tabs with improved animation -->
    <nav class="flex space-x-1 border-b border-gray-200 dark:border-gray-700">
      <?php foreach ($user_tabs as $tab): ?>
        <a href="?tab=<?php echo $tab['id']; ?>" 
           class="tab relative pb-3 px-4 transition-all duration-300 group <?php echo ($current_tab === $tab['id']) ? 'tab-active' : ''; ?>">
          <span class="relative z-10"><?php echo $tab['name']; ?></span>
          <?php if ($current_tab === $tab['id']): ?>
            <span class="absolute bottom-0 left-0 w-full h-0.5 bg-secondary dark:bg-secondary-light transition-all duration-300"></span>
          <?php else: ?>
            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-secondary dark:bg-secondary-light transition-all duration-300 group-hover:w-full"></span>
          <?php endif; ?>
          <span class="absolute inset-x-0 -bottom-px h-px w-full bg-gradient-to-r from-transparent via-accent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

  <!-- Main Content -->
  <main class="flex-grow container mx-auto p-6">
    <?php if ($current_tab === 'users' && $user['role'] === 'admin'): ?>
<!-- Users Tab (Admin only) -->
<div class="card mb-8">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
    <h2 class="text-2xl font-bold text-secondary">User Management</h2>
    
    <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
      <div class="relative flex-grow">
        <input type="text" id="user-search" placeholder="Search users..." 
               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute right-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      
      <select id="role-filter" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
        <option value="">All Roles</option>
        <option value="admin">Admin</option>
        <option value="client">Client</option>
        <option value="sales">Sales</option>
        <option value="editor">Editor</option>
        <option value="operator">Operator</option>
      </select>
    </div>
  </div>
  
  <div class="overflow-x-auto">
    <table class="min-w-full bg-white dark:bg-primary-light rounded-lg overflow-hidden">
      <thead class="bg-gray-100 dark:bg-primary">
        <tr>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Username</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody id="user-table-body" class="divide-y divide-gray-200 dark:divide-gray-700">
        <?php
        $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id != ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($other_user = $result->fetch_assoc()):
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-primary" data-user-id="<?php echo $other_user['id']; ?>" data-role="<?php echo $other_user['role']; ?>">
          <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300" data-search="<?php echo strtolower(htmlspecialchars($other_user['username'])); ?>">
            <?php echo htmlspecialchars($other_user['username']); ?>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300" data-search="<?php echo strtolower(htmlspecialchars($other_user['email'])); ?>">
            <?php echo htmlspecialchars($other_user['email']); ?>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <select class="role-select bg-transparent border border-gray-300 dark:border-gray-600 rounded px-2 py-1 text-gray-700 dark:text-gray-300" 
                    data-user-id="<?php echo $other_user['id']; ?>"
                    data-original-role="<?php echo $other_user['role']; ?>"
                    onchange="updateUserRole(this)">
              <?php
              $roles = ['client', 'sales', 'editor', 'operator'];
              foreach ($roles as $role) {
                $selected = ($role === $other_user['role']) ? 'selected' : '';
                echo "<option value=\"$role\" $selected>" . ucfirst($role) . "</option>";
              }
              ?>
            </select>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <button onclick="deleteUser(<?php echo $other_user['id']; ?>)" 
                    class="text-danger hover:text-danger-dark focus:outline-none flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Delete
            </button>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

    <?php if ($current_tab === 'available_clients' && $user['role'] === 'sales'): ?>
    <!-- Available Clients Tab (Sales only) -->
    <div class="card mb-8">
      <h2 class="text-2xl font-bold text-secondary mb-4">Available Clients</h2>
      <p class="text-gray-700 dark:text-gray-300">This section will display available clients for sales agents.</p>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'current_clients' && $user['role'] === 'sales'): ?>
    <!-- Current Clients Tab (Sales only) -->
    <div class="card mb-8">
      <h2 class="text-2xl font-bold text-secondary mb-4">Current Clients</h2>
      <p class="text-gray-700 dark:text-gray-300">This section will display current clients for sales agents.</p>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'editors' && $user['role'] === 'sales'): ?>
    <!-- Editors Tab (Sales only) -->
    <div class="card mb-8">
      <h2 class="text-2xl font-bold text-secondary mb-4">Editors</h2>
      <p class="text-gray-700 dark:text-gray-300">This section will display editors for sales agents.</p>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'tasks' && ($user['role'] === 'editor' || $user['role'] === 'operator')): ?>
    <!-- Tasks Tab (Editor and Operator only) -->
    <div class="card mb-8">
      <h2 class="text-2xl font-bold text-secondary mb-4">Tasks</h2>
      <p class="text-gray-700 dark:text-gray-300">This section will display tasks for editors and operators.</p>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'sales_agents' && $user['role'] === 'editor'): ?>
    <!-- Sales Agents Tab (Editor only) -->
    <div class="card mb-8">
      <h2 class="text-2xl font-bold text-secondary mb-4">Sales Agents</h2>
      <p class="text-gray-700 dark:text-gray-300">This section will display sales agents for editors.</p>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'printing_operators' && $user['role'] === 'editor'): ?>
    <!-- Printing Operators Tab (Editor only) -->
    <div class="card mb-8">
      <h2 class="text-2xl font-bold text-secondary mb-4">Printing Operators</h2>
      <p class="text-gray-700 dark:text-gray-300">This section will display printing operators for editors.</p>
    </div>
    <?php endif; ?>
  </main>

  <!-- Footer -->
  <footer class="bg-white dark:bg-primary-light text-center py-6 text-sm border-t border-gray-200 dark:border-gray-700">
    <div class="container mx-auto">
      <p class="text-gray-600 dark:text-gray-300">&copy; 2025 ADOMee$. All rights reserved.</p>
    </div>
  </footer>

  <!-- Notification Container -->
  <div id="notification-container"></div>

  <!-- Profile Modal -->
  <div id="profile-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-primary-light rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="profile-modal-content">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-bold text-secondary">Profile Settings</h3>
          <button onclick="closeProfileModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <form id="profile-form" class="space-y-4">
          <div>
            <label for="profile-username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
            <input type="text" id="profile-username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" 
                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Username must be unique.</p>
          </div>
          
          <div>
            <label for="profile-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input type="email" id="profile-email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white bg-gray-100 dark:bg-gray-700" disabled>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Email cannot be changed.</p>
          </div>
          
          <div>
            <label for="profile-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
            <input type="password" id="profile-password" name="password" 
                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave blank to keep current password.</p>
          </div>
          
          <div>
            <label for="profile-confirm-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
            <input type="password" id="profile-confirm-password" name="confirm_password" 
                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
          </div>
          
          <div class="flex justify-end space-x-3 mt-6">
            <button type="button" onclick="closeProfileModal()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
              Cancel
            </button>
            <button type="submit" class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-dark transition-colors">
              Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Settings Modal -->
  <div id="settings-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-primary-light rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="settings-modal-content">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-bold text-secondary">Settings</h3>
          <button onclick="closeSettingsModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <div class="space-y-6">
          <!-- Theme Settings -->
          <div>
            <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-3">Theme</h4>
            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-primary rounded-lg">
              <span class="text-gray-700 dark:text-gray-300">Dark Mode</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="dark-mode-toggle" class="sr-only peer" onchange="toggleDarkMode()">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 dark:peer-focus:ring-accent/30 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-accent"></div>
              </label>
            </div>
          </div>
          
          <!-- Quality of Life Settings -->
          <div>
            <h4 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-3">Quality of Life</h4>
            
            <div class="space-y-3">
              <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-primary rounded-lg">
                <span class="text-gray-700 dark:text-gray-300">Notifications</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 dark:peer-focus:ring-accent/30 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-accent"></div>
                </label>
              </div>
              
              <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-primary rounded-lg">
                <span class="text-gray-700 dark:text-gray-300">Auto-save</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 dark:peer-focus:ring-accent/30 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-accent"></div>
                </label>
              </div>
              
              <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-primary rounded-lg">
                <span class="text-gray-700 dark:text-gray-300">Keyboard shortcuts</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 dark:peer-focus:ring-accent/30 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-accent"></div>
                </label>
              </div>
              
              <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-primary rounded-lg">
                <span class="text-gray-700 dark:text-gray-300">Compact view</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 dark:peer-focus:ring-accent/30 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-accent"></div>
                </label>
              </div>
            </div>
          </div>
          
          <div class="flex justify-end mt-6">
            <button onclick="closeSettingsModal()" class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-dark transition-colors">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmation-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-primary-light rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="modal-content">
      <div class="p-6">
        <h3 class="text-xl font-bold text-secondary mb-4" id="modal-title">Confirm Action</h3>
        <p class="text-gray-700 dark:text-gray-300 mb-6" id="modal-message"></p>
        <div class="flex justify-end space-x-3">
          <button id="modal-cancel" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
            Cancel
          </button>
          <button id="modal-confirm" class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-dark transition-colors">
            Confirm
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Function to show notifications
    function showNotification(message, type = 'success') {
      const container = document.getElementById('notification-container');
      const notification = document.createElement('div');
      notification.className = `notification notification-${type}`;
      notification.textContent = message;
      
      container.appendChild(notification);
      
      // Trigger reflow to enable animation
      notification.offsetHeight;
      
      // Show notification
      notification.classList.add('show');
      
      // Hide and remove after 3 seconds
      setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
          container.removeChild(notification);
        }, 300);
      }, 3000);
    }

    // Custom confirmation modal
    function showConfirmationModal(title, message, onConfirm) {
      const modal = document.getElementById('confirmation-modal');
      const modalContent = document.getElementById('modal-content');
      const modalTitle = document.getElementById('modal-title');
      const modalMessage = document.getElementById('modal-message');
      const confirmButton = document.getElementById('modal-confirm');
      const cancelButton = document.getElementById('modal-cancel');
      
      // Set modal content
      modalTitle.textContent = title;
      modalMessage.textContent = message;
      
      // Show modal with animation
      modal.classList.remove('hidden');
      setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
      }, 10);
      
      // Handle confirm button click
      const handleConfirm = () => {
        hideModal();
        onConfirm();
      };
      
      // Handle cancel button click
      const handleCancel = () => {
        hideModal();
      };
      
      // Remove previous event listeners
      confirmButton.removeEventListener('click', handleConfirm);
      cancelButton.removeEventListener('click', handleCancel);
      
      // Add new event listeners
      confirmButton.addEventListener('click', handleConfirm);
      cancelButton.addEventListener('click', handleCancel);
    }
    
    // Hide modal function
    function hideModal() {
      const modal = document.getElementById('confirmation-modal');
      const modalContent = document.getElementById('modal-content');
      
      modalContent.classList.remove('scale-100', 'opacity-100');
      modalContent.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 300);
    }

    function updateUserRole(selectElement) {
      const userId = selectElement.dataset.userId;
      const newRole = selectElement.value;
      const originalRole = selectElement.getAttribute('data-original-role');
      
      // Store the original role in case we need to revert
      if (!originalRole) {
        selectElement.setAttribute('data-original-role', newRole);
      }
      
      // Check if the new role is the same as the current role
      if (newRole === originalRole) {
        showNotification('User already has this role', 'info');
        return;
      }
      
      // Show custom confirmation dialog
      showConfirmationModal(
        'Confirm Role Change',
        `Are you sure you want to change this user's role from ${originalRole} to ${newRole}?`,
        () => {
          // Show loading state
          selectElement.disabled = true;
          
          fetch('./php/update_role.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}&role=${newRole}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Show success message
              showNotification(data.message || 'Role updated successfully', 'success');
              // Refresh the page after a short delay
              setTimeout(() => {
                window.location.reload();
              }, 1000);
            } else {
              // Show error message and revert selection
              showNotification(data.message || 'Failed to update role', 'error');
              selectElement.value = selectElement.getAttribute('data-original-role');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while updating the role. Please try again.', 'error');
            selectElement.value = selectElement.getAttribute('data-original-role');
          })
          .finally(() => {
            // Re-enable select element
            selectElement.disabled = false;
          });
        }
      );
      
      // If user cancels, revert the selection
      selectElement.value = selectElement.getAttribute('data-original-role');
    }

    function deleteUser(userId) {
      showConfirmationModal(
        'Confirm User Deletion',
        'Are you sure you want to delete this user? This action cannot be undone.',
        () => {
          // Find the row to be deleted
          const row = document.querySelector(`tr[data-user-id="${userId}"]`);
          if (!row) return;
          
          // Show loading state
          const deleteButton = row.querySelector('button');
          if (deleteButton) {
            deleteButton.disabled = true;
            deleteButton.textContent = 'Deleting...';
          }
          
          fetch('./php/delete_user.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Show success message
              showNotification('User deleted successfully', 'success');
              // Refresh the page after a short delay
              setTimeout(() => {
                window.location.reload();
              }, 1000);
            } else {
              showNotification(data.message || 'Failed to delete user', 'error');
              // Reset button state
              if (deleteButton) {
                deleteButton.disabled = false;
                deleteButton.textContent = 'Delete';
              }
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while deleting the user. Please try again.', 'error');
            // Reset button state
            if (deleteButton) {
              deleteButton.disabled = false;
              deleteButton.textContent = 'Delete';
            }
          });
        }
      );
    }

    // Search and filter functionality
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('user-search');
      const roleFilter = document.getElementById('role-filter');
      const userRows = document.querySelectorAll('#user-table-body tr');
      
      function filterUsers() {
        const searchTerm = searchInput.value.toLowerCase();
        const roleValue = roleFilter.value;
        
        userRows.forEach(row => {
          const usernameCell = row.querySelector('td[data-search]');
          const emailCell = row.querySelector('td:nth-child(2)');
          
          if (!usernameCell || !emailCell) return;
          
          const usernameMatch = usernameCell.dataset.search.includes(searchTerm);
          const emailMatch = emailCell.dataset.search.includes(searchTerm);
          const roleMatch = roleValue === '' || row.dataset.role === roleValue;
          
          if ((usernameMatch || emailMatch) && roleMatch) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
      }
      
      if (searchInput && roleFilter) {
        searchInput.addEventListener('input', filterUsers);
        roleFilter.addEventListener('change', filterUsers);
      }
    });

    document.addEventListener('DOMContentLoaded', () => {
      // Add fade-in animation to all cards
      document.querySelectorAll('.card').forEach((card, index) => {
        card.style.opacity = '0';
        card.style.animation = `fadeIn 0.5s ease-out ${index * 0.1}s forwards`;
      });
      
      // Add hover effects to all buttons
      document.querySelectorAll('button, a.btn, .btn').forEach(btn => {
        btn.classList.add('button-pop');
      });
      
      // Add ripple effect to buttons
      document.querySelectorAll('.btn-ripple').forEach(btn => {
        btn.addEventListener('click', function(e) {
          const x = e.clientX - e.target.getBoundingClientRect().left;
          const y = e.clientY - e.target.getBoundingClientRect().top;
          
          const ripple = document.createElement('span');
          ripple.classList.add('ripple-effect');
          ripple.style.left = `${x}px`;
          ripple.style.top = `${y}px`;
          
          this.appendChild(ripple);
          
          setTimeout(() => {
            ripple.remove();
          }, 1000);
        });
      });
    });

    // Add this to your admin dashboard script
    document.querySelectorAll('#user-table-body tr').forEach(row => {
      row.style.transform = 'translateX(-10px)';
      row.style.opacity = '0';
      row.style.transition = 'all 0.3s ease-out';
      
      setTimeout(() => {
        row.style.transform = 'translateX(0)';
        row.style.opacity = '1';
      }, 100);
      
      // Add hover effect
      row.addEventListener('mouseenter', () => {
        row.style.transform = 'scale(1.01)';
        row.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
      });
      
      row.addEventListener('mouseleave', () => {
        row.style.transform = 'scale(1)';
        row.style.boxShadow = 'none';
      });
    });
  </script>

</body>

</html>