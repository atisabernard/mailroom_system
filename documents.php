<?php
// documents/list.php
require_once './config/db.php';
session_start();

$message = '';
$error = '';

// Handle Add Document
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_submit'])) {
    $document_name = $_POST['document_name'];
    $type = $_POST['type'];
    $origin = $_POST['origin'];
    $copies_received = (int)$_POST['copies_received'];
    $date_received = $_POST['date_received'];

    $stmt = $conn->prepare("INSERT INTO documents (document_name, type, origin, copies_received, date_received) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $document_name, $type, $origin, $copies_received, $date_received);

    if ($stmt->execute()) {
        $message = "Document added successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $stmt->close();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Document deleted successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $stmt->close();
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_submit'])) {
    $id = (int)$_POST['document_id'];
    $document_name = $_POST['document_name'];
    $type = $_POST['type'];
    $origin = $_POST['origin'];
    $copies_received = (int)$_POST['copies_received'];
    $date_received = $_POST['date_received'];

    $stmt = $conn->prepare("UPDATE documents SET document_name = ?, type = ?, origin = ?, copies_received = ?, date_received = ? WHERE id = ?");
    $stmt->bind_param("sssisi", $document_name, $type, $origin, $copies_received, $date_received, $id);

    if ($stmt->execute()) {
        $message = "Document updated successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $stmt->close();
}

// Get all documents
$documents = $conn->query("SELECT * FROM documents ORDER BY date_received DESC, id DESC");
if (!$documents) {
    $error = "Error fetching documents: " . $conn->error;
}

// Get statistics
$total_result = $conn->query("SELECT COUNT(*) as count FROM documents");
$total = $total_result ? $total_result->fetch_assoc()['count'] : 0;

$month_result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE MONTH(date_received) = MONTH(CURDATE())");
$month = $month_result ? $month_result->fetch_assoc()['count'] : 0;

$today_result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE date_received = CURDATE()");
$today = $today_result ? $today_result->fetch_assoc()['count'] : 0;

// Get unique types for filter
$types_result = $conn->query("SELECT DISTINCT type FROM documents WHERE type IS NOT NULL AND type != '' ORDER BY type");
$types = [];
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $types[] = $row['type'];
    }
}

// Get all documents for JavaScript (limited to recent 100 to avoid huge data)
$docs_for_js = [];
$docs_result = $conn->query("SELECT id, document_name, type, origin, copies_received, date_received FROM documents ORDER BY date_received DESC LIMIT 100");
if ($docs_result) {
    while ($row = $docs_result->fetch_assoc()) {
        $docs_for_js[] = $row;
    }
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
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <div class="flex">
        <!-- Sidebar - adjust path as needed -->
        <?php include './sidebar.php'; ?>

        <main class="flex-1 ml-60 min-h-screen">
            <!-- Header -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-medium text-[#1e1e1e]">Documents</h1>
                    <div class="flex gap-2">
                        <a href="../index.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-regular fa-home mr-1 text-[#6e6e6e]"></i>Dashboard
                        </a>
                        <button onclick="openAddModal()" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-regular fa-plus mr-1 text-[#6e6e6e]"></i>Add Document
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 p-3 border border-[#e5e5e5] bg-white rounded-md text-sm text-[#1e1e1e]">
                        <i class="fa-regular fa-circle-check mr-2 text-[#4a4a4a]"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 p-3 border border-[#e5e5e5] bg-white rounded-md text-sm text-[#1e1e1e]">
                        <i class="fa-regular fa-circle-exclamation mr-2 text-[#4a4a4a]"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

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
                    <div class="flex flex-col md:flex-row gap-3">
                        <div class="flex-1">
                            <input type="text" id="searchInput" placeholder="Search by name, origin, or type..."
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                        </div>
                        <select id="typeFilter" class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="all">All Types</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo htmlspecialchars(strtolower($type)); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="clearFilters()" class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-regular fa-rotate-right mr-1 text-[#6e6e6e]"></i>Clear
                        </button>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr class="bg-[#fafafa]">
                                    <th class="text-xs">ID</th>
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
                                    <?php while ($row = $documents->fetch_assoc()): ?>
                                        <tr class="hover:bg-[#fafafa] document-row"
                                            data-type="<?php echo htmlspecialchars(strtolower($row['type'] ?? '')); ?>"
                                            data-search="<?php echo htmlspecialchars(strtolower(($row['document_name'] ?? '') . ' ' . ($row['origin'] ?? '') . ' ' . ($row['type'] ?? ''))); ?>">
                                            <td class="text-sm text-[#6e6e6e]"><?php echo $row['id']; ?></td>
                                            <td class="text-sm font-medium text-[#1e1e1e]"><?php echo htmlspecialchars($row['document_name'] ?? ''); ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo htmlspecialchars($row['type'] ?? ''); ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo htmlspecialchars($row['origin'] ?? ''); ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo $row['copies_received'] ?? 0; ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo $row['date_received'] ? date('M j, Y', strtotime($row['date_received'])) : ''; ?></td>
                                            <td class="text-sm">
                                                <div class="flex gap-2">
                                                    <button onclick="openEditModal(<?php echo $row['id']; ?>)"
                                                        class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                                        <i class="fa-regular fa-pen-to-square"></i>
                                                    </button>
                                                    <a href="?delete=<?php echo $row['id']; ?>"
                                                        onclick="return confirm('Delete this document?')"
                                                        class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                                        <i class="fa-regular fa-trash-can"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-sm text-[#6e6e6e] text-center py-8">
                                            No documents found. Add one to get started.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Add Document</h3>
                <button onclick="closeAddModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-regular fa-xmark"></i>
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
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Type</label>
                        <select name="type" required id="add_type"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="">Select type</option>
                            <option value="Legislative Documents">Legislative Documents</option>
                            <option value="Committee Reports">Committee Reports</option>
                            <option value="Report from MDA">Report from MDA</option>
                            <option value="Report from CSD">Report from CSD</option>
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

                    <div class="pt-2">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide mb-2">Quick Select</p>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" onclick="setType('Legislative Documents')"
                                class="text-left px-2 py-1 text-xs border border-[#e5e5e5] rounded-md hover:bg-[#f5f5f4]">
                                Legislative
                            </button>
                            <button type="button" onclick="setType('Committee Reports')"
                                class="text-left px-2 py-1 text-xs border border-[#e5e5e5] rounded-md hover:bg-[#f5f5f4]">
                                Committee
                            </button>
                            <button type="button" onclick="setType('Report from MDA')"
                                class="text-left px-2 py-1 text-xs border border-[#e5e5e5] rounded-md hover:bg-[#f5f5f4]">
                                MDA Report
                            </button>
                            <button type="button" onclick="setType('Report from CSD')"
                                class="text-left px-2 py-1 text-xs border border-[#e5e5e5] rounded-md hover:bg-[#f5f5f4]">
                                CSD Report
                            </button>
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
    <div id="editModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Edit Document</h3>
                <button onclick="closeEditModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-regular fa-xmark"></i>
                </button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="document_id" id="edit_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Document Name</label>
                        <input type="text" name="document_name" id="edit_name" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Type</label>
                        <select name="type" id="edit_type" required
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="Legislative Documents">Legislative Documents</option>
                            <option value="Committee Reports">Committee Reports</option>
                            <option value="Report from MDA">Report from MDA</option>
                            <option value="Report from CSD">Report from CSD</option>
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

    <script>
        // Store document data for editing (from PHP)
        const documents = <?php echo json_encode($docs_for_js); ?>;

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
                document.getElementById('edit_name').value = doc.document_name || '';
                document.getElementById('edit_type').value = doc.type || '';
                document.getElementById('edit_origin').value = doc.origin || '';
                document.getElementById('edit_copies').value = doc.copies_received || 1;
                document.getElementById('edit_date').value = doc.date_received || '';
                document.getElementById('editModal').style.display = 'flex';
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Quick type selector
        function setType(type) {
            const select = document.getElementById('add_type');
            if (select) {
                select.value = type;
            }
        }

        // Search and filter
        document.getElementById('searchInput')?.addEventListener('input', filterTable);
        document.getElementById('typeFilter')?.addEventListener('change', filterTable);

        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const rows = document.getElementsByClassName('document-row');

            for (let row of rows) {
                const searchText = row.getAttribute('data-search') || '';
                const type = row.getAttribute('data-type') || '';

                const matchesSearch = searchText.includes(searchTerm);
                const matchesType = typeFilter === 'all' || type === typeFilter;

                row.style.display = matchesSearch && matchesType ? '' : 'none';
            }
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('typeFilter').value = 'all';
            filterTable();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        // Keyboard shortcut: ESC to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });
    </script>
</body>

</html>