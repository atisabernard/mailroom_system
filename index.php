<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
session_start();

// Initialize stats array with defaults
$stats = [
    'documents' => 0,
    'parcels_received' => 0,
    'pending_parcels' => 0,
    'newspapers' => 0,
    'today_documents' => 0,
    'today_parcels' => 0,
    'today_newspapers' => 0
];

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get statistics with error handling
try {
    // Documents count
    $result = $conn->query("SELECT COUNT(*) as total FROM documents");
    if ($result) {
        $stats['documents'] = $result->fetch_assoc()['total'];
    }

    // Parcels received
    $result = $conn->query("SELECT COUNT(*) as total FROM parcels_received");
    if ($result) {
        $stats['parcels_received'] = $result->fetch_assoc()['total'];
    }

    // Pending parcels (not picked up)
    $result = $conn->query("
        SELECT COUNT(*) as total 
        FROM parcels_received pr 
        LEFT JOIN parcels_pickup pp ON pr.id = pp.parcel_id 
        WHERE pp.id IS NULL
    ");
    if ($result) {
        $stats['pending_parcels'] = $result->fetch_assoc()['total'];
    }

    // Newspapers count
    $result = $conn->query("SELECT COUNT(*) as total FROM newspapers");
    if ($result) {
        $stats['newspapers'] = $result->fetch_assoc()['total'];
    }

    // Today's activities
    $today = date('Y-m-d');

    // Today's documents
    $result = $conn->query("SELECT COUNT(*) as total FROM documents WHERE date_received = '$today'");
    if ($result) {
        $stats['today_documents'] = $result->fetch_assoc()['total'];
    }

    // Today's parcels
    $result = $conn->query("SELECT COUNT(*) as total FROM parcels_received WHERE date_received = '$today'");
    if ($result) {
        $stats['today_parcels'] = $result->fetch_assoc()['total'];
    }

    // Today's newspapers
    $result = $conn->query("SELECT COUNT(*) as total FROM newspapers WHERE date_received = '$today'");
    if ($result) {
        $stats['today_newspapers'] = $result->fetch_assoc()['total'];
    }

    // Recent activities
    $recent_activities = [];

    // Get recent documents
    $doc_result = $conn->query("
        SELECT 'document' as type, document_name as title, date_received as date 
        FROM documents 
        ORDER BY date_received DESC 
        LIMIT 5
    ");
    if ($doc_result) {
        while ($row = $doc_result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }

    // Get recent parcels with pickup status
    $parcel_result = $conn->query("
        SELECT 'parcel' as type, 
               CONCAT(pr.tracking_id, ': ', LEFT(pr.description, 30)) as title, 
               pr.date_received as date,
               CASE WHEN pp.id IS NULL THEN 'pending' ELSE 'picked' END as status
        FROM parcels_received pr 
        LEFT JOIN parcels_pickup pp ON pr.id = pp.parcel_id 
        ORDER BY pr.date_received DESC 
        LIMIT 5
    ");
    if ($parcel_result) {
        while ($row = $parcel_result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }

    // Get recent newspapers
    $news_result = $conn->query("
        SELECT 'newspaper' as type, 
               CONCAT(newspaper_name, ' #', newspaper_number) as title, 
               date_received as date 
        FROM newspapers 
        ORDER BY date_received DESC 
        LIMIT 5
    ");
    if ($news_result) {
        while ($row = $news_result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }

    // Sort by date (most recent first)
    usort($recent_activities, function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Get recent pickups
    $recent_pickups = [];
    $pickup_result = $conn->query("
        SELECT pp.*, pr.tracking_id, pr.description 
        FROM parcels_pickup pp
        JOIN parcels_received pr ON pp.parcel_id = pr.id
        ORDER BY pp.date_picked DESC 
        LIMIT 5
    ");
    if ($pickup_result) {
        while ($row = $pickup_result->fetch_assoc()) {
            $recent_pickups[] = $row;
        }
    }
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
}
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

        .stat-card {
            background: white;
            border: 1px solid #e5e5e5;
            padding: 1.25rem;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: #9e9e9e;
        }

        .activity-item {
            border-bottom: 1px solid #eaeaea;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            border-radius: 3px;
            background-color: #f5f5f4;
            color: #4a4a4a;
        }

        .badge-success {
            background-color: #e8f0e8;
            color: #2c5e2c;
        }

        .badge-warning {
            background-color: #fef7e0;
            color: #9e6b0b;
        }
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <div class="flex">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 ml-60 min-h-screen">
            <!-- Header -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-medium text-[#1e1e1e]">Dashboard</h1>
                        <p class="text-sm text-[#6e6e6e] mt-1"><?php echo date('l, F j, Y'); ?></p>
                    </div>

                    <!-- Quick Action Buttons - Updated paths to match your files -->
                    <div class="flex gap-2">
                        <a href="receive.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-box mr-1 text-[#6e6e6e]"></i> Receive Parcel
                        </a>
                        <a href="add.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-file mr-1 text-[#6e6e6e]"></i> Add Document
                        </a>
                        <a href="newspaper_categories.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-newspaper mr-1 text-[#6e6e6e]"></i> Newspapers
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <?php if (isset($error)): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-md text-red-700">
                        <i class="fa-regular fa-circle-exclamation mr-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <!-- Documents Card -->
                    <div class="stat-card rounded-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Documents</p>
                                <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $stats['documents']; ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-2">
                                    <i class="fa-regular fa-calendar mr-1"></i> +<?php echo $stats['today_documents']; ?> today
                                </p>
                            </div>
                            <i class="fa-regular fa-file-lines text-2xl text-[#9e9e9e]"></i>
                        </div>
                    </div>

                    <!-- Parcels Card -->
                    <div class="stat-card rounded-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Parcels</p>
                                <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $stats['parcels_received']; ?></p>
                                <div class="flex items-center gap-3 mt-2">
                                    <p class="text-xs text-[#6e6e6e]">
                                        <i class="fa-regular fa-clock mr-1"></i> <?php echo $stats['pending_parcels']; ?> pending
                                    </p>
                                    <p class="text-xs text-[#6e6e6e]">
                                        <i class="fa-regular fa-calendar mr-1"></i> +<?php echo $stats['today_parcels']; ?> today
                                    </p>
                                </div>
                            </div>
                            <i class="fa-regular fa-box text-2xl text-[#9e9e9e]"></i>
                        </div>
                    </div>

                    <!-- Newspapers Card -->
                    <div class="stat-card rounded-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Newspapers</p>
                                <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $stats['newspapers']; ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-2">
                                    <i class="fa-regular fa-calendar mr-1"></i> +<?php echo $stats['today_newspapers']; ?> today
                                </p>
                            </div>
                            <i class="fa-regular fa-newspaper text-2xl text-[#9e9e9e]"></i>
                        </div>
                    </div>

                    <!-- Pickups Card -->
                    <div class="stat-card rounded-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Pickups</p>
                                <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo count($recent_pickups); ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-2">
                                    <i class="fa-regular fa-truck mr-1"></i> Recent pickups
                                </p>
                            </div>
                            <i class="fa-regular fa-truck text-2xl text-[#9e9e9e]"></i>
                        </div>
                    </div>
                </div>

                <!-- Two column layout -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Recent Activities - left column 2/3 -->
                    <div class="lg:col-span-2">
                        <div class="bg-white border border-[#e5e5e5] rounded-md p-5 mb-6">
                            <h2 class="text-sm font-medium text-[#1e1e1e] mb-4">Recent Activities</h2>
                            <div>
                                <?php if (!empty($recent_activities)): ?>
                                    <?php foreach (array_slice($recent_activities, 0, 10) as $activity): ?>
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
                                                <div class="flex items-center gap-2">
                                                    <p class="text-sm text-[#1e1e1e]">
                                                        <?php echo htmlspecialchars(substr($activity['title'], 0, 60)); ?>
                                                        <?php if (strlen($activity['title']) > 60) echo '...'; ?>
                                                    </p>
                                                    <?php if (isset($activity['status'])): ?>
                                                        <?php if ($activity['status'] == 'pending'): ?>
                                                            <span class="badge badge-warning text-xs">Pending</span>
                                                        <?php elseif ($activity['status'] == 'picked'): ?>
                                                            <span class="badge badge-success text-xs">Picked</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-xs text-[#9e9e9e] mt-1">
                                                    <?php echo date('M j, Y', strtotime($activity['date'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-sm text-[#6e6e6e] text-center py-4">No recent activities</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Pickups Section -->
                        <div class="bg-white border border-[#e5e5e5] rounded-md p-5">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-sm font-medium text-[#1e1e1e]">Recent Parcel Pickups</h2>
                                <a href="pickup.php" class="text-xs text-[#6e6e6e] hover:text-[#1e1e1e]">View all <i class="fa-regular fa-arrow-right ml-1"></i></a>
                            </div>
                            <?php if (!empty($recent_pickups)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($recent_pickups as $pickup): ?>
                                        <div class="flex items-start gap-3 p-2 hover:bg-[#f5f5f4] rounded">
                                            <div class="w-6 text-center">
                                                <i class="fa-regular fa-truck text-[#6e6e6e]"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-mono text-[#4a4a4a]"><?php echo htmlspecialchars($pickup['tracking_id']); ?></span>
                                                    <span class="badge badge-success text-xs">Picked up</span>
                                                </div>
                                                <p class="text-sm text-[#1e1e1e] mt-1"><?php echo htmlspecialchars(substr($pickup['description'], 0, 40)); ?>...</p>
                                                <p class="text-xs text-[#9e9e9e] mt-1">
                                                    <i class="fa-regular fa-user mr-1"></i> <?php echo htmlspecialchars($pickup['picked_by']); ?>
                                                    <i class="fa-regular fa-phone ml-2 mr-1"></i> <?php echo htmlspecialchars($pickup['phone_number']); ?>
                                                    <i class="fa-regular fa-calendar ml-2 mr-1"></i> <?php echo date('M j, Y', strtotime($pickup['date_picked'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-[#6e6e6e] text-center py-4">No recent pickups</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right column 1/3 -->
                    <div class="space-y-6">
                        <!-- Today's summary -->
                        <div class="bg-white border border-[#e5e5e5] rounded-md p-5">
                            <h3 class="text-sm font-medium text-[#1e1e1e] mb-4">Today's Summary</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between text-sm">
                                    <span class="text-[#6e6e6e]">Documents</span>
                                    <span class="font-medium text-[#1e1e1e]"><?php echo $stats['today_documents']; ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-[#6e6e6e]">Parcels</span>
                                    <span class="font-medium text-[#1e1e1e]"><?php echo $stats['today_parcels']; ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-[#6e6e6e]">Newspapers</span>
                                    <span class="font-medium text-[#1e1e1e]"><?php echo $stats['today_newspapers']; ?></span>
                                </div>
                                <div class="flex justify-between text-sm pt-2 border-t border-[#e5e5e5]">
                                    <span class="text-[#6e6e6e]">Pending Pickup</span>
                                    <span class="font-medium text-[#d97706]"><?php echo $stats['pending_parcels']; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick actions - Updated paths to match your files -->
                        <div class="bg-white border border-[#e5e5e5] rounded-md p-5">
                            <h3 class="text-sm font-medium text-[#1e1e1e] mb-3">Quick Actions</h3>
                            <div class="space-y-1">
                                <a href="print.php" class="flex items-center px-3 py-2 text-sm text-[#1e1e1e] hover:bg-[#f5f5f4] rounded-md">
                                    <i class="fa-regular fa-print text-[#6e6e6e] mr-2 w-5"></i>
                                    Print Reports
                                </a>
                                <a href="pickup.php" class="flex items-center px-3 py-2 text-sm text-[#1e1e1e] hover:bg-[#f5f5f4] rounded-md">
                                    <i class="fa-regular fa-truck text-[#6e6e6e] mr-2 w-5"></i>
                                    Process Pickups
                                </a>
                                <a href="add.php" class="flex items-center px-3 py-2 text-sm text-[#1e1e1e] hover:bg-[#f5f5f4] rounded-md">
                                    <i class="fa-regular fa-plus text-[#6e6e6e] mr-2 w-5"></i>
                                    Add Document
                                </a>
                                <a href="receive.php" class="flex items-center px-3 py-2 text-sm text-[#1e1e1e] hover:bg-[#f5f5f4] rounded-md">
                                    <i class="fa-regular fa-box text-[#6e6e6e] mr-2 w-5"></i>
                                    Receive Parcel
                                </a>
                                <a href="list.php" class="flex items-center px-3 py-2 text-sm text-[#1e1e1e] hover:bg-[#f5f5f4] rounded-md">
                                    <i class="fa-regular fa-list text-[#6e6e6e] mr-2 w-5"></i>
                                    View All Records
                                </a>
                            </div>
                        </div>

                        <!-- System Info -->
                        <div class="bg-white border border-[#e5e5e5] rounded-md p-5">
                            <h3 class="text-sm font-medium text-[#1e1e1e] mb-3">System Info</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-[#6e6e6e]">Total Records</span>
                                    <span class="text-[#1e1e1e]"><?php echo $stats['documents'] + $stats['parcels_received'] + $stats['newspapers']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[#6e6e6e]">Documents</span>
                                    <span class="text-[#1e1e1e]"><?php echo $stats['documents']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[#6e6e6e]">Parcels</span>
                                    <span class="text-[#1e1e1e]"><?php echo $stats['parcels_received']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[#6e6e6e]">Newspapers</span>
                                    <span class="text-[#1e1e1e]"><?php echo $stats['newspapers']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>