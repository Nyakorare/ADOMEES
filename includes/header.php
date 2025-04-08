<!-- Header -->
<header class="bg-white dark:bg-primary-light shadow-md p-4 flex justify-between items-center animate-fade sticky top-0 z-40 border-b border-gray-200 dark:border-gray-700">
  <div class="flex items-center space-x-4">
    <img src="assets/logo.png" alt="ADOMee$ Logo" class="h-10 w-10 animate-pulse">
    <h1 class="text-2xl font-bold text-secondary">ADOMee$ Dashboard</h1>
    <button id="themeToggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-primary transition-colors">
      <svg id="sunIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-warning" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
      </svg>
      <svg id="moonIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-accent hidden" viewBox="0 0 20 20" fill="currentColor">
        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
      </svg>
    </button>
  </div>
  <div class="flex items-center space-x-4">
    <span class="text-gray-700 dark:text-gray-300">Welcome, <span class="font-semibold text-secondary"><?php echo htmlspecialchars($_SESSION['username']); ?></span> <span class="px-2 py-1 text-xs rounded-full bg-accent-light text-white"><?php echo ucfirst($role); ?></span></span>
    <a href="../auth/logout.php" class="btn btn-danger hover:scale-105 transition-transform duration-300">Logout</a>
  </div>
</header> 