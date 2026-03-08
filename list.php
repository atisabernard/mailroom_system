<?php
// list.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once './config/db.php';

// Start session for messages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ========== HELPER FUNCTIONS ==========

/**
 * Set a toast message in session
 */
function setToast($type, $message)
{
    $_SESSION['toast'] = ['type' => $type, 'message' => $message];
}

/**
 * Generate issue number
 */
function generateIssueNumber($category_name, $date_received)
{
    $category_prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $category_name), 0, 3));
    $date_prefix = date('Ymd', strtotime($date_received));
    $random_suffix = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    return $category_prefix . '-' . $date_prefix . '-' . $random_suffix;
}

// ========== CATEGORY HANDLERS ==========

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category_submit'])) {
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);

    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO newspaper_categories (category_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $category_name, $description);

        if ($stmt->execute()) {
            setToast('success', "Category added successfully!");
        } else {
            setToast('error', "Error adding category: " . $conn->error);
        }
        $stmt->close();
    } else {
        setToast('error', "Category name is required");
    }

    header('Location: list.php');
    exit();
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category_submit'])) {
    $id = (int)$_POST['category_id'];
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);

    if (!empty($category_name)) {
        $stmt = $conn->prepare("UPDATE newspaper_categories SET category_name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $category_name, $description, $id);

        if ($stmt->execute()) {
            setToast('success', "Category updated successfully!");
        } else {
            setToast('error', "Error updating category: " . $conn->error);
        }
        $stmt->close();
    } else {
        setToast('error', "Category name is required");
    }

    header('Location: list.php');
    exit();
}

// Handle Delete Category
if (isset($_GET['delete_category'])) {
    $id = (int)$_GET['delete_category'];

    // Check if category is used in newspapers
    $check = $conn->query("SELECT COUNT(*) as count FROM newspapers WHERE category_id = $id");
    $row = $check->fetch_assoc();

    if ($row['count'] > 0) {
        setToast('error', "Cannot delete: This category has $row[count] newspaper(s)");
    } else {
        $stmt = $conn->prepare("DELETE FROM newspaper_categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            setToast('success', "Category deleted successfully!");
        } else {
            setToast('error', "Error deleting category: " . $conn->error);
        }
        $stmt->close();
    }

    header('Location: list.php');
    exit();
}

// ========== NEWSPAPER HANDLERS ==========

// Handle Add Newspaper
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_newspaper_submit'])) {
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $date_received = $_POST['date_received'];
    $copies_received = (int)$_POST['copies_received'];
    $received_by = trim($_POST['received_by']);

    // Get category name for newspaper name and issue number
    $cat_result = $conn->query("SELECT category_name FROM newspaper_categories WHERE id = $category_id");
    $cat_row = $cat_result->fetch_assoc();
    $newspaper_name = $cat_row['category_name'];

    // Generate issue number
    $newspaper_number = generateIssueNumber($newspaper_name, $date_received);

    // Insert into newspapers table
    $stmt = $conn->prepare("INSERT INTO newspapers (newspaper_name, newspaper_number, category_id, date_received, received_by, available_copies, status) VALUES (?, ?, ?, ?, ?, ?, 'available')");
    $stmt->bind_param("ssissi", $newspaper_name, $newspaper_number, $category_id, $date_received, $received_by, $copies_received);

    if ($stmt->execute()) {
        setToast('success', "Newspaper added successfully! Issue #: $newspaper_number");
    } else {
        setToast('error', "Error adding newspaper: " . $conn->error);
    }
    $stmt->close();

    header('Location: list.php');
    exit();
}

// Handle Delete Newspaper
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Check if newspaper is used in distribution
    $check = $conn->query("SELECT COUNT(*) as count FROM distribution WHERE newspaper_id = $id");
    $row = $check->fetch_assoc();

    if ($row['count'] > 0) {
        setToast('error', "Cannot delete: This newspaper has been distributed");
    } else {
        $stmt = $conn->prepare("DELETE FROM newspapers WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            setToast('success', "Newspaper deleted successfully!");
        } else {
            setToast('error', "Error deleting newspaper: " . $conn->error);
        }
        $stmt->close();
    }

    // Preserve filters and pagination
    $query_params = $_GET;
    unset($query_params['delete']);
    $redirect_url = 'list.php' . (!empty($query_params) ? '?' . http_build_query($query_params) : '');
    header('Location: ' . $redirect_url);
    exit();
}

// Handle Update Newspaper Copies
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_copies_submit'])) {
    $id = (int)$_POST['newspaper_id'];
    $available_copies = (int)$_POST['available_copies'];

    $stmt = $conn->prepare("UPDATE newspapers SET available_copies = ? WHERE id = ?");
    $stmt->bind_param("ii", $available_copies, $id);

    if ($stmt->execute()) {
        // Update status based on available copies
        if ($available_copies == 0) {
            $conn->query("UPDATE newspapers SET status = 'distributed' WHERE id = $id");
        } else {
            $conn->query("UPDATE newspapers SET status = 'available' WHERE id = $id");
        }
        setToast('success', "Newspaper copies updated successfully!");
    } else {
        setToast('error', "Error updating copies: " . $conn->error);
    }
    $stmt->close();

    // Preserve filters and pagination
    $query_params = $_GET;
    $redirect_url = 'list.php' . (!empty($query_params) ? '?' . http_build_query($query_params) : '');
    header('Location: ' . $redirect_url);
    exit();
}

// Handle Distribution
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
                                        WHERE n.id = $newspaper_id");
                $paper = $result->fetch_assoc();

                if ($paper && $paper['available_copies'] > 0) {
                    // Update newspaper available copies (distribute 1 copy)
                    $conn->query("UPDATE newspapers SET available_copies = available_copies - 1 WHERE id = $newspaper_id");

                    // Update status
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
                    $distributed_details[] = $paper['newspaper_name'] . " (" . $paper['category_name'] . ")";
                }
            }

            $conn->commit();

            if ($success_count > 0) {
                setToast('success', "$success_count newspaper(s) distributed to $individual_name");

                // Store last distribution info in session
                $_SESSION['last_distribution'] = [
                    'individual' => $individual_name,
                    'department' => $department,
                    'count' => $success_count,
                    'newspapers' => $distributed_details,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } else {
                setToast('error', "No newspapers were available for distribution");
            }
        } catch (Exception $e) {
            $conn->rollback();
            setToast('error', "Distribution failed: " . $e->getMessage());
        }
    } else {
        setToast('error', "No newspapers selected for distribution");
    }

    // Preserve filters and pagination
    $query_params = $_GET;
    $redirect_url = 'list.php' . (!empty($query_params) ? '?' . http_build_query($query_params) : '');
    header('Location: ' . $redirect_url);
    exit();
}

// ========== GET DATA FOR DISPLAY ==========

// Get all categories
$categories_query = $conn->query("SELECT * FROM newspaper_categories ORDER BY category_name");
$all_categories = [];
while ($cat = $categories_query->fetch_assoc()) {
    $all_categories[] = $cat;
}

// Get available newspapers with their categories for available modal
$available_newspapers = $conn->query("SELECT n.*, nc.category_name, nc.id as category_id 
                                     FROM newspapers n 
                                     LEFT JOIN newspaper_categories nc ON n.category_id = nc.id 
                                     WHERE n.available_copies > 0 
                                     ORDER BY nc.category_name, n.newspaper_name");

// Group newspapers by category for available modal
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

// Pagination settings for newspapers
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter settings for newspapers
$filter_category = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : 0;
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_received';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Build query for all newspapers with filters
$where_clauses = [];
if ($filter_category > 0) {
    $where_clauses[] = "n.category_id = $filter_category";
}
if (!empty($filter_status)) {
    $where_clauses[] = "n.status = '$filter_status'";
}
if (!empty($search)) {
    $where_clauses[] = "(n.newspaper_name LIKE '%$search%' OR n.newspaper_number LIKE '%$search%')";
}
$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM newspapers n $where_sql";
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get all newspapers for management with filters, sorting and pagination
$all_newspapers = $conn->query("SELECT n.*, nc.category_name 
                               FROM newspapers n 
                               LEFT JOIN newspaper_categories nc ON n.category_id = nc.id 
                               $where_sql
                               ORDER BY 
                                   CASE WHEN '$sort_by' = 'date_received' THEN n.date_received END $sort_order,
                                   CASE WHEN '$sort_by' = 'newspaper_name' THEN n.newspaper_name END $sort_order,
                                   CASE WHEN '$sort_by' = 'category_name' THEN nc.category_name END $sort_order,
                                   CASE WHEN '$sort_by' = 'status' THEN n.status END $sort_order,
                                   CASE WHEN '$sort_by' = 'available_copies' THEN n.available_copies END $sort_order
                               LIMIT $offset, $limit");

// Pagination settings for distribution history
$dist_page = isset($_GET['dist_page']) ? (int)$_GET['dist_page'] : 1;
$dist_limit = 10;
$dist_offset = ($dist_page - 1) * $dist_limit;

// Filter settings for distribution history
$dist_search = isset($_GET['dist_search']) ? trim($_GET['dist_search']) : '';
$dist_filter_category = isset($_GET['dist_filter_category']) ? (int)$_GET['dist_filter_category'] : 0;
$dist_sort_by = isset($_GET['dist_sort_by']) ? $_GET['dist_sort_by'] : 'date_distributed';
$dist_sort_order = isset($_GET['dist_sort_order']) ? $_GET['dist_sort_order'] : 'DESC';

// Build query for distribution history with filters
$dist_where_clauses = [];
if (!empty($dist_search)) {
    $dist_where_clauses[] = "(n.newspaper_name LIKE '%$dist_search%' OR n.newspaper_number LIKE '%$dist_search%' OR d.distributed_to LIKE '%$dist_search%' OR d.department LIKE '%$dist_search%')";
}
if ($dist_filter_category > 0) {
    $dist_where_clauses[] = "n.category_id = $dist_filter_category";
}
$dist_where_sql = !empty($dist_where_clauses) ? "WHERE " . implode(" AND ", $dist_where_clauses) : "";

// Get total count for distribution pagination
$dist_count_query = "SELECT COUNT(*) as total FROM distribution d 
                     JOIN newspapers n ON d.newspaper_id = n.id 
                     LEFT JOIN newspaper_categories nc ON n.category_id = nc.id 
                     $dist_where_sql";
$dist_count_result = $conn->query($dist_count_query);
$dist_total_rows = $dist_count_result->fetch_assoc()['total'];
$dist_total_pages = ceil($dist_total_rows / $dist_limit);

// Get distribution history with filters, sorting and pagination
$distribution_history = $conn->query("SELECT d.*, n.newspaper_name, n.newspaper_number, nc.category_name 
                                      FROM distribution d 
                                      JOIN newspapers n ON d.newspaper_id = n.id 
                                      LEFT JOIN newspaper_categories nc ON n.category_id = nc.id 
                                      $dist_where_sql
                                      ORDER BY 
                                          CASE WHEN '$dist_sort_by' = 'date_distributed' THEN d.date_distributed END $dist_sort_order,
                                          CASE WHEN '$dist_sort_by' = 'newspaper_name' THEN n.newspaper_name END $dist_sort_order,
                                          CASE WHEN '$dist_sort_by' = 'category_name' THEN nc.category_name END $dist_sort_order,
                                          CASE WHEN '$dist_sort_by' = 'distributed_to' THEN d.distributed_to END $dist_sort_order,
                                          CASE WHEN '$dist_sort_by' = 'department' THEN d.department END $dist_sort_order
                                      LIMIT $dist_offset, $dist_limit");

// Get statistics
$total_available = $conn->query("SELECT SUM(available_copies) as total FROM newspapers")->fetch_assoc()['total'] ?? 0;
$total_newspapers = $conn->query("SELECT COUNT(*) as count FROM newspapers")->fetch_assoc()['count'] ?? 0;
$total_categories = $conn->query("SELECT COUNT(*) as count FROM newspaper_categories")->fetch_assoc()['count'] ?? 0;
$total_distributed_today = $conn->query("SELECT COUNT(*) as count FROM distribution WHERE date_distributed = CURDATE()")->fetch_assoc()['count'] ?? 0;
$total_distributed_month = $conn->query("SELECT COUNT(*) as count FROM distribution WHERE MONTH(date_distributed) = MONTH(CURDATE())")->fetch_assoc()['count'] ?? 0;

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
    <title>Newspaper List - Mailroom</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f5f5f4;
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
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e5e5;
            font-size: 0.875rem;
            color: #1e1e1e;
        }

        .newspaper-grid {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            padding: 0.75rem;
        }

        .category-group {
            margin-bottom: 1rem;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            overflow: hidden;
        }

        .category-header {
            font-weight: 500;
            color: #4a4a4a;
            padding: 0.75rem;
            background-color: #f5f5f4;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .newspaper-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.5rem 0.5rem 2rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .newspaper-item:last-child {
            border-bottom: none;
        }

        .newspaper-item:hover {
            background-color: #fafafa;
        }

        .available-badge {
            font-size: 0.7rem;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 0.15rem 0.5rem;
            border-radius: 1rem;
            margin-left: 0.5rem;
        }

        .status-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 1rem;
        }

        .status-available {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-partial {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .status-distributed {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .issue-number {
            font-family: monospace;
            font-size: 0.75rem;
            color: #6b7280;
        }

        .generated-issue {
            background-color: #f9f9f9;
            border: 1px dashed #9e9e9e;
            padding: 0.5rem;
            border-radius: 0.375rem;
            font-family: monospace;
            font-size: 0.875rem;
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

        /* Toast notification */
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

        .toast-info {
            border-left: 4px solid #3b82f6;
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

        /* Quick action button at top */
        .quick-action-btn {
            background-color: #1e1e1e;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.875rem;
        }

        .quick-action-btn:hover {
            background-color: #2d2d2d;
            transform: scale(1.02);
        }

        .quick-action-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background-color: white;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            padding: 0.5rem;
            min-width: 200px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: none;
            z-index: 50;
        }

        .quick-action-menu.show {
            display: block;
        }

        .quick-action-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #1e1e1e;
            text-decoration: none;
            border-radius: 0.375rem;
            transition: background-color 0.2s;
            cursor: pointer;
        }

        .quick-action-item:hover {
            background-color: #f5f5f4;
        }

        .quick-action-item i {
            width: 20px;
            color: #6e6e6e;
        }
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <div class="flex">
        <main class="flex-1 ml-60 min-h-screen">
            <!-- Header with Quick Action -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-medium text-[#1e1e1e]">Newspaper Management</h1>
                        <p class="text-sm text-[#6e6e6e] mt-1">Manage newspapers and track distributions</p>
                    </div>

                    <!-- Quick Action Button at Top -->
                    <div class="relative">
                        <button class="quick-action-btn" onclick="toggleQuickMenu()">
                            <i class="fa-regular fa-bolt"></i>
                            <span>Quick Actions</span>
                            <i class="fa-regular fa-chevron-down text-sm"></i>
                        </button>

                        <!-- Quick Action Menu -->
                        <div id="quickActionMenu" class="quick-action-menu">
                            <div class="quick-action-item" onclick="openAddModal()">
                                <i class="fa-regular fa-plus"></i>
                                <span>Add Newspaper</span>
                            </div>
                            <div class="quick-action-item" onclick="openAddCategoryModal()">
                                <i class="fa-regular fa-tags"></i>
                                <span>Add Category</span>
                            </div>
                            <div class="quick-action-item" onclick="openAvailableModal()">
                                <i class="fa-regular fa-eye"></i>
                                <span>View Available</span>
                            </div>
                            <div class="quick-action-item" onclick="openDistributeModal()">
                                <i class="fa-regular fa-hand-holding-hand"></i>
                                <span>Distribute</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Available Copies</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_available; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Total Newspapers</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_newspapers; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Categories</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_categories; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Distributed Today</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_distributed_today; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">This Month</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_distributed_month; ?></p>
                    </div>
                </div>

                <!-- Last Distribution Info (if exists) -->
                <?php if (isset($_SESSION['last_distribution'])): ?>
                    <div class="mb-6 p-3 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-800">
                        <i class="fa-regular fa-circle-info mr-2"></i>
                        Last distribution: <?php echo $_SESSION['last_distribution']['count']; ?> newspaper(s) to
                        <strong><?php echo htmlspecialchars($_SESSION['last_distribution']['individual']); ?></strong>
                        <?php if (!empty($_SESSION['last_distribution']['department'])): ?>
                            (<?php echo htmlspecialchars($_SESSION['last_distribution']['department']); ?>)
                        <?php endif; ?>
                        at <?php echo $_SESSION['last_distribution']['timestamp']; ?>
                    </div>
                <?php endif; ?>

                <!-- Newspapers Table -->
                <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden mb-6">
                    <div class="px-4 py-3 bg-[#fafafa] border-b border-[#e5e5e5]">
                        <h3 class="text-sm font-medium text-[#1e1e1e]">Newspapers</h3>
                    </div>

                    <!-- Filters -->
                    <div class="p-4 border-b border-[#e5e5e5]">
                        <form method="GET" class="flex flex-wrap gap-3 items-center">
                            <input type="hidden" name="page" value="1">

                            <div class="flex-1 min-w-[200px]">
                                <input type="text" name="search"
                                    placeholder="Search by name or issue number..."
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    class="w-full px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                            </div>

                            <select name="filter_category" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white">
                                <option value="0">All Categories</option>
                                <?php foreach ($all_categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="filter_status" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white">
                                <option value="">All Status</option>
                                <option value="available" <?php echo $filter_status == 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="partial" <?php echo $filter_status == 'partial' ? 'selected' : ''; ?>>Partial</option>
                                <option value="distributed" <?php echo $filter_status == 'distributed' ? 'selected' : ''; ?>>Distributed</option>
                            </select>

                            <select name="sort_by" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white">
                                <option value="date_received" <?php echo $sort_by == 'date_received' ? 'selected' : ''; ?>>Sort by Date</option>
                                <option value="newspaper_name" <?php echo $sort_by == 'newspaper_name' ? 'selected' : ''; ?>>Sort by Name</option>
                                <option value="category_name" <?php echo $sort_by == 'category_name' ? 'selected' : ''; ?>>Sort by Category</option>
                                <option value="status" <?php echo $sort_by == 'status' ? 'selected' : ''; ?>>Sort by Status</option>
                                <option value="available_copies" <?php echo $sort_by == 'available_copies' ? 'selected' : ''; ?>>Sort by Copies</option>
                            </select>

                            <select name="sort_order" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white">
                                <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>

                            <button type="submit" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4]">
                                <i class="fa-regular fa-sliders mr-1"></i>Apply
                            </button>

                            <a href="list.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4]">
                                <i class="fa-regular fa-rotate-right mr-1"></i>Reset
                            </a>
                        </form>

                        <!-- Active Filters Display -->
                        <?php if ($filter_category > 0 || !empty($filter_status) || !empty($search)): ?>
                            <div class="flex flex-wrap gap-2 mt-3">
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
                                            <i class="fa-regular fa-xmark"></i>
                                        </a>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($filter_status)): ?>
                                    <span class="filter-badge">
                                        Status: <?php echo ucfirst($filter_status); ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_status' => '', 'page' => 1])); ?>" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                            <i class="fa-regular fa-xmark"></i>
                                        </a>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($search)): ?>
                                    <span class="filter-badge">
                                        Search: "<?php echo htmlspecialchars($search); ?>"
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])); ?>" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                            <i class="fa-regular fa-xmark"></i>
                                        </a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Newspapers Table -->
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr class="bg-[#fafafa]">
                                    <th class="text-xs">ID</th>
                                    <th class="text-xs">Newspaper</th>
                                    <th class="text-xs">Issue #</th>
                                    <th class="text-xs">Category</th>
                                    <th class="text-xs">Date Received</th>
                                    <th class="text-xs">Available</th>
                                    <th class="text-xs">Status</th>
                                    <th class="text-xs">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_newspapers && $all_newspapers->num_rows > 0): ?>
                                    <?php while ($paper = $all_newspapers->fetch_assoc()): ?>
                                        <tr class="hover:bg-[#fafafa]">
                                            <td class="text-sm text-[#6e6e6e]"><?php echo $paper['id']; ?></td>
                                            <td class="text-sm font-medium text-[#1e1e1e]"><?php echo htmlspecialchars($paper['newspaper_name']); ?></td>
                                            <td class="text-sm font-mono text-[#1e1e1e] issue-number"><?php echo htmlspecialchars($paper['newspaper_number']); ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo htmlspecialchars($paper['category_name'] ?? 'Uncategorized'); ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo date('M j, Y', strtotime($paper['date_received'])); ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo $paper['available_copies']; ?></td>
                                            <td class="text-sm">
                                                <?php
                                                $status_class = '';
                                                if ($paper['status'] == 'available') {
                                                    $status_class = 'status-available';
                                                } elseif ($paper['status'] == 'partial') {
                                                    $status_class = 'status-partial';
                                                } else {
                                                    $status_class = 'status-distributed';
                                                }
                                                ?>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($paper['status'] ?? 'available'); ?>
                                                </span>
                                            </td>
                                            <td class="text-sm">
                                                <div class="flex gap-2">
                                                    <button onclick="openUpdateModal(<?php echo $paper['id']; ?>, '<?php echo htmlspecialchars($paper['newspaper_name']); ?>', <?php echo $paper['available_copies']; ?>)"
                                                        class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                                        <i class="fa-regular fa-pen-to-square"></i>
                                                    </button>
                                                    <a href="?delete=<?php echo $paper['id']; ?>&<?php echo http_build_query($_GET); ?>"
                                                        onclick="return confirm('Delete this newspaper?')"
                                                        class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                                        <i class="fa-regular fa-trash-can"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-sm text-[#6e6e6e] text-center py-8">
                                            No newspapers found.
                                            <button onclick="openAddModal()" class="text-blue-600 hover:underline">Add one</button> to get started.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="px-4 py-3 bg-[#fafafa] border-t border-[#e5e5e5]">
                            <div class="flex justify-between items-center">
                                <div class="text-xs text-[#6e6e6e]">
                                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> entries
                                </div>
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-item">
                                            <i class="fa-regular fa-chevrons-left"></i>
                                        </a>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-item">
                                            <i class="fa-regular fa-chevron-left"></i>
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
                                            <i class="fa-regular fa-chevron-right"></i>
                                        </a>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="pagination-item">
                                            <i class="fa-regular fa-chevrons-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Distribution History with Filters and Pagination -->
                <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                    <div class="px-4 py-3 bg-[#fafafa] border-b border-[#e5e5e5]">
                        <h3 class="text-sm font-medium text-[#1e1e1e]">Distribution History</h3>
                    </div>

                    <!-- Distribution Filters -->
                    <div class="p-4 border-b border-[#e5e5e5]">
                        <form method="GET" class="flex flex-wrap gap-3 items-center">
                            <input type="hidden" name="dist_page" value="1">
                            <!-- Preserve newspaper filters -->
                            <?php if (!empty($search)): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <?php endif; ?>
                            <?php if ($filter_category > 0): ?>
                                <input type="hidden" name="filter_category" value="<?php echo $filter_category; ?>">
                            <?php endif; ?>
                            <?php if (!empty($filter_status)): ?>
                                <input type="hidden" name="filter_status" value="<?php echo $filter_status; ?>">
                            <?php endif; ?>
                            <input type="hidden" name="sort_by" value="<?php echo $sort_by; ?>">
                            <input type="hidden" name="sort_order" value="<?php echo $sort_order; ?>">

                            <div class="flex-1 min-w-[200px]">
                                <input type="text" name="dist_search"
                                    placeholder="Search by newspaper, recipient, department..."
                                    value="<?php echo htmlspecialchars($dist_search); ?>"
                                    class="w-full px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                            </div>

                            <select name="dist_filter_category" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white">
                                <option value="0">All Categories</option>
                                <?php foreach ($all_categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $dist_filter_category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="dist_sort_by" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white">
                                <option value="date_distributed" <?php echo $dist_sort_by == 'date_distributed' ? 'selected' : ''; ?>>Sort by Date</option>
                                <option value="newspaper_name" <?php echo $dist_sort_by == 'newspaper_name' ? 'selected' : ''; ?>>Sort by Newspaper</option>
                                <option value="category_name" <?php echo $dist_sort_by == 'category_name' ? 'selected' : ''; ?>>Sort by Category</option>
                                <option value="distributed_to" <?php echo $dist_sort_by == 'distributed_to' ? 'selected' : ''; ?>>Sort by Recipient</option>
                                <option value="department" <?php echo $dist_sort_by == 'department' ? 'selected' : ''; ?>>Sort by Department</option>
                            </select>

                            <select name="dist_sort_order" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white">
                                <option value="DESC" <?php echo $dist_sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $dist_sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>

                            <button type="submit" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4]">
                                <i class="fa-regular fa-sliders mr-1"></i>Apply
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr class="bg-[#fafafa]">
                                    <th class="text-xs">Date</th>
                                    <th class="text-xs">Newspaper</th>
                                    <th class="text-xs">Issue #</th>
                                    <th class="text-xs">Category</th>
                                    <th class="text-xs">Distributed To</th>
                                    <th class="text-xs">Department</th>
                                    <th class="text-xs">By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($distribution_history && $distribution_history->num_rows > 0): ?>
                                    <?php while ($dist = $distribution_history->fetch_assoc()): ?>
                                        <tr class="hover:bg-[#fafafa]">
                                            <td class="text-sm py-2 px-4"><?php echo date('M j, Y', strtotime($dist['date_distributed'])); ?></td>
                                            <td class="text-sm font-medium py-2 px-4"><?php echo htmlspecialchars($dist['newspaper_name']); ?></td>
                                            <td class="text-sm font-mono py-2 px-4 issue-number"><?php echo htmlspecialchars($dist['newspaper_number']); ?></td>
                                            <td class="text-sm py-2 px-4"><?php echo htmlspecialchars($dist['category_name'] ?? 'N/A'); ?></td>
                                            <td class="text-sm py-2 px-4"><?php echo htmlspecialchars($dist['distributed_to']); ?></td>
                                            <td class="text-sm py-2 px-4"><?php echo htmlspecialchars($dist['department'] ?? 'N/A'); ?></td>
                                            <td class="text-sm py-2 px-4"><?php echo htmlspecialchars($dist['distributed_by'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-sm text-[#6e6e6e] text-center py-8">
                                            No distribution history yet
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Distribution Pagination -->
                    <?php if ($dist_total_pages > 1): ?>
                        <div class="px-4 py-3 bg-[#fafafa] border-t border-[#e5e5e5]">
                            <div class="flex justify-between items-center">
                                <div class="text-xs text-[#6e6e6e]">
                                    Showing <?php echo $dist_offset + 1; ?> to <?php echo min($dist_offset + $dist_limit, $dist_total_rows); ?> of <?php echo $dist_total_rows; ?> entries
                                </div>
                                <div class="pagination">
                                    <?php if ($dist_page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['dist_page' => 1])); ?>" class="pagination-item">
                                            <i class="fa-regular fa-chevrons-left"></i>
                                        </a>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['dist_page' => $dist_page - 1])); ?>" class="pagination-item">
                                            <i class="fa-regular fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $dist_start = max(1, $dist_page - 2);
                                    $dist_end = min($dist_total_pages, $dist_page + 2);
                                    for ($i = $dist_start; $i <= $dist_end; $i++):
                                    ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['dist_page' => $i])); ?>"
                                            class="pagination-item <?php echo $i == $dist_page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($dist_page < $dist_total_pages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['dist_page' => $dist_page + 1])); ?>" class="pagination-item">
                                            <i class="fa-regular fa-chevron-right"></i>
                                        </a>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['dist_page' => $dist_total_pages])); ?>" class="pagination-item">
                                            <i class="fa-regular fa-chevrons-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Available Newspapers Modal -->
    <div id="availableModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-3xl p-5 max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Available Newspapers</h3>
                <button type="button" onclick="closeAvailableModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-regular fa-xmark"></i>
                </button>
            </div>

            <div class="space-y-3">
                <?php if (!empty($newspapers_by_category)): ?>
                    <?php foreach ($newspapers_by_category as $category_name => $category_data): ?>
                        <?php $newspapers = $category_data['newspapers']; ?>
                        <div class="border border-[#e5e5e5] rounded-md overflow-hidden">
                            <div class="px-4 py-2 bg-[#fafafa] border-b border-[#e5e5e5] flex justify-between items-center">
                                <span class="text-sm font-medium"><?php echo htmlspecialchars($category_name); ?></span>
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded-full">
                                    <?php echo $category_totals[$category_name]; ?> copies total
                                </span>
                            </div>
                            <div class="p-3">
                                <?php foreach ($newspapers as $paper): ?>
                                    <div class="flex justify-between items-center py-2 border-b border-[#f0f0f0] last:border-0">
                                        <div>
                                            <span class="text-sm font-medium"><?php echo htmlspecialchars($paper['newspaper_name']); ?></span>
                                            <span class="text-xs text-[#6e6e6e] ml-2 issue-number"><?php echo htmlspecialchars($paper['newspaper_number']); ?></span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded-full">
                                                <?php echo $paper['available_copies']; ?> copies
                                            </span>
                                            <span class="text-xs text-[#6e6e6e]">
                                                Received: <?php echo date('M j, Y', strtotime($paper['date_received'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-[#6e6e6e]">
                        No newspapers available at the moment
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex justify-end mt-4">
                <button type="button" onclick="closeAvailableModal()"
                    class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Add Newspaper Modal -->
    <div id="addModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Add Newspaper</h3>
                <button type="button" onclick="closeAddModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-regular fa-xmark"></i>
                </button>
            </div>

            <!-- Preview of generated issue number -->
            <div class="mb-4 p-3 bg-[#fafafa] border border-[#e5e5e5] rounded-md">
                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Auto-generated Issue #</p>
                <p id="previewIssueNumber" class="text-sm font-mono text-[#1e1e1e]">-</p>
            </div>

            <form method="POST" action="list.php">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Category</label>
                        <select name="category_id" id="categorySelect" required
                            onchange="updateIssuePreview()"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="">Select category</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" data-name="<?php echo htmlspecialchars($cat['category_name']); ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Date Received</label>
                            <input type="date" name="date_received" id="dateReceived" required value="<?php echo date('Y-m-d'); ?>"
                                onchange="updateIssuePreview()"
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                        </div>
                        <div>
                            <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Copies</label>
                            <input type="number" name="copies_received" min="1" required
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                                placeholder="e.g., 5">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Received By</label>
                        <input type="text" name="received_by" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="Staff name">
                    </div>

                    <div class="text-xs text-[#6e6e6e] bg-[#fafafa] p-2 rounded-md">
                        <i class="fa-regular fa-circle-info mr-1"></i>
                        Issue number will be auto-generated based on category and date
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeAddModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="submit" name="add_newspaper_submit"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-regular fa-floppy-disk mr-1 text-[#6e6e6e]"></i>
                        Add Newspaper
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Newspaper Copies Modal -->
    <div id="updateModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Update Available Copies</h3>
                <button type="button" onclick="closeUpdateModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-regular fa-xmark"></i>
                </button>
            </div>
            <form method="POST" action="list.php">
                <input type="hidden" name="newspaper_id" id="update_id">
                <div class="mb-4">
                    <p class="text-sm font-medium text-[#1e1e1e] mb-2" id="update_name"></p>
                </div>
                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Available Copies</label>
                    <input type="number" name="available_copies" id="update_copies" min="0" required
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeUpdateModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="submit" name="update_copies_submit"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-regular fa-floppy-disk mr-1 text-[#6e6e6e]"></i>
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Add Category</h3>
                <button type="button" onclick="closeAddCategoryModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-regular fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="list.php">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Category Name</label>
                        <input type="text" name="category_name" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="e.g., Daily News, Sports, Business">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Description</label>
                        <textarea name="description" rows="3"
                            placeholder="Optional description"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeAddCategoryModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="submit" name="add_category_submit"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-regular fa-floppy-disk mr-1 text-[#6e6e6e]"></i>
                        Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Distribute Modal -->
    <div id="distributeModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-2xl p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Distribute Newspapers</h3>
                <button type="button" onclick="closeDistributeModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-regular fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="list.php" id="distributeForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Individual's Name</label>
                        <input type="text" name="individual_name" id="individual_name" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="e.g., John Doe">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Department/Office</label>
                        <input type="text" name="department" id="department"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="e.g., HR Department">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Distributed By</label>
                    <input type="text" name="distributed_by" id="distributed_by" required
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                        placeholder="Your name">
                </div>

                <div class="mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-xs text-[#6e6e6e] uppercase tracking-wide">Select Newspapers</label>
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
                                    <div class="category-header">
                                        <div class="category-header-left">
                                            <input type="checkbox"
                                                class="category-checkbox category-<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $category_name); ?>-main"
                                                onchange="toggleCategory('<?php echo htmlspecialchars($category_name); ?>', this.checked)"
                                                id="cat_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $category_name); ?>">
                                            <label for="cat_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $category_name); ?>" class="font-medium cursor-pointer">
                                                <?php echo htmlspecialchars($category_name); ?>
                                            </label>
                                            <span class="available-badge">
                                                <?php echo count($newspapers); ?> titles (<?php echo $category_totals[$category_name]; ?> copies)
                                            </span>
                                        </div>
                                        <span class="category-select-all text-xs text-blue-600 hover:underline cursor-pointer" onclick="toggleAllInCategory('<?php echo htmlspecialchars($category_name); ?>')">
                                            Toggle All
                                        </span>
                                    </div>
                                    <?php foreach ($newspapers as $paper): ?>
                                        <div class="newspaper-item">
                                            <input type="checkbox"
                                                name="selected_newspapers[]"
                                                value="<?php echo $paper['id']; ?>"
                                                id="paper_<?php echo $paper['id']; ?>"
                                                class="mr-3 newspaper-checkbox category-<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $category_name); ?>"
                                                data-category="<?php echo htmlspecialchars($category_name); ?>"
                                                onchange="updateCategoryCheckbox('<?php echo htmlspecialchars($category_name); ?>')">
                                            <label for="paper_<?php echo $paper['id']; ?>" class="flex-1 text-sm cursor-pointer flex justify-between items-center">
                                                <span>
                                                    <span class="font-medium"><?php echo htmlspecialchars($paper['newspaper_name']); ?></span>
                                                    <span class="text-xs text-[#6e6e6e] ml-2 issue-number"><?php echo htmlspecialchars($paper['newspaper_number']); ?></span>
                                                </span>
                                                <span class="text-xs <?php echo $paper['available_copies'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                    (<?php echo $paper['available_copies']; ?>)
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-[#6e6e6e]">
                                No newspapers available for distribution
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <div class="text-sm text-[#6e6e6e]">
                        Selected: <span id="selectedCount">0</span> newspaper(s)
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeDistributeModal()"
                            class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            Cancel
                        </button>
                        <button type="submit" name="distribute_submit"
                            class="px-3 py-1.5 text-sm border border-transparent rounded-md bg-green-600 hover:bg-green-700 text-white"
                            onclick="return validateDistribution()">
                            Distribute (1 copy each)
                        </button>
                    </div>
                </div>
            </form>
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
                    <i class="fa-regular fa-xmark"></i>
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

        // ========== QUICK ACTION MENU ==========
        function toggleQuickMenu() {
            document.getElementById('quickActionMenu').classList.toggle('show');
        }

        // Close quick menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('quickActionMenu');
            const button = document.querySelector('.quick-action-btn');

            if (!button.contains(event.target) && !menu.contains(event.target)) {
                menu.classList.remove('show');
            }
        });

        // ========== ISSUE NUMBER PREVIEW ==========
        function updateIssuePreview() {
            const categorySelect = document.getElementById('categorySelect');
            const dateInput = document.getElementById('dateReceived');
            const previewEl = document.getElementById('previewIssueNumber');

            if (categorySelect.value && dateInput.value) {
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                const categoryName = selectedOption.getAttribute('data-name') || '';

                const categoryPrefix = categoryName.replace(/[^a-zA-Z0-9]/g, '').substring(0, 3).toUpperCase();
                const datePrefix = dateInput.value.replace(/-/g, '');

                previewEl.textContent = categoryPrefix + '-' + datePrefix + '-001';
            } else {
                previewEl.textContent = '-';
            }
        }

        // ========== NEWSPAPER MODAL FUNCTIONS ==========
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
            updateIssuePreview();
            document.getElementById('quickActionMenu').classList.remove('show');
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openUpdateModal(id, name, copies) {
            document.getElementById('update_id').value = id;
            document.getElementById('update_name').textContent = name;
            document.getElementById('update_copies').value = copies;
            document.getElementById('updateModal').style.display = 'flex';
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').style.display = 'none';
        }

        // ========== AVAILABLE MODAL FUNCTIONS ==========
        function openAvailableModal() {
            document.getElementById('availableModal').style.display = 'flex';
            document.getElementById('quickActionMenu').classList.remove('show');
        }

        function closeAvailableModal() {
            document.getElementById('availableModal').style.display = 'none';
        }

        // ========== CATEGORY MODAL FUNCTIONS ==========
        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'flex';
            document.getElementById('quickActionMenu').classList.remove('show');
        }

        function closeAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'none';
        }

        function openDistributeModal() {
            document.getElementById('distributeModal').style.display = 'flex';
            deselectAllCategories();
            updateCounts();
            document.getElementById('quickActionMenu').classList.remove('show');
        }

        function closeDistributeModal() {
            document.getElementById('distributeModal').style.display = 'none';
        }

        // ========== CATEGORY CHECKBOX FUNCTIONS ==========
        function toggleCategory(categoryName, checked) {
            let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
            let checkboxes = document.querySelectorAll('.' + className);
            checkboxes.forEach(cb => {
                cb.checked = checked;
            });
            updateCounts();
        }

        function toggleAllInCategory(categoryName) {
            let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
            let checkboxes = document.querySelectorAll('.' + className);

            let allChecked = true;
            checkboxes.forEach(cb => {
                if (!cb.checked) allChecked = false;
            });

            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });

            let mainCheckbox = document.querySelector('.category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_') + '-main');
            if (mainCheckbox) {
                mainCheckbox.checked = !allChecked;
                mainCheckbox.indeterminate = false;
            }

            updateCounts();
        }

        function updateCategoryCheckbox(categoryName) {
            let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
            let checkboxes = document.querySelectorAll('.' + className);
            let totalCheckboxes = checkboxes.length;
            let checkedCount = 0;

            checkboxes.forEach(cb => {
                if (cb.checked) checkedCount++;
            });

            let mainCheckbox = document.querySelector('.category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_') + '-main');
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

            updateCounts();
        }

        function selectAllCategories() {
            let categoryGroups = document.querySelectorAll('.category-group');
            categoryGroups.forEach(group => {
                let categoryName = group.dataset.category;
                if (categoryName) {
                    let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
                    let checkboxes = document.querySelectorAll('.' + className);
                    checkboxes.forEach(cb => {
                        cb.checked = true;
                    });

                    let mainCheckbox = document.querySelector('.category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_') + '-main');
                    if (mainCheckbox) {
                        mainCheckbox.checked = true;
                        mainCheckbox.indeterminate = false;
                    }
                }
            });
            updateCounts();
        }

        function deselectAllCategories() {
            let categoryGroups = document.querySelectorAll('.category-group');
            categoryGroups.forEach(group => {
                let categoryName = group.dataset.category;
                if (categoryName) {
                    let className = 'category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_');
                    let checkboxes = document.querySelectorAll('.' + className);
                    checkboxes.forEach(cb => {
                        cb.checked = false;
                    });

                    let mainCheckbox = document.querySelector('.category-' + categoryName.replace(/[^a-zA-Z0-9]/g, '_') + '-main');
                    if (mainCheckbox) {
                        mainCheckbox.checked = false;
                        mainCheckbox.indeterminate = false;
                    }
                }
            });
            updateCounts();
        }

        function updateCounts() {
            let checkboxes = document.querySelectorAll('.newspaper-checkbox:checked');
            document.getElementById('selectedCount').textContent = checkboxes.length;
        }

        function validateDistribution() {
            let checkboxes = document.querySelectorAll('.newspaper-checkbox:checked');
            let individualName = document.getElementById('individual_name').value.trim();
            let distributedBy = document.getElementById('distributed_by').value.trim();

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

            return confirm(`Distribute 1 copy each to ${individualName}?`);
        }

        // ========== MODAL CLICK HANDLERS ==========
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const updateModal = document.getElementById('updateModal');
            const distributeModal = document.getElementById('distributeModal');
            const availableModal = document.getElementById('availableModal');
            const addCategoryModal = document.getElementById('addCategoryModal');

            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == updateModal) {
                closeUpdateModal();
            }
            if (event.target == distributeModal) {
                closeDistributeModal();
            }
            if (event.target == availableModal) {
                closeAvailableModal();
            }
            if (event.target == addCategoryModal) {
                closeAddCategoryModal();
            }
        }

        // ========== KEYBOARD SHORTCUTS ==========
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeUpdateModal();
                closeDistributeModal();
                closeAvailableModal();
                closeAddCategoryModal();
                document.getElementById('quickActionMenu').classList.remove('show');
            }
        });
    </script>
</body>

</html>