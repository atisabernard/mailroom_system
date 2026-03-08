<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Room</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        /* Sidebar slide transition */
        .sidebar {
            transition: transform 0.3s ease-in-out;
        }
    </style>
</head>

<body class="bg-[#f5f5f4] text-[#1e1e1e]">

    <?php
    // Get current script path for active link detection
    $current_page = $_SERVER['SCRIPT_NAME'];
    ?>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar w-60 lg:w-60 bg-white border-r border-[#e5e5e5] min-h-screen fixed left-0 top-0 overflow-y-auto transform -translate-x-full lg:translate-x-0 transition-all duration-300">
        <!-- Collapse Button (Desktop) -->
        <button id="collapseBtn" class="hidden lg:flex absolute top-4 right-[-12px] bg-white border border-[#e5e5e5] text-[#1e1e1e] p-1 rounded-full z-50">
            <i class="fa-solid fa-angle-left"></i>
        </button>

        <!-- Logo -->
        <div class="px-4 py-5 flex items-center gap-2">
            <img src="images/logo.png" alt="Mail Room" class="w-6 h-6">
            <span class="text-[#1e1e1e] text-base font-medium sidebar-text">Mail Room</span>
        </div>

        <!-- Navigation -->
        <nav class="px-3">
            <!-- Dashboard -->
            <a href="./index.php"
                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?= $current_page == '/index.php' ? 'bg-[#f0f0f0] text-[#1e1e1e]' : 'text-[#6e6e6e] hover:text-[#1e1e1e] hover:bg-[#f5f5f4]'; ?>">
                <i class="fa-solid fa-home w-4 text-current"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>

            <!-- Newspapers -->
            <div class="text-xs text-[#9e9e9e] px-3 pt-5 pb-1 sidebar-text">Newspapers</div>
            <a href="./newspaper_categories.php"
                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?= $current_page == './add.php' ? 'bg-[#f0f0f0] text-[#1e1e1e]' : 'text-[#6e6e6e] hover:text-[#1e1e1e] hover:bg-[#f5f5f4]'; ?>">
                <i class="fa-solid fa-plus w-4"></i>
                <span class="sidebar-text">Add Newspaper</span>
            </a>
            <a href="./list.php"
                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?= $current_page == './list.php' ? 'bg-[#f0f0f0] text-[#1e1e1e]' : 'text-[#6e6e6e] hover:text-[#1e1e1e] hover:bg-[#f5f5f4]'; ?>">
                <i class="fa-solid fa-newspaper w-4"></i>
                <span class="sidebar-text">Newspaper List</span>
            </a>

            <!-- Documents -->
            <div class="text-xs text-[#9e9e9e] px-3 pt-5 pb-1 sidebar-text">Documents</div>
            <a href="./documents.php"
                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?= $current_page == '/documents/add.php' ? 'bg-[#f0f0f0] text-[#1e1e1e]' : 'text-[#6e6e6e] hover:text-[#1e1e1e] hover:bg-[#f5f5f4]'; ?>">
                <i class="fa-solid fa-upload w-4"></i>
                <span class="sidebar-text">Add Document</span>
            </a>
            <a href="./document_type.php"
                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?= $current_page == '/documents/list.php' ? 'bg-[#f0f0f0] text-[#1e1e1e]' : 'text-[#6e6e6e] hover:text-[#1e1e1e] hover:bg-[#f5f5f4]'; ?>">
                <i class="fa-solid fa-file-lines w-4"></i>
                <span class="sidebar-text">Document List</span>
            </a>

            <!-- Parcels -->
            <div class="text-xs text-[#9e9e9e] px-3 pt-5 pb-1 sidebar-text">Parcels</div>
            <a href="./parcels.php"
                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?= $current_page == './parcels.php' ? 'bg-[#f0f0f0] text-[#1e1e1e]' : 'text-[#6e6e6e] hover:text-[#1e1e1e] hover:bg-[#f5f5f4]'; ?>">
                <i class="fa-solid fa-inbox w-4"></i>
                <span class="sidebar-text">Parcels</span>
            </a>
            <!--<a href="./pickup.php"
                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?= $current_page == '/parcels/pickup.php' ? 'bg-[#f0f0f0] text-[#1e1e1e]' : 'text-[#6e6e6e] hover:text-[#1e1e1e] hover:bg-[#f5f5f4]'; ?>">
                <i class="fa-solid fa-truck w-4"></i>
                <span class="sidebar-text">Parcel Pickup</span>
            </a>-->

            <!-- Distribution -->
            <div class="text-xs text-[#9e9e9e] px-3 pt-5 pb-1 sidebar-text">Distribution</div>
            <a href="/distribution/add.php"
                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?= $current_page == '/distribution/add.php' ? 'bg-[#f0f0f0] text-[#1e1e1e]' : 'text-[#6e6e6e] hover:text-[#1e1e1e] hover:bg-[#f5f5f4]'; ?>">
                <i class="fa-solid fa-chart-line w-4"></i>
                <span class="sidebar-text">Add Distribution</span>
            </a>
            <a href="./print.php"
                class="flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?= $current_page == '/distribution/print.php' ? 'bg-[#f0f0f0] text-[#1e1e1e]' : 'text-[#6e6e6e] hover:text-[#1e1e1e] hover:bg-[#f5f5f4]'; ?>">
                <i class="fa-solid fa-print w-4"></i>
                <span class="sidebar-text">Print Distribution</span>
            </a>
        </nav>

        <!-- User info -->
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-[#e5e5e5] flex items-center gap-3">
            <div class="w-8 h-8 rounded-md bg-[#f0f0f0] flex items-center justify-center text-sm text-[#1e1e1e]">MS</div>
            <span class="sidebar-text text-[#1e1e1e]">Mailroom Staff</span>
        </div>
    </div>

    <!-- Mobile Menu Button -->
    <button id="mobileMenuBtn" class="lg:hidden fixed top-4 left-4 z-50 bg-white border border-[#e5e5e5] text-[#1e1e1e] p-2 rounded-md">
        <i class="fa-solid fa-bars"></i>
    </button>

    <script>
        const sidebar = document.getElementById('sidebar');
        const mobileBtn = document.getElementById('mobileMenuBtn');
        const collapseBtn = document.getElementById('collapseBtn');

        // Mobile toggle
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
        });

        // Desktop collapse toggle
        collapseBtn.addEventListener('click', () => {
            sidebar.classList.toggle('w-60');
            sidebar.classList.toggle('w-16');
            document.querySelectorAll('.sidebar-text').forEach(el => {
                el.classList.toggle('hidden');
            });
            collapseBtn.querySelector('i').classList.toggle('fa-angle-left');
            collapseBtn.querySelector('i').classList.toggle('fa-angle-right');
        });
    </script>
</body>

</html>