<?php
session_start();
include './php/db.php';
include './php/auth_functions.php';
include './php/document_functions.php';

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
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-secondary">Available Clients</h2>
        <div class="relative">
          <input type="text" id="client-search" placeholder="Search clients..." 
                 class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute right-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
    </div>
      </div>
      
      <div class="overflow-x-auto">
        <table class="min-w-full bg-white dark:bg-primary-light rounded-lg overflow-hidden">
          <thead class="bg-gray-100 dark:bg-primary">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Client</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Document Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody id="available-clients-table" class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php
            $available_documents = getAvailableDocuments($conn);
            while ($document = $available_documents->fetch_assoc()):
            ?>
            <tr>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['client_name']); ?></div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['title']); ?></div>
                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($document['description']); ?></div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                  Available
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button onclick="acceptClient(<?php echo $document['id']; ?>)" class="text-accent hover:text-accent-dark">
                  Accept Client
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'current_clients' && $user['role'] === 'sales'): ?>
    <!-- Current Clients Tab (Sales only) -->
    <div class="card mb-8">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-secondary">Current Clients</h2>
        <div class="relative">
          <input type="text" id="current-client-search" placeholder="Search clients..." 
                 class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute right-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>
      
      <div class="overflow-x-auto">
        <table class="min-w-full bg-white dark:bg-primary-light rounded-lg overflow-hidden">
          <thead class="bg-gray-100 dark:bg-primary">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Client</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Document Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Current Stage</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Updated</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody id="current-clients-table" class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php
            $documents = getSalesAgentDocuments($_SESSION['user_id'], $conn);
            while ($document = $documents->fetch_assoc()):
              switch ($document['current_stage']) {
                case 'sales_review':
                  $stageClass = 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
                  $stageText = 'Sales Agent Review';
                  break;
                case 'editor_polishing':
                  $stageClass = 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200';
                  $stageText = 'Editor Polishing';
                  break;
                case 'printing_document':
                  $stageClass = 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200';
                  $stageText = 'Printing Document';
                  break;
                case 'payment_pending':
                  $stageClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                  $stageText = 'Payment Pending';
                  break;
                case 'finished':
                  $stageClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                  $stageText = 'Finished';
                  break;
              }
            ?>
            <tr>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['client_name']); ?></div>
                            </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['title']); ?></div>
                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($document['description']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $stageClass; ?>">
                  <?php echo $stageText; ?>
                                </span>
                            </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                <?php echo date('M d, Y H:i', strtotime($document['updated_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button onclick="openDocumentDetailsModal(<?php echo $document['id']; ?>)" class="text-accent hover:text-accent-dark mr-3">
                                    View Details
                                </button>
                <?php if ($document['current_stage'] === 'sales_review'): ?>
                  <button onclick="assignToEditor(<?php echo $document['id']; ?>)" class="text-blue-600 hover:text-blue-800 mr-3">
                    Assign to Editor
                  </button>
                <?php endif; ?>
                <?php if ($document['current_stage'] === 'payment_pending'): ?>
                  <button onclick="requestPayment(<?php echo $document['id']; ?>)" class="text-blue-600 hover:text-blue-800 mr-3">
                    Request Payment
                  </button>
                <?php endif; ?>
                <?php if ($document['current_stage'] === 'payment_pending' && $document['payment_status'] === 'accepted'): ?>
                  <button onclick="markAsFinished(<?php echo $document['id']; ?>)" class="text-green-600 hover:text-green-800">
                    Mark as Finished
                  </button>
                <?php endif; ?>
                            </td>
                        </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'editors' && $user['role'] === 'sales'): ?>
    <!-- Editors Tab -->
    <div class="bg-white dark:bg-primary-light shadow rounded-lg p-6 animate-fade">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Editors</h2>
        <div class="relative">
          <input type="text" id="editorSearch" placeholder="Search editors..." class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent/20 dark:bg-primary dark:border-gray-700 dark:text-white">
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-primary">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Current Tasks</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody id="editors-table" class="bg-white dark:bg-primary-light divide-y divide-gray-200 dark:divide-gray-700">
            <?php
            $stmt = $conn->prepare("
              SELECT u.*, 
                (SELECT COUNT(*) FROM document_workflow w WHERE w.editor_id = u.id AND w.current_stage = 'editor_polishing') as current_tasks
              FROM users u 
              WHERE u.role = 'editor'
              ORDER BY current_tasks ASC
            ");
            $stmt->execute();
            $editors = $stmt->get_result();
            while ($editor = $editors->fetch_assoc()):
              $status = $editor['is_available'] && $editor['current_tasks'] < 3 ? 'Available' : 'Not Available';
              $statusClass = $status === 'Available' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-primary transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($editor['username']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($editor['email']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo $editor['current_tasks']; ?> active document(s)</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                  <?php echo $status; ?>
                </span>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'printing_operators' && $user['role'] === 'editor'): ?>
    <!-- Printing Operators Tab (Editor only) -->
    <div class="bg-white dark:bg-primary-light shadow rounded-lg p-6 animate-fade">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Available Printing Operators</h2>
        <div class="relative">
          <input type="text" id="operatorSearch" placeholder="Search operators..." class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent/20 dark:bg-primary dark:border-gray-700 dark:text-white">
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-primary">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Current Tasks</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-primary-light divide-y divide-gray-200 dark:divide-gray-700">
            <?php
            $stmt = $conn->prepare("
              SELECT u.*, 
                (SELECT COUNT(*) FROM document_workflow w WHERE w.operator_id = u.id AND w.current_stage = 'printing_document') as current_tasks
              FROM users u 
              WHERE u.role = 'operator'
              ORDER BY current_tasks ASC
            ");
            $stmt->execute();
            $operators = $stmt->get_result();
            while ($operator = $operators->fetch_assoc()):
              $status = $operator['is_available'] && $operator['current_tasks'] < 3 ? 'Available' : 'Not Available';
              $statusClass = $status === 'Available' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-primary transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($operator['username']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($operator['email']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo $operator['current_tasks']; ?> active document(s)</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                  <?php echo $status; ?>
                </span>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'tasks' && ($user['role'] === 'editor' || $user['role'] === 'operator')): ?>
    <!-- Tasks Tab (Editor and Operator only) -->
    <div class="bg-white dark:bg-primary-light shadow rounded-lg p-6 animate-fade">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">My Tasks</h2>
        <div class="flex items-center space-x-4">
          <div class="flex items-center">
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" id="availabilityToggle" class="sr-only peer" <?php echo $user['is_available'] ? 'checked' : ''; ?>>
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 dark:peer-focus:ring-accent/20 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-accent"></div>
              <span class="ml-3 text-sm font-medium text-gray-900 dark:text-white" id="availabilityStatus">
                <?php echo $user['is_available'] ? 'Available' : 'Not Available'; ?>
              </span>
            </label>
          </div>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-primary">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assigned By</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assigned Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-primary-light divide-y divide-gray-200 dark:divide-gray-700">
            <?php
            $documents = [];
            if ($user['role'] === 'editor') {
                $documents = getEditorDocuments($_SESSION['user_id'], $conn);
            } else {
                $documents = getOperatorDocuments($_SESSION['user_id'], $conn);
            }
            
            while ($document = $documents->fetch_assoc()):
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-primary transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['client_name']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['title']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full <?php echo getStageColor($document['current_stage']); ?>">
                  <?php echo formatStage($document['current_stage']); ?>
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                <?php 
                if ($user['role'] === 'editor') {
                    echo htmlspecialchars($document['sales_agent_name']);
                } else {
                    echo htmlspecialchars($document['editor_name']);
                }
                ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                <?php echo date('M d, Y', strtotime($document['assigned_at'])); ?>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button onclick="openDocumentDetailsModal(<?php echo $document['id']; ?>)" class="text-accent hover:text-accent-dark mr-3">
                  View Details
                </button>
                <?php if ($user['role'] === 'editor' && $document['current_stage'] === 'editor_polishing'): ?>
                  <button onclick="assignToOperator(<?php echo $document['id']; ?>)" class="text-blue-600 hover:text-blue-800">
                    Assign to Operator
                  </button>
                <?php endif; ?>
                <?php if ($user['role'] === 'operator' && $document['current_stage'] === 'printing_document'): ?>
                  <button onclick="uploadPrintReceipt(<?php echo $document['id']; ?>)" class="text-blue-600 hover:text-blue-800">
                    Upload Receipt
                  </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($current_tab === 'sales_agents' && $user['role'] === 'editor'): ?>
    <!-- Sales Agents Tab (Editor only) -->
    <div class="card mb-8">
      <h2 class="text-2xl font-bold text-secondary mb-4">Sales Agents</h2>
      <p class="text-gray-700 dark:text-gray-300">This section will display sales agents for editors.</p>
    </div>
    <?php endif; ?>

    <?php if ($_SESSION['role'] !== 'admin'): ?>
    <!-- Document Management Section -->
    <div class="bg-white dark:bg-primary-light rounded-lg shadow-lg p-6 mb-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-secondary">Document Management</h2>
        <?php if ($_SESSION['role'] === 'client'): ?>
        <button onclick="openUploadDocumentModal()" class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-dark transition-colors">
          Upload New Document
        </button>
        <?php endif; ?>
      </div>

      <!-- Document List -->
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-primary">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Current Stage</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Updated</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-primary-light divide-y divide-gray-200 dark:divide-gray-700">
            <?php
            require_once 'php/document_functions.php';
            
            $documents = [];
            switch ($_SESSION['role']) {
              case 'client':
                $documents = getClientDocuments($_SESSION['user_id'], $conn);
                break;
              case 'sales':
                $documents = getSalesAgentDocuments($_SESSION['user_id'], $conn);
                break;
              case 'editor':
                $documents = getEditorDocuments($_SESSION['user_id'], $conn);
                break;
              case 'operator':
                $documents = getOperatorDocuments($_SESSION['user_id'], $conn);
                break;
            }
            
            if (empty($documents)) {
              echo '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No documents found</td></tr>';
            } else {
              foreach ($documents as $document) {
                $statusClass = '';
                switch ($document['status']) {
                  case 'pending':
                    $statusClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                    break;
                  case 'in_progress':
                    $statusClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                    break;
                  case 'completed':
                    $statusClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                    break;
                  case 'cancelled':
                    $statusClass = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                    break;
                }
                
                $stageClass = '';
                switch ($document['current_stage']) {
                  case 'sales_review':
                    $stageClass = 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
                    $stageText = 'Sales Agent Review';
                    break;
                  case 'editor_polishing':
                    $stageClass = 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200';
                    $stageText = 'Editor Polishing';
                    break;
                  case 'printing_document':
                    $stageClass = 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200';
                    $stageText = 'Printing Document';
                    break;
                  case 'payment_pending':
                    $stageClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                    $stageText = 'Payment Pending';
                    break;
                  case 'finished':
                    $stageClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                    $stageText = 'Finished';
                    break;
                }
                ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['title']); ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($document['description']); ?></div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $stageClass; ?>">
                      <?php echo $stageText; ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <?php echo date('M d, Y H:i', strtotime($document['updated_at'])); ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button onclick="openDocumentDetailsModal(<?php echo $document['id']; ?>)" class="text-accent hover:text-accent-dark mr-3">
                      View Details
                    </button>
                    <?php if ($_SESSION['role'] === 'sales' && $document['current_stage'] === 'payment_pending'): ?>
                        <button onclick="requestPayment(<?php echo $document['id']; ?>)" class="text-blue-600 hover:text-blue-800 mr-3">
                            Request Payment
                    </button>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] === 'client' && $document['current_stage'] === 'payment_pending'): ?>
                        <button onclick="acceptPayment(<?php echo $document['id']; ?>)" class="text-green-600 hover:text-green-800 mr-3">
                            Accept Payment
                    </button>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] === 'sales' && $document['current_stage'] === 'payment_pending' && $document['payment_status'] === 'accepted'): ?>
                        <button onclick="markAsFinished(<?php echo $document['id']; ?>)" class="text-green-600 hover:text-green-800">
                            Mark as Finished
                    </button>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php
              }
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Notifications Button -->
    <button onclick="openNotificationsModal()" class="fixed bottom-6 right-6 bg-accent text-white rounded-full p-4 shadow-lg hover:bg-accent-dark transition-colors">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
    </button>
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

  <!-- Document Management Modals -->
  <!-- Upload Document Modal -->
  <div id="upload-document-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-primary-light rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="upload-document-modal-content">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-bold text-secondary">Upload Document</h3>
          <button onclick="closeUploadDocumentModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <form id="upload-document-form" class="space-y-4">
          <div>
            <label for="document-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
            <input type="text" id="document-title" name="title" required
                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
          </div>
          
          <div>
            <label for="document-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
            <textarea id="document-description" name="description" rows="3"
                      class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary"></textarea>
          </div>
          
          <div>
            <label for="document-file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Document File</label>
            <input type="file" id="document-file" name="document" required
                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
          </div>

          <div>
            <label for="payment-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Type</label>
            <select id="payment-type" name="payment_type" required
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
              <option value="full_payment">Full Payment</option>
              <option value="down_payment">Down Payment</option>
            </select>
          </div>
          
          <div class="flex justify-end space-x-3 mt-6">
            <button type="button" onclick="closeUploadDocumentModal()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
              Cancel
            </button>
            <button type="submit" class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-dark transition-colors">
              Upload
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Document Details Modal -->
  <div id="documentDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-primary-light">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modalTitle">Document Details</h3>
            <div class="mt-2 px-7 py-3">
                <div id="documentDetails" class="text-sm text-gray-500 dark:text-gray-400">
                    <!-- Document details will be loaded here -->
        </div>
        </div>
            <div class="items-center px-4 py-3">
                <button id="closeModal" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    Close
                </button>
      </div>
    </div>
  </div>
        </div>
        
  <!-- Payment Agreement Modal -->
  <div id="paymentAgreementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-primary-light rounded-lg shadow-xl max-w-md w-full mx-4">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-secondary">Payment Agreement</h3>
                <button onclick="closePaymentAgreementModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
            <div id="paymentAgreementContent">
                <div class="text-center py-4">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent mx-auto"></div>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Loading payment agreement...</p>
          </div>
          </div>
      </div>
    </div>
  </div>

  <!-- Notifications Modal -->
  <div id="notifications-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
      <div class="bg-white dark:bg-primary-light rounded-lg shadow-xl max-w-md w-full mx-4">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-bold text-secondary">Notifications</h3>
          <button onclick="closeNotificationsModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
              <div id="notifications-content" class="max-h-96 overflow-y-auto">
                  <div class="text-center py-4">
                      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent mx-auto"></div>
                      <p class="mt-2 text-gray-600 dark:text-gray-400">Loading notifications...</p>
                  </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Editor Assignment Modal -->
  <div id="editorAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-primary-light">
          <div class="mt-3">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Assign to Editor</h3>
              <div class="mt-2 px-7 py-3">
                  <select id="editor-select" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-primary dark:text-white focus:ring-secondary focus:border-secondary">
                      <option value="">Select an editor...</option>
                      <?php
                      $editors = getAllUsers($conn);
                      while ($editor = $editors->fetch_assoc()):
                          if ($editor['role'] === 'editor'):
                              // Get editor's current tasks
                              $editor_docs = getEditorDocuments($editor['id'], $conn);
                              $task_count = $editor_docs->num_rows;
                              $status = $task_count < 3 ? 'Available' : 'Busy';
                      ?>
                          <option value="<?php echo $editor['id']; ?>" <?php echo $task_count >= 3 ? 'disabled' : ''; ?>>
                              <?php echo htmlspecialchars($editor['username']); ?> (<?php echo $status; ?>, <?php echo $task_count; ?> tasks)
                          </option>
                      <?php
                          endif;
                      endwhile;
                      ?>
                  </select>
              </div>
              <div class="flex justify-end mt-4 space-x-3">
                  <button onclick="closeEditorAssignmentModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                      Cancel
                  </button>
                  <button onclick="confirmEditorAssignment()" class="px-4 py-2 bg-secondary text-white rounded-lg hover:bg-secondary-dark transition-colors">
                      Assign
                  </button>
              </div>
          </div>
      </div>
  </div>

  <?php if ($current_tab === 'documents' && $user['role'] === 'editor'): ?>
  <!-- Editor's Documents Tab -->
  <div class="bg-white dark:bg-primary-light shadow rounded-lg p-6 animate-fade">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">My Documents</h2>
      <div class="flex items-center space-x-4">
        <div class="flex items-center">
          <span class="mr-2 text-sm text-gray-600 dark:text-gray-400">Availability:</span>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" id="availabilityToggle" class="sr-only peer" <?php echo $user['is_available'] ? 'checked' : ''; ?>>
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent"></div>
            <span class="ml-2 text-sm font-medium text-gray-600 dark:text-gray-400" id="availabilityStatus"><?php echo $user['is_available'] ? 'Available' : 'Not Available'; ?></span>
          </label>
        </div>
        <div class="relative">
          <input type="text" id="editorSearch" placeholder="Search documents..." class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent/20 dark:bg-primary dark:border-gray-700 dark:text-white">
        </div>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-primary">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sales Agent</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Assigned Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white dark:bg-primary-light divide-y divide-gray-200 dark:divide-gray-700">
          <?php
          $documents = getEditorDocuments($_SESSION['user_id'], $conn);
          while ($document = $documents->fetch_assoc()):
          ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-primary transition-colors">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['client_name']); ?></td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['title']); ?></td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 py-1 text-xs rounded-full <?php echo getStageColor($document['current_stage']); ?>">
                <?php echo formatStage($document['current_stage']); ?>
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['sales_agent_name']); ?></td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo date('M d, Y', strtotime($document['assigned_at'])); ?></td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
              <button onclick="openDocumentDetailsModal(<?php echo $document['id']; ?>)" class="text-accent hover:text-accent-dark mr-3">
                View Details
              </button>
              <?php if ($document['current_stage'] === 'editor_polishing'): ?>
                <button onclick="assignToOperator(<?php echo $document['id']; ?>)" class="text-blue-600 hover:text-blue-800">
                  Assign to Operator
                </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

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

    // Document Management Functions
    function openUploadDocumentModal() {
      const modal = document.getElementById('upload-document-modal');
      const modalContent = document.getElementById('upload-document-modal-content');
      
      modal.classList.remove('hidden');
      setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
      }, 10);
    }

    function closeUploadDocumentModal() {
      const modal = document.getElementById('upload-document-modal');
      const modalContent = document.getElementById('upload-document-modal-content');
      
      modalContent.classList.remove('scale-100', 'opacity-100');
      modalContent.classList.add('scale-95', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 300);
    }

    // Add document upload form handler
    document.addEventListener('DOMContentLoaded', function() {
        const uploadForm = document.getElementById('upload-document-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('action', 'upload_document');
                formData.append('title', document.getElementById('document-title').value);
                formData.append('description', document.getElementById('document-description').value);
                formData.append('document', document.getElementById('document-file').files[0]);
                formData.append('payment_type', document.getElementById('payment-type').value);
                
                // Show loading state
                const submitButton = uploadForm.querySelector('button[type="submit"]');
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Uploading...';
                
                fetch('php/document_handlers.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Document uploaded successfully', 'success');
                        closeUploadDocumentModal();
                        // Reset form
                        uploadForm.reset();
                        // Refresh the page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showNotification(data.message || 'Failed to upload document', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while uploading the document', 'error');
                })
                .finally(() => {
                    // Reset button state
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                });
            });
        }
    });

    function closeDocumentDetailsModal() {
        const modal = document.getElementById('documentDetailsModal');
        modal.classList.add('hidden');
    }

    function closePaymentAgreementModal() {
        const modal = document.getElementById('paymentAgreementModal');
        modal.classList.add('hidden');
    }

    function openDocumentDetailsModal(documentId) {
        fetch('php/document_handlers.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_document_details&document_id=${documentId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
                const doc = data.document;
                const detailsHtml = `
                    <div class="space-y-4">
                        <div class="border-b pb-2">
                            <h4 class="font-semibold text-gray-900 dark:text-white">Document Information</h4>
                            <p><strong>Title:</strong> ${doc.title}</p>
                            <p><strong>Description:</strong> ${doc.description}</p>
                            <p><strong>Client:</strong> ${doc.client_name}</p>
                            <p><strong>Current Stage:</strong> <span class="px-2 py-1 rounded-full text-xs ${getStageColor(doc.current_stage)}">${formatStage(doc.current_stage)}</span></p>
              </div>

                        <div class="border-b pb-2">
                            <h4 class="font-semibold text-gray-900 dark:text-white">Workflow Timeline</h4>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 rounded-full bg-blue-500 mr-2"></div>
              <div>
                                        <p class="text-sm"><strong>Sales Agent:</strong> ${doc.sales_agent_name || 'Not assigned'}</p>
                                        ${doc.sales_notes ? `<p class="text-xs text-gray-500">${doc.sales_notes}</p>` : ''}
                                        ${doc.sales_assigned_at ? `<p class="text-xs text-gray-500">Assigned: ${new Date(doc.sales_assigned_at).toLocaleString()}</p>` : ''}
              </div>
              </div>
                                <div class="flex items-center">
                                    <div class="w-2 h-2 rounded-full bg-green-500 mr-2"></div>
              <div>
                                        <p class="text-sm"><strong>Editor:</strong> ${doc.editor_name || 'Not assigned'}</p>
                                        ${doc.editor_notes ? `<p class="text-xs text-gray-500">${doc.editor_notes}</p>` : ''}
              </div>
                        </div>
                                <div class="flex items-center">
                                    <div class="w-2 h-2 rounded-full bg-purple-500 mr-2"></div>
                        <div>
                                        <p class="text-sm"><strong>Operator:</strong> ${doc.operator_name || 'Not assigned'}</p>
                                        ${doc.operator_notes ? `<p class="text-xs text-gray-500">${doc.operator_notes}</p>` : ''}
                        </div>
                        </div>
                            </div>
            </div>
            
                        <div class="border-b pb-2">
                            <h4 class="font-semibold text-gray-900 dark:text-white">Payment Information</h4>
                            ${doc.payment_status ? `
                                <p><strong>Status:</strong> <span class="px-2 py-1 rounded-full text-xs ${getPaymentStatusColor(doc.payment_status)}">${formatPaymentStatus(doc.payment_status)}</span></p>
                                <p><strong>Type:</strong> ${formatPaymentType(doc.payment_type)}</p>
                                ${doc.payment_amount ? `<p><strong>Amount:</strong> $${doc.payment_amount}</p>` : ''}
                            ` : '<p>No payment information available</p>'}
            </div>
          
                        ${doc.cost_receipt_path ? `
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">Print Receipt</h4>
                                <p><a href="uploads/receipts/${doc.cost_receipt_path}" target="_blank" class="text-blue-600 hover:text-blue-800">View Receipt</a></p>
                                ${doc.print_receipt_notes ? `<p class="text-sm text-gray-500">${doc.print_receipt_notes}</p>` : ''}
                            </div>
                        ` : ''}
              </div>
            `;
                document.getElementById('documentDetails').innerHTML = detailsHtml;
                document.getElementById('documentDetailsModal').classList.remove('hidden');
            }
        });
    }

    function getStageColor(stage) {
        const colors = {
            'sales_review': 'bg-blue-100 text-blue-800',
            'editor_polishing': 'bg-green-100 text-green-800',
            'printing_document': 'bg-purple-100 text-purple-800',
            'payment_pending': 'bg-yellow-100 text-yellow-800',
            'finished': 'bg-gray-100 text-gray-800'
        };
        return colors[stage] || 'bg-gray-100 text-gray-800';
    }

    function formatStage(stage) {
        const stages = {
            'sales_review': 'Sales Review',
            'editor_polishing': 'Editor Polishing',
            'printing_document': 'Printing Document',
            'payment_pending': 'Payment Pending',
            'finished': 'Finished'
        };
        return stages[stage] || stage;
    }

    function getPaymentStatusColor(status) {
        const colors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'accepted': 'bg-green-100 text-green-800',
            'rejected': 'bg-red-100 text-red-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    }

    function formatPaymentStatus(status) {
        const statuses = {
            'pending': 'Pending',
            'accepted': 'Accepted',
            'rejected': 'Rejected'
        };
        return statuses[status] || status;
    }

    function formatPaymentType(type) {
        const types = {
            'full_payment': 'Full Payment',
            'down_payment': 'Down Payment'
        };
        return types[type] || type;
    }

    function requestPayment(documentId) {
        if (confirm('Are you sure you want to request payment for this document?')) {
        fetch('php/document_handlers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
                body: `action=request_payment&document_id=${documentId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                    alert('Payment request sent successfully');
                    location.reload();
            } else {
                    alert('Failed to request payment: ' + data.message);
                }
            });
        }
    }

    function acceptPayment(documentId) {
        if (confirm('Are you sure you want to accept the payment request?')) {
        fetch('php/document_handlers.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
                body: `action=accept_payment&document_id=${documentId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
                    alert('Payment accepted successfully');
                    location.reload();
        } else {
                    alert('Failed to accept payment: ' + data.message);
                }
            });
        }
    }

    function markAsFinished(documentId) {
        if (confirm('Are you sure you want to mark this document as finished?')) {
            fetch('php/document_handlers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_as_finished&document_id=${documentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Document marked as finished successfully');
                    location.reload();
                } else {
                    alert('Failed to mark document as finished: ' + data.message);
                }
            });
        }
    }

    document.getElementById('closeModal').addEventListener('click', function() {
        document.getElementById('documentDetailsModal').classList.add('hidden');
    });

    function updatePaymentAgreement(documentId, status) {
        fetch('php/document_handlers.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
            body: `action=update_payment_agreement&document_id=${documentId}&status=${status}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
                showNotification(data.message, 'success');
                closePaymentAgreementModal();
                // Refresh the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
        } else {
                showNotification(data.message, 'error');
        }
      })
      .catch(error => {
            showNotification('Error updating payment agreement: ' + error.message, 'error');
        });
    }

    function openPaymentAgreementModal(documentId) {
        const modal = document.getElementById('paymentAgreementModal');
        const content = document.getElementById('paymentAgreementContent');
        
        // Show modal
        modal.classList.remove('hidden');
        
        // Show loading state
        content.innerHTML = `
            <div class="text-center py-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent mx-auto"></div>
                <p class="mt-2 text-gray-600 dark:text-gray-400">Loading payment agreement...</p>
            </div>
        `;

        // Fetch payment agreement details
        fetch('php/document_handlers.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
            body: `action=get_payment_agreement&document_id=${documentId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
                const agreement = data.agreement;
                content.innerHTML = `
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300">Initial Amount</h4>
                            <p class="text-gray-600 dark:text-gray-400">$${agreement.initial_amount}</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300">Counter Offer Amount</h4>
                            <p class="text-gray-600 dark:text-gray-400">$${agreement.proposed_amount}</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300">Status</h4>
                            <p class="text-gray-600 dark:text-gray-400">${agreement.status}</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300">Client Accepted</h4>
                            <p class="text-gray-600 dark:text-gray-400">${agreement.client_accepted ? 'Yes' : 'No'}</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300">Sales Agent Accepted</h4>
                            <p class="text-gray-600 dark:text-gray-400">${agreement.sales_accepted ? 'Yes' : 'No'}</p>
                        </div>
                        
                        ${agreement.status === 'pending' ? `
                            <div class="flex justify-end space-x-3 mt-4">
                                <button onclick="updatePaymentAgreement(${documentId}, 'accepted')" class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-dark transition-colors">
                                    Accept Agreement
                                </button>
                                <button onclick="updatePaymentAgreement(${documentId}, 'rejected')" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                                    Reject Agreement
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
        } else {
                content.innerHTML = `
                    <div class="text-center py-4 text-red-500">
                        ${data.message || 'Failed to load payment agreement'}
                    </div>
                `;
        }
      })
      .catch(error => {
            content.innerHTML = `
                <div class="text-center py-4 text-red-500">
                    Error loading payment agreement: ${error.message}
                </div>
            `;
        });
    }

    function openNotificationsModal() {
        const modal = document.getElementById('notifications-modal');
        const content = document.getElementById('notifications-content');
        
        // Show modal
        modal.classList.remove('hidden');
        
        // Show loading state
        content.innerHTML = `
            <div class="text-center py-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent mx-auto"></div>
                <p class="mt-2 text-gray-600 dark:text-gray-400">Loading notifications...</p>
            </div>
        `;

        // Fetch notifications
        fetch('php/document_handlers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_notifications'
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
                if (data.notifications.length === 0) {
                    content.innerHTML = `
                        <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                            No new notifications
                        </div>
                    `;
            } else {
                    content.innerHTML = data.notifications.map(notification => `
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-gray-700 dark:text-gray-300">${notification.message}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        ${new Date(notification.created_at).toLocaleString()}
                                    </p>
                                </div>
                                <button onclick="markNotificationAsRead(${notification.id})" 
                                        class="text-accent hover:text-accent-dark text-sm">
                                    Mark as read
                                </button>
                            </div>
                        </div>
                    `).join('');
                }
            } else {
                content.innerHTML = `
                    <div class="text-center py-4 text-red-500">
                        ${data.message || 'Failed to load notifications'}
                    </div>
                `;
            }
          })
          .catch(error => {
            content.innerHTML = `
                <div class="text-center py-4 text-red-500">
                    Error loading notifications: ${error.message}
                </div>
            `;
        });
    }

    function closeNotificationsModal() {
        const modal = document.getElementById('notifications-modal');
        modal.classList.add('hidden');
    }

    function markNotificationAsRead(notificationId) {
        fetch('php/document_handlers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_notifications_read&notification_ids[]=${notificationId}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
                // Remove the notification from the list
                const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notificationElement) {
                    notificationElement.remove();
                }
                
                // If no notifications left, show "No new notifications" message
                const notificationsContent = document.getElementById('notifications-content');
                if (!notificationsContent.querySelector('.border-b')) {
                    notificationsContent.innerHTML = `
                        <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                            No new notifications
                        </div>
                    `;
                }
            } else {
                showNotification(data.message || 'Failed to mark notification as read', 'error');
            }
          })
          .catch(error => {
            showNotification('Error marking notification as read: ' + error.message, 'error');
        });
      }

    function acceptClient(documentId) {
        if (confirm('Are you sure you want to accept this client?')) {
            fetch('php/document_handlers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=accept_client&document_id=${documentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Client accepted successfully');
                    location.reload();
                } else {
                    alert('Failed to accept client: ' + data.message);
                }
            });
        }
    }

    let currentDocumentId = null;

    function assignToEditor(documentId) {
        currentDocumentId = documentId;
        document.getElementById('editorAssignmentModal').classList.remove('hidden');
    }

    function closeEditorAssignmentModal() {
        document.getElementById('editorAssignmentModal').classList.add('hidden');
        currentDocumentId = null;
    }

    function confirmEditorAssignment() {
        const editorId = document.getElementById('editor-select').value;
        if (!editorId) {
            alert('Please select an editor');
            return;
        }

        fetch('php/document_handlers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=assign_to_editor&document_id=${currentDocumentId}&editor_id=${editorId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Document assigned to editor successfully');
                location.reload();
            } else {
                alert('Failed to assign document: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while assigning the document');
        })
        .finally(() => {
            closeEditorAssignmentModal();
        });
    }

    // Add search functionality for each table
    document.addEventListener('DOMContentLoaded', function() {
        const clientSearch = document.getElementById('client-search');
        if (clientSearch) {
            clientSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#available-clients-table tr');
                
                rows.forEach(row => {
                    const clientName = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                    const documentTitle = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                    
                    if (clientName.includes(searchTerm) || documentTitle.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        const currentClientSearch = document.getElementById('current-client-search');
        if (currentClientSearch) {
            currentClientSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#current-clients-table tr');
                
                rows.forEach(row => {
                    const clientName = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                    const documentTitle = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                    
                    if (clientName.includes(searchTerm) || documentTitle.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        const editorSearch = document.getElementById('editorSearch');
        if (editorSearch) {
            editorSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#editors-table tr');
                
                rows.forEach(row => {
                    const editorName = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                    const editorEmail = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                    
                    if (editorName.includes(searchTerm) || editorEmail.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        const operatorSearch = document.getElementById('operatorSearch');
        if (operatorSearch) {
            operatorSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('table tbody tr');
                
                rows.forEach(row => {
                    const operatorName = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                    const operatorEmail = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                    
                    if (operatorName.includes(searchTerm) || operatorEmail.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });

    // Add this to your existing JavaScript
    document.getElementById('availabilityToggle')?.addEventListener('change', function() {
        const isAvailable = this.checked;
        const statusText = document.getElementById('availabilityStatus');
        
        fetch('php/document_handlers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_availability&is_available=${isAvailable ? 1 : 0}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusText.textContent = isAvailable ? 'Available' : 'Not Available';
                showNotification('Availability updated successfully', 'success');
            } else {
                this.checked = !isAvailable;
                showNotification(data.message || 'Failed to update availability', 'error');
            }
        })
        .catch(error => {
            this.checked = !isAvailable;
            showNotification('Error updating availability', 'error');
        });
    });

    // Availability toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const availabilityToggle = document.getElementById('availabilityToggle');
        if (availabilityToggle) {
            // Remove any existing event listeners
            const newToggle = availabilityToggle.cloneNode(true);
            availabilityToggle.parentNode.replaceChild(newToggle, availabilityToggle);
            
            newToggle.addEventListener('change', function() {
                const isAvailable = this.checked;
                const statusText = document.getElementById('availabilityStatus');
                
                // Show loading state
                statusText.textContent = 'Updating...';
                
                fetch('php/update_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `is_available=${isAvailable ? 1 : 0}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusText.textContent = isAvailable ? 'Available' : 'Not Available';
                        showNotification(data.message, 'success');
                    } else {
                        // Revert toggle if update failed
                        this.checked = !isAvailable;
                        statusText.textContent = !isAvailable ? 'Available' : 'Not Available';
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Revert toggle on error
                    this.checked = !isAvailable;
                    statusText.textContent = !isAvailable ? 'Available' : 'Not Available';
                    showNotification('An error occurred while updating availability', 'error');
                });
            });
        }
    });
  </script>

</body>

</html>