<?php
// available_documents.php
require_once './config/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for toast messages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Handle AJAX request for quick distribution
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'quick_distribute') {
    header('Content-Type: application/json');

    $document_id = (int)$_POST['document_id'];
    $department = trim($_POST['department']);
    $recipient = trim($_POST['recipient']);
    $copies = (int)$_POST['copies'];
    $date_distributed = date('Y-m-d');

    // Validate inputs
    if (empty($department)) {
        echo json_encode(['success' => false, 'message' => 'Department is required']);
        exit();
    }

    if (empty($recipient)) {
        echo json_encode(['success' => false, 'message' => 'Recipient name is required']);
        exit();
    }

    if ($copies < 1) {
        echo json_encode(['success' => false, 'message' => 'Number of copies must be at least 1']);
        exit();
    }

    // Check if document exists and has enough copies
    $check_stmt = $conn->prepare("SELECT id, document_name, copies_received FROM documents WHERE id = ?");
    $check_stmt->bind_param("i", $document_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $document = $check_result->fetch_assoc();
    $check_stmt->close();

    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit();
    }

    if ($document['copies_received'] < $copies) {
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient copies available. Only ' . $document['copies_received'] . ' copies left.'
        ]);
        exit();
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Insert distribution record
        $insert_stmt = $conn->prepare("INSERT INTO document_distribution 
            (document_id, department, recipient_name, number_received, number_distributed, date_distributed) 
            VALUES (?, ?, ?, ?, ?, ?)");

        // Using same number for both received and distributed
        $insert_stmt->bind_param("issiis", $document_id, $department, $recipient, $copies, $copies, $date_distributed);

        if (!$insert_stmt->execute()) {
            throw new Exception($insert_stmt->error);
        }
        $insert_stmt->close();

        // Update document copies
        $new_copies = $document['copies_received'] - $copies;
        $update_stmt = $conn->prepare("UPDATE documents SET copies_received = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $new_copies, $document_id);

        if (!$update_stmt->execute()) {
            throw new Exception($update_stmt->error);
        }
        $update_stmt->close();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => $copies . ' copy(ies) of "' . $document['document_name'] . '" distributed to ' . $recipient,
            'new_copies' => $new_copies
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

    exit();
}

// Handle bulk distribution via AJAX
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'bulk_distribute') {
    header('Content-Type: application/json');

    $distributions = json_decode($_POST['distributions'], true);
    $date_distributed = date('Y-m-d');

    if (empty($distributions)) {
        echo json_encode(['success' => false, 'message' => 'No distribution data provided']);
        exit();
    }

    $conn->begin_transaction();
    $success_count = 0;
    $errors = [];

    try {
        foreach ($distributions as $dist) {
            $document_id = (int)$dist['document_id'];
            $department = trim($dist['department']);
            $recipient = trim($dist['recipient']);
            $copies = (int)$dist['copies'];

            if (empty($department) || empty($recipient) || $copies < 1) {
                $errors[] = "Invalid data for document ID: $document_id";
                continue;
            }

            // Check available copies
            $check_stmt = $conn->prepare("SELECT copies_received FROM documents WHERE id = ?");
            $check_stmt->bind_param("i", $document_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $document = $check_result->fetch_assoc();
            $check_stmt->close();

            if (!$document) {
                $errors[] = "Document ID $document_id not found";
                continue;
            }

            if ($document['copies_received'] < $copies) {
                $errors[] = "Insufficient copies for document ID $document_id";
                continue;
            }

            // Insert distribution
            $insert_stmt = $conn->prepare("INSERT INTO document_distribution 
                (document_id, department, recipient_name, number_received, number_distributed, date_distributed) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("issiis", $document_id, $department, $recipient, $copies, $copies, $date_distributed);

            if (!$insert_stmt->execute()) {
                $errors[] = "Failed to insert distribution for document ID $document_id";
                continue;
            }
            $insert_stmt->close();

            // Update document
            $new_copies = $document['copies_received'] - $copies;
            $update_stmt = $conn->prepare("UPDATE documents SET copies_received = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_copies, $document_id);
            $update_stmt->execute();
            $update_stmt->close();

            $success_count++;
        }

        if ($success_count > 0) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => "$success_count distribution(s) completed successfully",
                'errors' => $errors
            ]);
        } else {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'No distributions were successful',
                'errors' => $errors
            ]);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

    exit();
}

// Get all documents with their current stock and distribution info
$sql = "SELECT 
            d.*, 
            dt.type_name as document_type,
            COALESCE((
                SELECT SUM(number_distributed) 
                FROM document_distribution 
                WHERE document_id = d.id
            ), 0) as total_distributed,
            (d.copies_received - COALESCE((
                SELECT SUM(number_distributed) 
                FROM document_distribution 
                WHERE document_id = d.id
            ), 0)) as available_copies
        FROM documents d
        LEFT JOIN document_types dt ON d.type_id = dt.id
        ORDER BY 
            CASE 
                WHEN (d.copies_received - COALESCE((
                    SELECT SUM(number_distributed) 
                    FROM document_distribution 
                    WHERE document_id = d.id
                ), 0)) > 0 THEN 0 
                ELSE 1 
            END,
            d.date_received DESC";

$documents_result = $conn->query($sql);

if (!$documents_result) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => "Database error: " . $conn->error];
}

// Calculate statistics
$stats = [
    'total_documents' => 0,
    'total_copies' => 0,
    'available_copies' => 0,
    'distributed_copies' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'in_stock' => 0
];

$stats_sql = "SELECT 
                COUNT(DISTINCT d.id) as total_documents,
                SUM(d.copies_received) as total_copies,
                SUM(COALESCE(dd.total_distributed, 0)) as distributed_copies,
                SUM(d.copies_received - COALESCE(dd.total_distributed, 0)) as available_copies,
                SUM(CASE 
                    WHEN (d.copies_received - COALESCE(dd.total_distributed, 0)) > 5 
                    THEN 1 ELSE 0 END) as in_stock,
                SUM(CASE 
                    WHEN (d.copies_received - COALESCE(dd.total_distributed, 0)) BETWEEN 1 AND 5 
                    THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE 
                    WHEN (d.copies_received - COALESCE(dd.total_distributed, 0)) <= 0 
                    THEN 1 ELSE 0 END) as out_of_stock
              FROM documents d
              LEFT JOIN (
                  SELECT document_id, SUM(number_distributed) as total_distributed
                  FROM document_distribution
                  GROUP BY document_id
              ) dd ON d.id = dd.document_id";

$stats_result = $conn->query($stats_sql);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
}

// Get document types for filter
$types_result = $conn->query("SELECT id, type_name FROM document_types ORDER BY type_name");
$document_types = [];
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $document_types[] = $row;
    }
}

// Get recent distributions for activity feed
$recent_distributions = $conn->query("
    SELECT dd.*, d.document_name
    FROM document_distribution dd
    JOIN documents d ON dd.document_id = d.id
    ORDER BY dd.date_distributed DESC, dd.id DESC
    LIMIT 10
");

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
    <title>Available Documents - Mailroom Management System</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f5f5f4;
        }

        .stat-card {
            transition: all 0.2s ease;
            border: 1px solid #e5e5e5;
            background-color: white;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-color: #9e9e9e;
        }

        .document-card {
            transition: all 0.2s ease;
            border: 1px solid #e5e5e5;
            background-color: white;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .document-card:hover {
            border-color: #9e9e9e;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .stock-high {
            background-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .stock-medium {
            background-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .stock-low {
            background-color: #ef4444;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }

        .stock-out {
            background-color: #9e9e9e;
            box-shadow: 0 0 0 2px rgba(158, 158, 158, 0.2);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 500;
            border-radius: 3px;
        }

        .badge-success {
            background-color: #e8f0e8;
            color: #2c5e2c;
        }

        .badge-warning {
            background-color: #fff3e0;
            color: #b45b0b;
        }

        .badge-danger {
            background-color: #fee9e7;
            color: #c73b2b;
        }

        .badge-info {
            background-color: #e3f2fd;
            color: #0b5e8a;
        }

        .badge-default {
            background-color: #f5f5f4;
            color: #4a4a4a;
        }

        .distribute-btn {
            background-color: #1e1e1e;
            color: white;
            transition: all 0.2s;
        }

        .distribute-btn:hover {
            background-color: #2d2d2d;
        }

        .distribute-btn:disabled {
            background-color: #9e9e9e;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            background-color: white;
            color: #1e1e1e;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background-color: #f5f5f4;
        }

        .filter-btn.active {
            background-color: #1e1e1e;
            color: white;
            border-color: #1e1e1e;
        }

        .modal {
            transition: opacity 0.3s ease;
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

        .progress-bar {
            height: 4px;
            background-color: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }

        .activity-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item:hover {
            background-color: #fafafa;
        }

        .quick-actions {
            position: sticky;
            top: 1rem;
        }
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <div class="flex">
        <!-- Sidebar -->
        <?php include './sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 ml-60 min-h-screen">
            <!-- Header -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-medium text-[#1e1e1e]">Available Documents</h1>
                        <p class="text-sm text-[#6e6e6e] mt-1">View and distribute documents with available copies</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="distribution.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] flex items-center">
                            <i class="fa-regular fa-clock mr-1 text-[#6e6e6e]"></i>
                            Distribution History
                        </a>
                        <a href="list.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] flex items-center">
                            <i class="fa-regular fa-folder mr-1 text-[#6e6e6e]"></i>
                            Manage Documents
                        </a>
                        <a href="document_types.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] flex items-center">
                            <i class="fa-solid fa-tags mr-1 text-[#6e6e6e]"></i>
                            Document Types
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="stat-card rounded-md p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Total Documents</p>
                                <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo number_format($stats['total_documents'] ?? 0); ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-1">Unique documents</p>
                            </div>
                            <div class="w-10 h-10 bg-[#f5f5f4] rounded-full flex items-center justify-center">
                                <i class="fa-regular fa-file-lines text-[#6e6e6e] text-lg"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card rounded-md p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Total Copies</p>
                                <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo number_format($stats['total_copies'] ?? 0); ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-1"><?php echo number_format($stats['distributed_copies'] ?? 0); ?> distributed</p>
                            </div>
                            <div class="w-10 h-10 bg-[#f5f5f4] rounded-full flex items-center justify-center">
                                <i class="fa-regular fa-copy text-[#6e6e6e] text-lg"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card rounded-md p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Available Copies</p>
                                <p class="text-2xl font-medium text-[#1e1e1e] mt-1"><?php echo number_format($stats['available_copies'] ?? 0); ?></p>
                                <p class="text-xs text-[#6e6e6e] mt-1">Ready for distribution</p>
                            </div>
                            <div class="w-10 h-10 bg-[#f5f5f4] rounded-full flex items-center justify-center">
                                <i class="fa-regular fa-circle-check text-[#6e6e6e] text-lg"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card rounded-md p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Stock Status</p>
                                <div class="flex items-center gap-3 mt-2">
                                    <div>
                                        <span class="stock-indicator stock-high"></span>
                                        <span class="text-xs text-[#1e1e1e]"><?php echo number_format($stats['in_stock'] ?? 0); ?></span>
                                    </div>
                                    <div>
                                        <span class="stock-indicator stock-medium"></span>
                                        <span class="text-xs text-[#1e1e1e]"><?php echo number_format($stats['low_stock'] ?? 0); ?></span>
                                    </div>
                                    <div>
                                        <span class="stock-indicator stock-out"></span>
                                        <span class="text-xs text-[#1e1e1e]"><?php echo number_format($stats['out_of_stock'] ?? 0); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="w-10 h-10 bg-[#f5f5f4] rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-chart-pie text-[#6e6e6e] text-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="bg-white border border-[#e5e5e5] rounded-md p-4 mb-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-medium text-[#1e1e1e]">Filter:</span>

                        <select id="typeFilter" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="">All Document Types</option>
                            <?php foreach ($document_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['type_name']); ?>">
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="stockFilter" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="all">All Stock Levels</option>
                            <option value="in-stock">In Stock (>5)</option>
                            <option value="low-stock">Low Stock (1-5)</option>
                            <option value="out-of-stock">Out of Stock</option>
                            <option value="available">Available Only</option>
                        </select>

                        <div class="flex-1 relative">
                            <input type="text" id="searchInput" placeholder="Search by document name, serial number, or type..."
                                class="w-full px-3 py-1.5 pl-9 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-sm text-[#9e9e9e]"></i>
                        </div>

                        <button onclick="applyFilters()" class="px-4 py-1.5 text-sm bg-[#1e1e1e] text-white rounded-md hover:bg-[#2d2d2d]">
                            Apply Filters
                        </button>

                        <button onclick="resetFilters()" class="px-4 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            Reset
                        </button>

                        <button onclick="toggleBulkMode()" id="bulkModeBtn" class="px-4 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-regular fa-layer-group mr-1 text-[#6e6e6e]"></i>
                            Bulk Mode
                        </button>
                    </div>
                </div>

                <!-- Bulk Actions Bar (hidden by default) -->
                <div id="bulkBar" class="bg-[#1e1e1e] text-white rounded-md p-3 mb-4 hidden items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fa-regular fa-cubes"></i>
                        <span id="selectedCount">0</span> document(s) selected
                    </div>
                    <div class="flex gap-2">
                        <button onclick="clearSelection()" class="px-3 py-1 text-sm bg-white text-[#1e1e1e] rounded-md hover:bg-[#f5f5f4]">
                            Clear
                        </button>
                        <button onclick="processBulkDistribution()" class="px-3 py-1 text-sm bg-white text-[#1e1e1e] rounded-md hover:bg-[#f5f5f4]">
                            <i class="fa-regular fa-share-from-square mr-1"></i>
                            Distribute Selected
                        </button>
                        <button onclick="toggleBulkMode()" class="px-3 py-1 text-sm border border-white text-white rounded-md hover:bg-white hover:text-[#1e1e1e]">
                            Exit Bulk Mode
                        </button>
                    </div>
                </div>

                <!-- Main Grid: Documents and Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Documents Grid (Left Column - 2/3 width) -->
                    <div class="lg:col-span-2">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="documentsGrid">
                            <?php if ($documents_result && $documents_result->num_rows > 0): ?>
                                <?php while ($doc = $documents_result->fetch_assoc()):
                                    $available = $doc['available_copies'];
                                    $total = $doc['copies_received'];
                                    $percentage = $total > 0 ? round(($available / $total) * 100) : 0;

                                    // Determine stock level class
                                    if ($available <= 0) {
                                        $stockClass = 'stock-out';
                                        $stockText = 'Out of Stock';
                                        $badgeClass = 'badge-danger';
                                        $progressClass = 'bg-[#9e9e9e]';
                                    } elseif ($available <= 5) {
                                        $stockClass = 'stock-low';
                                        $stockText = 'Low Stock';
                                        $badgeClass = 'badge-warning';
                                        $progressClass = 'bg-[#ef4444]';
                                    } elseif ($available <= 10) {
                                        $stockClass = 'stock-medium';
                                        $stockText = 'Medium Stock';
                                        $badgeClass = 'badge-warning';
                                        $progressClass = 'bg-[#f59e0b]';
                                    } else {
                                        $stockClass = 'stock-high';
                                        $stockText = 'High Stock';
                                        $badgeClass = 'badge-success';
                                        $progressClass = 'bg-[#10b981]';
                                    }
                                ?>
                                    <div class="document-card document-item p-4"
                                        data-id="<?php echo $doc['id']; ?>"
                                        data-type="<?php echo strtolower(htmlspecialchars($doc['document_type'] ?? 'uncategorized')); ?>"
                                        data-available="<?php echo $available; ?>"
                                        data-name="<?php echo strtolower(htmlspecialchars($doc['document_name'])); ?>"
                                        data-serial="<?php echo strtolower(htmlspecialchars($doc['serial_number'] ?? '')); ?>">

                                        <!-- Selection Checkbox for Bulk Mode -->
                                        <div class="bulk-checkbox hidden mb-2">
                                            <label class="flex items-center">
                                                <input type="checkbox" class="document-checkbox rounded border-[#e5e5e5] text-[#1e1e1e] focus:ring-[#1e1e1e]" value="<?php echo $doc['id']; ?>">
                                                <span class="ml-2 text-xs text-[#6e6e6e]">Select for bulk distribution</span>
                                            </label>
                                        </div>

                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="stock-indicator <?php echo $stockClass; ?>"></span>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        <?php echo $stockText; ?>
                                                    </span>
                                                </div>
                                                <h3 class="text-base font-medium text-[#1e1e1e] line-clamp-2">
                                                    <?php echo htmlspecialchars($doc['document_name']); ?>
                                                </h3>
                                                <p class="text-xs text-[#6e6e6e] mt-1">
                                                    Serial: <span class="font-mono"><?php echo htmlspecialchars($doc['serial_number'] ?? 'DOC-000001'); ?></span>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-2xl font-medium <?php echo $available > 0 ? 'text-[#1e1e1e]' : 'text-[#9e9e9e]'; ?>">
                                                    <?php echo $available; ?>
                                                </p>
                                                <p class="text-xs text-[#6e6e6e]">of <?php echo $total; ?></p>
                                            </div>
                                        </div>

                                        <!-- Progress Bar -->
                                        <div class="mt-3">
                                            <div class="progress-bar">
                                                <div class="progress-fill <?php echo $progressClass; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <div class="flex justify-between text-xs text-[#6e6e6e] mt-1">
                                                <span><?php echo $doc['document_type'] ?? 'Uncategorized'; ?></span>
                                                <span><?php echo $percentage; ?>% available</span>
                                            </div>
                                        </div>

                                        <!-- Document Details -->
                                        <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
                                            <div>
                                                <span class="text-[#6e6e6e]">Origin:</span>
                                                <span class="text-[#1e1e1e] ml-1"><?php echo htmlspecialchars($doc['origin'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div>
                                                <span class="text-[#6e6e6e]">Received:</span>
                                                <span class="text-[#1e1e1e] ml-1"><?php echo $doc['date_received'] ? date('M j, Y', strtotime($doc['date_received'])) : 'N/A'; ?></span>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex gap-2 mt-3 pt-3 border-t border-[#e5e5e5]">
                                            <?php if ($available > 0): ?>
                                                <button onclick="openDistributeModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['document_name'])); ?>', <?php echo $available; ?>)"
                                                    class="flex-1 distribute-btn px-3 py-1.5 text-sm rounded-md flex items-center justify-center gap-1">
                                                    <i class="fa-regular fa-share-from-square"></i>
                                                    Distribute
                                                </button>
                                            <?php else: ?>
                                                <button disabled class="flex-1 px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-[#f5f5f4] text-[#9e9e9e] cursor-not-allowed flex items-center justify-center gap-1">
                                                    <i class="fa-regular fa-ban"></i>
                                                    Out of Stock
                                                </button>
                                            <?php endif; ?>

                                            <a href="list.php?search=<?php echo urlencode($doc['document_name']); ?>"
                                                class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] flex items-center justify-center"
                                                title="View Details">
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-span-2 bg-white border border-[#e5e5e5] rounded-md p-8 text-center">
                                    <i class="fa-regular fa-folder-open text-4xl text-[#9e9e9e] mb-3"></i>
                                    <p class="text-sm text-[#6e6e6e]">No documents found.</p>
                                    <a href="list.php" class="inline-block mt-2 text-sm text-[#1e1e1e] underline">Add your first document</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- No Results Message -->
                        <div id="noResultsMessage" class="hidden bg-white border border-[#e5e5e5] rounded-md p-8 text-center">
                            <i class="fa-regular fa-circle-xmark text-4xl text-[#9e9e9e] mb-3"></i>
                            <p class="text-sm text-[#6e6e6e]">No documents match your filters.</p>
                            <button onclick="resetFilters()" class="mt-2 text-sm text-[#1e1e1e] underline">Clear filters</button>
                        </div>
                    </div>

                    <!-- Right Sidebar - Quick Actions and Activity -->
                    <div class="lg:col-span-1">
                        <div class="quick-actions space-y-4">
                            <!-- Quick Stats Card -->
                            <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                                <h3 class="text-sm font-medium text-[#1e1e1e] mb-3">Quick Stats</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-[#6e6e6e]">Documents in stock:</span>
                                        <span class="font-medium"><?php echo number_format(($stats['in_stock'] ?? 0) + ($stats['low_stock'] ?? 0)); ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-[#6e6e6e]">Out of stock:</span>
                                        <span class="font-medium"><?php echo number_format($stats['out_of_stock'] ?? 0); ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-[#6e6e6e]">Total copies available:</span>
                                        <span class="font-medium"><?php echo number_format($stats['available_copies'] ?? 0); ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-[#6e6e6e]">Total distributed:</span>
                                        <span class="font-medium"><?php echo number_format($stats['distributed_copies'] ?? 0); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions Card -->
                            <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                                <h3 class="text-sm font-medium text-[#1e1e1e] mb-3">Quick Actions</h3>
                                <div class="space-y-2">
                                    <a href="list.php"
                                        class="block w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] text-center">
                                        <i class="fa-regular fa-plus mr-1 text-[#6e6e6e]"></i>
                                        Add New Document
                                    </a>
                                    <a href="distribution.php"
                                        class="block w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] text-center">
                                        <i class="fa-regular fa-clock mr-1 text-[#6e6e6e]"></i>
                                        View Distribution History
                                    </a>
                                    <a href="document_types.php?action=create"
                                        class="block w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e] text-center">
                                        <i class="fa-solid fa-tag mr-1 text-[#6e6e6e]"></i>
                                        Create Document Type
                                    </a>
                                </div>
                            </div>

                            <!-- Recent Activity Card -->
                            <div class="bg-white border border-[#e5e5e5] rounded-md">
                                <div class="px-4 py-3 border-b border-[#e5e5e5] bg-[#fafafa]">
                                    <h3 class="text-sm font-medium text-[#1e1e1e]">Recent Distributions</h3>
                                </div>
                                <div class="divide-y divide-[#e5e5e5] max-h-96 overflow-y-auto">
                                    <?php if ($recent_distributions && $recent_distributions->num_rows > 0): ?>
                                        <?php while ($activity = $recent_distributions->fetch_assoc()): ?>
                                            <div class="activity-item p-3">
                                                <div class="flex items-start gap-2">
                                                    <div class="w-6 h-6 bg-[#f5f5f4] rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                                        <i class="fa-regular fa-share-from-square text-xs text-[#6e6e6e]"></i>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-[#1e1e1e] truncate">
                                                            <?php echo htmlspecialchars($activity['document_name']); ?>
                                                        </p>
                                                        <p class="text-xs text-[#6e6e6e]">
                                                            <?php echo htmlspecialchars($activity['recipient_name']); ?> •
                                                            <?php echo $activity['number_distributed']; ?> copies •
                                                            <?php echo htmlspecialchars($activity['department']); ?>
                                                        </p>
                                                        <p class="text-xs text-[#9e9e9e] mt-1">
                                                            <?php echo date('M j, Y g:i A', strtotime($activity['date_distributed'])); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="p-4 text-center">
                                            <p class="text-sm text-[#6e6e6e]">No recent distributions</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="px-4 py-2 border-t border-[#e5e5e5] bg-[#fafafa]">
                                    <a href="distribution.php" class="text-xs text-[#1e1e1e] hover:underline flex items-center justify-center">
                                        View All
                                        <i class="fa-solid fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Quick Distribute Modal -->
    <div id="distributeModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Quick Distribute</h3>
                <button onclick="closeDistributeModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="mb-4 p-3 bg-[#f5f5f4] rounded-md">
                <p class="text-sm font-medium text-[#1e1e1e]" id="modalDocumentName"></p>
                <p class="text-xs text-[#6e6e6e] mt-1">Available copies: <span id="modalAvailableCopies" class="font-medium">0</span></p>
            </div>

            <form id="distributeForm" onsubmit="return false;">
                <input type="hidden" id="modalDocumentId">

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Department <span class="text-red-400">*</span></label>
                    <input type="text" id="modalDepartment" required
                        placeholder="e.g., IT, HR, Finance"
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                        autocomplete="off">
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Recipient Name <span class="text-red-400">*</span></label>
                    <input type="text" id="modalRecipient" required
                        placeholder="Full name of recipient"
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                        autocomplete="off">
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Number of Copies <span class="text-red-400">*</span></label>
                    <div class="flex items-center gap-2">
                        <input type="number" id="modalCopies" required min="1" value="1"
                            class="flex-1 px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                        <button type="button" onclick="decrementCopies()" class="px-3 py-2 border border-[#e5e5e5] rounded-md hover:bg-[#f5f5f4]">
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <button type="button" onclick="incrementCopies()" class="px-3 py-2 border border-[#e5e5e5] rounded-md hover:bg-[#f5f5f4]">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                    <p class="text-xs text-[#6e6e6e] mt-1">Maximum: <span id="modalMaxCopies">0</span></p>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeDistributeModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="button" onclick="submitDistribution()" id="distributeSubmitBtn"
                        class="px-4 py-2 text-sm bg-[#1e1e1e] text-white rounded-md hover:bg-[#2d2d2d]">
                        <i class="fa-regular fa-share-from-square mr-1"></i>
                        Distribute
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Distribution Modal -->
    <div id="bulkModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-2xl p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Bulk Distribution</h3>
                <button onclick="closeBulkModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <p class="text-sm text-[#6e6e6e] mb-4">You are about to distribute <span id="bulkCount">0</span> document(s).</p>

            <div id="bulkDocumentsList" class="max-h-60 overflow-y-auto border border-[#e5e5e5] rounded-md mb-4">
                <!-- Will be populated by JavaScript -->
            </div>

            <div class="mb-4">
                <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Common Department (Optional)</label>
                <input type="text" id="bulkDepartment"
                    placeholder="If all documents go to the same department"
                    class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                <p class="text-xs text-[#6e6e6e] mt-1">Leave blank to enter per-document departments</p>
            </div>

            <div class="flex justify-end gap-2">
                <button onclick="closeBulkModal()"
                    class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    Cancel
                </button>
                <button onclick="processBulkDistributionSubmit()" id="bulkSubmitBtn"
                    class="px-4 py-2 text-sm bg-[#1e1e1e] text-white rounded-md hover:bg-[#2d2d2d]">
                    <i class="fa-regular fa-share-from-square mr-1"></i>
                    Process Bulk Distribution
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container (for custom toasts) -->
    <div id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Store document data
        let documents = [];
        let selectedDocuments = new Set();
        let bulkMode = false;

        // Toast notification function
        function showToast(message, type = 'success') {
            const backgroundColor = type === 'success' ? '#10b981' :
                type === 'error' ? '#ef4444' :
                type === 'warning' ? '#f59e0b' : '#3b82f6';

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

        // Show toast from PHP session
        <?php if ($toast): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($toast['message']); ?>', '<?php echo $toast['type']; ?>');
            });
        <?php endif; ?>

        // Distribute Modal Functions
        function openDistributeModal(id, name, available) {
            document.getElementById('modalDocumentId').value = id;
            document.getElementById('modalDocumentName').textContent = name;
            document.getElementById('modalAvailableCopies').textContent = available;
            document.getElementById('modalMaxCopies').textContent = available;
            document.getElementById('modalCopies').max = available;
            document.getElementById('modalCopies').value = 1;
            document.getElementById('modalDepartment').value = '';
            document.getElementById('modalRecipient').value = '';

            document.getElementById('distributeModal').style.display = 'flex';
        }

        function closeDistributeModal() {
            document.getElementById('distributeModal').style.display = 'none';
        }

        function incrementCopies() {
            const input = document.getElementById('modalCopies');
            const max = parseInt(document.getElementById('modalMaxCopies').textContent);
            let value = parseInt(input.value) || 0;
            if (value < max) {
                input.value = value + 1;
            }
        }

        function decrementCopies() {
            const input = document.getElementById('modalCopies');
            let value = parseInt(input.value) || 0;
            if (value > 1) {
                input.value = value - 1;
            }
        }

        function submitDistribution() {
            const documentId = document.getElementById('modalDocumentId').value;
            const department = document.getElementById('modalDepartment').value.trim();
            const recipient = document.getElementById('modalRecipient').value.trim();
            const copies = parseInt(document.getElementById('modalCopies').value);
            const maxCopies = parseInt(document.getElementById('modalMaxCopies').textContent);

            // Validation
            if (!department) {
                showToast('Please enter a department', 'warning');
                return;
            }

            if (!recipient) {
                showToast('Please enter a recipient name', 'warning');
                return;
            }

            if (!copies || copies < 1) {
                showToast('Please enter a valid number of copies', 'warning');
                return;
            }

            if (copies > maxCopies) {
                showToast(`Cannot distribute more than ${maxCopies} copies`, 'warning');
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('distributeSubmitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-regular fa-spinner fa-spin mr-1"></i> Processing...';
            submitBtn.disabled = true;

            // Submit via AJAX
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax_action=quick_distribute&document_id=${documentId}&department=${encodeURIComponent(department)}&recipient=${encodeURIComponent(recipient)}&copies=${copies}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        closeDistributeModal();

                        // Reload the page after a short delay to show updated data
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast(data.message, 'error');
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    showToast('An error occurred. Please try again.', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        }

        // Bulk Mode Functions
        function toggleBulkMode() {
            bulkMode = !bulkMode;
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            const bulkBar = document.getElementById('bulkBar');
            const bulkBtn = document.getElementById('bulkModeBtn');

            checkboxes.forEach(cb => {
                cb.classList.toggle('hidden', !bulkMode);
            });

            if (bulkMode) {
                bulkBar.classList.remove('hidden');
                bulkBar.classList.add('flex');
                bulkBtn.classList.add('active');
                selectedDocuments.clear();
                updateSelectedCount();
            } else {
                bulkBar.classList.add('hidden');
                bulkBar.classList.remove('flex');
                bulkBtn.classList.remove('active');
                // Uncheck all checkboxes
                document.querySelectorAll('.document-checkbox').forEach(cb => {
                    cb.checked = false;
                });
            }
        }

        // Update document selection
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('document-checkbox')) {
                const docId = e.target.value;
                if (e.target.checked) {
                    selectedDocuments.add(docId);
                } else {
                    selectedDocuments.delete(docId);
                }
                updateSelectedCount();
            }
        });

        function updateSelectedCount() {
            document.getElementById('selectedCount').textContent = selectedDocuments.size;
        }

        function clearSelection() {
            document.querySelectorAll('.document-checkbox').forEach(cb => {
                cb.checked = false;
            });
            selectedDocuments.clear();
            updateSelectedCount();
        }

        function processBulkDistribution() {
            if (selectedDocuments.size === 0) {
                showToast('Please select at least one document', 'warning');
                return;
            }

            // Populate bulk modal with selected documents
            const list = document.getElementById('bulkDocumentsList');
            list.innerHTML = '';

            selectedDocuments.forEach(docId => {
                const docCard = document.querySelector(`.document-item[data-id="${docId}"]`);
                if (docCard) {
                    const docName = docCard.querySelector('h3').textContent;
                    const available = docCard.dataset.available;

                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'p-3 border-b border-[#e5e5e5] last:border-b-0';
                    itemDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium">${docName}</p>
                                <p class="text-xs text-[#6e6e6e]">Available: ${available} copies</p>
                            </div>
                            <div class="w-32">
                                <input type="number" 
                                       class="bulk-copies w-full px-2 py-1 text-sm border border-[#e5e5e5] rounded-md"
                                       data-id="${docId}"
                                       min="1"
                                       max="${available}"
                                       value="1"
                                       placeholder="Copies">
                            </div>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <input type="text" 
                                   class="bulk-department w-full px-2 py-1 text-xs border border-[#e5e5e5] rounded-md"
                                   data-id="${docId}"
                                   placeholder="Department">
                            <input type="text" 
                                   class="bulk-recipient w-full px-2 py-1 text-xs border border-[#e5e5e5] rounded-md"
                                   data-id="${docId}"
                                   placeholder="Recipient">
                        </div>
                    `;
                    list.appendChild(itemDiv);
                }
            });

            document.getElementById('bulkCount').textContent = selectedDocuments.size;
            document.getElementById('bulkModal').style.display = 'flex';
        }

        function closeBulkModal() {
            document.getElementById('bulkModal').style.display = 'none';
        }

        function processBulkDistributionSubmit() {
            const commonDepartment = document.getElementById('bulkDepartment').value.trim();
            const distributions = [];

            selectedDocuments.forEach(docId => {
                const copiesInput = document.querySelector(`.bulk-copies[data-id="${docId}"]`);
                const deptInput = document.querySelector(`.bulk-department[data-id="${docId}"]`);
                const recipientInput = document.querySelector(`.bulk-recipient[data-id="${docId}"]`);

                const department = commonDepartment || deptInput.value.trim();
                const recipient = recipientInput.value.trim();
                const copies = parseInt(copiesInput.value);

                if (department && recipient && copies > 0) {
                    distributions.push({
                        document_id: docId,
                        department: department,
                        recipient: recipient,
                        copies: copies
                    });
                }
            });

            if (distributions.length === 0) {
                showToast('Please fill in at least one valid distribution', 'warning');
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('bulkSubmitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-regular fa-spinner fa-spin mr-1"></i> Processing...';
            submitBtn.disabled = true;

            // Submit via AJAX
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax_action=bulk_distribute&distributions=' + encodeURIComponent(JSON.stringify(distributions))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        closeBulkModal();
                        toggleBulkMode(); // Exit bulk mode

                        // Show any errors that occurred
                        if (data.errors && data.errors.length > 0) {
                            console.log('Partial errors:', data.errors);
                        }

                        // Reload the page after a short delay
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast(data.message, 'error');
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    showToast('An error occurred. Please try again.', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        }

        // Filter Functions
        function applyFilters() {
            const typeFilter = document.getElementById('typeFilter').value.toLowerCase();
            const stockFilter = document.getElementById('stockFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();

            const documents = document.querySelectorAll('.document-item');
            let visibleCount = 0;

            documents.forEach(doc => {
                const docType = doc.getAttribute('data-type');
                const available = parseInt(doc.getAttribute('data-available'));
                const docName = doc.getAttribute('data-name');
                const docSerial = doc.getAttribute('data-serial');

                // Type filter
                let typeMatch = !typeFilter || docType.includes(typeFilter);

                // Stock filter
                let stockMatch = true;
                if (stockFilter === 'in-stock') {
                    stockMatch = available > 5;
                } else if (stockFilter === 'low-stock') {
                    stockMatch = available > 0 && available <= 5;
                } else if (stockFilter === 'out-of-stock') {
                    stockMatch = available <= 0;
                } else if (stockFilter === 'available') {
                    stockMatch = available > 0;
                }

                // Search filter
                let searchMatch = !searchTerm ||
                    docName.includes(searchTerm) ||
                    docSerial.includes(searchTerm) ||
                    docType.includes(searchTerm);

                if (typeMatch && stockMatch && searchMatch) {
                    doc.style.display = '';
                    visibleCount++;
                } else {
                    doc.style.display = 'none';
                }
            });

            // Show/hide no results message
            const grid = document.getElementById('documentsGrid');
            const noResults = document.getElementById('noResultsMessage');

            if (visibleCount === 0) {
                grid.classList.add('hidden');
                noResults.classList.remove('hidden');
            } else {
                grid.classList.remove('hidden');
                noResults.classList.add('hidden');
            }

            showToast(`Showing ${visibleCount} document(s)`, 'info', 2000);
        }

        function resetFilters() {
            document.getElementById('typeFilter').value = '';
            document.getElementById('stockFilter').value = 'all';
            document.getElementById('searchInput').value = '';

            const documents = document.querySelectorAll('.document-item');
            documents.forEach(doc => {
                doc.style.display = '';
            });

            document.getElementById('documentsGrid').classList.remove('hidden');
            document.getElementById('noResultsMessage').classList.add('hidden');

            showToast('Filters cleared', 'info', 2000);
        }

        // Search on enter key
        document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const distributeModal = document.getElementById('distributeModal');
            const bulkModal = document.getElementById('bulkModal');

            if (event.target == distributeModal) {
                closeDistributeModal();
            }
            if (event.target == bulkModal) {
                closeBulkModal();
            }
        }

        // ESC key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDistributeModal();
                closeBulkModal();
            }
        });
    </script>
</body>

</html>