<?php
$currentPage = getCurrentPage();
?>
<nav class="fixed top-0 left-0 w-full z-50 nav-transition">
    <!-- Top Contact Pop Bar (Always Visible) -->
    <div class="bg-gradient-to-r from-[#003366] via-[#0047AB] to-[#003366] text-white py-2.5 shadow-lg border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-between items-center text-[10px] md:text-[11px] font-bold tracking-widest uppercase">
                <!-- Location & Hours -->
                <div class="flex items-center space-x-4 md:space-x-8">
                    <div class="flex items-center gap-2 group cursor-pointer">
                        <div class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center group-hover:bg-primary-green transition-all">
                            <i class="fas fa-map-marker-alt text-[10px] text-primary-green group-hover:text-white animate-pulse"></i>
                        </div>
                        <span class="hidden sm:inline text-white/90">Ikaze House 2nd Floor, Gisimenti</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center">
                            <i class="fas fa-clock text-[10px] text-primary-light"></i>
                        </div>
                        <span class="hidden sm:inline text-white/90">Mon-Fri: 9AM - 6PM</span>
                    </div>
                </div>

                <!-- Phone & Email -->
                <div class="flex items-center space-x-4 md:space-x-8 ml-auto sm:ml-0">
                    <a href="tel:+250796880272" class="flex items-center gap-2 group">
                        <div class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center group-hover:bg-primary-green transition-all">
                            <i class="fas fa-phone-alt text-[10px] text-primary-green group-hover:text-white"></i>
                        </div>
                        <span class="text-white/90 group-hover:text-primary-green transition-colors">+250 796 880 272</span>
                    </a>
                    <a href="mailto:info@cbfinance.rw" class="hidden md:flex items-center gap-2 group">
                        <div class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center group-hover:bg-primary-light transition-all">
                            <i class="fas fa-envelope text-[10px] text-primary-light group-hover:text-white"></i>
                        </div>
                        <span class="text-white/90 group-hover:text-primary-light transition-colors">info@cbfinance.rw</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main White Navigation Bar -->
    <div class="bg-white/95 backdrop-blur-md shadow-sm border-b border-gray-100 h-20 transition-all duration-300" id="main-nav">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full">
            <div class="flex justify-between items-center h-full">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="index.php" class="flex items-center space-x-3 group">
                        <img src="images/CAPITAL BRIDGE LOGO.png" alt="CB Finance Logo" class="h-12 w-auto transition-transform duration-300 group-hover:scale-105">
                        <div class="flex flex-col">
                            <span class="text-primary-blue font-black text-xl leading-none tracking-tighter">CB FINANCE</span>
                            <span class="text-[8px] text-primary-green font-bold tracking-[0.2em] uppercase mt-1">Capital Bridge Finance Ltd</span>
                        </div>
                    </a>
                </div>

                <!-- Desktop Navigation Links -->
                <div class="hidden md:flex items-center space-x-1 lg:space-x-4">
                    <a href="index.php" class="px-4 py-2 rounded-xl <?php echo $currentPage == 'index.php' ? 'bg-green-50 text-primary-green' : 'text-gray-600 hover:bg-gray-50 hover:text-primary-blue'; ?> font-bold text-sm transition-all duration-200 flex items-center gap-2">
                        Home
                    </a>
                    <a href="services.php" class="px-4 py-2 rounded-xl <?php echo $currentPage == 'services.php' ? 'bg-green-50 text-primary-green' : 'text-gray-600 hover:bg-gray-50 hover:text-primary-blue'; ?> font-bold text-sm transition-all duration-200 flex items-center gap-2">
                        Services
                    </a>
                    <a href="requirements.php" class="px-4 py-2 rounded-xl <?php echo $currentPage == 'requirements.php' ? 'bg-green-50 text-primary-green' : 'text-gray-600 hover:bg-gray-50 hover:text-primary-blue'; ?> font-bold text-sm transition-all duration-200 flex items-center gap-2">
                        Requirements
                    </a>
                    <a href="team.php" class="px-4 py-2 rounded-xl <?php echo $currentPage == 'team.php' ? 'bg-green-50 text-primary-green' : 'text-gray-600 hover:bg-gray-50 hover:text-primary-blue'; ?> font-bold text-sm transition-all duration-200 flex items-center gap-2">
                        Our Team
                    </a>
                    <a href="contact.php" class="px-4 py-2 rounded-xl <?php echo $currentPage == 'contact.php' ? 'bg-green-50 text-primary-green' : 'text-gray-600 hover:bg-gray-50 hover:text-primary-blue'; ?> font-bold text-sm transition-all duration-200 flex items-center gap-2">
                        Contact
                    </a>
                    
                    <!-- Vertical Divider -->
                    <div class="h-8 w-[1px] bg-gray-100 mx-2"></div>

                    <a href="https://app.cbfinance.rw/login.php" target="_blank" class="border-2 border-primary-blue/20 text-primary-blue hover:bg-primary-blue hover:text-white px-5 py-2 rounded-full font-bold text-sm transition-all duration-300 flex items-center gap-2 group">
                        <i class="fas fa-lock text-xs text-primary-blue group-hover:text-white"></i> Portal
                    </a>
                    <a href="apply.php" class="bg-primary-green hover:bg-green-600 text-white px-6 py-2.5 rounded-full font-bold text-sm transition-all duration-300 transform hover:shadow-xl flex items-center gap-2 h-11">
                        Apply Now
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Enhanced Spacer for the new fixed height -->
<div class="h-[120px] md:h-[130px]"></div>

<script>
    // Scroll effect for the main navbar
    window.addEventListener('scroll', function() {
        const mainNav = document.getElementById('main-nav');
        if (window.scrollY > 40) {
            mainNav.classList.add('h-16');
            mainNav.classList.remove('h-20');
        } else {
            mainNav.classList.add('h-20');
            mainNav.classList.remove('h-16');
        }
    });
</script>
