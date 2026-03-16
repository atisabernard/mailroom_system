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
    'document_types' => 0,
    'newspaper_categories' => 0,
    'today_documents' => 0,
    'today_parcels' => 0,
    'today_newspapers' => 0,
    'week_documents' => 0,
    'week_parcels' => 0,
    'week_newspapers' => 0,
    'month_documents' => 0,
    'month_parcels' => 0,
    'month_newspapers' => 0,
    'total_distributions' => 0,
    'total_pickups' => 0,
    'total_copies_received' => 0,
    'total_copies_distributed' => 0
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

    // Document types count
    $result = $conn->query("SELECT COUNT(*) as total FROM document_types");
    if ($result) {
        $stats['document_types'] = $result->fetch_assoc()['total'];
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

    // Total pickups
    $result = $conn->query("SELECT COUNT(*) as total FROM parcels_pickup");
    if ($result) {
        $stats['total_pickups'] = $result->fetch_assoc()['total'];
    }

    // Newspapers count
    $result = $conn->query("SELECT COUNT(*) as total FROM newspapers");
    if ($result) {
        $stats['newspapers'] = $result->fetch_assoc()['total'];
    }

    // Newspaper categories count
    $result = $conn->query("SELECT COUNT(*) as total FROM newspaper_categories");
    if ($result) {
        $stats['newspaper_categories'] = $result->fetch_assoc()['total'];
    }

    // Document distributions
    $result = $conn->query("SELECT COUNT(*) as total FROM document_distribution");
    if ($result) {
        $stats['total_distributions'] = $result->fetch_assoc()['total'];
    }

    // Total copies received
    $result = $conn->query("SELECT SUM(copies_received) as total FROM documents");
    if ($result) {
        $stats['total_copies_received'] = $result->fetch_assoc()['total'] ?? 0;
    }

    // Total copies distributed
    $result = $conn->query("SELECT SUM(number_distributed) as total FROM document_distribution");
    if ($result) {
        $stats['total_copies_distributed'] = $result->fetch_assoc()['total'] ?? 0;
    }

    // Today's dates
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $month_start = date('Y-m-01');

    // Today's documents
    $result = $conn->query("SELECT COUNT(*) as total FROM documents WHERE date_received = '$today'");
    if ($result) {
        $stats['today_documents'] = $result->fetch_assoc()['total'];
    }

    // This week's documents
    $result = $conn->query("SELECT COUNT(*) as total FROM documents WHERE date_received >= '$week_start'");
    if ($result) {
        $stats['week_documents'] = $result->fetch_assoc()['total'];
    }

    // This month's documents
    $result = $conn->query("SELECT COUNT(*) as total FROM documents WHERE date_received >= '$month_start'");
    if ($result) {
        $stats['month_documents'] = $result->fetch_assoc()['total'];
    }

    // Today's parcels
    $result = $conn->query("SELECT COUNT(*) as total FROM parcels_received WHERE date_received = '$today'");
    if ($result) {
        $stats['today_parcels'] = $result->fetch_assoc()['total'];
    }

    // This week's parcels
    $result = $conn->query("SELECT COUNT(*) as total FROM parcels_received WHERE date_received >= '$week_start'");
    if ($result) {
        $stats['week_parcels'] = $result->fetch_assoc()['total'];
    }

    // This month's parcels
    $result = $conn->query("SELECT COUNT(*) as total FROM parcels_received WHERE date_received >= '$month_start'");
    if ($result) {
        $stats['month_parcels'] = $result->fetch_assoc()['total'];
    }

    // Today's newspapers
    $result = $conn->query("SELECT COUNT(*) as total FROM newspapers WHERE date_received = '$today'");
    if ($result) {
        $stats['today_newspapers'] = $result->fetch_assoc()['total'];
    }

    // This week's newspapers
    $result = $conn->query("SELECT COUNT(*) as total FROM newspapers WHERE date_received >= '$week_start'");
    if ($result) {
        $stats['week_newspapers'] = $result->fetch_assoc()['total'];
    }

    // This month's newspapers
    $result = $conn->query("SELECT COUNT(*) as total FROM newspapers WHERE date_received >= '$month_start'");
    if ($result) {
        $stats['month_newspapers'] = $result->fetch_assoc()['total'];
    }

    // Recent activities - combine all recent items
    $recent_activities = [];

    // Get recent documents
    $doc_result = $conn->query("
        SELECT 'document' as type, document_name as title, date_received as date,
               CONCAT('Received: ', copies_received, ' copies') as details
        FROM documents 
        ORDER BY date_received DESC 
        LIMIT 5
    ");
    if ($doc_result) {
        while ($row = $doc_result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }

    // Get recent parcels
    $parcel_result = $conn->query("
        SELECT 'parcel' as type, 
               CONCAT(pr.tracking_id, ' - ', LEFT(pr.description, 30)) as title, 
               pr.date_received as date,
               CASE WHEN pp.id IS NULL THEN 'Pending' ELSE 'Picked up' END as status,
               CONCAT('From: ', pr.sender) as details
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
               date_received as date,
               CONCAT('Available: ', available_copies, ' copies') as details
        FROM newspapers 
        ORDER BY date_received DESC 
        LIMIT 5
    ");
    if ($news_result) {
        while ($row = $news_result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }

    // Get recent distributions
    $dist_result = $conn->query("
        SELECT 'distribution' as type, 
               d.document_name as title, 
               dd.date_distributed as date,
               CONCAT('To: ', dd.department, ' - ', dd.recipient_name, ' (', dd.number_distributed, ' copies)') as details
        FROM document_distribution dd
        JOIN documents d ON dd.document_id = d.id
        ORDER BY dd.date_distributed DESC 
        LIMIT 5
    ");
    if ($dist_result) {
        while ($row = $dist_result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }

    // Get recent pickups
    $pickup_result = $conn->query("
        SELECT 'pickup' as type, 
               pr.tracking_id as title, 
               pp.date_picked as date,
               CONCAT('Picked by: ', pp.picked_by) as details
        FROM parcels_pickup pp
        JOIN parcels_received pr ON pp.parcel_id = pr.id
        ORDER BY pp.date_picked DESC 
        LIMIT 5
    ");
    if ($pickup_result) {
        while ($row = $pickup_result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }

    // Sort by date (most recent first)
    usort($recent_activities, function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Get top documents by distribution
    $top_documents = $conn->query("
        SELECT d.document_name, dt.type_name as document_type, 
               COUNT(dd.id) as distribution_count,
               SUM(dd.number_distributed) as total_distributed
        FROM documents d
        LEFT JOIN document_distribution dd ON d.id = dd.document_id
        LEFT JOIN document_types dt ON d.type_id = dt.id
        GROUP BY d.id
        ORDER BY total_distributed DESC
        LIMIT 5
    ");

    // Get recent pickups detailed
    $recent_pickups = $conn->query("
        SELECT pp.*, pr.tracking_id, pr.description, pr.sender
        FROM parcels_pickup pp
        JOIN parcels_received pr ON pp.parcel_id = pr.id
        ORDER BY pp.date_picked DESC 
        LIMIT 5
    ");

    // Get document type breakdown
    $doc_types_breakdown = $conn->query("
        SELECT dt.type_name, COUNT(d.id) as document_count
        FROM document_types dt
        LEFT JOIN documents d ON dt.id = d.type_id
        GROUP BY dt.id
        ORDER BY document_count DESC
    ");

    // Get newspaper category breakdown
    $news_categories_breakdown = $conn->query("
        SELECT nc.category_name, COUNT(n.id) as newspaper_count
        FROM newspaper_categories nc
        LEFT JOIN newspapers n ON nc.id = n.category_id
        GROUP BY nc.id
        ORDER BY newspaper_count DESC
    ");
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
            border-radius: 0.5rem;
        }

        .stat-card:hover {
            border-color: #9e9e9e;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .activity-item {
            border-bottom: 1px solid #eaeaea;
            transition: background-color 0.2s;
            padding: 0.75rem;
        }

        .activity-item:hover {
            background-color: #f9f9f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            border-radius: 9999px;
            font-weight: 500;
            background-color: #f0f0f0;
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

        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e1e1e;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }

        .value-large {
            font-size: 2rem;
            font-weight: 500;
            color: #1e1e1e;
            line-height: 1.2;
        }

        .value-small {
            font-size: 1.25rem;
            font-weight: 500;
            color: #1e1e1e;
        }

        .metric-label {
            font-size: 0.7rem;
            color: #6e6e6e;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .icon-circle {
            width: 2.5rem;
            height: 2.5rem;
            background-color: #f0f0f0;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a4a4a;
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
                        <h1 class="text-2xl font-medium text-[#1e1e1e]">Mail Room Dashboard</h1>
                        <p class="text-sm text-[#6e6e6e] mt-1"><?php echo date('l, F j, Y'); ?></p>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="flex gap-2">
                        <a href="./parcels.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] flex items-center">
                            <i class="fa-solid fa-gift mr-1 text-[#6e6e6e]"></i> Parcels
                        </a>
                        <a href="./list.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] flex items-center">
                            <i class="fa-solid fa-file mr-1 text-[#6e6e6e]"></i> Documents
                        </a>
                        <a href="./newspaper_categories.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] flex items-center">
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

                <!-- System Overview Cards - Monochromatic -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="stat-card">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="metric-label">Total Documents</p>
                                <p class="value-large"><?php echo number_format($stats['documents']); ?></p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="text-xs text-[#6e6e6e]">
                                        <i class="fa-solid fa-tag mr-1"></i><?php echo $stats['document_types']; ?> types
                                    </span>
                                    <span class="text-xs text-[#6e6e6e]">
                                        <i class="fa-regular fa-copy mr-1"></i><?php echo number_format($stats['total_copies_received']); ?> copies
                                    </span>
                                </div>
                            </div>
                            <div class="icon-circle">
                                <i class="fa-regular fa-file-lines"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="metric-label">Total Parcels</p>
                                <p class="value-large"><?php echo number_format($stats['parcels_received']); ?></p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="text-xs text-[#6e6e6e]">
                                        <i class="fa-regular fa-clock mr-1"></i><?php echo $stats['pending_parcels']; ?> pending
                                    </span>
                                    <span class="text-xs text-[#6e6e6e]">
                                        <i class="fa-solid fa-truck mr-1"></i><?php echo $stats['total_pickups']; ?> picked up
                                    </span>
                                </div>
                            </div>
                            <div class="icon-circle">
                                <i class="fa-solid fa-box"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="metric-label">Total Newspapers</p>
                                <p class="value-large"><?php echo number_format($stats['newspapers']); ?></p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="text-xs text-[#6e6e6e]">
                                        <i class="fa-solid fa-tag mr-1"></i><?php echo $stats['newspaper_categories']; ?> categories
                                    </span>
                                </div>
                            </div>
                            <div class="icon-circle">
                                <i class="fa-regular fa-newspaper"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="metric-label">Distributions</p>
                                <p class="value-large"><?php echo number_format($stats['total_distributions']); ?></p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="text-xs text-[#6e6e6e]">
                                        <i class="fa-regular fa-copy mr-1"></i><?php echo number_format($stats['total_copies_distributed']); ?> copies
                                    </span>
                                </div>
                            </div>
                            <div class="icon-circle">
                                <i class="fa-solid fa-share-from-square"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Activity Summary - Monochromatic -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <div class="bg-white border border-[#e5e5e5] rounded-lg p-4">
                        <h3 class="text-sm font-medium text-[#1e1e1e] mb-3 flex items-center">
                            <i class="fa-regular fa-calendar mr-2 text-[#6e6e6e]"></i> Today's Documents
                        </h3>
                        <p class="value-small"><?php echo $stats['today_documents']; ?></p>
                        <div class="flex justify-between text-xs text-[#6e6e6e] mt-2">
                            <span>Week: <?php echo $stats['week_documents']; ?></span>
                            <span>Month: <?php echo $stats['month_documents']; ?></span>
                        </div>
                    </div>

                    <div class="bg-white border border-[#e5e5e5] rounded-lg p-4">
                        <h3 class="text-sm font-medium text-[#1e1e1e] mb-3 flex items-center">
                            <i class="fa-regular fa-calendar mr-2 text-[#6e6e6e]"></i> Today's Parcels
                        </h3>
                        <p class="value-small"><?php echo $stats['today_parcels']; ?></p>
                        <div class="flex justify-between text-xs text-[#6e6e6e] mt-2">
                            <span>Week: <?php echo $stats['week_parcels']; ?></span>
                            <span>Month: <?php echo $stats['month_parcels']; ?></span>
                        </div>
                    </div>

                    <div class="bg-white border border-[#e5e5e5] rounded-lg p-4">
                        <h3 class="text-sm font-medium text-[#1e1e1e] mb-3 flex items-center">
                            <i class="fa-regular fa-calendar mr-2 text-[#6e6e6e]"></i> Today's Newspapers
                        </h3>
                        <p class="value-small"><?php echo $stats['today_newspapers']; ?></p>
                        <div class="flex justify-between text-xs text-[#6e6e6e] mt-2">
                            <span>Week: <?php echo $stats['week_newspapers']; ?></span>
                            <span>Month: <?php echo $stats['month_newspapers']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column - Recent Activities (spans 2 columns) -->
                    <div class="lg:col-span-2">
                        <div class="bg-white border border-[#e5e5e5] rounded-lg p-5 mb-6">
                            <h2 class="section-title flex items-center">
                                <i class="fa-regular fa-clock mr-2 text-[#6e6e6e]"></i>
                                Recent Activities
                            </h2>
                            <div class="divide-y divide-[#eaeaea]">
                                <?php if (!empty($recent_activities)): ?>
                                    <?php foreach (array_slice($recent_activities, 0, 8) as $activity): ?>
                                        <div class="activity-item flex items-start gap-3">
                                            <div class="icon-circle w-8 h-8 text-sm">
                                                <?php if ($activity['type'] == 'document'): ?>
                                                    <i class="fa-regular fa-file-lines"></i>
                                                <?php elseif ($activity['type'] == 'parcel'): ?>
                                                    <i class="fa-solid fa-box"></i>
                                                <?php elseif ($activity['type'] == 'newspaper'): ?>
                                                    <i class="fa-regular fa-newspaper"></i>
                                                <?php elseif ($activity['type'] == 'distribution'): ?>
                                                    <i class="fa-solid fa-share-from-square"></i>
                                                <?php elseif ($activity['type'] == 'pickup'): ?>
                                                    <i class="fa-solid fa-truck"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-medium uppercase tracking-wide text-[#6e6e6e]">
                                                        <?php echo ucfirst($activity['type']); ?>
                                                    </span>
                                                    <?php if (isset($activity['status'])): ?>
                                                        <span class="badge <?php echo $activity['status'] == 'Picked up' ? 'badge-success' : 'badge-warning'; ?>">
                                                            <?php echo $activity['status']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm font-medium text-[#1e1e1e] mt-1">
                                                    <?php echo htmlspecialchars(substr($activity['title'], 0, 60)); ?>
                                                </p>
                                                <p class="text-xs text-[#6e6e6e] mt-1">
                                                    <?php echo htmlspecialchars($activity['details']); ?>
                                                </p>
                                                <p class="text-xs text-[#9e9e9e] mt-1">
                                                    <i class="fa-regular fa-calendar mr-1"></i>
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
                        <div class="bg-white border border-[#e5e5e5] rounded-lg p-5">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="section-title flex items-center">
                                    <i class="fa-solid fa-truck mr-2 text-[#6e6e6e]"></i>
                                    Recent Parcel Pickups
                                </h2>
                                <a href="./parcels.php" class="text-xs text-[#6e6e6e] hover:text-[#1e1e1e] flex items-center">
                                    View all <i class="fa-solid fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                            <?php if ($recent_pickups && $recent_pickups->num_rows > 0): ?>
                                <div class="space-y-3">
                                    <?php while ($pickup = $recent_pickups->fetch_assoc()): ?>
                                        <div class="flex items-start gap-3 p-2 hover:bg-[#f5f5f4] rounded-lg transition-colors">
                                            <div class="icon-circle w-8 h-8 text-sm">
                                                <i class="fa-solid fa-truck"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-mono font-medium text-[#4a4a4a]">
                                                        <?php echo htmlspecialchars($pickup['tracking_id']); ?>
                                                    </span>
                                                    <span class="badge badge-success">Picked up</span>
                                                </div>
                                                <p class="text-sm text-[#1e1e1e] mt-1">
                                                    <?php echo htmlspecialchars(substr($pickup['description'], 0, 50)); ?>
                                                </p>
                                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-[#6e6e6e]">
                                                    <span><i class="fa-regular fa-user mr-1"></i> <?php echo htmlspecialchars($pickup['picked_by']); ?></span>
                                                    <span><i class="fa-solid fa-phone mr-1"></i> <?php echo htmlspecialchars($pickup['phone_number']); ?></span>
                                                    <span><i class="fa-regular fa-calendar mr-1"></i> <?php echo date('M j, Y', strtotime($pickup['date_picked'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-[#6e6e6e] text-center py-4">No recent pickups</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column - Stats and Breakdowns -->
                    <div class="space-y-6">
                        <!-- Quick Stats -->
                        <div class="bg-white border border-[#e5e5e5] rounded-lg p-5">
                            <h3 class="section-title flex items-center">
                                <i class="fa-solid fa-chart-simple mr-2 text-[#6e6e6e]"></i>
                                Quick Stats
                            </h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-[#6e6e6e]">Pending Parcels</span>
                                    <span class="text-lg font-medium text-[#1e1e1e]"><?php echo $stats['pending_parcels']; ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-[#6e6e6e]">Total Pickups</span>
                                    <span class="text-lg font-medium text-[#1e1e1e]"><?php echo $stats['total_pickups']; ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-[#6e6e6e]">Document Types</span>
                                    <span class="text-lg font-medium text-[#1e1e1e]"><?php echo $stats['document_types']; ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-[#6e6e6e]">Newspaper Categories</span>
                                    <span class="text-lg font-medium text-[#1e1e1e]"><?php echo $stats['newspaper_categories']; ?></span>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-[#e5e5e5]">
                                    <span class="text-sm text-[#6e6e6e]">Total Records</span>
                                    <span class="text-lg font-medium text-[#1e1e1e]">
                                        <?php echo number_format($stats['documents'] + $stats['parcels_received'] + $stats['newspapers']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Top Distributed Documents -->
                        <div class="bg-white border border-[#e5e5e5] rounded-lg p-5">
                            <h3 class="section-title flex items-center">
                                <i class="fa-solid fa-ranking-star mr-2 text-[#6e6e6e]"></i>
                                Top Distributed Documents
                            </h3>
                            <?php if ($top_documents && $top_documents->num_rows > 0): ?>
                                <div class="space-y-3">
                                    <?php while ($doc = $top_documents->fetch_assoc()): ?>
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-[#1e1e1e]">
                                                    <?php echo htmlspecialchars(substr($doc['document_name'], 0, 25)); ?>
                                                </p>
                                                <p class="text-xs text-[#6e6e6e]">
                                                    <?php echo htmlspecialchars($doc['document_type'] ?? 'Uncategorized'); ?>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-sm font-medium text-[#1e1e1e]">
                                                    <?php echo $doc['total_distributed'] ?? 0; ?>
                                                </span>
                                                <span class="text-xs text-[#6e6e6e] block">copies</span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-[#6e6e6e] text-center py-2">No distribution data</p>
                            <?php endif; ?>
                        </div>

                        <!-- Document Types Breakdown -->
                        <div class="bg-white border border-[#e5e5e5] rounded-lg p-5">
                            <h3 class="section-title flex items-center">
                                <i class="fa-solid fa-tags mr-2 text-[#6e6e6e]"></i>
                                Document Types
                            </h3>
                            <?php if ($doc_types_breakdown && $doc_types_breakdown->num_rows > 0): ?>
                                <div class="space-y-2">
                                    <?php while ($type = $doc_types_breakdown->fetch_assoc()): ?>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-[#1e1e1e]">
                                                <?php echo htmlspecialchars($type['type_name']); ?>
                                            </span>
                                            <span class="text-sm font-medium text-[#6e6e6e]">
                                                <?php echo $type['document_count']; ?>
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-[#6e6e6e] text-center py-2">No document types</p>
                            <?php endif; ?>
                        </div>

                        <!-- Newspaper Categories Breakdown -->
                        <div class="bg-white border border-[#e5e5e5] rounded-lg p-5">
                            <h3 class="section-title flex items-center">
                                <i class="fa-solid fa-tags mr-2 text-[#6e6e6e]"></i>
                                Newspaper Categories
                            </h3>
                            <?php if ($news_categories_breakdown && $news_categories_breakdown->num_rows > 0): ?>
                                <div class="space-y-2">
                                    <?php while ($cat = $news_categories_breakdown->fetch_assoc()): ?>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-[#1e1e1e]">
                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                            </span>
                                            <span class="text-sm font-medium text-[#6e6e6e]">
                                                <?php echo $cat['newspaper_count']; ?>
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-[#6e6e6e] text-center py-2">No newspaper categories</p>
                            <?php endif; ?>
                        </div>

                        <!-- System Info -->
                        <!--<div class="bg-white border border-[#e5e5e5] rounded-lg p-5">
                            <h3 class="section-title flex items-center">
                                <i class="fa-solid fa-circle-info mr-2 text-[#6e6e6e]"></i>
                                System Information
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-[#6e6e6e]">Server Time</span>
                                    <span class="text-[#1e1e1e]"><?php echo date('H:i:s'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[#6e6e6e]">Total Tables</span>
                                    <span class="text-[#1e1e1e]">
                                        <?/*php
                                        $tables = $conn->query("SHOW TABLES");
                                        echo $tables ? $tables->num_rows : 0;
                                        */ ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[#6e6e6e]">Database</span>
                                    <span class="text-[#1e1e1e]">mailroom_system</span>
                                </div>
                            </div>
                        </div>-->
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>