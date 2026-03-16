<?php
// sidebar.php
// Get current script path for active link detection
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
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

        /* Active link styling */
        .nav-link.active {
            background-color: #f0f0f0;
            color: #1e1e1e;
        }

        .nav-link {
            color: #6e6e6e;
        }

        .nav-link:hover {
            background-color: #f5f5f4;
            color: #1e1e1e;
        }

        /* Enable vertical scrolling for sidebar */
        .sidebar {
            overflow-y: auto;
            max-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Make navigation section scrollable */
        .sidebar nav {
            flex: 1 1 auto;
            overflow-y: auto;
            padding-bottom: 20px;
            /* Add some space before user info */
        }

        /* Keep user info fixed at bottom */
        .user-info-container {
            position: sticky;
            bottom: 0;
            background: white;
            border-top: 1px solid #e5e5e5;
            margin-top: auto;
            width: 100%;
        }

        /* Custom scrollbar styling (optional) */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
    </style>
</head>

<body class="bg-[#f5f5f4] text-[#1e1e1e]">

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar w-60 lg:w-60 bg-white border-r border-[#e5e5e5] min-h-screen fixed left-0 top-0 overflow-y-auto transform -translate-x-full lg:translate-x-0 transition-all duration-300 z-40">
        <!-- Logo (always visible) -->
        <div class="px-4 py-5 flex items-center gap-2 sticky top-0 bg-white z-10">
            <img src="images/logo.png" alt="Mail Room" class="w-6 h-6">
            <span class="text-[#1e1e1e] text-base font-medium sidebar-text">LIBRARY</span>
        </div>

        <!-- Navigation (scrollable area) -->
        <nav class="px-3 pb-4">
            <!-- Dashboard -->
            <div class="text-xs text-[#9e9e9e] px-3 pt-2 pb-1 sidebar-text">MAIN</div>
            <a href="index.php"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-home w-4 text-current"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>

            <!-- Newspapers Section -->
            <div class="text-xs text-[#9e9e9e] px-3 pt-5 pb-1 sidebar-text">NEWSPAPERS</div>
            <a href="newspaper_categories.php"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'newspaper_categories.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-tags w-4"></i>
                <span class="sidebar-text">Newspaper Categories</span>
            </a>
            <a href="list.php"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'list.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-newspaper w-4"></i>
                <span class="sidebar-text">Newspaper List</span>
            </a>
            <a href="available.php"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'available.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-share-from-square w-4"></i>
                <span class="sidebar-text">Newspaper Distribution</span>
            </a>
            <!--<a href="distribution.php"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'distribution.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clock-rotate-left w-4"></i>
                <span class="sidebar-text">Distribution History</span>
            </a>-->

            <!-- Documents Section -->
            <div class="text-xs text-[#9e9e9e] px-3 pt-5 pb-1 sidebar-text">DOCUMENTS</div>
            <a href="documents.php"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'list.php' && isset($_GET['tab']) && $_GET['tab'] == 'documents' ? 'active' : ''; ?>">
                <i class="fa-regular fa-file-lines w-4"></i>
                <span class="sidebar-text">All Documents</span>
            </a>
            <a href="document_type.php"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'document_types.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-tags w-4"></i>
                <span class="sidebar-text">Document Types</span>
            </a>
            <a href="distribution.php"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'document_distribution.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-share-from-square w-4"></i>
                <span class="sidebar-text">Document Distribution</span>
            </a>

            <!-- Parcels Section -->
            <div class="text-xs text-[#9e9e9e] px-3 pt-5 pb-1 sidebar-text">PARCELS</div>
            <a href="parcels.php"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'parcels.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-box w-4"></i>
                <span class="sidebar-text">Parcel Management</span>
            </a>
            <!--<a href="parcels.php?tab=pickup"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'parcels.php' && isset($_GET['tab']) && $_GET['tab'] == 'pickup' ? 'active' : ''; ?>">
                <i class="fa-solid fa-truck w-4"></i>
                <span class="sidebar-text">Pickups</span>
            </a>
            <a href="parcels.php?tab=records"
                class="nav-link flex items-center gap-3 px-3 py-2 text-sm rounded-md mb-1 <?php echo $current_page == 'parcels.php' && isset($_GET['tab']) && $_GET['tab'] == 'records' ? 'active' : ''; ?>">
                <i class="fa-regular fa-rectangle-list w-4"></i>
                <span class="sidebar-text">All Records</span>
            </a>-->
        </nav>

        <!-- User info (fixed at bottom) -->
        <div class="user-info-container p-4 border-t border-[#e5e5e5] flex items-center gap-3 bg-white">
            <div class="w-8 h-8 rounded-md bg-[#f0f0f0] flex items-center justify-center text-sm text-[#1e1e1e] font-medium">
                <img src="./images/logo.png" alt="" srcset="">
            </div>
            <div class="sidebar-text">
                <div class="text-sm text-[#1e1e1e] font-medium">Library Staff</div>
                <div class="text-xs text-[#9e9e9e]">Administrator</div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Button -->
    <button id="mobileMenuBtn" class="lg:hidden fixed top-4 left-4 z-50 bg-white border border-[#e5e5e5] text-[#1e1e1e] p-2 rounded-md shadow-md">
        <i class="fa-solid fa-bars"></i>
    </button>

    <!-- Overlay for mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-30 lg:hidden"></div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const mobileBtn = document.getElementById('mobileMenuBtn');
        const overlay = document.getElementById('sidebarOverlay');

        // Function to close sidebar
        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }

        // Function to open sidebar
        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        }

        // Mobile toggle
        if (mobileBtn) {
            mobileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (sidebar.classList.contains('-translate-x-full')) {
                    openSidebar();
                } else {
                    closeSidebar();
                }
            });
        }

        // Close sidebar when clicking overlay
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar on window resize if in desktop mode
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) { // lg breakpoint
                sidebar.classList.remove('-translate-x-full');
                if (overlay) overlay.classList.add('hidden');
            }
        });

        // Prevent closing when clicking inside sidebar
        sidebar.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Highlight current page in navigation
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');

            navLinks.forEach(link => {
                const href = link.getAttribute('href').split('?')[0].split('/').pop();
                if (href === currentPath) {
                    link.classList.add('active');
                } else if (currentPath === 'parcels.php' && href === 'parcels.php') {
                    // Special handling for parcels.php with tabs
                    const urlParams = new URLSearchParams(window.location.search);
                    const tab = urlParams.get('tab');
                    const linkTab = link.getAttribute('href').split('tab=')[1];

                    if ((!tab && !linkTab) || (tab === linkTab)) {
                        link.classList.add('active');
                    }
                }
            });
        });
    </script>
</body>

</html>