<?php include 'includes/head.php'; ?>
<?php include 'includes/navbar.php'; ?>

<!-- Header -->
<section class="gradient-blue py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Contact Us</h1>
        <p class="text-blue-100 text-lg max-w-2xl mx-auto">Get in touch with our team for any questions or to start your loan application.</p>
    </div>
</section>

<!-- Contact Info Cards -->
<section class="py-12 -mt-10 relative z-20 max-w-7xl mx-auto px-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-50 flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-primary-blue flex-shrink-0">
                <i class="fas fa-phone-alt text-xl"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Phone</p>
                <p class="text-sm font-bold text-neutral-heading">+250 796 880 272</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-50 flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center text-primary-green flex-shrink-0">
                <i class="fas fa-envelope text-xl"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Email</p>
                <p class="text-sm font-bold text-neutral-heading">info@cbfinance.rw</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-50 flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-primary-light flex-shrink-0">
                <i class="fas fa-map-marker-alt text-xl"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Address</p>
                <p class="text-sm font-bold text-neutral-heading">Ikaze House 2nd floor, Kigali</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-50 flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center text-gray-500 flex-shrink-0">
                <i class="fas fa-clock text-xl"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Working Hours</p>
                <p class="text-sm font-bold text-neutral-heading">Mon - Fri, 9AM - 6PM</p>
            </div>
        </div>
    </div>
</section>

<!-- Form and Map -->
<section class="py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">
        <!-- Message Form -->
        <div class="bg-white rounded-3xl p-8 md:p-12 shadow-xl border border-gray-100">
            <div class="mb-10">
                <h3 class="text-3xl font-bold text-neutral-heading mb-4">Send us a Message</h3>
                <p class="text-gray-500">Fill out the form below and we'll get back to you within 24 hours.</p>
            </div>

            <form action="#" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700 ml-1">First Name</label>
                        <input type="text" placeholder="John" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-green focus:bg-white transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700 ml-1">Last Name</label>
                        <input type="text" placeholder="Doe" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-green focus:bg-white transition-all">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700 ml-1">Email Address</label>
                    <input type="email" placeholder="john.doe@example.com" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-green focus:bg-white transition-all">
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700 ml-1">Phone Number</label>
                    <input type="tel" placeholder="+250 788 123 456" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-green focus:bg-white transition-all">
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700 ml-1">Your Message</label>
                    <textarea rows="4" placeholder="Tell us more about your loan requirements..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-green focus:bg-white transition-all resize-none"></textarea>
                </div>

                <button type="submit" class="w-full bg-primary-green hover:bg-green-600 text-white font-bold py-4 rounded-xl shadow-lg transition-all duration-300 transform hover:scale-[1.02] flex items-center justify-center space-x-2">
                    <span>Send Message</span>
                    <i class="fas fa-paper-plane text-sm"></i>
                </button>

                <div class="flex items-center justify-center space-x-2 text-sm text-gray-400 mt-6">
                    <i class="fas fa-shield-alt"></i>
                    <span>Your privacy is protected. We never share your data.</span>
                </div>
            </form>
        </div>

        <!-- Map and Info -->
        <div class="flex flex-col space-y-8">
            <div class="bg-gray-200 rounded-3xl overflow-hidden h-[400px] shadow-inner relative group">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3987.5034636906225!2d30.119044814753!3d-1.951528498577317!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x19dca685521ed6c7%3A0x77c2270914603688!2sGisimenti!5e0!3m2!1sen!2srw!4v1654321098765!5m2!1sen!2srw" 
                    width="100%" 
                    height="100%" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade"
                    class="grayscale group-hover:grayscale-0 transition-all duration-500">
                </iframe>
                <div class="absolute bottom-4 left-4 right-4 bg-white/90 backdrop-blur-md p-4 rounded-2xl shadow-lg flex items-center justify-between pointer-events-none transition-all duration-300 group-hover:translate-y-20 opacity-100 group-hover:opacity-0">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase">Our Location</p>
                        <p class="text-sm font-bold text-neutral-heading">Ikaze House, 2nd Floor, Remera</p>
                    </div>
                    <i class="fas fa-directions text-primary-blue text-xl"></i>
                </div>
            </div>

            <div class="bg-primary-blue rounded-3xl p-10 text-white relative overflow-hidden flex-grow shadow-2xl">
                <div class="relative z-10">
                    <h4 class="text-2xl font-bold mb-6">Visit Our Office</h4>
                    <p class="text-blue-100 mb-8 leading-relaxed">
                        We are located in the heart of Gisimenti. Stop by for a coffee and let's discuss how we can support your financial goals.
                    </p>
                    
                    <div class="space-y-6">
                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center"><i class="fas fa-bus text-primary-light"></i></div>
                            <div>
                                <p class="text-sm font-bold">Public Transport</p>
                                <p class="text-xs text-blue-200">Easy access via Remera/Gisimenti bus stop</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center"><i class="fas fa-parking text-primary-green"></i></div>
                            <div>
                                <p class="text-sm font-bold">Free Parking</p>
                                <p class="text-xs text-blue-200">Dedicated parking slots for our visitors</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-white/5 rounded-full"></div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/bottom_nav.php'; ?>
<?php include 'includes/footer.php'; ?>
