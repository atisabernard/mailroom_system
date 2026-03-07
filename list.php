<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once './config/db.php';

// Handle Add Newspaper
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_submit'])) {
    $newspaper_name = $_POST['newspaper_name'];
    $newspaper_number = $_POST['newspaper_number'];
    $date_received = $_POST['date_received'];
    $received_by = $_POST['received_by'];

    $sql = "INSERT INTO newspapers (newspaper_name, newspaper_number, date_received, received_by) 
            VALUES ('$newspaper_name', '$newspaper_number', '$date_received', '$received_by')";

    if ($conn->query($sql)) {
        $success = "Newspaper added successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($conn->query("DELETE FROM newspapers WHERE id=$id")) {
        $success = "Newspaper deleted successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Get all newspapers
$result = $conn->query("SELECT * FROM newspapers ORDER BY date_received DESC, id DESC");

// Get statistics
$total = $conn->query("SELECT COUNT(*) as count FROM newspapers")->fetch_assoc()['count'];
$month = $conn->query("SELECT COUNT(*) as count FROM newspapers WHERE MONTH(date_received) = MONTH(CURDATE())")->fetch_assoc()['count'];
$today = $conn->query("SELECT COUNT(*) as count FROM newspapers WHERE date_received = CURDATE()")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newspapers - Mailroom</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f5f5f4;
        }

        /* Simple table styling */
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
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e5e5;
        }
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <div class="flex">
        <!-- Include sidebar -->
        <?php include './sidebar.php'; ?>

        <main class="flex-1 ml-60 min-h-screen">
            <!-- Simple header -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-medium text-[#1e1e1e]">Newspapers</h1>
                    <div class="flex gap-2">
                        <a href="./index.php" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-regular fa-home mr-1 text-[#6e6e6e]"></i>Dashboard
                        </a>
                        <button onclick="openAddModal()" class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                            <i class="fa-regular fa-plus mr-1 text-[#6e6e6e]"></i>Add Newspaper
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Simple messages -->
                <?php if (isset($success)): ?>
                    <div class="mb-4 p-3 border border-[#e5e5e5] bg-white rounded-md text-sm text-[#1e1e1e]">
                        <i class="fa-regular fa-circle-check mr-2 text-[#4a4a4a]"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="mb-4 p-3 border border-[#e5e5e5] bg-white rounded-md text-sm text-[#1e1e1e]">
                        <i class="fa-regular fa-circle-exclamation mr-2 text-[#4a4a4a]"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Stats - simple cards with borders, no gradients -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-4">
                        <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Total Newspapers</p>
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

                <!-- Search - simple -->
                <div class="mb-4">
                    <input type="text" id="searchInput" placeholder="Search newspapers..."
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                </div>

                <!-- Newspapers table - simple container -->
                <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                    <table>
                        <thead>
                            <tr class="bg-[#fafafa]">
                                <th class="text-xs">ID</th>
                                <th class="text-xs">Name</th>
                                <th class="text-xs">Issue</th>
                                <th class="text-xs">Date Received</th>
                                <th class="text-xs">Received By</th>
                                <th class="text-xs">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-[#fafafa]">
                                        <td class="text-sm text-[#6e6e6e]"><?php echo $row['id']; ?></td>
                                        <td class="text-sm font-medium text-[#1e1e1e]"><?php echo htmlspecialchars($row['newspaper_name']); ?></td>
                                        <td class="text-sm text-[#1e1e1e]"><?php echo htmlspecialchars($row['newspaper_number']); ?></td>
                                        <td class="text-sm text-[#1e1e1e]"><?php echo date('M j, Y', strtotime($row['date_received'])); ?></td>
                                        <td class="text-sm text-[#1e1e1e]"><?php echo htmlspecialchars($row['received_by']); ?></td>
                                        <td class="text-sm">
                                            <a href="?delete=<?php echo $row['id']; ?>"
                                                onclick="return confirm('Delete this newspaper?')"
                                                class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-sm text-[#6e6e6e] text-center py-8">
                                        No newspapers found. Add one to get started.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Modal - simple, no gradient -->
    <div id="addModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Add Newspaper</h3>
                <button onclick="closeAddModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-regular fa-xmark"></i>
                </button>
            </div>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Newspaper Name</label>
                    <input type="text" name="newspaper_name" required
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Issue/Number</label>
                    <input type="text" name="newspaper_number" required
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Date Received</label>
                    <input type="date" name="date_received" required value="<?php echo date('Y-m-d'); ?>"
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Received By</label>
                    <input type="text" name="received_by" required
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeAddModal()"
                        class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="submit" name="add_submit"
                        class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Add Newspaper
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        // Search
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchText = this.value.toLowerCase();
            let rows = document.querySelectorAll('#tableBody tr');

            rows.forEach(row => {
                if (row.querySelector('td[colspan]')) return; // Skip "no results" row
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target == modal) {
                closeAddModal();
            }
        }
    </script>
</body>

</html>