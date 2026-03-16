<?php
// available.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once './config/db.php';
session_start();

// Handle Distribution Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['distribute_submit'])) {
    $individual_name = trim($_POST['individual_name']);
    $department = trim($_POST['department']);
    $distributed_by = trim($_POST['distributed_by']);
    $selected_newspapers = $_POST['selected_newspapers'] ?? [];
    $date_distributed = date('Y-m-d');

    if (!empty($selected_newspapers)) {
        $conn->begin_transaction();

        try {
            $success_count = 0;
            $distributed_details = [];

            foreach ($selected_newspapers as $newspaper_id) {
                // Get newspaper details
                $result = $conn->query("SELECT n.*, nc.category_name FROM newspapers n 
                                        LEFT JOIN newspaper_categories nc ON n.category_id = nc.id 
                                        WHERE n.id = $newspaper_id AND n.available_copies > 0");
                $paper = $result->fetch_assoc();

                if ($paper) {
                    // Update newspaper available copies (distribute 1 copy)
                    $conn->query("UPDATE newspapers SET available_copies = available_copies - 1 WHERE id = $newspaper_id");

                    // Update status based on new available copies
                    $new_available = $paper['available_copies'] - 1;
                    if ($new_available == 0) {
                        $conn->query("UPDATE newspapers SET status = 'distributed' WHERE id = $newspaper_id");
                    } else {
                        $conn->query("UPDATE newspapers SET status = 'partial' WHERE id = $newspaper_id");
                    }

                    // Insert distribution record
                    $stmt = $conn->prepare("INSERT INTO distribution (newspaper_id, distributed_to, department, copies, date_distributed, distributed_by) VALUES (?, ?, ?, 1, ?, ?)");
                    $stmt->bind_param("issss", $newspaper_id, $individual_name, $department, $date_distributed, $distributed_by);
                    $stmt->execute();

                    $success_count++;
                    $distributed_details[] = $paper['newspaper_name'] . " (" . $paper['category_name'] . ") - Issue: " . $paper['newspaper_number'];
                }
            }

            $conn->commit();

            if ($success_count > 0) {
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => "$success_count newspaper(s) distributed to $individual_name"
                ];

                // Store last distribution info in session
                $_SESSION['last_distribution'] = [
                    'individual' => $individual_name,
                    'department' => $department,
                    'count' => $success_count,
                    'newspapers' => $distributed_details,
                    'date' => $date_distributed,
                    'distributed_by' => $distributed_by,
                    'timestamp' => time() // Add timestamp for expiration
                ];
            } else {
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => "No newspapers were available for distribution"
                ];
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => "Distribution failed: " . $e->getMessage()
            ];
        }
    } else {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => "No newspapers selected for distribution"
        ];
    }

    header('Location: available.php');
    exit();
}

// Handle Edit Distribution
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_distribution_submit'])) {
    $id = (int)$_POST['edit_id'];
    $individual_name = trim($_POST['edit_individual_name']);
    $department = trim($_POST['edit_department']);
    $copies = (int)$_POST['edit_copies'];

    $conn->begin_transaction();

    try {
        // Get current distribution details
        $current = $conn->query("SELECT newspaper_id, copies FROM distribution WHERE id = $id")->fetch_assoc();

        if ($current) {
            $newspaper_id = $current['newspaper_id'];
            $old_copies = $current['copies'];

            // Calculate the difference
            $copy_difference = $copies - $old_copies;

            if ($copy_difference != 0) {
                // Update newspaper available copies
                $conn->query("UPDATE newspapers SET available_copies = available_copies - $copy_difference WHERE id = $newspaper_id");

                // Update newspaper status
                $check = $conn->query("SELECT available_copies FROM newspapers WHERE id = $newspaper_id");
                $paper = $check->fetch_assoc();

                if ($paper['available_copies'] > 0) {
                    $conn->query("UPDATE newspapers SET status = 'available' WHERE id = $newspaper_id");
                } else {
                    $conn->query("UPDATE newspapers SET status = 'distributed' WHERE id = $newspaper_id");
                }
            }

            // Update distribution record
            $stmt = $conn->prepare("UPDATE distribution SET distributed_to = ?, department = ?, copies = ? WHERE id = ?");
            $stmt->bind_param("ssii", $individual_name, $department, $copies, $id);
            $stmt->execute();

            $conn->commit();
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => "Distribution updated successfully"
            ];
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => "Error updating distribution: " . $e->getMessage()
        ];
    }

    header('Location: available.php');
    exit();
}

// Handle Delete Distribution
if (isset($_GET['delete_distribution'])) {
    $id = (int)$_GET['delete_distribution'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Get distribution details first
        $dist_result = $conn->query("SELECT newspaper_id, copies FROM distribution WHERE id = $id");
        $distribution = $dist_result->fetch_assoc();

        if ($distribution) {
            // Delete the distribution record
            $stmt = $conn->prepare("DELETE FROM distribution WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            // Restore the copies back to newspaper
            $newspaper_id = $distribution['newspaper_id'];
            $copies = $distribution['copies'];

            $conn->query("UPDATE newspapers SET available_copies = available_copies + $copies WHERE id = $newspaper_id");

            // Update newspaper status
            $check = $conn->query("SELECT available_copies FROM newspapers WHERE id = $newspaper_id");
            $paper = $check->fetch_assoc();

            if ($paper['available_copies'] > 0) {
                $conn->query("UPDATE newspapers SET status = 'available' WHERE id = $newspaper_id");
            }

            $conn->commit();
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => "Distribution record deleted and copies restored successfully"
            ];
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => "Error deleting distribution: " . $e->getMessage()
        ];
    }

    // Preserve filters and pagination
    $query_params = $_GET;
    unset($query_params['delete_distribution']);
    $redirect_url = 'available.php' . (!empty($query_params) ? '?' . http_build_query($query_params) : '');
    header('Location: ' . $redirect_url);
    exit();
}

// Handle AJAX request for getting distribution data
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_distribution' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];

    $result = $conn->query("
        SELECT d.*, n.newspaper_name, n.newspaper_number, nc.category_name 
        FROM distribution d 
        JOIN newspapers n ON d.newspaper_id = n.id 
        LEFT JOIN newspaper_categories nc ON n.category_id = nc.id 
        WHERE d.id = $id
    ");

    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'distribution' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Distribution not found']);
    }
    exit();
}

// Handle AJAX request to dismiss last distribution notification
if (isset($_POST['ajax']) && $_POST['ajax'] == 'dismiss_last_distribution') {
    if (isset($_SESSION['last_distribution'])) {
        unset($_SESSION['last_distribution']);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Get all available newspapers with their categories
$available_newspapers = $conn->query("SELECT n.*, nc.category_name, nc.id as category_id 
                                     FROM newspapers n 
                                     LEFT JOIN newspaper_categories nc ON n.category_id = nc.id 
                                     WHERE n.available_copies > 0 
                                     ORDER BY nc.category_name, n.newspaper_name");

// Group newspapers by category for display
$newspapers_by_category = [];
$category_totals = [];

if ($available_newspapers && $available_newspapers->num_rows > 0) {
    while ($row = $available_newspapers->fetch_assoc()) {
        $cat_name = $row['category_name'] ?? 'Uncategorized';
        $cat_id = $row['category_id'] ?? 0;

        if (!isset($newspapers_by_category[$cat_name])) {
            $newspapers_by_category[$cat_name] = [
                'id' => $cat_id,
                'newspapers' => []
            ];
            $category_totals[$cat_name] = 0;
        }
        $newspapers_by_category[$cat_name]['newspapers'][] = $row;
        $category_totals[$cat_name] += $row['available_copies'];
    }
}

// Get statistics
$total_available = $conn->query("SELECT SUM(available_copies) as total FROM newspapers")->fetch_assoc()['total'] ?? 0;
$total_titles = $conn->query("SELECT COUNT(*) as count FROM newspapers WHERE available_copies > 0")->fetch_assoc()['count'] ?? 0;
$total_categories = count($newspapers_by_category);

// Get all categories for filter dropdown
$categories = $conn->query("SELECT * FROM newspaper_categories ORDER BY category_name");
$all_categories = [];
while ($cat = $categories->fetch_assoc()) {
    $all_categories[] = $cat;
}

// Pagination and filter settings for distribution history
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_distributed';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Build where clause for distribution history
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = "(n.newspaper_name LIKE '%$search%' OR n.newspaper_number LIKE '%$search%' OR d.distributed_to LIKE '%$search%' OR d.department LIKE '%$search%' OR d.distributed_by LIKE '%$search%')";
}

if ($filter_category > 0) {
    $where_clauses[] = "n.category_id = $filter_category";
}

if (!empty($date_from)) {
    $where_clauses[] = "d.date_distributed >= '$date_from'";
}

if (!empty($date_to)) {
    $where_clauses[] = "d.date_distributed <= '$date_to'";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM distribution d 
                JOIN newspapers n ON d.newspaper_id = n.id 
                LEFT JOIN newspaper_categories nc ON n.category_id = nc.id 
                $where_sql";
$count_result = $conn->query($count_query);
$total_distributions = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_distributions / $limit);

// Get distribution history with filters, sorting and pagination
$distribution_history = $conn->query("
    SELECT d.*, n.newspaper_name, n.newspaper_number, nc.category_name 
    FROM distribution d 
    JOIN newspapers n ON d.newspaper_id = n.id 
    LEFT JOIN newspaper_categories nc ON n.category_id = nc.id 
    $where_sql
    ORDER BY 
        CASE WHEN '$sort_by' = 'date_distributed' THEN d.date_distributed END $sort_order,
        CASE WHEN '$sort_by' = 'newspaper_name' THEN n.newspaper_name END $sort_order,
        CASE WHEN '$sort_by' = 'category_name' THEN nc.category_name END $sort_order,
        CASE WHEN '$sort_by' = 'distributed_to' THEN d.distributed_to END $sort_order,
        CASE WHEN '$sort_by' = 'department' THEN d.department END $sort_order,
        CASE WHEN '$sort_by' = 'distributed_by' THEN d.distributed_by END $sort_order,
        CASE WHEN '$sort_by' = 'copies' THEN d.copies END $sort_order
    LIMIT $offset, $limit
");

// Get toast message from session
$toast = null;
if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

// Include sidebar
include './sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Newspapers - Mailroom</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f5f5f4;
        }

        .category-card {
            border: 1px solid #e5e5e5;
            border-radius: 0.5rem;
            overflow: hidden;
            background-color: white;
            margin-bottom: 1rem;
        }

        .category-header {
            background-color: #fafafa;
            padding: 1rem;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .newspaper-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
            cursor: pointer;
        }

        .newspaper-item:hover {
            background-color: #fafafa;
        }

        .newspaper-item:last-child {
            border-bottom: none;
        }

        .newspaper-item.selected {
            background-color: #f0f7ff;
            border-left: 3px solid #1e1e1e;
        }

        .newspaper-checkbox {
            width: 1.2rem;
            height: 1.2rem;
            cursor: pointer;
            accent-color: #1e1e1e;
        }

        .issue-number {
            font-family: monospace;
            font-size: 0.75rem;
            color: #6b7280;
        }

        .available-badge {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .stat-card {
            background: white;
            border: 1px solid #e5e5e5;
            padding: 1.25rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: #9e9e9e;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .distribute-btn {
            background-color: #1e1e1e;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.875rem;
        }

        .distribute-btn:hover {
            background-color: #2d2d2d;
        }

        .distribute-btn:disabled {
            background-color: #9e9e9e;
            cursor: not-allowed;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #e5e5e5;
            font-weight: 500;
            color: #4a4a4a;
            font-size: 0.75rem;
            cursor: pointer;
            user-select: none;
        }

        th:hover {
            background-color: #f0f0f0;
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e5e5;
            font-size: 0.875rem;
            color: #1e1e1e;
        }

        .sort-icon {
            font-size: 0.7rem;
            margin-left: 0.25rem;
            opacity: 0.5;
        }

        th.active-sort .sort-icon {
            opacity: 1;
        }

        .action-btn {
            color: #9e9e9e;
            transition: color 0.2s;
            margin: 0 0.25rem;
            background: none;
            border: none;
            cursor: pointer;
        }

        .action-btn:hover {
            color: #1e1e1e;
        }

        .delete-btn:hover {
            color: #dc2626;
        }

        .pagination {
            display: flex;
            gap: 0.25rem;
            justify-content: flex-end;
            align-items: center;
        }

        .pagination-item {
            padding: 0.375rem 0.75rem;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            color: #1e1e1e;
            background-color: white;
        }

        .pagination-item:hover {
            background-color: #f5f5f4;
        }

        .pagination-item.active {
            background-color: #1e1e1e;
            color: white;
            border-color: #1e1e1e;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: #f0f0f0;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
        }

        .modal {
            transition: opacity 0.3s ease;
        }

        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        .newspaper-grid {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            padding: 0.5rem;
        }

        .category-group {
            margin-bottom: 0.5rem;
        }

        .category-header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .category-checkbox {
            width: 1.2rem;
            height: 1.2rem;
            cursor: pointer;
            accent-color: #1e1e1e;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            min-width: 300px;
            max-width: 400px;
            background-color: white;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .toast-success {
            border-left: 4px solid #10b981;
        }

        .toast-error {
            border-left: 4px solid #ef4444;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .toast.fade-out {
            animation: fadeOut 0.3s ease-in-out forwards;
        }

        /* Last distribution notification styles */
        .notification {
            animation: slideDown 0.3s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        .notification.fade-out {
            animation: fadeOutUp 0.3s ease-in-out forwards;
        }

        .notification-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background-color: rgba(16, 185, 129, 0.3);
            width: 100%;
            animation: progress 3s linear forwards;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeOutUp {
            from {
                transform: translateY(0);
                opacity: 1;
            }

            to {
                transform: translateY(-100%);
                opacity: 0;
            }
        }

        @keyframes progress {
            from {
                width: 100%;
            }

            to {
                width: 0%;
            }
        }

        .dismiss-btn {
            cursor: pointer;
            transition: all 0.2s;
        }

        .dismiss-btn:hover {
            color: #1e1e1e;
            transform: scale(1.1);
        }
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <div class="flex">
        <main class="flex-1 ml-60 min-h-screen">
            <!-- Header -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-medium text-[#1e1e1e]">Available Newspapers</h1>
                        <p class="text-sm text-[#6e6e6e] mt-1">View and select newspapers for distribution</p>
                    </div>
                    <div class="flex gap-2">
                        <button id="distributeBtn" class="distribute-btn" onclick="openDistributeModal()" disabled>
                            <i class="fa-solid fa-hand-holding-hand"></i>
                            <span>Distribute (<span id="selectedCount">0</span>)</span>
                        </button>
                        <a href="list.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-list mr-1"></i> Newspaper List
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Last Distribution Notification -->
                <?php if (isset($_SESSION['last_distribution'])): ?>
                    <div id="lastDistributionNotification" class="mb-6 notification">
                        <div class="bg-green-50 border border-green-200 rounded-md text-sm text-green-800 p-4 relative">
                            <div class="flex items-start gap-3">
                                <i class="fa-regular fa-circle-check text-green-600 mt-0.5"></i>
                                <div class="flex-1">
                                    <p class="font-medium">Last Distribution: <?php echo $_SESSION['last_distribution']['count']; ?> newspaper(s) to <strong><?php echo htmlspecialchars($_SESSION['last_distribution']['individual']); ?></strong></p>
                                    <p class="text-xs mt-1 text-green-700">
                                        <?php echo implode(', ', array_slice($_SESSION['last_distribution']['newspapers'], 0, 3)); ?>
                                        <?php if (count($_SESSION['last_distribution']['newspapers']) > 3): ?>
                                            and <?php echo count($_SESSION['last_distribution']['newspapers']) - 3; ?> more
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-xs mt-1 text-green-600">
                                        <?php echo date('M j, Y', strtotime($_SESSION['last_distribution']['date'])); ?> by <?php echo htmlspecialchars($_SESSION['last_distribution']['distributed_by']); ?>
                                    </p>
                                </div>
                                <button onclick="dismissLastDistribution()" class="dismiss-btn text-green-600 hover:text-green-800" title="Dismiss">
                                    <i class="fa-solid fa-xmark text-lg"></i>
                                </button>
                            </div>
                            <div class="notification-progress"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="stat-card">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Available Copies</p>
                        <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_available; ?></p>
                    </div>
                    <div class="stat-card">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Available Titles</p>
                        <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_titles; ?></p>
                    </div>
                    <div class="stat-card">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Categories</p>
                        <p class="text-3xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_categories; ?></p>
                    </div>
                </div>

                <!-- Available Newspapers Display (Selectable view) -->
                <div class="bg-white border border-[#e5e5e5] rounded-lg p-6 mb-8">
                    <h2 class="text-lg font-medium text-[#1e1e1e] mb-4">Select Newspapers to Distribute</h2>

                    <?php if (!empty($newspapers_by_category)): ?>
                        <div class="space-y-4">
                            <?php foreach ($newspapers_by_category as $category_name => $category_data): ?>
                                <?php $newspapers = $category_data['newspapers']; ?>
                                <div class="category-card">
                                    <div class="category-header">
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-medium text-[#1e1e1e]"><?php echo htmlspecialchars($category_name); ?></h3>
                                            <span class="available-badge">
                                                <?php echo $category_totals[$category_name]; ?> copies
                                            </span>
                                        </div>
                                        <span class="text-xs text-[#6e6e6e]"><?php echo count($newspapers); ?> titles</span>
                                    </div>
                                    <div class="divide-y divide-[#f0f0f0]">
                                        <?php foreach ($newspapers as $paper): ?>
                                            <div class="newspaper-item" onclick="toggleNewspaperSelection(<?php echo $paper['id']; ?>)">
                                                <div class="flex-1 flex items-center justify-between">
                                                    <div>
                                                        <span class="font-medium"><?php echo htmlspecialchars($paper['newspaper_name']); ?></span>
                                                        <span class="text-xs text-[#6e6e6e] ml-2 issue-number"><?php echo htmlspecialchars($paper['newspaper_number']); ?></span>
                                                    </div>
                                                    <div class="flex items-center gap-4">
                                                        <span class="text-xs <?php echo $paper['available_copies'] > 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                                            <?php echo $paper['available_copies']; ?> copies
                                                        </span>
                                                        <span class="text-xs text-[#6e6e6e]">
                                                            <?php echo date('M j, Y', strtotime($paper['date_received'])); ?>
                                                        </span>
                                                        <i class="fa-regular fa-circle-check text-lg" id="selection-icon-<?php echo $paper['id']; ?>" style="color: #9e9e9e;"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-[#6e6e6e]">
                            <i class="fa-regular fa-newspaper text-3xl mb-2"></i>
                            <p>No newspapers available for distribution</p>
                            <a href="list.php" class="inline-block mt-3 text-sm text-blue-600 hover:underline">Add Newspapers →</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Distribution History with Search and Filters -->
                <div class="bg-white border border-[#e5e5e5] rounded-lg overflow-hidden">
                    <div class="px-5 py-4 bg-[#fafafa] border-b border-[#e5e5e5]">
                        <div class="flex justify-between items-center">
                            <h3 class="text-sm font-medium text-[#1e1e1e]">Distribution History</h3>
                        </div>

                        <!-- Search and Filter Bar -->
                        <div class="mt-3">
                            <form method="GET" class="flex flex-wrap gap-2 items-end">
                                <div class="flex-1 min-w-[200px]">
                                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Search</label>
                                    <div class="relative">
                                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-sm text-[#9e9e9e]"></i>
                                        <input type="text" name="search"
                                            placeholder="Newspaper, recipient, department, staff..."
                                            value="<?php echo htmlspecialchars($search); ?>"
                                            class="w-full pl-9 pr-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Category</label>
                                    <select name="filter_category" class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white min-w-[150px]">
                                        <option value="0">All Categories</option>
                                        <?php foreach ($all_categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">From Date</label>
                                    <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                                        class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-md">
                                </div>

                                <div>
                                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">To Date</label>
                                    <input type="date" name="date_to" value="<?php echo $date_to; ?>"
                                        class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-md">
                                </div>

                                <div class="flex gap-2">
                                    <button type="submit" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4]">
                                        <i class="fa-solid fa-sliders mr-1"></i>Apply
                                    </button>
                                    <a href="available.php" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4]">
                                        <i class="fa-solid fa-rotate-right mr-1"></i>Reset
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Active Filters Display -->
                        <?php if (!empty($search) || $filter_category > 0 || !empty($date_from) || !empty($date_to)): ?>
                            <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-[#e5e5e5]">
                                <?php if (!empty($search)): ?>
                                    <span class="filter-badge">
                                        Search: "<?php echo htmlspecialchars($search); ?>"
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])); ?>" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                            <i class="fa-solid fa-xmark"></i>
                                        </a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filter_category > 0):
                                    $cat_name = '';
                                    foreach ($all_categories as $cat) {
                                        if ($cat['id'] == $filter_category) {
                                            $cat_name = $cat['category_name'];
                                            break;
                                        }
                                    }
                                ?>
                                    <span class="filter-badge">
                                        Category: <?php echo htmlspecialchars($cat_name); ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_category' => 0, 'page' => 1])); ?>" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                            <i class="fa-solid fa-xmark"></i>
                                        </a>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($date_from)): ?>
                                    <span class="filter-badge">
                                        From: <?php echo $date_from; ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['date_from' => '', 'page' => 1])); ?>" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                            <i class="fa-solid fa-xmark"></i>
                                        </a>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($date_to)): ?>
                                    <span class="filter-badge">
                                        To: <?php echo $date_to; ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['date_to' => '', 'page' => 1])); ?>" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                            <i class="fa-solid fa-xmark"></i>
                                        </a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($distribution_history && $distribution_history->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr class="bg-[#fafafa]">
                                        <th onclick="sortTable('date_distributed')" class="<?php echo $sort_by == 'date_distributed' ? 'active-sort' : ''; ?>">
                                            Date
                                            <?php if ($sort_by == 'date_distributed'): ?>
                                                <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th onclick="sortTable('newspaper_name')" class="<?php echo $sort_by == 'newspaper_name' ? 'active-sort' : ''; ?>">
                                            Newspaper
                                            <?php if ($sort_by == 'newspaper_name'): ?>
                                                <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th onclick="sortTable('category_name')" class="<?php echo $sort_by == 'category_name' ? 'active-sort' : ''; ?>">
                                            Category
                                            <?php if ($sort_by == 'category_name'): ?>
                                                <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th onclick="sortTable('distributed_to')" class="<?php echo $sort_by == 'distributed_to' ? 'active-sort' : ''; ?>">
                                            Distributed To
                                            <?php if ($sort_by == 'distributed_to'): ?>
                                                <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th onclick="sortTable('department')" class="<?php echo $sort_by == 'department' ? 'active-sort' : ''; ?>">
                                            Department
                                            <?php if ($sort_by == 'department'): ?>
                                                <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th onclick="sortTable('copies')" class="<?php echo $sort_by == 'copies' ? 'active-sort' : ''; ?>">
                                            Copies
                                            <?php if ($sort_by == 'copies'): ?>
                                                <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th onclick="sortTable('distributed_by')" class="<?php echo $sort_by == 'distributed_by' ? 'active-sort' : ''; ?>">
                                            By
                                            <?php if ($sort_by == 'distributed_by'): ?>
                                                <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                            <?php endif; ?>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($dist = $distribution_history->fetch_assoc()): ?>
                                        <tr class="hover:bg-[#fafafa]" id="distribution-row-<?php echo $dist['id']; ?>">
                                            <td class="text-sm"><?php echo date('M j, Y', strtotime($dist['date_distributed'])); ?></td>
                                            <td class="text-sm font-medium">
                                                <?php echo htmlspecialchars($dist['newspaper_name']); ?>
                                                <span class="text-xs font-mono issue-number ml-1"><?php echo htmlspecialchars($dist['newspaper_number']); ?></span>
                                            </td>
                                            <td class="text-sm"><?php echo htmlspecialchars($dist['category_name'] ?? 'N/A'); ?></td>
                                            <td class="text-sm"><?php echo htmlspecialchars($dist['distributed_to']); ?></td>
                                            <td class="text-sm"><?php echo htmlspecialchars($dist['department'] ?? '-'); ?></td>
                                            <td class="text-sm"><?php echo $dist['copies']; ?></td>
                                            <td class="text-sm"><?php echo htmlspecialchars($dist['distributed_by'] ?? 'N/A'); ?></td>
                                            <td class="text-sm">
                                                <div class="flex items-center gap-2">
                                                    <button onclick="viewDistribution(<?php echo htmlspecialchars(json_encode($dist)); ?>)"
                                                        class="action-btn" title="View Details">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </button>
                                                    <button onclick="editDistribution(<?php echo $dist['id']; ?>)"
                                                        class="action-btn" title="Edit">
                                                        <i class="fa-regular fa-pen-to-square"></i>
                                                    </button>
                                                    <button onclick="openDeleteModal(<?php echo $dist['id']; ?>, '<?php echo htmlspecialchars($dist['newspaper_name']); ?>', '<?php echo htmlspecialchars($dist['distributed_to']); ?>')"
                                                        class="action-btn delete-btn" title="Delete">
                                                        <i class="fa-regular fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="px-4 py-3 bg-[#fafafa] border-t border-[#e5e5e5]">
                                <div class="flex justify-between items-center">
                                    <div class="text-xs text-[#6e6e6e]">
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_distributions); ?> of <?php echo $total_distributions; ?> entries
                                    </div>
                                    <div class="pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-item">
                                                <i class="fa-solid fa-chevrons-left"></i>
                                            </a>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-item">
                                                <i class="fa-solid fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                                class="pagination-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-item">
                                                <i class="fa-solid fa-chevron-right"></i>
                                            </a>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="pagination-item">
                                                <i class="fa-solid fa-chevrons-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-[#6e6e6e]">
                            No distribution records found
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Distribution Modal -->
    <div id="distributeModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-4xl p-6 modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-[#1e1e1e]">Distribute Newspapers</h2>
                <button type="button" onclick="closeDistributeModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form method="POST" action="available.php" id="distributeForm">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Individual's Name *</label>
                        <input type="text" name="individual_name" id="modal_individual_name" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="e.g., John Doe">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Department/Office</label>
                        <input type="text" name="department" id="modal_department"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="e.g., HR Department">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Distributed By *</label>
                        <input type="text" name="distributed_by" id="modal_distributed_by" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="Your name">
                    </div>
                </div>

                <div class="mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-xs text-[#6e6e6e] uppercase tracking-wide font-medium">Selected Newspapers</label>
                        <div class="flex gap-3">
                            <button type="button" onclick="selectAllCategories()" class="text-xs text-blue-600 hover:underline">Select All</button>
                            <span class="text-xs text-[#6e6e6e]">|</span>
                            <button type="button" onclick="deselectAllCategories()" class="text-xs text-blue-600 hover:underline">Deselect All</button>
                        </div>
                    </div>

                    <div class="newspaper-grid">
                        <?php if (!empty($newspapers_by_category)): ?>
                            <?php foreach ($newspapers_by_category as $category_name => $category_data): ?>
                                <?php $newspapers = $category_data['newspapers']; ?>
                                <div class="category-group" data-category="<?php echo htmlspecialchars($category_name); ?>">
                                    <div class="flex items-center justify-between p-2 bg-[#fafafa] border-b border-[#e5e5e5]">
                                        <div class="category-header-left">
                                            <input type="checkbox"
                                                class="category-checkbox category-<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $category_name); ?>-main"
                                                onchange="toggleCategory('<?php echo htmlspecialchars($category_name); ?>', this.checked)"
                                                id="modal_cat_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $category_name); ?>">
                                            <label for="modal_cat_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $category_name); ?>" class="text-sm font-medium cursor-pointer">
                                                <?php echo htmlspecialchars($category_name); ?>
                                            </label>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="available-badge">
                                                <?php echo count($newspapers); ?> titles (<?php echo $category_totals[$category_name]; ?> copies)
                                            </span>
                                            <button type="button" onclick="toggleAllInCategory('<?php echo htmlspecialchars($category_name); ?>')"
                                                class="text-xs text-blue-600 hover:underline">
                                                Toggle All
                                            </button>
                                        </div>
                                    </div>
                                    <?php foreach ($newspapers as $paper): ?>
                                        <div class="newspaper-item">
                                            <input type="checkbox"
                                                name="selected_newspapers[]"
                                                value="<?php echo $paper['id']; ?>"
                                                id="modal_paper_<?php echo $paper['id']; ?>"
                                                class="newspaper-checkbox mr-3 category-<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $category_name); ?>"
                                                data-category="<?php echo htmlspecialchars($category_name); ?>"
                                                onchange="updateCategoryCheckbox('<?php echo htmlspecialchars($category_name); ?>'); updateModalCount()">
                                            <label for="modal_paper_<?php echo $paper['id']; ?>" class="flex-1 text-sm cursor-pointer flex justify-between items-center">
                                                <div>
                                                    <span class="font-medium"><?php echo htmlspecialchars($paper['newspaper_name']); ?></span>
                                                    <span class="text-xs text-[#6e6e6e] ml-2 issue-number"><?php echo htmlspecialchars($paper['newspaper_number']); ?></span>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <span class="text-xs <?php echo $paper['available_copies'] > 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                                        <?php echo $paper['available_copies']; ?> copies
                                                    </span>
                                                    <span class="text-xs text-[#6e6e6e]">
                                                        <?php echo date('M j, Y', strtotime($paper['date_received'])); ?>
                                                    </span>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-[#6e6e6e]">
                                <p>No newspapers available for distribution</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-[#e5e5e5]">
                    <button type="button" onclick="closeDistributeModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="submit" name="distribute_submit"
                        class="px-4 py-2 text-sm bg-[#1e1e1e] text-white rounded-md hover:bg-[#2d2d2d]"
                        onclick="return validateDistribution()">
                        <i class="fa-solid fa-hand-holding-hand mr-1"></i>
                        Distribute Selected (<span id="modalSelectedCount">0</span>)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Distribution Modal -->
    <div id="viewModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-[#1e1e1e]">Distribution Details</h2>
                <button type="button" onclick="closeViewModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div id="viewContent" class="space-y-4">
                <!-- Content will be filled by JavaScript -->
            </div>

            <div class="flex justify-end mt-4">
                <button onclick="closeViewModal()"
                    class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Distribution Modal -->
    <div id="editModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-[#1e1e1e]">Edit Distribution</h2>
                <button type="button" onclick="closeEditModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div id="editLoading" class="text-center py-4">
                <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading distribution data...
            </div>

            <form method="POST" action="available.php" id="editForm" style="display: none;">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Newspaper</label>
                        <p id="edit_newspaper_display" class="text-sm font-medium bg-[#f5f5f4] p-2 rounded-md"></p>
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Individual's Name *</label>
                        <input type="text" name="edit_individual_name" id="edit_individual_name" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Department</label>
                        <input type="text" name="edit_department" id="edit_department"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Copies *</label>
                        <input type="number" name="edit_copies" id="edit_copies" min="1" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                        <p class="text-xs text-[#6e6e6e] mt-1">Note: Changing copies will update newspaper inventory</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="submit" name="edit_distribution_submit"
                        class="px-4 py-2 text-sm bg-[#1e1e1e] text-white rounded-md hover:bg-[#2d2d2d]">
                        Update Distribution
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-[#1e1e1e]">Confirm Delete</h2>
                <button type="button" onclick="closeDeleteModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="py-2">
                <p class="text-sm text-[#6e6e6e]">Are you sure you want to delete this distribution record?</p>
                <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                    <p class="text-sm font-medium text-red-800" id="deleteNewspaperName"></p>
                    <p class="text-xs text-red-600 mt-1" id="deleteRecipientName"></p>
                </div>
                <p class="text-xs text-[#9e9e9e] mt-3">
                    <i class="fa-solid fa-circle-info mr-1"></i>
                    This will restore the copies back to the newspaper inventory.
                </p>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button onclick="closeDeleteModal()"
                    class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    Cancel
                </button>
                <a href="#" id="confirmDeleteBtn"
                    class="px-4 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700">
                    Delete Permanently
                </a>
            </div>
        </div>
    </div>

    <script>
        // ========== TOAST NOTIFICATION ==========
        function showToast(type, message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;

            const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';

            toast.innerHTML = `
                <div class="flex items-center gap-3">
                    <i class="fa-regular ${icon} text-${type === 'success' ? 'green' : 'red'}-500"></i>
                    <span class="text-sm text-[#1e1e1e]">${message}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            }, 5000);
        }

        <?php if ($toast): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo $toast['type']; ?>', '<?php echo addslashes($toast['message']); ?>');
            });
        <?php endif; ?>

        // ========== LAST DISTRIBUTION NOTIFICATION ==========
        function dismissLastDistribution() {
            const notification = document.getElementById('lastDistributionNotification');
            if (notification) {
                notification.classList.add('fade-out');

                // Send AJAX request to dismiss from session
                fetch('available.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax=dismiss_last_distribution'
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Notification dismissed');
                    })
                    .catch(error => {
                        console.error('Error dismissing notification:', error);
                    });

                // Remove from DOM after animation
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }

        // Auto-dismiss last distribution notification after 3 seconds
        <?php if (isset($_SESSION['last_distribution'])): ?>
            setTimeout(() => {
                dismissLastDistribution();
            }, 3000);
        <?php endif; ?>

        // ========== NEWSPAPER SELECTION ==========
        let selectedNewspapers = new Set();

        function toggleNewspaperSelection(id) {
            const icon = document.getElementById(`selection-icon-${id}`);

            if (selectedNewspapers.has(id)) {
                selectedNewspapers.delete(id);
                icon.style.color = '#9e9e9e';
                icon.classList.remove('fa-solid');
                icon.classList.add('fa-regular');
            } else {
                selectedNewspapers.add(id);
                icon.style.color = '#1e1e1e';
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
            }

            updateSelectionCount();
        }

        function updateSelectionCount() {
            const count = selectedNewspapers.size;
            document.getElementById('selectedCount').textContent = count;

            const distributeBtn = document.getElementById('distributeBtn');
            if (count > 0) {
                distributeBtn.disabled = false;
            } else {
                distributeBtn.disabled = true;
            }
        }

        // ========== MODAL FUNCTIONS ==========
        function openDistributeModal() {
            if (selectedNewspapers.size === 0) {
                alert('Please select at least one newspaper to distribute');
                return;
            }

            // Pre-select newspapers in modal
            const modalCheckboxes = document.querySelectorAll('#distributeModal .newspaper-checkbox');
            modalCheckboxes.forEach(cb => {
                if (selectedNewspapers.has(parseInt(cb.value))) {
                    cb.checked = true;
                } else {
                    cb.checked = false;
                }
            });

            // Update category checkboxes in modal
            updateAllCategoryCheckboxes();
            updateModalCount();

            document.getElementById('distributeModal').style.display = 'flex';
        }

        function closeDistributeModal() {
            document.getElementById('distributeModal').style.display = 'none';
        }

        // ========== VIEW MODAL FUNCTIONS ==========
        function viewDistribution(dist) {
            const content = document.getElementById('viewContent');
            content.innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Newspaper</p>
                        <p class="text-sm font-medium">${escapeHtml(dist.newspaper_name)}</p>
                        <p class="text-xs font-mono text-[#6e6e6e] mt-1">${escapeHtml(dist.newspaper_number)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Category</p>
                        <p class="text-sm">${escapeHtml(dist.category_name || 'N/A')}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Copies</p>
                        <p class="text-sm">${dist.copies}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Date Distributed</p>
                        <p class="text-sm">${new Date(dist.date_distributed).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Distributed To</p>
                        <p class="text-sm">${escapeHtml(dist.distributed_to)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Department</p>
                        <p class="text-sm">${escapeHtml(dist.department || '-')}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Distributed By</p>
                        <p class="text-sm">${escapeHtml(dist.distributed_by || 'N/A')}</p>
                    </div>
                </div>
            `;
            document.getElementById('viewModal').style.display = 'flex';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        // ========== EDIT MODAL FUNCTIONS ==========
        function editDistribution(id) {
            const editModal = document.getElementById('editModal');
            const loadingEl = document.getElementById('editLoading');
            const formEl = document.getElementById('editForm');

            editModal.style.display = 'flex';
            loadingEl.style.display = 'block';
            formEl.style.display = 'none';

            // Fetch distribution data via AJAX
            fetch('available.php?ajax=get_distribution&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_id').value = data.distribution.id;
                        document.getElementById('edit_newspaper_display').textContent =
                            data.distribution.newspaper_name + ' (' + data.distribution.newspaper_number + ')';
                        document.getElementById('edit_individual_name').value = data.distribution.distributed_to;
                        document.getElementById('edit_department').value = data.distribution.department || '';
                        document.getElementById('edit_copies').value = data.distribution.copies;

                        loadingEl.style.display = 'none';
                        formEl.style.display = 'block';
                    } else {
                        showToast('error', 'Failed to load distribution data');
                        closeEditModal();
                    }
                })
                .catch(error => {
                    showToast('error', 'Error loading distribution data');
                    closeEditModal();
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editLoading').style.display = 'block';
            document.getElementById('editForm').style.display = 'none';
        }

        // ========== DELETE MODAL FUNCTIONS ==========
        let currentDeleteId = null;

        function openDeleteModal(id, newspaper, recipient) {
            currentDeleteId = id;
            document.getElementById('deleteNewspaperName').textContent = newspaper;
            document.getElementById('deleteRecipientName').textContent = 'Recipient: ' + recipient;
            document.getElementById('confirmDeleteBtn').href = '?delete_distribution=' + id + '&<?php echo http_build_query($_GET); ?>';
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            currentDeleteId = null;
        }

        // ========== CATEGORY CHECKBOX FUNCTIONS (Modal) ==========
        function toggleCategory(categoryName, checked) {
            let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
            let checkboxes = document.querySelectorAll('#distributeModal .' + className);
            checkboxes.forEach(cb => {
                cb.checked = checked;
            });
            updateModalCount();
            updateAllCategoryCheckboxes();
        }

        function toggleAllInCategory(categoryName) {
            let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
            let checkboxes = document.querySelectorAll('#distributeModal .' + className);

            let allChecked = true;
            checkboxes.forEach(cb => {
                if (!cb.checked) allChecked = false;
            });

            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });

            let mainCheckbox = document.querySelector('#distributeModal .category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_') + '-main');
            if (mainCheckbox) {
                mainCheckbox.checked = !allChecked;
                mainCheckbox.indeterminate = false;
            }

            updateModalCount();
        }

        function updateCategoryCheckbox(categoryName) {
            let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
            let checkboxes = document.querySelectorAll('#distributeModal .' + className);
            let totalCheckboxes = checkboxes.length;
            let checkedCount = 0;

            checkboxes.forEach(cb => {
                if (cb.checked) checkedCount++;
            });

            let mainCheckbox = document.querySelector('#distributeModal .category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_') + '-main');
            if (mainCheckbox) {
                if (checkedCount === 0) {
                    mainCheckbox.checked = false;
                    mainCheckbox.indeterminate = false;
                } else if (checkedCount === totalCheckboxes) {
                    mainCheckbox.checked = true;
                    mainCheckbox.indeterminate = false;
                } else {
                    mainCheckbox.checked = false;
                    mainCheckbox.indeterminate = true;
                }
            }
        }

        function updateAllCategoryCheckboxes() {
            let categoryGroups = document.querySelectorAll('#distributeModal .category-group');
            categoryGroups.forEach(group => {
                let categoryName = group.dataset.category;
                if (categoryName) {
                    updateCategoryCheckbox(categoryName);
                }
            });
        }

        function selectAllCategories() {
            let categoryGroups = document.querySelectorAll('#distributeModal .category-group');
            categoryGroups.forEach(group => {
                let categoryName = group.dataset.category;
                if (categoryName) {
                    let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
                    let checkboxes = document.querySelectorAll('#distributeModal .' + className);
                    checkboxes.forEach(cb => {
                        cb.checked = true;
                    });

                    let mainCheckbox = document.querySelector('#distributeModal .category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_') + '-main');
                    if (mainCheckbox) {
                        mainCheckbox.checked = true;
                        mainCheckbox.indeterminate = false;
                    }
                }
            });
            updateModalCount();
        }

        function deselectAllCategories() {
            let categoryGroups = document.querySelectorAll('#distributeModal .category-group');
            categoryGroups.forEach(group => {
                let categoryName = group.dataset.category;
                if (categoryName) {
                    let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
                    let checkboxes = document.querySelectorAll('#distributeModal .' + className);
                    checkboxes.forEach(cb => {
                        cb.checked = false;
                    });

                    let mainCheckbox = document.querySelector('#distributeModal .category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_') + '-main');
                    if (mainCheckbox) {
                        mainCheckbox.checked = false;
                        mainCheckbox.indeterminate = false;
                    }
                }
            });
            updateModalCount();
        }

        function updateModalCount() {
            let checkboxes = document.querySelectorAll('#distributeModal .newspaper-checkbox:checked');
            document.getElementById('modalSelectedCount').textContent = checkboxes.length;
        }

        function validateDistribution() {
            let checkboxes = document.querySelectorAll('#distributeModal .newspaper-checkbox:checked');
            let individualName = document.getElementById('modal_individual_name').value.trim();
            let distributedBy = document.getElementById('modal_distributed_by').value.trim();

            if (checkboxes.length === 0) {
                alert('Please select at least one newspaper to distribute');
                return false;
            }

            if (individualName === '') {
                alert('Please enter the individual\'s name');
                return false;
            }

            if (distributedBy === '') {
                alert('Please enter who is distributing');
                return false;
            }

            return confirm(`Distribute 1 copy each of ${checkboxes.length} newspaper(s) to ${individualName}?`);
        }

        // ========== SORTING FUNCTION ==========
        function sortTable(column) {
            const url = new URL(window.location.href);
            const currentSort = '<?php echo $sort_by; ?>';
            const currentOrder = '<?php echo $sort_order; ?>';

            let newOrder = 'ASC';
            if (column === currentSort) {
                newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
            }

            url.searchParams.set('sort_by', column);
            url.searchParams.set('sort_order', newOrder);
            url.searchParams.set('page', '1');

            window.location.href = url.toString();
        }

        // ========== ESCAPE HTML ==========
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const distributeModal = document.getElementById('distributeModal');
            const viewModal = document.getElementById('viewModal');
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');

            if (event.target == distributeModal) {
                closeDistributeModal();
            }
            if (event.target == viewModal) {
                closeViewModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        // ESC key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDistributeModal();
                closeViewModal();
                closeEditModal();
                closeDeleteModal();
            }
        });
    </script>
</body>

</html>