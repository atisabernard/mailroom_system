<?php
require_once 'config/db.php';
session_start();

// Get statistics
$stats = [];

// Documents count
$result = $conn->query("SELECT COUNT(*) as total FROM documents");
$stats['documents'] = $result->fetch_assoc()['total'];

// Parcels received
$result = $conn->query("SELECT COUNT(*) as total FROM parcels_received");
$stats['parcels_received'] = $result->fetch_assoc()['total'];

// Pending parcels (not picked up)
$result = $conn->query("
    SELECT COUNT(*) as total 
    FROM parcels_received pr 
    LEFT JOIN parcels_pickup pp ON pr.id = pp.parcel_id 
    WHERE pp.id IS NULL
");
$stats['pending_parcels'] = $result->fetch_assoc()['total'];

// Newspapers count
$result = $conn->query("SELECT COUNT(*) as total FROM newspapers");
$stats['newspapers'] = $result->fetch_assoc()['total'];

// Today's activities
$today = date('Y-m-d');
$result = $conn->query("
    SELECT COUNT(*) as total FROM documents WHERE date_received = '$today'
");
$stats['today_documents'] = $result->fetch_assoc()['total'];

$result = $conn->query("
    SELECT COUNT(*) as total FROM parcels_received WHERE date_received = '$today'
");
$stats['today_parcels'] = $result->fetch_assoc()['total'];

// Recent activities
$recent_activities = $conn->query("
    (SELECT 'document' as type, document_name as title, date_received as date FROM documents)
    UNION ALL
    (SELECT 'parcel' as type, description as title, date_received as date FROM parcels_received)
    UNION ALL
    (SELECT 'newspaper' as type, newspaper_name as title, date_received as date FROM newspapers)
    ORDER BY date DESC LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mailroom Dashboard</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f5f5f4;
        }

        /* Simple card style */
        .stat-card {
            background: white;
            border: 1px solid #e5e5e5;
            padding: 1.25rem;
        }

        .activity-item {
            border-bottom: 1px solid #eaeaea;
        }

        .activity-item:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <div class="flex">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content - ml-60 to match sidebar width -->
        <main class="flex-1 ml-60 min-h-screen">
            <!-- Simple header, no gradient -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-medium text-[#1e1e1e]">Dashboard</h1>
                        <p class="text-sm text-[#6e6e6e] mt-1"><?php echo date('l, F j, Y'); ?></p>
                    </div>

                    <!-- Simple button group, no gradient -->
                    <div class="flex gap-2">
                        <a href="./receive.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-box mr-1 text-[#6e6e6e]"></i> Receive
                        </a>
                        <a href="./distribution/add.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-file mr-1 text-[#6e6e6e]"></i> Add Document
                        </a>
                        <a href="./newspapers/add.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-newspaper mr-1 text-[#6e6e6e]"></i> Add Newspaper
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Statistics Cards - simple grid, no gradients, no transforms -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <!-- Documents Card -->
                    <div class="stat-card rounded-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Documents</p>
                                <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $stats['documents']; ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-2">+<?php echo $stats['today_documents']; ?> today</p>
                            </div>
                            <i class="fa-regular fa-file-lines text-xl text-[#9e9e9e]"></i>
                        </div>
                    </div>

                    <!-- Parcels Card -->
                    <div class="stat-card rounded-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Parcels</p>
                                <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $stats['parcels_received']; ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-2">+<?php echo $stats['today_parcels']; ?> today</p>
                            </div>
                            <i class="fa-regular fa-box text-xl text-[#9e9e9e]"></i>
                        </div>
                    </div>

                    <!-- Pending Parcels Card -->
                    <div class="stat-card rounded-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Pending Pickup</p>
                                <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $stats['pending_parcels']; ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-2">Awaiting collection</p>
                            </div>
                            <i class="fa-regular fa-clock text-xl text-[#9e9e9e]"></i>
                        </div>
                    </div>

                    <!-- Newspapers Card -->
                    <div class="stat-card rounded-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Newspapers</p>
                                <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $stats['newspapers']; ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-2">Total editions</p>
                            </div>
                            <i class="fa-regular fa-newspaper text-xl text-[#9e9e9e]"></i>
                        </div>
                    </div>
                </div>

                <!-- Search Section - simple container -->
                <div class="bg-white border border-[#e5e5e5] rounded-md p-5 mb-8">
                    <h2 class="text-sm font-medium text-[#1e1e1e] mb-3">Search</h2>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-sm text-[#9e9e9e]"></i>
                        <input type="text" id="searchInput" placeholder="Search documents, parcels, newspapers..."
                            class="w-full pl-9 pr-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                    </div>
                    <div id="searchResults" class="mt-3 hidden border-t border-[#e5e5e5] pt-3"></div>
                </div>

                <!-- Two column layout -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Recent Activities - left column 2/3 -->
                    <div class="lg:col-span-2 bg-white border border-[#e5e5e5] rounded-md p-5">
                        <h2 class="text-sm font-medium text-[#1e1e1e] mb-4">Recent Activities</h2>
                        <div>
                            <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                <div class="activity-item py-3 flex items-start gap-3">
                                    <div class="w-6 text-center">
                                        <?php if ($activity['type'] == 'document'): ?>
                                            <i class="fa-regular fa-file-lines text-[#6e6e6e]"></i>
                                        <?php elseif ($activity['type'] == 'parcel'): ?>
                                            <i class="fa-regular fa-box text-[#6e6e6e]"></i>
                                        <?php else: ?>
                                            <i class="fa-regular fa-newspaper text-[#6e6e6e]"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-[#1e1e1e]">
                                            <?php echo htmlspecialchars(substr($activity['title'], 0, 60)); ?>
                                            <?php if (strlen($activity['title']) > 60) echo '...'; ?>
                                        </p>
                                        <p class="text-xs text-[#9e9e9e] mt-1">
                                            <?php echo date('M j, Y', strtotime($activity['date'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Right column 1/3 -->
                    <div class="space-y-6">
                        <!-- Today's summary - simple, no gradient -->
                        <div class="bg-white border border-[#e5e5e5] rounded-md p-5">
                            <h3 class="text-sm font-medium text-[#1e1e1e] mb-4">Today</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-[#6e6e6e]">Documents</span>
                                    <span class="font-medium text-[#1e1e1e]"><?php echo $stats['today_documents']; ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-[#6e6e6e]">Parcels</span>
                                    <span class="font-medium text-[#1e1e1e]"><?php echo $stats['today_parcels']; ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-[#6e6e6e]">Pending</span>
                                    <span class="font-medium text-[#1e1e1e]"><?php echo $stats['pending_parcels']; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick actions - simple list -->
                        <div class="bg-white border border-[#e5e5e5] rounded-md p-5">
                            <h3 class="text-sm font-medium text-[#1e1e1e] mb-3">Quick Actions</h3>
                            <div class="space-y-1">
                                <a href="/mailroom-system/distribution/print.php" class="block px-3 py-2 text-sm text-[#1e1e1e] hover:bg-[#f5f5f4] rounded-md">
                                    <i class="fa-regular fa-print text-[#6e6e6e] mr-2"></i>
                                    Print Distribution Sheet
                                </a>
                                <a href="/mailroom-system/parcels/pickup.php" class="block px-3 py-2 text-sm text-[#1e1e1e] hover:bg-[#f5f5f4] rounded-md">
                                    <i class="fa-regular fa-truck text-[#6e6e6e] mr-2"></i>
                                    Process Pickups
                                </a>
                                <a href="/mailroom-system/distribution/add.php" class="block px-3 py-2 text-sm text-[#1e1e1e] hover:bg-[#f5f5f4] rounded-md">
                                    <i class="fa-regular fa-chart-line text-[#6e6e6e] mr-2"></i>
                                    New Distribution
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Simple search with no fancy UI
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value;
            if (query.length > 2) {
                fetch(`/mailroom-system/search.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        const resultsDiv = document.getElementById('searchResults');
                        resultsDiv.classList.remove('hidden');

                        let html = '';

                        if (data.documents.length > 0) {
                            html += '<div class="py-1"><div class="text-xs text-[#6e6e6e] px-2 py-1">Documents</div>';
                            data.documents.forEach(doc => {
                                html += `<div class="px-2 py-1.5 text-sm hover:bg-[#f5f5f4] rounded cursor-pointer">${doc.document_name}</div>`;
                            });
                            html += '</div>';
                        }

                        if (data.parcels.length > 0) {
                            html += '<div class="py-1"><div class="text-xs text-[#6e6e6e] px-2 py-1">Parcels</div>';
                            data.parcels.forEach(parcel => {
                                html += `<div class="px-2 py-1.5 text-sm hover:bg-[#f5f5f4] rounded">${parcel.description} (${parcel.tracking_id})</div>`;
                            });
                            html += '</div>';
                        }

                        if (data.newspapers.length > 0) {
                            html += '<div class="py-1"><div class="text-xs text-[#6e6e6e] px-2 py-1">Newspapers</div>';
                            data.newspapers.forEach(paper => {
                                html += `<div class="px-2 py-1.5 text-sm hover:bg-[#f5f5f4] rounded">${paper.newspaper_name} - ${paper.newspaper_number}</div>`;
                            });
                            html += '</div>';
                        }

                        if (html === '') {
                            html = '<div class="p-3 text-sm text-[#6e6e6e] text-center">No results</div>';
                        }

                        resultsDiv.innerHTML = html;
                    });
            } else {
                document.getElementById('searchResults').classList.add('hidden');
            }
        });

        // Simple print function
        function printDistributionSheet() {
            window.open('/mailroom-system/distribution/print.php', '_blank');
        }
    </script>
</body>

</html>