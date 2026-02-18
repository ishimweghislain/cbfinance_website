<?php
$currentPage = getCurrentPage();
?>
<!-- Mobile Bottom Navigation (Instagram Style) -->
<div class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 flex justify-around items-center py-3 shadow-[0_-2px_10px_rgba(0,0,0,0.1)]">
    <a href="index.php" class="flex flex-col items-center <?php echo $currentPage == 'index.php' ? 'text-primary-green' : 'text-gray-400'; ?>">
        <i class="fas fa-home text-xl"></i>
        <span class="text-[10px] mt-1 font-medium">Home</span>
    </a>
    <a href="services.php" class="flex flex-col items-center <?php echo $currentPage == 'services.php' ? 'text-primary-green' : 'text-gray-400'; ?>">
        <i class="fas fa-briefcase text-xl"></i>
        <span class="text-[10px] mt-1 font-medium">Services</span>
    </a>
    <!-- Center Action Button -->
    <a href="apply.php" class="flex flex-col items-center -translate-y-4">
        <div class="bg-primary-green text-white p-4 rounded-full shadow-lg border-4 border-neutral-bg">
            <i class="fas fa-plus text-xl"></i>
        </div>
        <span class="text-[10px] mt-1 font-medium text-primary-green -translate-y-2">Apply</span>
    </a>
    <a href="contact.php" class="flex flex-col items-center <?php echo $currentPage == 'contact.php' ? 'text-primary-green' : 'text-gray-400'; ?>">
        <i class="fas fa-envelope text-xl"></i>
        <span class="text-[10px] mt-1 font-medium">Contact</span>
    </a>
    <a href="https://app.cbfinance.rw/login.php" target="_blank" class="flex flex-col items-center text-primary-blue">
        <i class="fas fa-lock text-xl"></i>
        <span class="text-[10px] mt-1 font-medium">Portal</span>
    </a>
    
</div>
