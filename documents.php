<?php
// documents/list.php
require_once './config/db.php';
session_start();

$message = '';
$error = '';

// Get document types for dropdown
$types_result = $conn->query("SELECT id, type_name FROM document_types ORDER BY type_name");
$document_types = [];
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $document_types[] = $row;
    }
}

// Function to generate serial number
function generateSerialNumber($conn)
{
    // Get the last serial number
    $result = $conn->query("SELECT serial_number FROM documents ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $last_serial = $result->fetch_assoc()['serial_number'];
        // Extract the number part and increment
        if (preg_match('/DOC-(\d+)/', $last_serial, $matches)) {
            $num = intval($matches[1]) + 1;
            return 'DOC-' . str_pad($num, 6, '0', STR_PAD_LEFT);
        }
    }
    // Default starting serial
    return 'DOC-000001';
}

// Handle Add Document
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_submit'])) {
    $document_name = $_POST['document_name'];
    $type_id = !empty($_POST['type_id']) ? (int)$_POST['type_id'] : null;
    $origin = $_POST['origin'];
    $copies_received = (int)$_POST['copies_received'];
    $date_received = $_POST['date_received'];

    // Generate serial number
    $serial_number = generateSerialNumber($conn);

    // If type_id is provided, get the type name for backward compatibility
    $type_name = '';
    if ($type_id) {
        $type_query = $conn->prepare("SELECT type_name FROM document_types WHERE id = ?");
        $type_query->bind_param("i", $type_id);
        $type_query->execute();
        $type_result = $type_query->get_result();
        if ($type_row = $type_result->fetch_assoc()) {
            $type_name = $type_row['type_name'];
        }
        $type_query->close();
    }

    $stmt = $conn->prepare("INSERT INTO documents (document_name, type, type_id, origin, copies_received, date_received, serial_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissss", $document_name, $type_name, $type_id, $origin, $copies_received, $date_received, $serial_number);

    if ($stmt->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Document added successfully! Serial: ' . $serial_number];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error: ' . $conn->error];
    }
    $stmt->close();
    header('Location: list.php');
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Document deleted successfully!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error: ' . $conn->error];
    }
    $stmt->close();
    header('Location: list.php');
    exit();
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_submit'])) {
    $id = (int)$_POST['document_id'];
    $document_name = $_POST['document_name'];
    $type_id = !empty($_POST['type_id']) ? (int)$_POST['type_id'] : null;
    $origin = $_POST['origin'];
    $copies_received = (int)$_POST['copies_received'];
    $date_received = $_POST['date_received'];
    $serial_number = $_POST['serial_number']; // Keep existing serial number

    // If type_id is provided, get the type name for backward compatibility
    $type_name = '';
    if ($type_id) {
        $type_query = $conn->prepare("SELECT type_name FROM document_types WHERE id = ?");
        $type_query->bind_param("i", $type_id);
        $type_query->execute();
        $type_result = $type_query->get_result();
        if ($type_row = $type_result->fetch_assoc()) {
            $type_name = $type_row['type_name'];
        }
        $type_query->close();
    }

    $stmt = $conn->prepare("UPDATE documents SET document_name = ?, type = ?, type_id = ?, origin = ?, copies_received = ?, date_received = ? WHERE id = ?");
    $stmt->bind_param("ssisssi", $document_name, $type_name, $type_id, $origin, $copies_received, $date_received, $id);

    if ($stmt->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Document updated successfully!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error: ' . $conn->error];
    }
    $stmt->close();
    header('Location: list.php');
    exit();
}

// Get statistics
$total_result = $conn->query("SELECT COUNT(*) as count FROM documents");
$total = $total_result ? $total_result->fetch_assoc()['count'] : 0;

$month_result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE MONTH(date_received) = MONTH(CURDATE()) AND YEAR(date_received) = YEAR(CURDATE())");
$month = $month_result ? $month_result->fetch_assoc()['count'] : 0;

$today_result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE date_received = CURDATE()");
$today = $today_result ? $today_result->fetch_assoc()['count'] : 0;

// Get unique types for filter (from document_types table)
$types_result = $conn->query("SELECT id, type_name FROM document_types ORDER BY type_name");
$filter_types = [];
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $filter_types[] = $row;
    }
}

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$type_filter = isset($_GET['type_filter']) ? $_GET['type_filter'] : '';

// Build query for counting total records
$count_query = "SELECT COUNT(*) as total FROM documents d LEFT JOIN document_types dt ON d.type_id = dt.id WHERE 1=1";
$count_params = [];
$count_types = "";

if (!empty($search)) {
    $count_query .= " AND (d.document_name LIKE ? OR d.origin LIKE ? OR dt.type_name LIKE ? OR d.serial_number LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_types .= "ssss";
}

if (!empty($type_filter) && $type_filter !== 'all') {
    $count_query .= " AND LOWER(dt.type_name) = LOWER(?)";
    $count_params[] = $type_filter;
    $count_types .= "s";
}

$count_stmt = $conn->prepare($count_query);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get documents with pagination
$query = "
    SELECT d.*, dt.type_name as document_type_name 
    FROM documents d 
    LEFT JOIN document_types dt ON d.type_id = dt.id 
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (d.document_name LIKE ? OR d.origin LIKE ? OR dt.type_name LIKE ? OR d.serial_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($type_filter) && $type_filter !== 'all') {
    $query .= " AND LOWER(dt.type_name) = LOWER(?)";
    $params[] = $type_filter;
    $types .= "s";
}

$query .= " ORDER BY d.date_received DESC, d.id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$documents = $stmt->get_result();

// Get all documents for JavaScript (limited to recent 100 to avoid huge data)
$docs_for_js = [];
$docs_result = $conn->query("
    SELECT d.*, dt.type_name as document_type_name
    FROM documents d 
    LEFT JOIN document_types dt ON d.type_id = dt.id 
    ORDER BY d.date_received DESC LIMIT 100
");
if ($docs_result) {
    while ($row = $docs_result->fetch_assoc()) {
        $docs_for_js[] = $row;
    }
}

// Get toast message from session
$toast = null;
if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - Mailroom</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
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
            border-radius: 0.25rem;
        }

        .pagination-item:hover:not(.disabled):not(.active) {
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
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <div class="flex">
        <!-- Sidebar -->
        <?php include './sidebar.php'; ?>

        <main class="flex-1 ml-60 min-h-screen">
            <!-- Header -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-medium text-[#1e1e1e]">Documents</h1>
                    <div class="flex gap-2">
                        <button onclick="exportToCSV()" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-regular fa-file-excel mr-1 text-[#6e6e6e]"></i>Export
                        </button>
                        <button onclick="printTable()" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-print mr-1 text-[#6e6e6e]"></i>Print
                        </button>
                        <a href="document_types.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-tags mr-1 text-[#6e6e6e]"></i>Manage Types
                        </a>
                        <button onclick="openAddModal()" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-plus mr-1 text-[#6e6e6e]"></i>Add Document
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Total Documents</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $total; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">This Month</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $month; ?></p>
                    </div>
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Today</p>
                        <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo $today; ?></p>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="bg-white border border-[#e5e5e5] rounded-md p-4 mb-6">
                    <form method="GET" class="flex flex-col md:flex-row gap-3" id="filterForm">
                        <div class="flex-1">
                            <input type="text" name="search" id="searchInput"
                                placeholder="Search by serial, name, origin, or type..."
                                value="<?php echo htmlspecialchars($search); ?>"
                                autocomplete="off"
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                        </div>
                        <button type="submit" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-magnifying-glass mr-1 text-[#6e6e6e]"></i>Search
                        </button>
                        <select name="type_filter" id="typeFilter" class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                            <?php foreach ($filter_types as $type): ?>
                                <option value="<?php echo htmlspecialchars(strtolower($type['type_name'])); ?>"
                                    <?php echo strtolower($type_filter) == strtolower($type['type_name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="limit" id="itemsPerPage" class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5 per page</option>
                            <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 per page</option>
                            <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25 per page</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 per page</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 per page</option>
                        </select>

                        <a href="list.php" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-solid fa-rotate-right mr-1 text-[#6e6e6e]"></i>Clear
                        </a>
                    </form>
                </div>

                <!-- Documents Table -->
                <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr class="bg-[#fafafa]">
                                    <th class="text-xs">S/N</th>
                                    <th class="text-xs">Serial Number</th>
                                    <th class="text-xs">Document Name</th>
                                    <th class="text-xs">Type</th>
                                    <th class="text-xs">Origin</th>
                                    <th class="text-xs">Copies</th>
                                    <th class="text-xs">Date Received</th>
                                    <th class="text-xs">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if ($documents && $documents->num_rows > 0): ?>
                                    <?php
                                    $counter = $offset + 1;
                                    while ($row = $documents->fetch_assoc()):
                                    ?>
                                        <tr class="hover:bg-[#fafafa] document-row"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-type="<?php echo htmlspecialchars(strtolower($row['type'] ?? '')); ?>"
                                            data-type-id="<?php echo $row['type_id'] ?? ''; ?>">
                                            <td class="text-sm text-[#6e6e6e]"><?php echo $counter++; ?></td>
                                            <td class="text-sm font-mono text-[#1e1e1e] font-medium"><?php echo htmlspecialchars($row['serial_number'] ?? 'DOC-000001'); ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo htmlspecialchars($row['document_name'] ?? ''); ?></td>
                                            <td class="text-sm text-[#1e1e1e]">
                                                <?php if ($row['type_id']): ?>
                                                    <a href="document_types.php?action=view&id=<?php echo $row['type_id']; ?>"
                                                        class="hover:underline">
                                                        <?php echo htmlspecialchars($row['type'] ?? ''); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($row['type'] ?? '-'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo htmlspecialchars($row['origin'] ?? ''); ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo $row['copies_received'] ?? 0; ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo $row['date_received'] ? date('M j, Y', strtotime($row['date_received'])) : ''; ?></td>
                                            <td class="text-sm">
                                                <div class="flex gap-2">
                                                    <button onclick="viewDocument(<?php echo $row['id']; ?>)"
                                                        class="text-[#9e9e9e] hover:text-[#1e1e1e]" title="View">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </button>
                                                    <button onclick="openEditModal(<?php echo $row['id']; ?>)"
                                                        class="text-[#9e9e9e] hover:text-[#1e1e1e]" title="Edit">
                                                        <i class="fa-regular fa-pen-to-square"></i>
                                                    </button>
                                                    <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['document_name']); ?>')"
                                                        class="text-[#9e9e9e] hover:text-[#1e1e1e]" title="Delete">
                                                        <i class="fa-regular fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-sm text-[#6e6e6e] text-center py-8">
                                            No documents found. Add one to get started.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                        <div class="text-sm text-[#6e6e6e]">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> records
                        </div>

                        <div class="pagination">
                            <!-- First Page -->
                            <a href="?page=1&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&type_filter=<?php echo urlencode($type_filter); ?>"
                                class="pagination-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <i class="fa-regular fa-chevrons-left"></i>
                            </a>

                            <!-- Previous Page -->
                            <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&type_filter=<?php echo urlencode($type_filter); ?>"
                                class="pagination-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <i class="fa-regular fa-chevron-left"></i>
                            </a>

                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($total_pages, $page + 2);

                            if ($startPage > 1) {
                                echo '<a href="?page=1&limit=' . $limit . '&search=' . urlencode($search) . '&type_filter=' . urlencode($type_filter) . '" class="pagination-item">1</a>';
                                if ($startPage > 2) {
                                    echo '<span class="pagination-item disabled">...</span>';
                                }
                            }

                            for ($i = $startPage; $i <= $endPage; $i++) {
                                $activeClass = $i == $page ? 'active' : '';
                                echo '<a href="?page=' . $i . '&limit=' . $limit . '&search=' . urlencode($search) . '&type_filter=' . urlencode($type_filter) . '" class="pagination-item ' . $activeClass . '">' . $i . '</a>';
                            }

                            if ($endPage < $total_pages) {
                                if ($endPage < $total_pages - 1) {
                                    echo '<span class="pagination-item disabled">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&limit=' . $limit . '&search=' . urlencode($search) . '&type_filter=' . urlencode($type_filter) . '" class="pagination-item">' . $total_pages . '</a>';
                            }
                            ?>

                            <!-- Next Page -->
                            <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&type_filter=<?php echo urlencode($type_filter); ?>"
                                class="pagination-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>

                            <!-- Last Page -->
                            <a href="?page=<?php echo $total_pages; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&type_filter=<?php echo urlencode($type_filter); ?>"
                                class="pagination-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <i class="fa-solid fa-chevrons-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Add Document</h3>
                <button onclick="closeAddModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Document Name</label>
                        <input type="text" name="document_name" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="Enter document name">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Document Type</label>
                        <select name="type_id" id="add_type_id" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="">Select type</option>
                            <?php foreach ($document_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Origin</label>
                        <input type="text" name="origin" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            placeholder="e.g., Senate, Ministry, Department">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Copies</label>
                            <input type="number" name="copies_received" min="1" required
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                                placeholder="0">
                        </div>
                        <div>
                            <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Date Received</label>
                            <input type="date" name="date_received" required value="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                        </div>
                    </div>

                    <div class="bg-[#fafafa] p-3 rounded-md">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Serial Number</p>
                        <p class="text-sm font-mono text-[#1e1e1e]">Auto-generated on save</p>
                    </div>

                    <div class="pt-2">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide mb-2">Quick Actions</p>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="./document_types.php?action=create" target="_blank"
                                class="text-center px-2 py-1 text-xs border border-[#e5e5e5] rounded-md hover:bg-[#f5f5f4]">
                                + New Type
                            </a>
                            <a href="./document_types.php" target="_blank"
                                class="text-center px-2 py-1 text-xs border border-[#e5e5e5] rounded-md hover:bg-[#f5f5f4]">
                                Manage Types
                            </a>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeAddModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="submit" name="add_submit"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-regular fa-floppy-disk mr-1 text-[#6e6e6e]"></i>
                        Save Document
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Edit Document</h3>
                <button onclick="closeEditModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="document_id" id="edit_id">
                <input type="hidden" name="serial_number" id="edit_serial">
                <div class="space-y-4">
                    <div class="bg-[#fafafa] p-3 rounded-md">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Serial Number</p>
                        <p class="text-sm font-mono text-[#1e1e1e]" id="display_serial"></p>
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Document Name</label>
                        <input type="text" name="document_name" id="edit_name" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Document Type</label>
                        <select name="type_id" id="edit_type_id" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="">Select type</option>
                            <?php foreach ($document_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Origin</label>
                        <input type="text" name="origin" id="edit_origin" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Copies</label>
                            <input type="number" name="copies_received" id="edit_copies" min="1" required
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                        </div>
                        <div>
                            <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Date Received</label>
                            <input type="date" name="date_received" id="edit_date" required
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="submit" name="update_submit"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-regular fa-floppy-disk mr-1 text-[#6e6e6e]"></i>
                        Update Document
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-2xl p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Document Details</h3>
                <button onclick="closeViewModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-regular fa-xmark"></i>
                </button>
            </div>

            <div id="viewContent" class="space-y-4">
                <!-- Content will be filled by JavaScript -->
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
            <p class="text-sm text-[#6e6e6e] mb-4">Are you sure you want to delete <span id="deleteDocName" class="font-medium text-[#1e1e1e]"></span>?</p>
            <p class="text-xs text-[#9e9e9e] mb-6">This action cannot be undone.</p>

            <div class="flex justify-end gap-2">
                <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    Cancel
                </button>
                <a href="#" id="confirmDeleteBtn" class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    Delete
                </a>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Store document data for editing
        const documents = <?php echo json_encode($docs_for_js); ?>;
        let currentDeleteId = null;

        // Toast notification
        <?php if ($toast): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo $toast['message']; ?>', '<?php echo $toast['type']; ?>');
            });
        <?php endif; ?>

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
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(id) {
            const doc = documents.find(d => d.id == id);
            if (doc) {
                document.getElementById('edit_id').value = doc.id;
                document.getElementById('edit_serial').value = doc.serial_number || '';
                document.getElementById('display_serial').textContent = doc.serial_number || 'DOC-000001';
                document.getElementById('edit_name').value = doc.document_name || '';
                document.getElementById('edit_type_id').value = doc.type_id || '';
                document.getElementById('edit_origin').value = doc.origin || '';
                document.getElementById('edit_copies').value = doc.copies_received || 1;
                document.getElementById('edit_date').value = doc.date_received || '';
                document.getElementById('editModal').style.display = 'flex';
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function viewDocument(id) {
            const doc = documents.find(d => d.id == id);
            if (doc) {
                const content = document.getElementById('viewContent');
                content.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-[#6e6e6e] uppercase">ID</p>
                            <p class="text-sm">${doc.id}</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#6e6e6e] uppercase">Serial Number</p>
                            <p class="text-sm font-mono font-medium">${doc.serial_number || 'DOC-000001'}</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#6e6e6e] uppercase">Document Name</p>
                            <p class="text-sm font-medium">${escapeHtml(doc.document_name || '')}</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#6e6e6e] uppercase">Type</p>
                            <p class="text-sm">${escapeHtml(doc.type || '-')}</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#6e6e6e] uppercase">Origin</p>
                            <p class="text-sm">${escapeHtml(doc.origin || '')}</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#6e6e6e] uppercase">Copies Received</p>
                            <p class="text-sm">${doc.copies_received || 0}</p>
                        </div>
                        <div>
                            <p class="text-xs text-[#6e6e6e] uppercase">Date Received</p>
                            <p class="text-sm">${doc.date_received ? new Date(doc.date_received).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '-'}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-[#6e6e6e] uppercase">Created At</p>
                            <p class="text-sm">${doc.created_at ? new Date(doc.created_at).toLocaleString() : '-'}</p>
                        </div>
                    </div>
                `;
                document.getElementById('viewModal').style.display = 'flex';
            }
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function confirmDelete(id, name) {
            currentDeleteId = id;
            document.getElementById('deleteDocName').textContent = name;
            document.getElementById('confirmDeleteBtn').href = '?delete=' + id;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            currentDeleteId = null;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Export to CSV
        function exportToCSV() {
            const rows = [];
            const headers = ['ID', 'Serial Number', 'Document Name', 'Type', 'Origin', 'Copies', 'Date Received'];
            rows.push(headers.join(','));

            <?php
            $export_result = $conn->query("
                SELECT d.*, dt.type_name as document_type_name 
                FROM documents d 
                LEFT JOIN document_types dt ON d.type_id = dt.id 
                ORDER BY d.date_received DESC
            ");
            if ($export_result) {
                while ($row = $export_result->fetch_assoc()) {
                    echo "rows.push(['" . $row['id'] . "', " .
                        '"' . addslashes($row['serial_number'] ?? 'DOC-000001') . '", ' .
                        '"' . addslashes($row['document_name']) . '", ' .
                        '"' . addslashes($row['type'] ?? '') . '", ' .
                        '"' . addslashes($row['origin'] ?? '') . '", ' .
                        "'" . $row['copies_received'] . "', " .
                        '"' . $row['date_received'] . '"' . "].join(','));";
                }
            }
            ?>

            const csv = rows.join('\n');
            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `documents_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();

            showToast('Export completed successfully!', 'success');
        }

        // Print table
        function printTable() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Documents List</title>
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
                        <h1>Documents List</h1>
                        <div class="date">Generated: ${new Date().toLocaleString()}</div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Serial Number</th>
                                <th>Document Name</th>
                                <th>Type</th>
                                <th>Origin</th>
                                <th>Copies</th>
                                <th>Date Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $print_result = $conn->query("
                                SELECT d.*, dt.type_name as document_type_name 
                                FROM documents d 
                                LEFT JOIN document_types dt ON d.type_id = dt.id 
                                ORDER BY d.date_received DESC
                            ");
                            if ($print_result) {
                                while ($row = $print_result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $row['id'] . "</td>";
                                    echo "<td>" . ($row['serial_number'] ?? 'DOC-000001') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['document_name'] ?? '') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['type'] ?? '-') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['origin'] ?? '') . "</td>";
                                    echo "<td>" . $row['copies_received'] . "</td>";
                                    echo "<td>" . $row['date_received'] . "</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();

            showToast('Print dialog opened', 'success');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const viewModal = document.getElementById('viewModal');
            const deleteModal = document.getElementById('deleteModal');

            if (event.target == addModal) closeAddModal();
            if (event.target == editModal) closeEditModal();
            if (event.target == viewModal) closeViewModal();
            if (event.target == deleteModal) closeDeleteModal();
        }

        // ESC key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closeViewModal();
                closeDeleteModal();
            }
        });
    </script>
</body>

</html>