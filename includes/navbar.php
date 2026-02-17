<?php
$currentPage = getCurrentPage();
?>
<nav class="fixed top-0 left-0 w-full z-50 nav-transition bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
                <a href="index.php" class="flex items-center space-x-2">
                    <img src="images/CAPITAL BRIDGE LOGO.png" alt="CB Finance Logo" class="h-12 w-auto">
                    <span class="text-primary-blue font-bold text-xl hidden sm:block">CB FINANCE</span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="index.php" class="<?php echo $currentPage == 'index.php' ? 'text-primary-green' : 'text-gray-600'; ?> hover:text-primary-green font-medium transition-colors duration-200 flex items-center gap-2">
                    <i class="fas fa-home text-sm"></i> Home
                </a>
                <a href="services.php" class="<?php echo $currentPage == 'services.php' ? 'text-primary-green' : 'text-gray-600'; ?> hover:text-primary-green font-medium transition-colors duration-200 flex items-center gap-2">
                    <i class="fas fa-briefcase text-sm"></i> Services
                </a>
                <a href="requirements.php" class="<?php echo $currentPage == 'requirements.php' ? 'text-primary-green' : 'text-gray-600'; ?> hover:text-primary-green font-medium transition-colors duration-200 flex items-center gap-2">
                    <i class="fas fa-file-invoice text-sm"></i> Requirements
                </a>
                <a href="team.php" class="<?php echo $currentPage == 'team.php' ? 'text-primary-green' : 'text-gray-600'; ?> hover:text-primary-green font-medium transition-colors duration-200 flex items-center gap-2">
                    <i class="fas fa-users text-sm"></i> Team
                </a>
                <a href="contact.php" class="<?php echo $currentPage == 'contact.php' ? 'text-primary-green' : 'text-gray-600'; ?> hover:text-primary-green font-medium transition-colors duration-200 flex items-center gap-2">
                    <i class="fas fa-envelope text-sm"></i> Contact
                </a>
                <a href="https://app.cbfinance.rw/login.php" target="_blank" class="border-2 border-primary-blue text-primary-blue hover:bg-primary-blue hover:text-white px-5 py-2 rounded-full font-semibold transition-all duration-300 flex items-center gap-2">
                    <i class="fas fa-user-lock text-sm"></i> System Portal
                </a>
                <a href="apply.php" class="bg-primary-green hover:bg-green-600 text-white px-6 py-2.5 rounded-full font-semibold transition-all duration-300 transform hover:scale-105 shadow-md flex items-center gap-2">
                    <i class="fas fa-paper-plane text-sm"></i> Apply Now
                </a>
            </div>

            <!-- Mobile Menu Button (Hamburger) - Usually hidden if using bottom nav, but good to have -->
            <div class="md:hidden flex items-center">
                <button id="mobile-menu-button" class="text-primary-blue focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- Push content down -->
<div class="h-20"></div>
