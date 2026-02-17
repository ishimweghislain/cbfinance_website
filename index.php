<?php include 'includes/head.php'; ?>
<?php include 'includes/navbar.php'; ?>

<!-- Hero Section -->
<section class="relative min-h-[80vh] flex items-center overflow-hidden">
    <!-- Background Image with Overlay -->
    <div class="absolute inset-0 z-0">
        <img src="images/homepagepic.jpg" alt="Capital Bridge Finance" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-r from-primary-blue/90 via-primary-blue/70 to-transparent"></div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 py-20">
        <div class="max-w-2xl text-white">
            <span class="inline-block bg-primary-green/20 text-primary-light px-4 py-2 rounded-full text-sm font-semibold mb-6 backdrop-blur-sm border border-primary-light/30">
                <i class="fas fa-chart-line mr-2"></i> Trusted Financial Partner
            </span>
            <h1 class="text-5xl md:text-7xl font-bold leading-tight mb-6">
                Redefining Your <br>
                <span class="text-primary-green">Financial Future</span>
            </h1>
            <p class="text-xl text-gray-200 mb-10 leading-relaxed">
                Empowering individuals and businesses with fast, secure, and flexible financial solutions. Get the support you need, when you need it.
            </p>
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="apply.php" class="bg-primary-green hover:bg-green-600 text-white px-8 py-4 rounded-full font-bold text-lg transition-all duration-300 transform hover:scale-105 shadow-xl flex items-center justify-center gap-3">
                    Apply for a Loan <i class="fas fa-arrow-right"></i>
                </a>
                <a href="services.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/30 backdrop-blur-md px-8 py-4 rounded-full font-bold text-lg transition-all duration-300 flex items-center justify-center gap-3">
                    Explore Services
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-12 bg-white shadow-sm relative z-20 -mt-10 mx-4 rounded-2xl max-w-6xl md:mx-auto">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 px-8">
        <div class="text-center group hover:scale-105 transition-transform duration-300">
            <div class="text-3xl font-bold text-primary-blue mb-1">98%</div>
            <p class="text-gray-500 text-sm">Approval Rate</p>
        </div>
        <div class="text-center group hover:scale-105 transition-transform duration-300 border-l border-gray-100">
            <div class="text-3xl font-bold text-primary-blue mb-1">24h</div>
            <p class="text-gray-500 text-sm">Processing Time</p>
        </div>
        <div class="text-center group hover:scale-105 transition-transform duration-300 border-l border-gray-100">
            <div class="text-3xl font-bold text-primary-blue mb-1">5000+</div>
            <p class="text-gray-500 text-sm">Happy Clients</p>
        </div>
        <div class="text-center group hover:scale-105 transition-transform duration-300 border-l border-gray-100">
            <div class="text-3xl font-bold text-primary-blue mb-1">8%</div>
            <p class="text-gray-500 text-sm">Starting Interest</p>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-16">
        <h2 class="text-gray-400 font-semibold uppercase tracking-widest text-sm mb-3">Professional Edge</h2>
        <h3 class="text-4xl font-bold text-neutral-heading">Why Choose <span class="text-primary-blue border-b-4 border-primary-green">CB Finance?</span></h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Card 1 -->
        <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-50 group">
            <div class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center mb-8 group-hover:bg-primary-blue group-hover:text-white transition-colors duration-300">
                <i class="fas fa-shield-alt text-2xl text-primary-blue group-hover:text-white"></i>
            </div>
            <h4 class="text-2xl font-bold text-neutral-heading mb-4">Secure & Stable</h4>
            <p class="text-gray-600 leading-relaxed">
                Your financial data and transactions are protected by world-class security protocols. We offer stability you can trust.
            </p>
        </div>

        <!-- Card 2 -->
        <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-50 group">
            <div class="w-16 h-16 bg-green-50 rounded-2xl flex items-center justify-center mb-8 group-hover:bg-primary-green group-hover:text-white transition-colors duration-300">
                <i class="fas fa-bolt text-2xl text-primary-green group-hover:text-white"></i>
            </div>
            <h4 class="text-2xl font-bold text-neutral-heading mb-4">Fast Processing</h4>
            <p class="text-gray-600 leading-relaxed">
                Time is money. Our digital-first approach ensures that your applications are processed with unmatched speed.
            </p>
        </div>

        <!-- Card 3 -->
        <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-50 group">
            <div class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center mb-8 group-hover:bg-primary-light group-hover:text-white transition-colors duration-300">
                <i class="fas fa-hand-holding-usd text-2xl text-primary-light group-hover:text-white"></i>
            </div>
            <h4 class="text-2xl font-bold text-neutral-heading mb-4">Flexible Terms</h4>
            <p class="text-gray-600 leading-relaxed">
                We believe in tailored solutions. Our repayment terms and loan structures are designed to adapt to your unique needs.
            </p>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-24 bg-primary-blue relative overflow-hidden">
    <!-- Decorative patterns -->
    <div class="absolute top-0 right-0 w-64 h-64 bg-primary-light opacity-10 rounded-full translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 left-0 w-96 h-96 bg-primary-green opacity-10 rounded-full -translate-x-1/2 translate-y-1/2"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <h2 class="text-4xl md:text-5xl font-bold text-white mb-8">Ready to take the next step?</h2>
        <p class="text-xl text-blue-100 mb-12 max-w-3xl mx-auto">
            Get an instant decision on your loan application. Simple, fast, and completely secure.
        </p>
        <a href="apply.php" class="bg-white text-primary-blue hover:bg-primary-green hover:text-white px-10 py-5 rounded-full font-bold text-xl transition-all duration-300 shadow-2xl flex items-center justify-center gap-3 w-max mx-auto">
            Apply Now <i class="fas fa-paper-plane"></i>
        </a>
    </div>
</section>

<?php include 'includes/bottom_nav.php'; ?>
<?php include 'includes/footer.php'; ?>
