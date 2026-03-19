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

// ========== GET DATA FOR DISPLAY ==========

// Get all categories
$categories_query = $conn->query("SELECT * FROM newspaper_categories ORDER BY category_name");
$all_categories = [];
while ($cat = $categories_query->fetch_assoc()) {
    $all_categories[] = $cat;
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

// Get statistics
$total_available = $conn->query("SELECT SUM(available_copies) as total FROM newspapers")->fetch_assoc()['total'] ?? 0;
$total_newspapers = $conn->query("SELECT COUNT(*) as count FROM newspapers")->fetch_assoc()['count'] ?? 0;
$total_categories = $conn->query("SELECT COUNT(*) as count FROM newspaper_categories")->fetch_assoc()['count'] ?? 0;

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

        /* Modal styles */
        .modal {
            transition: opacity 0.3s ease;
        }

        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
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
                            <i class="fa-solid fa-bolt"></i>
                            <span>Quick Actions</span>
                            <i class="fa-solid fa-chevron-down text-sm"></i>
                        </button>

                        <!-- Quick Action Menu -->
                        <div id="quickActionMenu" class="quick-action-menu">
                            <div class="quick-action-item" onclick="openAddModal()">
                                <i class="fa-regular fa-plus"></i>
                                <span>Add Newspaper</span>
                            </div>
                            <div class="quick-action-item" onclick="openAddCategoryModal()">
                                <i class="fa-solid fa-tags"></i>
                                <span>Add Category</span>
                            </div>
                            
                            <div class="quick-action-item" onclick="window.location.href='available.php'">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                                <span>View Available</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-3 mb-6">
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Available Copies</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_available; ?></p>
                        <!--<a href="available.php" class="text-xs text-blue-600 hover:underline mt-2 inline-block">View details</a>-->
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Total Newspapers</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_newspapers; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Categories</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $total_categories; ?></p>
                    </div>
                </div>

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
                                    autocomplete="off"
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
                                <i class="fa-solid fa-sliders mr-1"></i>Apply
                            </button>

                            <a href="list.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4]">
                                <i class="fa-solid fa-rotate-right mr-1"></i>Reset
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
                                            <i class="fa-solid fa-xmark"></i>
                                        </a>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($search)): ?>
                                    <span class="filter-badge">
                                        Search: "<?php echo htmlspecialchars($search); ?>"
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])); ?>" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                            <i class="fa-solid fa-xmark"></i>
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
                                        <tr class="hover:bg-[#fafafa]" id="newspaper-row-<?php echo $paper['id']; ?>">
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
                                                    <button onclick="viewNewspaper(<?php echo htmlspecialchars(json_encode($paper)); ?>)"
                                                        class="action-btn" title="View Details">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </button>
                                                    <button onclick="openUpdateModal(<?php echo $paper['id']; ?>, '<?php echo htmlspecialchars($paper['newspaper_name']); ?>', <?php echo $paper['available_copies']; ?>)"
                                                        class="action-btn" title="Edit">
                                                        <i class="fa-regular fa-pen-to-square"></i>
                                                    </button>
                                                    <button onclick="openDeleteModal(<?php echo $paper['id']; ?>, '<?php echo htmlspecialchars($paper['newspaper_name']); ?>', '<?php echo htmlspecialchars($paper['newspaper_number']); ?>')"
                                                        class="action-btn delete-btn" title="Delete">
                                                        <i class="fa-regular fa-trash-can"></i>
                                                    </button>
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
            </div>
        </main>
    </div>

    <!-- Add Newspaper Modal -->
    <div id="addModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Add Newspaper</h3>
                <button type="button" onclick="closeAddModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark"></i>
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
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                                autocomplete='off'>
                        </div>
                        <div>
                            <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Copies</label>
                            <input type="number" name="copies_received" min="1" required
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                                placeholder="e.g., 5"
                                autocomplete='off'>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Received By</label>
                        <input type="text" name="received_by" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="Staff name" 
                            autocomplete='off'>
                    </div>

                    <div class="text-xs text-[#6e6e6e] bg-[#fafafa] p-2 rounded-md">
                        <i class="fa-solid fa-circle-info mr-1"></i>
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
                    <i class="fa-solid fa-xmark"></i>
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
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="list.php">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Category Name</label>
                        <input type="text" name="category_name" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="e.g., Daily News, Sports, Business"
                            autocomplete='off'>
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

    <!-- View Newspaper Modal -->
    <div id="viewModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-lg p-6 modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-[#1e1e1e]">Newspaper Details</h2>
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
                <p class="text-sm text-[#6e6e6e]">Are you sure you want to delete this newspaper?</p>
                <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                    <p class="text-sm font-medium text-red-800" id="deleteNewspaperName"></p>
                    <p class="text-xs text-red-600 mt-1" id="deleteIssueNumber"></p>
                </div>
                <p class="text-xs text-[#9e9e9e] mt-3">
                    <i class="fa-regular fa-circle-info mr-1"></i>
                    This action cannot be undone. The newspaper will be permanently deleted.
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

        // ========== CATEGORY MODAL FUNCTIONS ==========
        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'flex';
            document.getElementById('quickActionMenu').classList.remove('show');
        }

        function closeAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'none';
        }

        // ========== VIEW MODAL FUNCTIONS ==========
        function viewNewspaper(paper) {
            const content = document.getElementById('viewContent');

            // Determine status display
            let statusClass = '';
            let statusText = '';

            if (paper.status == 'available') {
                statusClass = 'bg-green-100 text-green-800';
                statusText = 'Available';
            } else if (paper.status == 'partial') {
                statusClass = 'bg-yellow-100 text-yellow-800';
                statusText = 'Partial';
            } else {
                statusClass = 'bg-red-100 text-red-800';
                statusText = 'Distributed';
            }

            content.innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Newspaper</p>
                        <p class="text-lg font-medium text-[#1e1e1e]">${escapeHtml(paper.newspaper_name)}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Issue Number</p>
                        <p class="text-sm font-mono text-[#1e1e1e]">${escapeHtml(paper.newspaper_number)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Category</p>
                        <p class="text-sm">${escapeHtml(paper.category_name || 'Uncategorized')}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Status</p>
                        <span class="inline-block px-2 py-1 text-xs rounded-md ${statusClass}">${statusText}</span>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Date Received</p>
                        <p class="text-sm">${new Date(paper.date_received).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Received By</p>
                        <p class="text-sm">${escapeHtml(paper.received_by)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6e6e6e] uppercase mb-1">Total Copies</p>
                        <p class="text-sm">${paper.available_copies}</p>
                    </div>
                </div>
            `;
            document.getElementById('viewModal').style.display = 'flex';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        // ========== DELETE MODAL FUNCTIONS ==========
        let currentDeleteId = null;

        function openDeleteModal(id, name, issueNumber) {
            currentDeleteId = id;
            document.getElementById('deleteNewspaperName').textContent = name;
            document.getElementById('deleteIssueNumber').textContent = 'Issue #: ' + issueNumber;

            // Build the delete URL with current query parameters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('delete', id);
            document.getElementById('confirmDeleteBtn').href = '?' + urlParams.toString();

            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            currentDeleteId = null;
        }

        // ========== ESCAPE HTML ==========
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ========== MODAL CLICK HANDLERS ==========
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const updateModal = document.getElementById('updateModal');
            const addCategoryModal = document.getElementById('addCategoryModal');
            const viewModal = document.getElementById('viewModal');
            const deleteModal = document.getElementById('deleteModal');

            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == updateModal) {
                closeUpdateModal();
            }
            if (event.target == addCategoryModal) {
                closeAddCategoryModal();
            }
            if (event.target == viewModal) {
                closeViewModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        // ========== KEYBOARD SHORTCUTS ==========
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeUpdateModal();
                closeAddCategoryModal();
                closeViewModal();
                closeDeleteModal();
                document.getElementById('quickActionMenu').classList.remove('show');
            }
        });
    </script>
</body>

</html>