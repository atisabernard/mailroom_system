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

// Handle add document via AJAX
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'add_document') {
    header('Content-Type: application/json');

    $serial_number = trim($_POST['serial_number']);
    $document_name = trim($_POST['document_name']);
    $type_id = (int)$_POST['type_id'];
    $origin = trim($_POST['origin']);
    $copies_received = (int)$_POST['copies_received'];
    $date_received = $_POST['date_received'];

    // Validate inputs
    if (empty($document_name)) {
        echo json_encode(['success' => false, 'message' => 'Document name is required']);
        exit();
    }

    if ($type_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a document type']);
        exit();
    }

    if ($copies_received < 1) {
        echo json_encode(['success' => false, 'message' => 'Number of copies must be at least 1']);
        exit();
    }

    if (empty($date_received)) {
        echo json_encode(['success' => false, 'message' => 'Date received is required']);
        exit();
    }

    // Generate serial number if not provided
    if (empty($serial_number)) {
        $prefix = 'DOC';
        $year = date('Y');
        $random = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $serial_number = $prefix . $year . $random;
    }

    // Check if serial number already exists
    $check_stmt = $conn->prepare("SELECT id FROM documents WHERE serial_number = ?");
    $check_stmt->bind_param("s", $serial_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Serial number already exists']);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();

    // Insert new document
    $insert_stmt = $conn->prepare("INSERT INTO documents 
        (serial_number, document_name, type_id, origin, copies_received, date_received) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("ssissi", $serial_number, $document_name, $type_id, $origin, $copies_received, $date_received);

    if ($insert_stmt->execute()) {
        $new_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Document added successfully',
            'document_id' => $new_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $insert_stmt->error]);
    }
    $insert_stmt->close();
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

// Get document types for filter and add form
$types_result = $conn->query("SELECT id, type_name FROM document_types ORDER BY type_name");
$document_types = [];
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $document_types[] = $row;
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
            background-color: #fafafa;
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
            vertical-align: middle;
        }

        tr:hover {
            background-color: #fafafa;
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
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 3px;
            border: none;
            cursor: pointer;
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
            width: 60px;
            height: 4px;
            background-color: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
            display: inline-block;
            margin-left: 8px;
        }

        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }

        .checkbox-column {
            width: 40px;
            text-align: center;
        }

        .serial-column {
            font-family: monospace;
            font-size: 0.8rem;
        }

        .action-btn {
            color: #9e9e9e;
            transition: color 0.2s;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem;
        }

        .action-btn:hover {
            color: #1e1e1e;
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

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: #1e1e1e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 100;
            border: none;
        }

        .fab:hover {
            background-color: #2d2d2d;
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        }

        .fab-tooltip {
            position: absolute;
            right: 70px;
            background-color: #1e1e1e;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .fab:hover .fab-tooltip {
            opacity: 1;
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
                    <!--<div class="flex gap-2">
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
                    </div>-->
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
                            </div>
                            <div class="w-10 h-10 bg-[#f5f5f4] rounded-full flex items-center justify-center">
                                <i class="fa-regular fa-circle-check text-[#6e6e6e] text-lg"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card rounded-md p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Document Status</p>
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

                <!-- Filters and Bulk Actions -->
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
                            <option value="all">All Document Levels</option>
                            <option value="in-stock">High Amount Documents</option>
                            <option value="low-stock">Low Amount Documents</option>
                            <option value="out-of-stock">Out of Documents</option>
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
                            <i class="fa-solid fa-layer-group mr-1 text-[#6e6e6e]"></i>
                            Bulk Mode
                        </button>
                    </div>

                    <!-- Bulk Actions Bar (hidden by default) -->
                    <div id="bulkBar" class="mt-4 bg-[#1e1e1e] text-white rounded-md p-3 hidden items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-cubes"></i>
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
                </div>

                <!-- Documents Table -->
                <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table id="documentsTable">
                            <thead>
                                <tr>
                                    <th class="checkbox-column">
                                        <input type="checkbox" id="selectAllCheckbox" class="bulk-checkbox-global hidden rounded border-[#e5e5e5] text-[#1e1e1e] focus:ring-[#1e1e1e]" onchange="toggleSelectAll()">
                                    </th>
                                    <th onclick="sortTable(1)">Serial # <i class="fa-solid fa-sort ml-1 text-[#9e9e9e]"></i></th>
                                    <th onclick="sortTable(2)">Document Name <i class="fa-solid fa-sort ml-1 text-[#9e9e9e]"></i></th>
                                    <th onclick="sortTable(3)">Type <i class="fa-solid fa-sort ml-1 text-[#9e9e9e]"></i></th>
                                    <th onclick="sortTable(4)">Origin <i class="fa-solid fa-sort ml-1 text-[#9e9e9e]"></i></th>
                                    <th onclick="sortTable(5)">Available <i class="fa-solid fa-sort ml-1 text-[#9e9e9e]"></i></th>
                                    <th onclick="sortTable(6)">Total <i class="fa-solid fa-sort ml-1 text-[#9e9e9e]"></i></th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if ($documents_result && $documents_result->num_rows > 0): ?>
                                    <?php
                                    $counter = 1;
                                    while ($doc = $documents_result->fetch_assoc()):
                                        $available = $doc['available_copies'];
                                        $total = $doc['copies_received'];
                                        $percentage = $total > 0 ? round(($available / $total) * 100) : 0;

                                        // Determine stock level class
                                        if ($available <= 0) {
                                            $stockClass = 'stock-out';
                                            $stockText = 'Out of Documents';
                                            $badgeClass = 'badge-danger';
                                            $progressClass = 'bg-[#9e9e9e]';
                                        } elseif ($available <= 5) {
                                            $stockClass = 'stock-low';
                                            $stockText = 'Low Amount Documents';
                                            $badgeClass = 'badge-warning';
                                            $progressClass = 'bg-[#ef4444]';
                                        } elseif ($available <= 10) {
                                            $stockClass = 'stock-medium';
                                            $stockText = 'Medium';
                                            $badgeClass = 'badge-warning';
                                            $progressClass = 'bg-[#f59e0b]';
                                        } else {
                                            $stockClass = 'stock-high';
                                            $stockText = 'High Amount Documents';
                                            $badgeClass = 'badge-success';
                                            $progressClass = 'bg-[#10b981]';
                                        }
                                    ?>
                                        <tr class="document-row hover:bg-[#fafafa]"
                                            data-id="<?php echo $doc['id']; ?>"
                                            data-type="<?php echo strtolower(htmlspecialchars($doc['document_type'] ?? 'uncategorized')); ?>"
                                            data-available="<?php echo $available; ?>"
                                            data-name="<?php echo strtolower(htmlspecialchars($doc['document_name'])); ?>"
                                            data-serial="<?php echo strtolower(htmlspecialchars($doc['serial_number'] ?? '')); ?>">

                                            <td class="checkbox-column">
                                                <input type="checkbox" class="document-checkbox bulk-checkbox hidden rounded border-[#e5e5e5] text-[#1e1e1e] focus:ring-[#1e1e1e]" value="<?php echo $doc['id']; ?>">
                                            </td>

                                            <td class="serial-column"><?php echo htmlspecialchars($doc['serial_number'] ?? 'DOC-000001'); ?></td>

                                            <td class="font-medium">
                                                <a href="list.php?search=<?php echo urlencode($doc['document_name']); ?>" class="hover:underline">
                                                    <?php echo htmlspecialchars($doc['document_name']); ?>
                                                </a>
                                            </td>

                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo htmlspecialchars($doc['document_type'] ?? 'Uncategorized'); ?>
                                                </span>
                                            </td>

                                            <td><?php echo htmlspecialchars($doc['origin'] ?? 'N/A'); ?></td>

                                            <td class="font-mono font-medium <?php echo $available > 0 ? 'text-[#1e1e1e]' : 'text-[#9e9e9e]'; ?>">
                                                <?php echo $available; ?>
                                                <div class="progress-bar align-middle">
                                                    <div class="progress-fill <?php echo $progressClass; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                            </td>

                                            <td class="font-mono"><?php echo $total; ?></td>

                                            <td>
                                                <div class="flex items-center">
                                                    <span class="stock-indicator <?php echo $stockClass; ?>"></span>
                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $stockText; ?></span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="flex items-center gap-2">
                                                    <?php if ($available > 0): ?>
                                                        <button onclick="openDistributeModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['document_name'])); ?>', <?php echo $available; ?>)"
                                                            class="distribute-btn flex items-center gap-1">
                                                            <i class="fa-regular fa-share-from-square text-xs"></i>
                                                            Distribute
                                                        </button>
                                                    <?php else: ?>
                                                        <button disabled class="distribute-btn opacity-50 cursor-not-allowed flex items-center gap-1">
                                                            <i class="fa-regular fa-ban text-xs"></i>
                                                            Out
                                                        </button>
                                                    <?php endif; ?>

                                                    <a href="list.php?search=<?php echo urlencode($doc['document_name']); ?>"
                                                        class="action-btn" title="View Details">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-8 text-sm text-[#6e6e6e]">
                                            <i class="fa-regular fa-folder-open text-3xl mb-2 block"></i>
                                            No documents found.
                                            <button onclick="openAddDocumentModal()" class="text-[#1e1e1e] underline">Add your first document</button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Footer with Record Count -->
                    <div class="px-5 py-3 border-t border-[#e5e5e5] bg-[#fafafa] text-xs text-[#6e6e6e] flex justify-between items-center">
                        <span>Showing <span id="visibleCount"><?php echo $documents_result ? $documents_result->num_rows : 0; ?></span> documents</span>
                        <span>Total Available Copies: <?php echo number_format($stats['available_copies'] ?? 0); ?></span>
                    </div>
                </div>

                <!-- No Results Message (hidden by default) -->
                <div id="noResultsMessage" class="hidden bg-white border border-[#e5e5e5] rounded-md p-8 text-center mt-4">
                    <i class="fa-regular fa-circle-xmark text-4xl text-[#9e9e9e] mb-3"></i>
                    <p class="text-sm text-[#6e6e6e]">No documents match your filters.</p>
                    <button onclick="resetFilters()" class="mt-2 text-sm text-[#1e1e1e] underline">Clear filters</button>
                </div>

                <!-- Simple Pagination (if needed) -->
                <?php if ($documents_result && $documents_result->num_rows > 20): ?>
                    <div class="mt-4 flex justify-end">
                        <div class="pagination">
                            <button class="pagination-item disabled"><i class="fa-solid fa-chevron-left"></i></button>
                            <button class="pagination-item active">1</button>
                            <button class="pagination-item">2</button>
                            <button class="pagination-item">3</button>
                            <button class="pagination-item">4</button>
                            <button class="pagination-item">5</button>
                            <button class="pagination-item"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Floating Action Button -->
    <button onclick="openAddDocumentModal()" class="fab" title="Add New Document">
        <i class="fa-solid fa-plus"></i>
        <span class="fab-tooltip">Add Document</span>
    </button>

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
                    <i class="fa-solid fa-share-from-square mr-1"></i>
                    Process Bulk Distribution
                </button>
            </div>
        </div>
    </div>

    <!-- Add Document Modal -->
    <div id="addDocumentModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50 modal">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-lg p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Add New Document</h3>
                <button onclick="closeAddDocumentModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form id="addDocumentForm" onsubmit="return false;">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Document Name <span class="text-red-400">*</span></label>
                        <input type="text" id="add_document_name" required
                            placeholder="Enter document name"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            autocomplete="off">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Document Type <span class="text-red-400">*</span></label>
                        <select id="add_type_id" required class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="">Select Type</option>
                            <?php foreach ($document_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Serial Number</label>
                        <input type="text" id="add_serial_number"
                            placeholder="Leave empty for auto-generation"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            autocomplete="off">
                        <p class="text-xs text-[#6e6e6e] mt-1">Auto-generated if left empty (e.g., DOC202312345)</p>
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Origin</label>
                        <input type="text" id="add_origin"
                            placeholder="e.g., Courier, Mail, Internal"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            autocomplete="off">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Number of Copies <span class="text-red-400">*</span></label>
                        <input type="number" id="add_copies_received" required min="1" value="1"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Date Received <span class="text-red-400">*</span></label>
                        <input type="date" id="add_date_received" required
                            value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" onclick="closeAddDocumentModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="button" onclick="submitAddDocument()" id="addDocumentSubmitBtn"
                        class="px-4 py-2 text-sm bg-[#1e1e1e] text-white rounded-md hover:bg-[#2d2d2d]">
                        <i class="fa-regular fa-floppy-disk mr-1"></i>
                        Save Document
                    </button>
                </div>
            </form>
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

        // Add Document Modal Functions
        function openAddDocumentModal() {
            // Reset form
            document.getElementById('add_document_name').value = '';
            document.getElementById('add_type_id').value = '';
            document.getElementById('add_serial_number').value = '';
            document.getElementById('add_origin').value = '';
            document.getElementById('add_copies_received').value = '1';
            document.getElementById('add_date_received').value = '<?php echo date('Y-m-d'); ?>';

            document.getElementById('addDocumentModal').style.display = 'flex';
        }

        function closeAddDocumentModal() {
            document.getElementById('addDocumentModal').style.display = 'none';
        }

        function submitAddDocument() {
            const document_name = document.getElementById('add_document_name').value.trim();
            const type_id = document.getElementById('add_type_id').value;
            const serial_number = document.getElementById('add_serial_number').value.trim();
            const origin = document.getElementById('add_origin').value.trim();
            const copies_received = parseInt(document.getElementById('add_copies_received').value);
            const date_received = document.getElementById('add_date_received').value;

            // Validation
            if (!document_name) {
                showToast('Please enter document name', 'warning');
                return;
            }

            if (!type_id) {
                showToast('Please select a document type', 'warning');
                return;
            }

            if (!copies_received || copies_received < 1) {
                showToast('Number of copies must be at least 1', 'warning');
                return;
            }

            if (!date_received) {
                showToast('Please select date received', 'warning');
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('addDocumentSubmitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-regular fa-spinner fa-spin mr-1"></i> Saving...';
            submitBtn.disabled = true;

            // Submit via AJAX
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax_action=add_document&document_name=${encodeURIComponent(document_name)}&type_id=${type_id}&serial_number=${encodeURIComponent(serial_number)}&origin=${encodeURIComponent(origin)}&copies_received=${copies_received}&date_received=${date_received}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        closeAddDocumentModal();

                        // Reload the page after a short delay to show the new document
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
            const checkboxes = document.querySelectorAll('.bulk-checkbox, .bulk-checkbox-global');
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
                document.getElementById('selectAllCheckbox').checked = false;
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

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAllCheckbox').checked;
            document.querySelectorAll('.document-checkbox').forEach(cb => {
                cb.checked = selectAll;
                const docId = cb.value;
                if (selectAll) {
                    selectedDocuments.add(docId);
                } else {
                    selectedDocuments.delete(docId);
                }
            });
            updateSelectedCount();
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

                // Update select all checkbox
                const totalCheckboxes = document.querySelectorAll('.document-checkbox').length;
                const checkedCheckboxes = document.querySelectorAll('.document-checkbox:checked').length;
                document.getElementById('selectAllCheckbox').checked = totalCheckboxes === checkedCheckboxes;
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
            document.getElementById('selectAllCheckbox').checked = false;
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
                const docRow = document.querySelector(`.document-row[data-id="${docId}"]`);
                if (docRow) {
                    const docName = docRow.querySelector('td:nth-child(3)').textContent;
                    const available = docRow.dataset.available;

                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'p-3 border-b border-[#e5e5e5] last:border-b-0';
                    itemDiv.innerHTML = `
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <p class="text-sm font-medium">${docName}</p>
                                <p class="text-xs text-[#6e6e6e]">Available: ${available} copies</p>
                            </div>
                            <div class="w-24">
                                <input type="number" 
                                       class="bulk-copies w-full px-2 py-1 text-sm border border-[#e5e5e5] rounded-md"
                                       data-id="${docId}"
                                       min="1"
                                       max="${available}"
                                       value="1"
                                       placeholder="Copies">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
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

            const rows = document.querySelectorAll('.document-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const docType = row.getAttribute('data-type');
                const available = parseInt(row.getAttribute('data-available'));
                const docName = row.getAttribute('data-name');
                const docSerial = row.getAttribute('data-serial');

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
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide no results message
            const table = document.getElementById('documentsTable');
            const noResults = document.getElementById('noResultsMessage');

            if (visibleCount === 0) {
                table.classList.add('hidden');
                noResults.classList.remove('hidden');
            } else {
                table.classList.remove('hidden');
                noResults.classList.add('hidden');
            }

            document.getElementById('visibleCount').textContent = visibleCount;
            showToast(`Showing ${visibleCount} document(s)`, 'info', 2000);
        }

        function resetFilters() {
            document.getElementById('typeFilter').value = '';
            document.getElementById('stockFilter').value = 'all';
            document.getElementById('searchInput').value = '';

            const rows = document.querySelectorAll('.document-row');
            rows.forEach(row => {
                row.style.display = '';
            });

            document.getElementById('documentsTable').classList.remove('hidden');
            document.getElementById('noResultsMessage').classList.add('hidden');
            document.getElementById('visibleCount').textContent = rows.length;

            showToast('Filters cleared', 'info', 2000);
        }

        // Table sorting
        let sortDirection = 'asc';
        let lastSortedColumn = -1;

        function sortTable(columnIndex) {
            const tbody = document.getElementById('tableBody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            // Toggle sort direction if same column
            if (lastSortedColumn === columnIndex) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortDirection = 'asc';
                lastSortedColumn = columnIndex;
            }

            // Sort rows
            rows.sort((a, b) => {
                const aCol = a.querySelectorAll('td')[columnIndex]?.textContent.trim() || '';
                const bCol = b.querySelectorAll('td')[columnIndex]?.textContent.trim() || '';

                // Check if numeric (for Available and Total columns)
                if (columnIndex === 5 || columnIndex === 6) {
                    const aNum = parseInt(aCol) || 0;
                    const bNum = parseInt(bCol) || 0;
                    return sortDirection === 'asc' ? aNum - bNum : bNum - aNum;
                }

                // String comparison
                const comparison = aCol.localeCompare(bCol);
                return sortDirection === 'asc' ? comparison : -comparison;
            });

            // Reorder table
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));

            showToast(`Sorted by column`, 'info', 1500);
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
            const addDocumentModal = document.getElementById('addDocumentModal');

            if (event.target == distributeModal) {
                closeDistributeModal();
            }
            if (event.target == bulkModal) {
                closeBulkModal();
            }
            if (event.target == addDocumentModal) {
                closeAddDocumentModal();
            }
        }

        // ESC key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDistributeModal();
                closeBulkModal();
                closeAddDocumentModal();
            }
        });
    </script>
</body>

</html>