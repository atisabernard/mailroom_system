<?php
require_once './config/db.php';
session_start();

$message = '';
$error = '';

// Handle pickup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['parcel_id'])) {
    $parcel_id = $_POST['parcel_id'];
    $picked_by = $_POST['picked_by'];
    $phone_number = $_POST['phone_number'];
    $designation = $_POST['designation'];
    $date_picked = date('Y-m-d');

    // Check if parcel exists and not picked up
    $check = $conn->query("SELECT * FROM parcels_received WHERE id = $parcel_id");
    if ($check->num_rows > 0) {
        $check_pickup = $conn->query("SELECT * FROM parcels_pickup WHERE parcel_id = $parcel_id");
        if ($check_pickup->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO parcels_pickup (parcel_id, picked_by, phone_number, designation, date_picked) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $parcel_id, $picked_by, $phone_number, $designation, $date_picked);

            if ($stmt->execute()) {
                $message = "Parcel picked up successfully!";
            } else {
                $error = "Error: " . $conn->error;
            }
        } else {
            $error = "This parcel has already been picked up!";
        }
    } else {
        $error = "Parcel not found!";
    }
}

// Get all parcels with pickup status
$parcels = $conn->query("
    SELECT pr.*, 
           pp.id as pickup_id, 
           pp.picked_by, 
           pp.date_picked,
           CASE WHEN pp.id IS NULL THEN 'Pending' ELSE 'Picked Up' END as status
    FROM parcels_received pr
    LEFT JOIN parcels_pickup pp ON pr.id = pp.parcel_id
    ORDER BY pr.date_received DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parcel Pickup - Mailroom</title>
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
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e5e5;
        }
    </style>
</head>

<body class="bg-[#f5f5f4]">
    <div class="flex">
        <?php include './sidebar.php'; ?>

        <main class="flex-1 ml-60 min-h-screen">
            <!-- Simple header -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <h1 class="text-2xl font-medium text-[#1e1e1e]">Parcel Pickup</h1>
                <p class="text-sm text-[#6e6e6e] mt-1">Process and track parcel pickups</p>
            </div>

            <div class="p-8">
                <!-- Simple messages -->
                <?php if ($message): ?>
                    <div class="mb-6 p-3 border border-[#e5e5e5] bg-white rounded-md text-sm text-[#1e1e1e]">
                        <i class="fa-regular fa-circle-check mr-2 text-[#4a4a4a]"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 p-3 border border-[#e5e5e5] bg-white rounded-md text-sm text-[#1e1e1e]">
                        <i class="fa-regular fa-circle-exclamation mr-2 text-[#4a4a4a]"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Search and filter - simple container -->
                <div class="bg-white border border-[#e5e5e5] rounded-md p-4 mb-6">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <input type="text" id="searchParcel" placeholder="Search by tracking ID, sender, or recipient..."
                                class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                        </div>
                        <select id="statusFilter" class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e] bg-white">
                            <option value="all">All</option>
                            <option value="pending">Pending</option>
                            <option value="picked-up">Picked Up</option>
                        </select>
                    </div>
                </div>

                <!-- Parcels table - simple container -->
                <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                    <table>
                        <thead>
                            <tr class="bg-[#fafafa]">
                                <th class="text-xs">Tracking ID</th>
                                <th class="text-xs">Description</th>
                                <th class="text-xs">Sender</th>
                                <th class="text-xs">Recipient</th>
                                <th class="text-xs">Date Received</th>
                                <th class="text-xs">Status</th>
                                <th class="text-xs">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="parcelTable">
                            <?php while ($parcel = $parcels->fetch_assoc()): ?>
                                <tr class="hover:bg-[#fafafa] parcel-row"
                                    data-status="<?php echo strtolower(str_replace(' ', '-', $parcel['status'])); ?>"
                                    data-search="<?php echo strtolower($parcel['tracking_id'] . ' ' . $parcel['sender'] . ' ' . $parcel['addressed_to']); ?>">
                                    <td class="text-sm font-mono text-[#1e1e1e]"><?php echo $parcel['tracking_id']; ?></td>
                                    <td class="text-sm text-[#1e1e1e]"><?php echo substr($parcel['description'], 0, 30); ?>...</td>
                                    <td class="text-sm text-[#1e1e1e]"><?php echo $parcel['sender']; ?></td>
                                    <td class="text-sm text-[#1e1e1e]"><?php echo $parcel['addressed_to']; ?></td>
                                    <td class="text-sm text-[#1e1e1e]"><?php echo $parcel['date_received']; ?></td>
                                    <td class="text-sm">
                                        <?php if ($parcel['status'] == 'Pending'): ?>
                                            <span class="text-[#9e9e9e]">Pending</span>
                                        <?php else: ?>
                                            <span class="text-[#1e1e1e]">Picked up</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm">
                                        <?php if ($parcel['status'] == 'Pending'): ?>
                                            <button onclick="openPickupModal(<?php echo $parcel['id']; ?>, '<?php echo $parcel['tracking_id']; ?>')"
                                                class="px-3 py-1 text-xs border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                                                <i class="fa-regular fa-truck mr-1"></i> Process
                                            </button>
                                        <?php else: ?>
                                            <button onclick="viewPickupDetails(<?php echo $parcel['id']; ?>)"
                                                class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                                <i class="fa-regular fa-circle-info"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Pickup Modal - simple, no gradient -->
    <div id="pickupModal" class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50" style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <h3 class="text-base font-medium text-[#1e1e1e] mb-4">Process Pickup</h3>
            <form method="POST" action="">
                <input type="hidden" name="parcel_id" id="modalParcelId">

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Tracking ID</label>
                    <input type="text" id="modalTrackingId" readonly
                        class="w-full px-3 py-2 text-sm bg-[#fafafa] border border-[#e5e5e5] rounded-md">
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Picked By</label>
                    <input type="text" name="picked_by" required
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Phone Number</label>
                    <input type="text" name="phone_number" required
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                </div>

                <div class="mb-4">
                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Designation</label>
                    <input type="text" name="designation" required
                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closePickupModal()"
                        class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Confirm Pickup
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openPickupModal(id, trackingId) {
            document.getElementById('pickupModal').style.display = 'flex';
            document.getElementById('modalParcelId').value = id;
            document.getElementById('modalTrackingId').value = trackingId;
        }

        function closePickupModal() {
            document.getElementById('pickupModal').style.display = 'none';
        }

        // Search and filter
        document.getElementById('searchParcel').addEventListener('input', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);

        function filterTable() {
            const searchTerm = document.getElementById('searchParcel').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.getElementsByClassName('parcel-row');

            for (let row of rows) {
                const searchText = row.getAttribute('data-search');
                const status = row.getAttribute('data-status');

                const matchesSearch = searchText.includes(searchTerm);
                const matchesStatus = statusFilter === 'all' || status === statusFilter;

                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('pickupModal');
            if (event.target == modal) {
                closePickupModal();
            }
        }
    </script>
</body>

</html>