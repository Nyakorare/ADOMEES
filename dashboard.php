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
        ['id' => 'printing_operators', 'name' => 'Printing Operators']
    ],
    'operator' => [
        ['id' => 'tasks', 'name' => 'Tasks']
    ],
    'client' => [
        ['id' => 'documents', 'name' => 'My Documents']
    ]
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
                  <button onclick="requestPayment(<?php echo $document['id']; ?>)" 
                          class="text-blue-600 hover:text-blue-800 mr-3"
                          id="requestPaymentBtn_<?php echo $document['id']; ?>"
                          <?php echo isset($document['payment_requested']) && $document['payment_requested'] ? 'disabled' : ''; ?>>
                    <?php echo isset($document['payment_requested']) && $document['payment_requested'] ? 'Payment Requested' : 'Request Payment'; ?>
                  </button>
                <?php endif; ?>
                <?php if ($document['current_stage'] === 'payment_pending' && isset($document['payment_status']) && $document['payment_status'] === 'accepted'): ?>
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
          <div class="flex items-center bg-gray-100 dark:bg-primary p-2 rounded-lg">
            <span class="mr-2 text-sm font-medium text-gray-700 dark:text-gray-300">Availability:</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" id="availabilityToggle" class="sr-only peer" <?php echo $user['is_available'] ? 'checked' : ''; ?>>
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent"></div>
              <span class="ml-2 text-sm font-medium <?php echo $user['is_available'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>" id="availabilityStatus">
                <?php echo $user['is_available'] ? 'Available' : 'Not Available'; ?>
              </span>
            </label>
          </div>
          <div class="relative">
            <input type="text" id="taskSearch" placeholder="Search tasks..." class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent/20 dark:bg-primary dark:border-gray-700 dark:text-white">
          </div>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-primary">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Document Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"><?php echo $user['role'] === 'editor' ? 'Sales Agent' : 'Editor'; ?></th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
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
            
            if ($documents->num_rows === 0) {
                    ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                  No tasks assigned at the moment.
                        </td>
                    </tr>
            <?php
            } else {
              while ($document = $documents->fetch_assoc()) {
                $stageClass = '';
                switch ($document['current_stage']) {
                            case 'editor_polishing':
                    $stageClass = 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
                    $stageText = 'In Progress';
                    break;
                  case 'editor_review':
                    $stageClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                    $stageText = 'Under Review';
                    break;
                            case 'printing_document':
                    $stageClass = 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200';
                    $stageText = 'Printing';
                    break;
                  default:
                    $stageClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                    $stageText = ucfirst(str_replace('_', ' ', $document['current_stage']));
                }
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-primary transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($document['title']); ?>
                <?php if ($document['description']): ?>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    <?php echo htmlspecialchars(substr($document['description'], 0, 100)) . (strlen($document['description']) > 100 ? '...' : ''); ?>
                  </div>
                <?php endif; ?>
                  </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                <?php echo htmlspecialchars($document['client_name']); ?>
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
                  <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $stageClass; ?>">
                  <?php echo $stageText; ?>
                    </span>
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
                  <button onclick="openFinishPrintingModal(<?php echo $document['id']; ?>)" class="text-green-600 hover:text-green-800">
                    Finish Printing
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

<?php if ($user['role'] === 'client'): ?>
        <?php if ($current_tab === 'documents'): ?>
        <!-- Client Documents Tab -->
        <div class="bg-white dark:bg-primary-light rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">My Documents</h2>
                <button onclick="openUploadModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-accent hover:bg-accent-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Upload New Document
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-primary">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Document Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sales Agent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Updated</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-primary-light divide-y divide-gray-200 dark:divide-gray-700">
                        <?php
                            $documents = getClientDocuments($_SESSION['user_id'], $conn);
                                while ($document = $documents->fetch_assoc()):
                            switch ($document['current_stage']) {
                                        case 'pending':
                                            $stageClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                                $stageText = 'Pending';
                                            break;
                              case 'sales_review':
                                                $stageClass = 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
                                                $stageText = 'Sales Review';
                                            break;
                                        case 'editor_polishing':
                                                $stageClass = 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200';
                                                $stageText = 'Editor Polishing';
                                    break;
                                        case 'printing_document':
                                                $stageClass = 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200';
                                                $stageText = 'Printing';
                                    break;
                                        case 'payment_pending':
                                                $stageClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                                $stageText = 'Payment Pending';
                                    break;
                                            case 'finished':
                                $stageClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                    $stageText = 'Finished';
                                    break;
                                            default:
                                        $stageClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                        $stageText = 'Unknown';
                            }
                            ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-primary transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($document['title']); ?></div>
                                            <?php if ($document['description']): ?>
                                            <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($document['description']); ?></div>
                                            <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $stageClass; ?>">
                                                <?php echo $stageText; ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo $document['sales_agent_name'] ? htmlspecialchars($document['sales_agent_name']) : 'Not assigned'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <?php echo date('M d, Y H:i', strtotime($document['workflow_updated_at'])); ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="openDocumentDetailsModal(<?php echo $document['id']; ?>)" class="text-accent hover:text-accent-dark mr-3">
                          View Details
              </button>
                                <?php if ($document['current_stage'] === 'pending'): ?>
                                    <button onclick="deleteDocument(<?php echo $document['id']; ?>)" class="text-red-600 hover:text-red-900">
                                    Delete
              </button>
                        <?php endif; ?>
                        <?php if ($document['current_stage'] === 'payment_pending' && !$document['payment_requested']): ?>
                            <button onclick="requestPayment(<?php echo $document['id']; ?>)" class="text-green-600 hover:text-green-900">
                                Request Payment
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

        <?php if ($current_tab === 'upload'): ?>
        <!-- Client Upload Document Tab -->
        <div class="bg-white dark:bg-primary-light rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Upload New Document</h2>
            </div>
            <form id="uploadForm" class="space-y-6">
          <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Document Title</label>
                    <input type="text" id="title" name="title" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-primary shadow-sm focus:border-accent focus:ring-accent">
          </div>
          <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-primary shadow-sm focus:border-accent focus:ring-accent"></textarea>
          </div>
          <div>
                        <label for="payment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Type</label>
                    <select id="payment_type" name="payment_type" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-primary shadow-sm focus:border-accent focus:ring-accent">
                        <option value="">Select payment type</option>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>
                <div>
                    <label for="document" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Upload Document</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg hover:border-accent dark:hover:border-accent transition-colors">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                <label for="document" class="relative cursor-pointer bg-white dark:bg-primary-light rounded-md font-medium text-accent hover:text-accent-dark focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-accent">
                                    <span>Upload a file</span>
                                    <input id="document" name="document" type="file" class="sr-only" required>
                                </label>
                                <p class="pl-1">or drag and drop</p>
          </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Any file type accepted
                            </p>
      </div>
    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-accent hover:bg-accent-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent">
                        Upload Document
                    </button>
                </div>
            </form>
  </div>
        <?php endif; ?>
    <?php endif; ?>

</main>

  <!-- Editor Assignment Modal -->
  <div id="editorAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-primary-light">
            <div class="mt-3">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Assign to Editor</h3>
        <div id="editorList" class="space-y-2">
          <!-- Editor list will be loaded here -->
        </div>
        <div class="mt-4 flex justify-end">
          <button onclick="closeEditorAssignmentModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary rounded-md">
              Cancel
            </button>
          </div>
              </div>
              </div>
  </div>

  <!-- Document Details Modal -->
  <div id="documentDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-3/4 max-w-4xl shadow-lg rounded-md bg-white dark:bg-primary-light">
      <div class="mt-3">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Document Details</h3>
        <div id="documentDetailsContent" class="space-y-4">
          <!-- Content will be loaded dynamically -->
        </div>
        <div class="mt-4 flex justify-end">
          <button onclick="closeDocumentDetailsModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary rounded-md">
            Close
              </button>
  </div>
        </div>
              </div>
  </div>

  <!-- Footer -->
  <footer class="bg-white dark:bg-primary-light text-center py-6 text-sm border-t border-gray-200 dark:border-gray-700">
    <div class="container mx-auto">
      <p class="text-gray-600 dark:text-gray-300">&copy; 2025 ADOMee$. All rights reserved.</p>
        </div>
  </footer>

  <!-- Operator Assignment Modal -->
  <div id="operatorAssignmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-primary-light rounded-lg p-6 w-full max-w-md mx-4">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-bold text-secondary">Assign to Operator</h3>
        <button onclick="closeOperatorAssignmentModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div id="operatorList" class="space-y-4">
        <div class="flex justify-center">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent"></div>
        </div>
      </div>
      <div class="mt-6 flex justify-end">
        <button onclick="closeOperatorAssignmentModal()" class="btn bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
          Cancel
        </button>
      </div>
    </div>
  </div>

  <!-- Forward to Operator Modal -->
  <div id="forwardToOperatorModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-primary-light">
      <div class="mt-3">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Forward to Operator</h3>
        <div id="operatorSelection" class="space-y-4">
          <div class="text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent mx-auto"></div>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Loading available operators...</p>
          </div>
        </div>
        <form id="forwardToOperatorForm" class="space-y-4 hidden">
          <input type="hidden" id="forwardDocumentId" name="document_id">
          <input type="hidden" id="selectedOperatorId" name="operator_id">
          
          <div>
            <label for="editedDocument" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Upload Edited Document</label>
            <input type="file" id="editedDocument" name="edited_document" required
                   class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-accent file:text-white
                          hover:file:bg-accent-dark">
          </div>
          
          <div>
            <label for="editorNotes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes for Operator</label>
            <textarea id="editorNotes" name="editor_notes" rows="3" 
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring-accent dark:bg-primary dark:border-gray-600 dark:text-white"></textarea>
          </div>
          
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeForwardToOperatorModal()" 
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary rounded-md">
              Cancel
            </button>
            <button type="submit" 
                    class="px-4 py-2 text-sm font-medium text-white bg-accent hover:bg-accent-dark rounded-md">
              Forward
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Add Upload Document Modal -->
  <div id="uploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-primary-light">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Upload New Document</h3>
            <form id="uploadForm" class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Document Title</label>
                    <input type="text" id="title" name="title" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-primary shadow-sm focus:border-accent focus:ring-accent">
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-primary shadow-sm focus:border-accent focus:ring-accent"></textarea>
                </div>
                <div>
                    <label for="payment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Type</label>
                    <select id="payment_type" name="payment_type" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-primary shadow-sm focus:border-accent focus:ring-accent">
                        <option value="">Select payment type</option>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label for="document" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Upload Document</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg hover:border-accent dark:hover:border-accent transition-colors">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                <label for="document" class="relative cursor-pointer bg-white dark:bg-primary-light rounded-md font-medium text-accent hover:text-accent-dark focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-accent">
                                    <span>Upload a file</span>
                                    <input id="document" name="document" type="file" class="sr-only" required>
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Any file type accepted
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeUploadModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary rounded-md">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-accent hover:bg-accent-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent">
                        Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

  <!-- Finish Printing Modal -->
  <div id="finishPrintingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-primary-light">
      <div class="mt-3">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Finish Printing</h3>
        <form id="finishPrintingForm" class="space-y-4">
          <input type="hidden" id="finishPrintingDocumentId" name="document_id">
          
          <div>
            <label for="printingCost" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Printing Cost ($)</label>
            <input type="number" id="printingCost" name="printing_cost" step="0.01" min="0" required
                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-primary shadow-sm focus:border-accent focus:ring-accent">
          </div>
          
          <div>
            <label for="printingNotes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes (Optional)</label>
            <textarea id="printingNotes" name="notes" rows="3" 
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring-accent dark:bg-primary dark:border-gray-600 dark:text-white"></textarea>
          </div>
          
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeFinishPrintingModal()" 
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-primary rounded-md">
              Cancel
            </button>
            <button type="submit" 
                    class="px-4 py-2 text-sm font-medium text-white bg-accent hover:bg-accent-dark rounded-md">
              Complete Printing
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Receipt Modal -->
  <div id="receiptModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-primary-light">
      <div class="mt-3">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">Printing Receipt</h3>
          <button onclick="closeReceiptModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div id="receiptContent" class="space-y-4">
          <!-- Receipt content will be loaded here -->
        </div>
      </div>
    </div>
  </div>

  <script>
    // Notification function
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 animate-fade ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        } text-white`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    // Document Upload Modal Functions
    function openUploadModal() {
        document.getElementById('uploadModal').classList.remove('hidden');
    }

    function closeUploadModal() {
        document.getElementById('uploadModal').classList.add('hidden');
    }

    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'upload_document'); // Add the action parameter
        
        // Log form data for debugging
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        fetch('php/document_handlers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification('Document uploaded successfully', 'success');
                closeUploadModal();
                // Refresh the documents list
                location.reload();
            } else {
                showNotification(data.message || 'Error uploading document', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error uploading document: ' + error.message, 'error');
        });
    });

    // Close modal when clicking outside
    document.getElementById('uploadModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeUploadModal();
        }
    });

    // Document Details Modal Functions
    function openDocumentDetailsModal(documentId) {
        const modal = document.getElementById('documentDetailsModal');
        const content = document.getElementById('documentDetailsContent');
        
        // Show loading state
        content.innerHTML = '<div class="text-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent mx-auto"></div><p class="mt-2 text-gray-600 dark:text-gray-400">Loading document details...</p></div>';
        modal.classList.remove('hidden');
        
        // Fetch document details
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
                content.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300">Basic Information</h4>
                            <div class="mt-2 space-y-2">
                                <p><span class="font-medium">Title:</span> ${doc.title}</p>
                                <p><span class="font-medium">Description:</span> ${doc.description || 'N/A'}</p>
                                <p><span class="font-medium">Client:</span> ${doc.client_name}</p>
                                <p><span class="font-medium">Status:</span> ${doc.current_stage.replace(/_/g, ' ').toUpperCase()}</p>
                                <div class="mt-4">
                                    <a href="php/download_document.php?document_id=${doc.id}" 
                                       class="inline-flex items-center px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-dark transition-colors">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        Download Document
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300">Workflow Information</h4>
                            <div class="mt-2 space-y-2">
                                <p><span class="font-medium">Sales Agent:</span> ${doc.sales_agent_name || 'Not assigned'}</p>
                                <p><span class="font-medium">Editor:</span> ${doc.editor_name || 'Not assigned'}</p>
                                <p><span class="font-medium">Operator:</span> ${doc.operator_name || 'Not assigned'}</p>
                                <p><span class="font-medium">Last Updated:</span> ${new Date(doc.workflow_updated_at).toLocaleString()}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6">
                        <h4 class="font-semibold text-gray-700 dark:text-gray-300">Workflow History</h4>
                        <div class="mt-2 space-y-2">
                            ${doc.workflow_history.map(entry => `
                                <div class="p-2 bg-gray-50 dark:bg-primary rounded">
                                    <p class="text-sm">
                                        <span class="font-medium">${entry.from_stage} → ${entry.to_stage}</span>
                                        <span class="text-gray-500 dark:text-gray-400">by ${entry.changed_by_name}</span>
                                        <span class="text-gray-500 dark:text-gray-400">on ${new Date(entry.created_at).toLocaleString()}</span>
                                    </p>
                                    ${entry.notes ? `<p class="text-sm text-gray-600 dark:text-gray-400 mt-1">${entry.notes}</p>` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = `<div class="text-red-500">${data.message || 'Failed to load document details'}</div>`;
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="text-red-500">An error occurred while loading document details</div>';
            console.error('Error:', error);
        });
    }

    function closeDocumentDetailsModal() {
        const modal = document.getElementById('documentDetailsModal');
        modal.classList.add('hidden');
    }

    // Availability toggle functionality
    function updateAvailability(isAvailable) {
        const statusText = document.getElementById('availabilityStatus');
        const toggle = document.getElementById('availabilityToggle');
        
        // Show loading state
        statusText.textContent = 'Updating...';
        toggle.disabled = true;
        
        fetch('php/document_handlers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_availability&is_available=${isAvailable ? 1 : 0}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing JSON:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            if (data.success) {
                statusText.textContent = isAvailable ? 'Available' : 'Not Available';
                statusText.className = `ml-2 text-sm font-medium ${isAvailable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`;
                showNotification('Availability updated successfully', 'success');
            } else {
                // If the update failed, revert the toggle state
                toggle.checked = !isAvailable;
                statusText.textContent = !isAvailable ? 'Available' : 'Not Available';
                statusText.className = `ml-2 text-sm font-medium ${!isAvailable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`;
                showNotification(data.message || 'Failed to update availability', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // If there's an error, revert the toggle state
            toggle.checked = !isAvailable;
            statusText.textContent = !isAvailable ? 'Available' : 'Not Available';
            statusText.className = `ml-2 text-sm font-medium ${!isAvailable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`;
            showNotification('Error updating availability: ' + error.message, 'error');
        })
        .finally(() => {
            toggle.disabled = false;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const availabilityToggle = document.getElementById('availabilityToggle');
        if (availabilityToggle) {
            availabilityToggle.addEventListener('change', function() {
                updateAvailability(this.checked);
            });
        }
    });

    // Client Acceptance Functions
    function acceptClient(documentId) {
        if (!confirm('Are you sure you want to accept this client?')) {
            return;
        }

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
                showNotification('Client accepted successfully', 'success');
                // Refresh the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification(data.message || 'Failed to accept client', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while accepting the client', 'error');
        });
    }

    // Editor Assignment Functions
    let currentDocumentId = null;

    function assignToEditor(documentId) {
        currentDocumentId = documentId;
        const modal = document.getElementById('editorAssignmentModal');
        const editorList = document.getElementById('editorList');
        
        // Show loading state
        editorList.innerHTML = '<div class="text-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent mx-auto"></div><p class="mt-2 text-gray-600 dark:text-gray-400">Loading available editors...</p></div>';
        modal.classList.remove('hidden');
        
        // Fetch available editors
        fetch('php/document_handlers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_available_editors'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.editors.length === 0) {
                    editorList.innerHTML = '<p class="text-center text-gray-600 dark:text-gray-400">No available editors at the moment.</p>';
                } else {
                    editorList.innerHTML = data.editors.map(editor => `
                        <button onclick="confirmEditorAssignment(${editor.id})" 
                                class="w-full p-3 text-left rounded-lg hover:bg-gray-100 dark:hover:bg-primary transition-colors">
                            <div class="font-medium text-gray-900 dark:text-white">${editor.username}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">${editor.email}</div>
                        </button>
                    `).join('');
                }
            } else {
                editorList.innerHTML = `<div class="text-red-500">${data.message || 'Failed to load editors'}</div>`;
            }
        })
        .catch(error => {
            editorList.innerHTML = '<div class="text-red-500">An error occurred while loading editors</div>';
            console.error('Error:', error);
        });
    }

    function confirmEditorAssignment(editorId) {
        if (!currentDocumentId) return;

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
                showNotification('Document assigned to editor successfully', 'success');
                closeEditorAssignmentModal();
                // Refresh the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification(data.message || 'Failed to assign document to editor', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while assigning the document', 'error');
        });
    }

    function closeEditorAssignmentModal() {
        const modal = document.getElementById('editorAssignmentModal');
        modal.classList.add('hidden');
        currentDocumentId = null;
    }

    function forwardToOperator(documentId) {
        currentDocumentId = documentId;
        const modal = document.getElementById('operatorAssignmentModal');
        const operatorList = document.getElementById('operatorList');
        
        // Show loading spinner
        operatorList.innerHTML = `
            <div class="flex justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent"></div>
            </div>
        `;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Fetch available operators
        fetch('php/document_handlers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_available_operators'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.operators.length === 0) {
                    operatorList.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400">No available operators at the moment.</p>';
                } else {
                    operatorList.innerHTML = data.operators.map(operator => `
                        <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-primary cursor-pointer"
                             onclick="confirmOperatorAssignment(${operator.id})">
                            <div class="font-medium text-secondary">${operator.username}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">${operator.email}</div>
                        </div>
                    `).join('');
                }
            } else {
                operatorList.innerHTML = '<p class="text-center text-red-500">Failed to load operators. Please try again.</p>';
            }
        })
        .catch(error => {
            operatorList.innerHTML = '<p class="text-center text-red-500">Failed to load operators. Please try again.</p>';
        });
    }

    function confirmOperatorAssignment(operatorId) {
        if (!currentDocumentId) return;
        
        fetch('php/document_handlers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=assign_to_operator&document_id=${currentDocumentId}&operator_id=${operatorId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Document assigned to operator successfully', 'success');
                closeOperatorAssignmentModal();
                // Refresh the page to update the document list
                window.location.reload();
            } else {
                showNotification(data.message || 'Failed to assign document to operator', 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while assigning the document', 'error');
        });
    }

    function closeOperatorAssignmentModal() {
        const modal = document.getElementById('operatorAssignmentModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentDocumentId = null;
    }

    // Forward to Operator Functions
    function assignToOperator(documentId) {
      const modal = document.getElementById('forwardToOperatorModal');
      document.getElementById('forwardDocumentId').value = documentId;
      modal.classList.remove('hidden');
      
      // Show operator selection and hide form initially
      document.getElementById('operatorSelection').classList.remove('hidden');
      document.getElementById('forwardToOperatorForm').classList.add('hidden');
      
      // Fetch available operators
      fetch('php/document_handlers.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_available_operators'
      })
      .then(response => response.json())
      .then(data => {
        const operatorSelection = document.getElementById('operatorSelection');
        if (data.success) {
          if (data.operators.length === 0) {
            operatorSelection.innerHTML = '<p class="text-center text-gray-600 dark:text-gray-400">No available operators at the moment.</p>';
          } else {
            operatorSelection.innerHTML = data.operators.map(operator => `
              <button onclick="selectOperator(${operator.id}, '${operator.username}')" 
                      class="w-full p-3 text-left rounded-lg hover:bg-gray-100 dark:hover:bg-primary transition-colors">
                <div class="font-medium text-gray-900 dark:text-white">${operator.username}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">${operator.email}</div>
                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                  Current tasks: ${operator.current_tasks}
                </div>
              </button>
            `).join('');
          }
        } else {
          operatorSelection.innerHTML = `<p class="text-center text-red-500">${data.message || 'Failed to load operators'}</p>`;
        }
      })
      .catch(error => {
        document.getElementById('operatorSelection').innerHTML = '<p class="text-center text-red-500">Failed to load operators. Please try again.</p>';
        console.error('Error:', error);
      });
    }

    function selectOperator(operatorId, operatorName) {
      document.getElementById('selectedOperatorId').value = operatorId;
      
      // Hide operator selection and show form
      document.getElementById('operatorSelection').classList.add('hidden');
      document.getElementById('forwardToOperatorForm').classList.remove('hidden');
      
      // Update form title to show selected operator
      const modalTitle = document.querySelector('#forwardToOperatorModal h3');
      modalTitle.textContent = `Forward to ${operatorName}`;
    }

    function closeForwardToOperatorModal() {
      const modal = document.getElementById('forwardToOperatorModal');
      modal.classList.add('hidden');
      document.getElementById('forwardToOperatorForm').reset();
      
      // Reset modal state
      document.getElementById('operatorSelection').classList.remove('hidden');
      document.getElementById('forwardToOperatorForm').classList.add('hidden');
      document.querySelector('#forwardToOperatorModal h3').textContent = 'Forward to Operator';
    }

    // Handle forward to operator form submission
    document.addEventListener('DOMContentLoaded', function() {
      const forwardForm = document.getElementById('forwardToOperatorForm');
      if (forwardForm) {
        forwardForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const formData = new FormData();
          formData.append('action', 'forward_to_operator');
          formData.append('document_id', document.getElementById('forwardDocumentId').value);
          formData.append('operator_id', document.getElementById('selectedOperatorId').value);
          formData.append('edited_document', document.getElementById('editedDocument').files[0]);
          formData.append('editor_notes', document.getElementById('editorNotes').value);
          
          // Show loading state
          const submitButton = forwardForm.querySelector('button[type="submit"]');
          const originalText = submitButton.textContent;
          submitButton.disabled = true;
          submitButton.textContent = 'Forwarding...';
          
          fetch('php/document_handlers.php', {
            method: 'POST',
            body: formData
          })
          .then(response => {
            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
              try {
                return JSON.parse(text);
              } catch (e) {
                console.error('Response text:', text);
                throw new Error('Invalid JSON response from server');
              }
            });
          })
          .then(data => {
            if (data.success) {
              showNotification('Document forwarded successfully', 'success');
              closeForwardToOperatorModal();
              // Refresh the page after a short delay
              setTimeout(() => {
                window.location.reload();
              }, 1000);
            } else {
              showNotification(data.message || 'Failed to forward document', 'error');
              submitButton.disabled = false;
              submitButton.textContent = originalText;
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while forwarding the document: ' + error.message, 'error');
            submitButton.disabled = false;
            submitButton.textContent = originalText;
          });
        });
      }
    });

    // Add file input change handler
    document.addEventListener('DOMContentLoaded', function() {
      const fileInput = document.getElementById('editedDocument');
      const fileInfo = document.getElementById('fileInfo');
      const fileName = document.getElementById('fileName');
      
      if (fileInput && fileInfo && fileName) {
        fileInput.addEventListener('change', function(e) {
          if (this.files && this.files[0]) {
            fileName.textContent = this.files[0].name;
            fileInfo.classList.remove('hidden');
          } else {
            fileInfo.classList.add('hidden');
          }
        });
        
        // Add drag and drop support
        const dropZone = fileInput.closest('.border-dashed');
        
        if (dropZone) {
          ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
          });
          
          function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
          }
          
          ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
          });
          
          ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
          });
          
          function highlight(e) {
            dropZone.classList.add('border-accent');
          }
          
          function unhighlight(e) {
            dropZone.classList.remove('border-accent');
          }
          
          dropZone.addEventListener('drop', handleDrop, false);
          
          function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length) {
              fileInput.files = files;
              fileName.textContent = files[0].name;
              fileInfo.classList.remove('hidden');
            }
          }
        }
      }
    });

    // Finish Printing Functions
    function openFinishPrintingModal(documentId) {
      const modal = document.getElementById('finishPrintingModal');
      document.getElementById('finishPrintingDocumentId').value = documentId;
      modal.classList.remove('hidden');
    }

    function closeFinishPrintingModal() {
      const modal = document.getElementById('finishPrintingModal');
      modal.classList.add('hidden');
      document.getElementById('finishPrintingForm').reset();
    }

    // Handle finish printing form submission
    document.addEventListener('DOMContentLoaded', function() {
      const finishPrintingForm = document.getElementById('finishPrintingForm');
      if (finishPrintingForm) {
        finishPrintingForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const formData = new FormData();
          formData.append('action', 'finish_printing');
          formData.append('document_id', document.getElementById('finishPrintingDocumentId').value);
          formData.append('printing_cost', document.getElementById('printingCost').value);
          formData.append('notes', document.getElementById('printingNotes').value);
          
          // Show loading state
          const submitButton = finishPrintingForm.querySelector('button[type="submit"]');
          const originalText = submitButton.textContent;
          submitButton.disabled = true;
          submitButton.textContent = 'Processing...';
          
          fetch('php/document_handlers.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showNotification('Printing completed successfully', 'success');
              closeFinishPrintingModal();
              // Show receipt modal
              openReceiptModal(data.receipt);
              // Refresh the page after a short delay
              setTimeout(() => {
                window.location.reload();
              }, 1500);
            } else {
              showNotification(data.message || 'Failed to complete printing', 'error');
              submitButton.disabled = false;
              submitButton.textContent = originalText;
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while completing printing', 'error');
            submitButton.disabled = false;
            submitButton.textContent = originalText;
          });
        });
      }
    });

    function openReceiptModal(receiptData) {
      const modal = document.getElementById('receiptModal');
      const content = document.getElementById('receiptContent');
      
      content.innerHTML = `
        <div class="space-y-4">
          <div class="text-center">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Printing Receipt</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">${new Date().toLocaleString()}</p>
          </div>
          <div class="border-t border-b border-gray-200 dark:border-gray-700 py-4">
            <p><span class="font-medium">Document:</span> ${receiptData.document_title}</p>
            <p><span class="font-medium">Cost:</span> $${receiptData.cost}</p>
            <p><span class="font-medium">Operator:</span> ${receiptData.operator_name}</p>
            ${receiptData.notes ? `<p><span class="font-medium">Notes:</span> ${receiptData.notes}</p>` : ''}
          </div>
          <div class="text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">Thank you for your business!</p>
          </div>
        </div>
      `;
      
      modal.classList.remove('hidden');
    }

    function closeReceiptModal() {
      const modal = document.getElementById('receiptModal');
      modal.classList.add('hidden');
      window.location.reload();
    }

    // Request Payment Function (Sales Agent)
    function requestPayment(documentId) {
      if (!confirm('Are you sure you want to request payment for this document?')) {
        return;
      }
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
          showNotification('Payment requested successfully', 'success');
          // Disable the request payment button
          const requestBtn = document.getElementById(`requestPaymentBtn_${documentId}`);
          if (requestBtn) {
            requestBtn.disabled = true;
            requestBtn.textContent = 'Payment Requested';
            requestBtn.classList.add('opacity-50', 'cursor-not-allowed');
          }
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          showNotification(data.message || 'Failed to request payment', 'error');
        }
      })
      .catch(error => {
        showNotification('An error occurred while requesting payment', 'error');
      });
    }

    // Client: View Receipt and Agree to Pay
    function openClientReceiptModal(documentId) {
      // Fetch receipt details from backend
      fetch('php/document_handlers.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_receipt&document_id=${documentId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const modal = document.getElementById('clientReceiptModal');
          const content = document.getElementById('clientReceiptContent');
          content.innerHTML = `
            <div class="space-y-4">
              <div class="text-center">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Payment Receipt</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">${new Date().toLocaleString()}</p>
              </div>
              <div class="border-t border-b border-gray-200 dark:border-gray-700 py-4">
                <p><span class="font-medium">Document:</span> ${data.receipt.document_title}</p>
                <p><span class="font-medium">Cost:</span> $${data.receipt.cost}</p>
                <p><span class="font-medium">Operator:</span> ${data.receipt.operator_name}</p>
                ${data.receipt.notes ? `<p><span class="font-medium">Notes:</span> ${data.receipt.notes}</p>` : ''}
              </div>
              <div class="text-center">
                <button onclick="agreeAndPay(${documentId})" 
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md">
                  Agree and Pay
                </button>
              </div>
            </div>
          `;
          modal.classList.remove('hidden');
        } else {
          showNotification(data.message || 'Failed to load receipt', 'error');
        }
      })
      .catch(error => {
        showNotification('An error occurred while loading the receipt', 'error');
      });
    }

    function closeClientReceiptModal() {
      const modal = document.getElementById('clientReceiptModal');
      modal.classList.add('hidden');
    }

    function agreeAndPay(documentId) {
      if (!confirm('Do you agree to pay and complete the transaction?')) return;
      fetch('php/document_handlers.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=agree_and_pay&document_id=${documentId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Payment completed and document transferred!', 'success');
          closeClientReceiptModal();
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          showNotification(data.message || 'Failed to complete payment', 'error');
        }
      })
      .catch(error => {
        showNotification('An error occurred while completing payment', 'error');
      });
    }
  </script>
</body>

</html>