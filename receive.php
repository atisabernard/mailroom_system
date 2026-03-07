<?php
require_once './config/db.php';
session_start();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Generate unique tracking ID
    $tracking_id = 'PRCL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    $description = $_POST['description'];
    $sender = $_POST['sender'];
    $addressed_to = $_POST['addressed_to'];
    $received_by = $_POST['received_by'];
    $date_received = $_POST['date_received'];

    $stmt = $conn->prepare("INSERT INTO parcels_received (description, sender, addressed_to, date_received, received_by, tracking_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $description, $sender, $addressed_to, $date_received, $received_by, $tracking_id);

    if ($stmt->execute()) {
        $message = "Parcel received successfully! Tracking ID: $tracking_id";
    } else {
        $error = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Parcel - Mailroom</title>
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
    <div class="flex h-screen">
        <?php include './sidebar.php'; ?>

        <main class="flex-1 ml-60 overflow-y-auto">
            <!-- Simple header -->
            <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
                <h1 class="text-2xl font-medium text-[#1e1e1e]">Receive Parcel</h1>
                <p class="text-sm text-[#6e6e6e] mt-1">Register a new parcel with tracking ID</p>
            </div>

            <div class="p-8">
                <div class="max-w-4xl mx-auto">
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

                    <!-- Form - simple container -->
                    <div class="bg-white border border-[#e5e5e5] rounded-md p-6 mb-8">
                        <form method="POST" action="">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="md:col-span-2">
                                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Description</label>
                                    <textarea name="description" rows="3" required
                                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                                        placeholder="Enter parcel description"></textarea>
                                </div>

                                <div>
                                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Sender</label>
                                    <input type="text" name="sender" required
                                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                                        placeholder="Sender name">
                                </div>

                                <div>
                                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Addressed To</label>
                                    <input type="text" name="addressed_to" required
                                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                                        placeholder="Recipient name">
                                </div>

                                <div>
                                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Date Received</label>
                                    <input type="date" name="date_received" required value="<?php echo date('Y-m-d'); ?>"
                                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]">
                                </div>

                                <div>
                                    <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Received By</label>
                                    <input type="text" name="received_by" required
                                        class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                                        placeholder="Staff name">
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button type="reset"
                                    class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                                    Clear
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                                    <i class="fa-regular fa-floppy-disk mr-1 text-[#6e6e6e]"></i>
                                    Receive Parcel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Recent Parcels - simple table -->
                    <div>
                        <h2 class="text-sm font-medium text-[#1e1e1e] mb-3">Recent Parcels</h2>
                        <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                            <table>
                                <thead>
                                    <tr class="bg-[#fafafa]">
                                        <th class="text-xs">Tracking ID</th>
                                        <th class="text-xs">Description</th>
                                        <th class="text-xs">Sender</th>
                                        <th class="text-xs">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recent = $conn->query("SELECT * FROM parcels_received ORDER BY date_received DESC LIMIT 5");
                                    while ($row = $recent->fetch_assoc()):
                                    ?>
                                        <tr class="hover:bg-[#fafafa]">
                                            <td class="text-sm font-mono text-[#1e1e1e]"><?php echo $row['tracking_id']; ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo substr($row['description'], 0, 50); ?>...</td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo $row['sender']; ?></td>
                                            <td class="text-sm text-[#1e1e1e]"><?php echo $row['date_received']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>