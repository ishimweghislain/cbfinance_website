<?php include 'includes/head.php'; ?>
<?php include 'includes/navbar.php'; ?>

<!-- Header -->
<section class="gradient-blue py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Our Services</h1>
        <p class="text-blue-100 text-lg max-w-2xl mx-auto">Currently, we specialize exclusively in loan products, providing the capital you need to succeed.</p>
    </div>
</section>

<!-- Loans Section -->
<section class="py-24 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-16">
        <h2 class="text-3xl font-extrabold text-neutral-heading uppercase tracking-widest mb-4">Loan Products</h2>
        <div class="w-24 h-1.5 bg-primary-green mx-auto rounded-full"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
        <!-- Salary Advance -->
        <div class="bg-white rounded-[2.5rem] shadow-2xl p-10 border border-gray-100 hover:border-primary-green transition-all group">
            <div class="w-20 h-20 bg-green-50 rounded-2xl flex items-center justify-center mb-8 group-hover:bg-primary-green transition-colors">
                <i class="fas fa-hand-holding-usd text-3xl text-primary-green group-hover:text-white"></i>
            </div>
            <h3 class="text-2xl font-bold text-neutral-heading mb-4">Salary Advance</h3>
            <p class="text-gray-600 mb-8">Quick financial assistance for employees to cover urgent needs before the next payday.</p>
            <a href="requirements.php" class="inline-flex items-center gap-2 bg-primary-green hover:bg-green-600 text-white px-8 py-4 rounded-2xl font-bold transition-all transform hover:scale-105">
                View Requirements <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>

        <!-- Business Loan -->
        <div class="bg-white rounded-[2.5rem] shadow-2xl p-10 border border-gray-100 hover:border-primary-blue transition-all group">
            <div class="w-20 h-20 bg-blue-50 rounded-2xl flex items-center justify-center mb-8 group-hover:bg-primary-blue transition-colors">
                <i class="fas fa-briefcase text-3xl text-primary-blue group-hover:text-white"></i>
            </div>
            <h3 class="text-2xl font-bold text-neutral-heading mb-4">Business Loan</h3>
            <p class="text-gray-600 mb-8">Strategic funding for businesses, startups, and SMEs to scale operations and handle larger projects.</p>
            <a href="requirements.php" class="inline-flex items-center gap-2 bg-primary-blue hover:bg-blue-800 text-white px-8 py-4 rounded-2xl font-bold transition-all transform hover:scale-105">
                View Requirements <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>
</section>

<!-- Fee Structure -->
<section class="py-24 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-[3rem] shadow-xl overflow-hidden">
            <div class="bg-[#1A1F2E] p-10 text-center">
                <h3 class="text-2xl font-bold text-white">Transparent Pricing</h3>
                <p class="text-gray-400 mt-2">No hidden costs. Simple and fair rates for everyone.</p>
            </div>
            
            <div class="p-10">
                <div class="space-y-6">
                    <!-- Interest Rate -->
                    <div class="flex items-center justify-between p-6 bg-blue-50 rounded-2xl border border-blue-100">
                        <div>
                            <span class="block font-bold text-primary-blue uppercase text-xs tracking-widest mb-1">Interest Rate</span>
                            <span class="text-gray-700 font-medium">Monthly reducing balance</span>
                        </div>
                        <div class="text-3xl font-black text-primary-blue">5%</div>
                    </div>

                    <!-- Application Fee -->
                    <div class="flex items-center justify-between p-6 bg-green-50 rounded-2xl border border-green-100">
                        <div>
                            <span class="block font-bold text-primary-green uppercase text-xs tracking-widest mb-1">Application Fee</span>
                            <span class="text-gray-700 font-medium">Standard processing fee</span>
                        </div>
                        <div class="text-2xl font-black text-primary-green text-right">
                            10,000 - 15,000 <br>
                            <small class="text-[10px] uppercase tracking-tighter text-gray-400">RWF (One-time)</small>
                        </div>
                    </div>

                    <!-- Commission -->
                    <div class="flex items-center justify-between p-6 bg-gray-50 rounded-2xl border border-gray-100">
                        <div>
                            <span class="block font-bold text-gray-400 uppercase text-xs tracking-widest mb-1">Commission</span>
                            <span class="text-gray-600 font-medium">Processing commission</span>
                        </div>
                        <div class="text-2xl font-black text-gray-700">1.5%</div>
                    </div>
                </div>

                <div class="mt-10 text-center">
                    <p class="text-sm text-gray-400">Note: Terms and conditions apply to all loan products.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/bottom_nav.php'; ?>
<?php include 'includes/footer.php'; ?>
