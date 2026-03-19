<?php
// document_types.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for messages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once 'config/db.php';

// Create connection with error handling
function getConnection()
{
    global $conn;

    // Check if connection exists from db.php
    if (!isset($conn) || $conn->connect_error) {
        error_log("Database connection not available");
        return null;
    }

    return $conn;
}

// Set flash message
function setFlashMessage($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Get all document types with pagination
function getAllDocumentTypes($sort_by = 'type_name', $sort_order = 'ASC', $filter = '', $limit = 10, $offset = 0)
{
    $conn = getConnection();
    if (!$conn) {
        return ['data' => [], 'total' => 0];
    }

    // Validate sort parameters to prevent SQL injection
    $allowed_sort = ['id', 'type_name', 'description', 'document_count', 'created_at'];
    $sort_by = in_array($sort_by, $allowed_sort) ? $sort_by : 'type_name';
    $sort_order = strtoupper($sort_order) == 'DESC' ? 'DESC' : 'ASC';

    // Get total count for pagination
    $count_sql = "SELECT COUNT(DISTINCT dt.id) as total 
                  FROM document_types dt";

    if (!empty($filter)) {
        $filter_escaped = $conn->real_escape_string($filter);
        $count_sql .= " WHERE dt.type_name LIKE '%$filter_escaped%' OR dt.description LIKE '%$filter_escaped%'";
    }

    $count_result = $conn->query($count_sql);
    $total = $count_result ? $count_result->fetch_assoc()['total'] : 0;

    // Main query with pagination
    $sql = "SELECT dt.*, COUNT(d.id) as document_count 
            FROM document_types dt
            LEFT JOIN documents d ON dt.id = d.type_id";

    if (!empty($filter)) {
        $filter_escaped = $conn->real_escape_string($filter);
        $sql .= " WHERE dt.type_name LIKE '%$filter_escaped%' OR dt.description LIKE '%$filter_escaped%'";
    }

    $sql .= " GROUP BY dt.id 
              ORDER BY $sort_by $sort_order 
              LIMIT $limit OFFSET $offset";

    $result = $conn->query($sql);

    if (!$result) {
        error_log("Error in getAllDocumentTypes: " . $conn->error);
        return ['data' => [], 'total' => 0];
    }

    $types = [];
    while ($row = $result->fetch_assoc()) {
        $types[] = $row;
    }

    return ['data' => $types, 'total' => $total];
}

// Get single document type by ID
function getDocumentTypeById($id)
{
    $conn = getConnection();
    if (!$conn) {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM document_types WHERE id = ?");
    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error);
        return null;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $type = $result->fetch_assoc();
    $stmt->close();

    return $type;
}

// Create new document type
function createDocumentType($type_name, $description = '')
{
    $conn = getConnection();
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection error'];
    }

    // Check if type already exists
    $check = $conn->prepare("SELECT id FROM document_types WHERE type_name = ?");
    if (!$check) {
        return ['success' => false, 'message' => 'Error preparing statement'];
    }

    $check->bind_param("s", $type_name);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        return ['success' => false, 'message' => 'Document type already exists!'];
    }
    $check->close();

    // Insert new type
    $stmt = $conn->prepare("INSERT INTO document_types (type_name, description) VALUES (?, ?)");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error preparing insert statement'];
    }

    $stmt->bind_param("ss", $type_name, $description);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Document type created successfully!'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error: ' . $error];
    }
}

// Update document type
function updateDocumentType($id, $type_name, $description = '')
{
    $conn = getConnection();
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection error'];
    }

    // Check if type name already exists (excluding current ID)
    $check = $conn->prepare("SELECT id FROM document_types WHERE type_name = ? AND id != ?");
    if (!$check) {
        return ['success' => false, 'message' => 'Error preparing check statement'];
    }

    $check->bind_param("si", $type_name, $id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        return ['success' => false, 'message' => 'Document type name already exists!'];
    }
    $check->close();

    // Update type
    $stmt = $conn->prepare("UPDATE document_types SET type_name = ?, description = ? WHERE id = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error preparing update statement'];
    }

    $stmt->bind_param("ssi", $type_name, $description, $id);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Document type updated successfully!'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error: ' . $error];
    }
}

// Delete document type
function deleteDocumentType($id)
{
    $conn = getConnection();
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection error'];
    }

    // Check if type is being used in documents
    $check = $conn->prepare("SELECT COUNT(*) as count FROM documents WHERE type_id = ?");
    if (!$check) {
        return ['success' => false, 'message' => 'Error preparing check statement'];
    }

    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    $check->close();

    if ($row['count'] > 0) {
        return ['success' => false, 'message' => 'Cannot delete: This document type is used by ' . $row['count'] . ' document(s)'];
    }

    // Delete type
    $stmt = $conn->prepare("DELETE FROM document_types WHERE id = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error preparing delete statement'];
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Document type deleted successfully!'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error: ' . $error];
    }
}

// Get documents by type
function getDocumentsByType($type_id)
{
    $conn = getConnection();
    if (!$conn) {
        return [];
    }

    $stmt = $conn->prepare("SELECT * FROM documents WHERE type_id = ? ORDER BY date_received DESC");
    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error);
        return [];
    }

    $stmt->bind_param("i", $type_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();

    return $documents;
}

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if ($_POST['ajax_action'] == 'create') {
        $type_name = trim($_POST['type_name']);
        $description = trim($_POST['description']);

        if (empty($type_name)) {
            echo json_encode(['success' => false, 'message' => 'Document type name is required!']);
        } else {
            $result = createDocumentType($type_name, $description);
            echo json_encode($result);
        }
        exit();
    }

    if ($_POST['ajax_action'] == 'update') {
        $id = $_POST['id'];
        $type_name = trim($_POST['type_name']);
        $description = trim($_POST['description']);

        if (empty($type_name)) {
            echo json_encode(['success' => false, 'message' => 'Document type name is required!']);
        } else {
            $result = updateDocumentType($id, $type_name, $description);
            echo json_encode($result);
        }
        exit();
    }

    if ($_POST['ajax_action'] == 'delete') {
        $id = $_POST['id'];
        $result = deleteDocumentType($id);
        echo json_encode($result);
        exit();
    }

    if ($_POST['ajax_action'] == 'get_type') {
        $id = $_POST['id'];
        $type = getDocumentTypeById($id);
        if ($type) {
            $documents = getDocumentsByType($id);
            echo json_encode(['success' => true, 'type' => $type, 'documents' => $documents]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Document type not found!']);
        }
        exit();
    }
}

// Handle form submissions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    if (isset($_POST['create'])) {
        $type_name = trim($_POST['type_name']);
        $description = trim($_POST['description']);

        if (empty($type_name)) {
            setFlashMessage('danger', 'Document type name is required!');
        } else {
            $result = createDocumentType($type_name, $description);
            setFlashMessage($result['success'] ? 'success' : 'danger', $result['message']);
            if ($result['success']) {
                header('Location: document_types.php?action=list');
                exit();
            }
        }
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $type_name = trim($_POST['type_name']);
        $description = trim($_POST['description']);

        if (empty($type_name)) {
            setFlashMessage('danger', 'Document type name is required!');
        } else {
            $result = updateDocumentType($id, $type_name, $description);
            setFlashMessage($result['success'] ? 'success' : 'danger', $result['message']);
            if ($result['success']) {
                header('Location: document_types.php?action=list');
                exit();
            }
        }
    }
}

// Handle GET requests for delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $result = deleteDocumentType($_GET['id']);
    setFlashMessage($result['success'] ? 'success' : 'danger', $result['message']);
    header('Location: document_types.php?action=list');
    exit();
}

// Get sorting, filtering and pagination parameters
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'type_name';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $limit;

// Get flash message
$flashMessage = '';
$flashType = '';
if (isset($_SESSION['flash'])) {
    $flashMessage = $_SESSION['flash']['message'];
    $flashType = $_SESSION['flash']['type'];
    unset($_SESSION['flash']);
}

// Include sidebar
include './sidebar.php';

// Get all document types for list view
$typesData = ['data' => [], 'total' => 0];
if ($action == 'list') {
    $typesData = getAllDocumentTypes($sort_by, $sort_order, $filter, $limit, $offset);
    $types = $typesData['data'];
    $totalRecords = $typesData['total'];
    $totalPages = ceil($totalRecords / $limit);
} else {
    // For stats view, get all records without pagination
    $stats_types = getAllDocumentTypes('type_name', 'ASC', '', 1000, 0)['data'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Types - Mailroom</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
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

        .action-btn {
            transition: all 0.2s;
        }

        .action-btn:hover {
            background-color: #f0f0f0;
        }

        .modal {
            transition: opacity 0.3s ease;
        }

        .sort-icon {
            font-size: 0.7rem;
            margin-left: 0.25rem;
            opacity: 0.5;
        }

        th.active-sort .sort-icon {
            opacity: 1;
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            gap: 0.25rem;
            margin-top: 1rem;
        }

        .pagination-item {
            padding: 0.5rem 0.75rem;
            border: 1px solid #e5e5e5;
            background-color: white;
            font-size: 0.875rem;
            color: #1e1e1e;
            cursor: pointer;
            transition: all 0.2s;
        }

        .pagination-item:hover {
            background-color: #f5f5f4;
        }

        .pagination-item.active {
            background-color: #1e1e1e;
            color: white;
            border-color: #1e1e1e;
        }

        .pagination-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .items-per-page {
            padding: 0.5rem;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        /* Toastify customization */
        .toastify {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 12px 20px;
            color: white;
            display: inline-block;
            box-shadow: 0 3px 6px -1px rgba(0, 0, 0, 0.12), 0 10px 36px -4px rgba(77, 96, 232, 0.3);
            border-radius: 4px;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <main class="ml-60 min-h-screen">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-medium text-[#1e1e1e]">Document Types</h1>
                <div class="flex gap-2">
                    <button onclick="openCreateModal()" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-regular fa-plus mr-1 text-[#6e6e6e]"></i>New Type
                    </button>
                    <button onclick="exportToCSV()" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-regular fa-file-excel mr-1 text-[#6e6e6e]"></i>Export
                    </button>
                    <button onclick="printTable()" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-solid fa-print mr-1 text-[#6e6e6e]"></i>Print
                    </button>
                    <a href="?action=stats" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-regular fa-chart-bar mr-1 text-[#6e6e6e]"></i>Stats
                    </a>
                </div>
            </div>
        </div>

        <div class="p-8">
            <!-- Flash Message (will be converted to toast) -->
            <?php if ($flashMessage): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showToast('<?php echo $flashMessage; ?>', '<?php echo $flashType; ?>');
                    });
                </script>
            <?php endif; ?>

            <?php
            // Check database connection
            $conn_check = getConnection();
            if (!$conn_check):
            ?>
                <div class="mb-6 p-3 border border-[#e5e5e5] bg-white rounded-md text-sm text-[#1e1e1e]">
                    <i class="fa-regular fa-circle-exclamation mr-2 text-[#4a4a4a]"></i>
                    Database connection error. Please check your configuration.
                </div>
            <?php
            endif;

            // LIST ALL DOCUMENT TYPES
            if ($action == 'list'):
            ?>
                <!-- Search and Filter Bar -->
                <div class="bg-white border border-[#e5e5e5] rounded-md p-4 mb-6">
                    <div class="flex flex-wrap gap-3">
                        <div class="flex-1 min-w-[300px]">
                            <div class="relative">
                                <input type="text" id="searchInput" placeholder="Search by name or description..."
                                    value="<?php echo htmlspecialchars($filter); ?>"
                                    class="w-full px-3 py-2 pl-10 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                                    autocomplete="off">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-[#9e9e9e] text-sm"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <select id="itemsPerPage" class="items-per-page" onchange="changeItemsPerPage()">
                                <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5 per page</option>
                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 per page</option>
                                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25 per page</option>
                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 per page</option>
                                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 per page</option>
                            </select>
                            <button onclick="applyFilter()" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                                Search
                            </button>
                            <button onclick="clearFilter()" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                    <table id="typesTable">
                        <thead>
                            <tr class="bg-[#fafafa]">
                                <th onclick="sortTable('id')" class="<?php echo $sort_by == 'id' ? 'active-sort' : ''; ?>">
                                    ID
                                    <?php if ($sort_by == 'id'): ?>
                                        <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </th>
                                <th onclick="sortTable('type_name')" class="<?php echo $sort_by == 'type_name' ? 'active-sort' : ''; ?>">
                                    Type Name
                                    <?php if ($sort_by == 'type_name'): ?>
                                        <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </th>
                                <th onclick="sortTable('description')" class="<?php echo $sort_by == 'description' ? 'active-sort' : ''; ?>">
                                    Description
                                    <?php if ($sort_by == 'description'): ?>
                                        <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </th>
                                <th onclick="sortTable('document_count')" class="<?php echo $sort_by == 'document_count' ? 'active-sort' : ''; ?>">
                                    Documents
                                    <?php if ($sort_by == 'document_count'): ?>
                                        <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </th>
                                <th onclick="sortTable('created_at')" class="<?php echo $sort_by == 'created_at' ? 'active-sort' : ''; ?>">
                                    Created
                                    <?php if ($sort_by == 'created_at'): ?>
                                        <i class="fa-solid fa-chevron-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                    <?php endif; ?>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (empty($types)): ?>
                                <tr>
                                    <td colspan="6" class="text-sm text-[#6e6e6e] text-center py-8">
                                        No document types found.
                                        <button onclick="openCreateModal()" class="text-[#1e1e1e] underline">Create one</button>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($types as $type): ?>
                                    <tr class="hover:bg-[#fafafa]" id="row-<?php echo $type['id']; ?>">
                                        <td class="text-sm text-[#6e6e6e]"><?php echo $type['id']; ?></td>
                                        <td class="text-sm font-medium text-[#1e1e1e]"><?php echo htmlspecialchars($type['type_name']); ?></td>
                                        <td class="text-sm text-[#1e1e1e]"><?php echo htmlspecialchars($type['description'] ?? '-'); ?></td>
                                        <td class="text-sm text-[#1e1e1e]"><?php echo $type['document_count']; ?></td>
                                        <td class="text-sm text-[#1e1e1e]"><?php echo date('M j, Y', strtotime($type['created_at'])); ?></td>
                                        <td class="text-sm">
                                            <div class="flex gap-2">
                                                <!-- View Action -->
                                                <button onclick="viewType(<?php echo $type['id']; ?>)" class="text-[#9e9e9e] hover:text-[#1e1e1e] action-btn p-1" title="View Details">
                                                    <i class="fa-regular fa-eye"></i>
                                                </button>
                                                <!-- Edit Action -->
                                                <button onclick="editType(<?php echo $type['id']; ?>)" class="text-[#9e9e9e] hover:text-[#1e1e1e] action-btn p-1" title="Edit">
                                                    <i class="fa-regular fa-pen-to-square"></i>
                                                </button>
                                                <!-- Delete Action -->
                                                <button onclick="confirmDelete(<?php echo $type['id']; ?>, '<?php echo htmlspecialchars($type['type_name']); ?>')" class="text-[#9e9e9e] hover:text-[#1e1e1e] action-btn p-1" title="Delete">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 0): ?>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                        <div class="text-sm text-[#6e6e6e]">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> records
                        </div>

                        <div class="pagination">
                            <!-- First Page -->
                            <button onclick="goToPage(1)" class="pagination-item <?php echo $page <= 1 ? 'disabled' : ''; ?>" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                                <i class="fa-solid fa-chevrons-left"></i>
                            </button>

                            <!-- Previous Page -->
                            <button onclick="goToPage(<?php echo $page - 1; ?>)" class="pagination-item <?php echo $page <= 1 ? 'disabled' : ''; ?>" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>

                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            if ($startPage > 1) {
                                echo '<button onclick="goToPage(1)" class="pagination-item">1</button>';
                                if ($startPage > 2) {
                                    echo '<span class="pagination-item disabled">...</span>';
                                }
                            }

                            for ($i = $startPage; $i <= $endPage; $i++) {
                                $activeClass = $i == $page ? 'active' : '';
                                echo '<button onclick="goToPage(' . $i . ')" class="pagination-item ' . $activeClass . '">' . $i . '</button>';
                            }

                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<span class="pagination-item disabled">...</span>';
                                }
                                echo '<button onclick="goToPage(' . $totalPages . ')" class="pagination-item">' . $totalPages . '</button>';
                            }
                            ?>

                            <!-- Next Page -->
                            <button onclick="goToPage(<?php echo $page + 1; ?>)" class="pagination-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>

                            <!-- Last Page -->
                            <button onclick="goToPage(<?php echo $totalPages; ?>)" class="pagination-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                                <i class="fa-solid fa-chevrons-right"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

            <?php
            // STATISTICS
            elseif ($action == 'stats'):
                $totalTypes = count($stats_types);
                $totalDocuments = 0;
                $usedTypes = 0;
                $unusedTypes = 0;

                foreach ($stats_types as $type) {
                    $totalDocuments += $type['document_count'];
                    if ($type['document_count'] > 0) {
                        $usedTypes++;
                    } else {
                        $unusedTypes++;
                    }
                }

                $avgPerType = $totalTypes > 0 ? round($totalDocuments / $totalTypes, 1) : 0;
            ?>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Total Types</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $totalTypes; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Total Documents</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $totalDocuments; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Avg per Type</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $avgPerType; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Types in Use</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $usedTypes; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Unused Types</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $unusedTypes; ?></p>
                    </div>
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                    <table>
                        <thead>
                            <tr class="bg-[#fafafa]">
                                <th class="text-xs">Type Name</th>
                                <th class="text-xs">Document Count</th>
                                <th class="text-xs">Percentage</th>
                                <th class="text-xs">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats_types as $type): ?>
                                <tr class="hover:bg-[#fafafa]">
                                    <td class="text-sm text-[#1e1e1e]"><?php echo htmlspecialchars($type['type_name']); ?></td>
                                    <td class="text-sm text-[#1e1e1e]"><?php echo $type['document_count']; ?></td>
                                    <td class="text-sm text-[#1e1e1e]">
                                        <?php
                                        $percentage = $totalDocuments > 0 ? round(($type['document_count'] / $totalDocuments) * 100, 1) : 0;
                                        echo $percentage . '%';
                                        ?>
                                    </td>
                                    <td class="text-sm">
                                        <?php if ($type['document_count'] > 0): ?>
                                            <span class="text-[#1e1e1e]">In Use</span>
                                        <?php else: ?>
                                            <span class="text-[#9e9e9e]">Unused</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <a href="?action=list" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-solid fa-arrow-left mr-1"></i>Back to List
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Create/Edit Modal -->
    <div id="typeModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <h3 id="modalTitle" class="text-base font-medium text-[#1e1e1e] mb-4">Create Document Type</h3>
            <form id="typeForm" onsubmit="return false;">
                <input type="hidden" id="typeId" name="id">

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Type Name *</label>
                    <input type="text" id="typeName" name="type_name" required
                        placeholder="e.g., Legislative Documents"
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                        autocomplete="off">
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Description</label>
                    <textarea id="typeDescription" name="description" rows="4"
                        placeholder="Optional description"
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                        autocomplete="off"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="button" onclick="saveType()" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-solid fa-floppy-disk mr-1 text-[#6e6e6e]"></i>
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-2xl p-5">
            <h3 class="text-base font-medium text-[#1e1e1e] mb-4">Document Type Details</h3>

            <div id="viewContent" class="space-y-4">
                <!-- Filled by JavaScript -->
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button onclick="closeViewModal()" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <h3 class="text-base font-medium text-[#1e1e1e] mb-2">Confirm Delete</h3>
            <p class="text-sm text-[#6e6e6e] mb-4">Are you sure you want to delete <span id="deleteTypeName" class="font-medium text-[#1e1e1e]"></span>?</p>
            <p class="text-xs text-[#9e9e9e] mb-6">This action cannot be undone.</p>

            <div class="flex justify-end gap-2">
                <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    Cancel
                </button>
                <button onclick="deleteType()" id="confirmDeleteBtn" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Current delete ID
        let currentDeleteId = null;
        let currentEditId = null;

        // Toast notification function
        function showToast(message, type = 'success') {
            const backgroundColor = type === 'success' ? '#10b981' : '#ef4444';

            Toastify({
                text: message,
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: backgroundColor,
                stopOnFocus: true,
                className: "toastify"
            }).showToast();
        }

        // Modal functions
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create Document Type';
            document.getElementById('typeId').value = '';
            document.getElementById('typeName').value = '';
            document.getElementById('typeDescription').value = '';
            document.getElementById('typeModal').style.display = 'flex';
        }

        function editType(id) {
            currentEditId = id;

            // Fetch type data
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax_action=get_type&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitle').textContent = 'Edit Document Type';
                        document.getElementById('typeId').value = data.type.id;
                        document.getElementById('typeName').value = data.type.type_name;
                        document.getElementById('typeDescription').value = data.type.description || '';
                        document.getElementById('typeModal').style.display = 'flex';
                    } else {
                        showToast(data.message, 'danger');
                    }
                });
        }

        function viewType(id) {
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax_action=get_type&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const type = data.type;
                        const documents = data.documents;

                        let documentsHtml = '';
                        if (documents.length > 0) {
                            documentsHtml = documents.map(doc => `
                            <div class="p-2 border border-[#e5e5e5] rounded-md">
                                <p class="text-sm font-medium">${escapeHtml(doc.document_name)}</p>
                                <p class="text-xs text-[#6e6e6e]">Origin: ${escapeHtml(doc.origin)} • Copies: ${doc.copies_received}</p>
                            </div>
                        `).join('');
                        } else {
                            documentsHtml = '<p class="text-sm text-[#6e6e6e]">No documents of this type.</p>';
                        }

                        document.getElementById('viewContent').innerHTML = `
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase">ID</p>
                                <p class="text-sm">${type.id}</p>
                            </div>
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase">Name</p>
                                <p class="text-sm font-medium">${escapeHtml(type.type_name)}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-xs text-[#6e6e6e] uppercase">Description</p>
                                <p class="text-sm">${escapeHtml(type.description || 'No description')}</p>
                            </div>
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase">Created</p>
                                <p class="text-sm">${new Date(type.created_at).toLocaleDateString()}</p>
                            </div>
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase">Documents</p>
                                <p class="text-sm">${documents.length}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-xs text-[#6e6e6e] uppercase mb-2">Documents of this Type</p>
                                <div class="space-y-2 max-h-60 overflow-y-auto">
                                    ${documentsHtml}
                                </div>
                            </div>
                        </div>
                    `;
                        document.getElementById('viewModal').style.display = 'flex';
                    } else {
                        showToast(data.message, 'danger');
                    }
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function closeModal() {
            document.getElementById('typeModal').style.display = 'none';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            currentDeleteId = null;
        }

        function saveType() {
            const id = document.getElementById('typeId').value;
            const typeName = document.getElementById('typeName').value;
            const description = document.getElementById('typeDescription').value;

            if (!typeName.trim()) {
                showToast('Document type name is required!', 'danger');
                return;
            }

            const action = id ? 'update' : 'create';
            const formData = `ajax_action=${action}&type_name=${encodeURIComponent(typeName)}&description=${encodeURIComponent(description)}${id ? '&id=' + id : ''}`;

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        closeModal();
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showToast(data.message, 'danger');
                    }
                });
        }

        function confirmDelete(id, name) {
            currentDeleteId = id;
            document.getElementById('deleteTypeName').textContent = name;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function deleteType() {
            if (!currentDeleteId) return;

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax_action=delete&id=' + currentDeleteId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        closeDeleteModal();
                        // Remove row from table
                        const row = document.getElementById('row-' + currentDeleteId);
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                // Check if table is empty
                                if (document.querySelectorAll('#tableBody tr').length === 0) {
                                    location.reload();
                                }
                            }, 300);
                        } else {
                            location.reload();
                        }
                    } else {
                        showToast(data.message, 'danger');
                        closeDeleteModal();
                    }
                });
        }

        // Sorting
        function sortTable(column) {
            const currentSort = '<?php echo $sort_by; ?>';
            const currentOrder = '<?php echo $sort_order; ?>';
            const filter = '<?php echo $filter; ?>';
            const limit = '<?php echo $limit; ?>';

            let newOrder = 'ASC';
            if (column === currentSort) {
                newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
            }

            window.location.href = `?action=list&sort_by=${column}&sort_order=${newOrder}&filter=${encodeURIComponent(filter)}&limit=${limit}`;
        }

        // Filtering
        function applyFilter() {
            const filter = document.getElementById('searchInput').value;
            const limit = document.getElementById('itemsPerPage').value;
            window.location.href = `?action=list&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>&filter=${encodeURIComponent(filter)}&limit=${limit}`;
        }

        function clearFilter() {
            document.getElementById('searchInput').value = '';
            const limit = document.getElementById('itemsPerPage').value;
            window.location.href = `?action=list&limit=${limit}`;
        }

        // Pagination
        function goToPage(page) {
            const filter = '<?php echo $filter; ?>';
            const sortBy = '<?php echo $sort_by; ?>';
            const sortOrder = '<?php echo $sort_order; ?>';
            const limit = '<?php echo $limit; ?>';

            window.location.href = `?action=list&page=${page}&sort_by=${sortBy}&sort_order=${sortOrder}&filter=${encodeURIComponent(filter)}&limit=${limit}`;
        }

        function changeItemsPerPage() {
            const limit = document.getElementById('itemsPerPage').value;
            const filter = document.getElementById('searchInput').value;
            window.location.href = `?action=list&filter=${encodeURIComponent(filter)}&limit=${limit}`;
        }

        // Export to CSV
        function exportToCSV() {
            const rows = [];
            const headers = ['ID', 'Type Name', 'Description', 'Document Count', 'Created At'];
            rows.push(headers.join(','));

            <?php foreach ($types as $type): ?>
                rows.push([
                    '<?php echo $type['id']; ?>',
                    '"<?php echo htmlspecialchars($type['type_name']); ?>"',
                    '"<?php echo htmlspecialchars($type['description'] ?? ''); ?>"',
                    '<?php echo $type['document_count']; ?>',
                    '"<?php echo $type['created_at']; ?>"'
                ].join(','));
            <?php endforeach; ?>

            const csv = rows.join('\n');
            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `document_types_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();

            showToast('Export completed successfully!', 'success');
        }

        // Print table
        function printTable() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Document Types</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { font-size: 24px; margin-bottom: 20px; }
                        table { border-collapse: collapse; width: 100%; }
                        th { background-color: #f2f2f2; text-align: left; padding: 12px; font-size: 12px; }
                        td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 14px; }
                        .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
                        .date { color: #666; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Document Types</h1>
                        <div class="date">Generated: ${new Date().toLocaleString()}</div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type Name</th>
                                <th>Description</th>
                                <th>Documents</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($types as $type): ?>
                            <tr>
                                <td><?php echo $type['id']; ?></td>
                                <td><?php echo htmlspecialchars($type['type_name']); ?></td>
                                <td><?php echo htmlspecialchars($type['description'] ?? '-'); ?></td>
                                <td><?php echo $type['document_count']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($type['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();

            showToast('Print dialog opened', 'success');
        }

        // Enter key for search
        document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilter();
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const typeModal = document.getElementById('typeModal');
            const viewModal = document.getElementById('viewModal');
            const deleteModal = document.getElementById('deleteModal');

            if (event.target == typeModal) {
                closeModal();
            }
            if (event.target == viewModal) {
                closeViewModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        // ESC key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
                closeViewModal();
                closeDeleteModal();
            }
        });
    </script>
</body>

</html>