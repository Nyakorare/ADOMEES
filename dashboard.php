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
        <span class="text-gray-700 dark:text-gray-300">Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
        <a href="auth/logout.php" class="btn btn-primary">Logout</a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="flex-grow container mx-auto p-6">
    <div class="card mb-8">
      <h2 class="text-2xl font-bold text-secondary mb-4">User Information</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <p class="text-gray-700 dark:text-gray-300"><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
          <p class="text-gray-700 dark:text-gray-300"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
          <p class="text-gray-700 dark:text-gray-300"><strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
        </div>
      </div>
    </div>

    <?php if ($user['role'] === 'admin'): ?>
    <div class="card mb-8">
      <h2 class="text-2xl font-bold text-secondary mb-4">User Management</h2>
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
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php
            // Get all users except current admin
            $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id != ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($other_user = $result->fetch_assoc()):
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-primary" data-user-id="<?php echo $other_user['id']; ?>">
              <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($other_user['username']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($other_user['email']); ?></td>
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
                        class="text-danger hover:text-danger-dark focus:outline-none">
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
  </main>

  <!-- Footer -->
  <footer class="bg-white dark:bg-primary-light text-center py-6 text-sm border-t border-gray-200 dark:border-gray-700">
    <div class="container mx-auto">
      <p class="text-gray-600 dark:text-gray-300">&copy; 2025 ADOMee$. All rights reserved.</p>
    </div>
  </footer>

  <!-- Notification Container -->
  <div id="notification-container"></div>

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

    function updateUserRole(selectElement) {
      const userId = selectElement.dataset.userId;
      const newRole = selectElement.value;
      const originalRole = selectElement.getAttribute('data-original-role');
      
      // Store the original role in case we need to revert
      if (!originalRole) {
        selectElement.setAttribute('data-original-role', newRole);
      }
      
      // Show confirmation dialog
      if (confirm(`Are you sure you want to change this user's role from ${originalRole} to ${newRole}?`)) {
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
            // Update the original role attribute
            selectElement.setAttribute('data-original-role', newRole);
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
      } else {
        // If user cancels, revert the selection
        selectElement.value = selectElement.getAttribute('data-original-role');
      }
    }

    function deleteUser(userId) {
      if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
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
            // Remove the row from the table with animation
            row.style.transition = 'all 0.5s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
              row.remove();
              showNotification('User deleted successfully', 'success');
            }, 500);
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
    }
  </script>
</body>

</html>