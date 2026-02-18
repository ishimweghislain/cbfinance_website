<footer class="bg-[#1A1F2E] text-white pt-16 pb-24 md:pb-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <!-- Company Info -->
            <div class="col-span-1 md:col-span-1 border-gray-600">
                <div class="flex items-center space-x-2 mb-6">
                    <img src="images/CAPITAL BRIDGE LOGO.png" alt="CB Finance Logo" class="h-12 w-auto brightness-0 invert">
                    <span class="text-white font-bold text-xl uppercase tracking-tighter">Capital Bridge Finance</span>
                </div>
                <p class="text-gray-400 text-xs mt-1 italic">Empowering your financial journey</p>
                <p class="text-gray-400 leading-relaxed mb-6">
                    Providing professional and innovative financial solutions to fuel your growth and stability.
                </p>
                <div class="flex space-x-4">
                    <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary-green transition-colors">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary-green transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary-green transition-colors">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-lg font-bold mb-6 border-b-2 border-primary-green w-16 pb-2">Quick Links</h4>
                <ul class="space-y-4 text-gray-400">
                    <li><a href="index.php" class="hover:text-primary-green transition-colors">Home</a></li>
                    <li><a href="services.php" class="hover:text-primary-green transition-colors">Our Services</a></li>
                    <li><a href="requirements.php" class="hover:text-primary-green transition-colors">Requirements</a></li>
                    <li><a href="team.php" class="hover:text-primary-green transition-colors">Our Team</a></li>
                    <li><a href="contact.php" class="hover:text-primary-green transition-colors">Contact Us</a></li>
                    <li><a href="https://app.cbfinance.rw/login.php" target="_blank" class="text-primary-light hover:text-white transition-colors flex items-center gap-2"><i class="fas fa-lock text-xs"></i> System Portal</a></li>
                </ul>
            </div>

            <!-- Services -->
            <div>
                <h4 class="text-lg font-bold mb-6 border-b-2 border-primary-green w-16 pb-2">Services</h4>
                <ul class="space-y-4 text-gray-400">
                    <li><a href="services.php" class="hover:text-primary-green transition-colors">Loans</a></li>
                  
                </ul>
            </div>

            <!-- Contact Info -->
            <div>
                <h4 class="text-lg font-bold mb-6 border-b-2 border-primary-green w-16 pb-2">Contact Us</h4>
                <ul class="space-y-4 text-gray-400">
                    <li class="flex items-start space-x-3">
                        <i class="fas fa-map-marker-alt mt-1 text-primary-green"></i>
                        <span>Ikaze House 2nd floor<br>Remera, Gisimenti KG11AV<br>Kigali, Rwanda</span>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fas fa-phone-alt text-primary-green"></i>
                        <span>+250 796 880 272</span>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fas fa-envelope text-primary-green"></i>
                        <span>info@cbfinance.rw</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="border-t border-gray-800 mt-16 pt-8 flex flex-col md:flex-row justify-between items-center text-sm text-gray-500">
            <p>© 2025 Capital Bridge Finance. All rights reserved.</p>
            <div class="flex space-x-6 mt-4 md:mt-0">
                <a href="#" class="hover:text-primary-green transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-primary-green transition-colors">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<script>
    // Add scroll effect to navbar
    window.addEventListener('scroll', function() {
        const nav = document.querySelector('nav');
        if (window.scrollY > 50) {
            nav.classList.add('shadow-lg');
            nav.classList.remove('h-20');
            nav.classList.add('h-16');
        } else {
            nav.classList.remove('shadow-lg');
            nav.classList.add('h-20');
            nav.classList.remove('h-16');
        }
    });

    // Simple mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-button');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            // For now, let's just alert since we have the bottom nav for mobile navigation
            // or we could show a full screen menu. Given bottom nav is requested,
            // we'll prioritize that.
        });
    }
</script>
</body>
</html>
