<?php
require_once './config/db.php';

// Get distribution data for printing
$result = $conn->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM documents WHERE date_received = d.date_distributed) as documents_count,
           (SELECT COUNT(*) FROM parcels_received WHERE date_received = d.date_distributed) as parcels_count
    FROM distribution d 
    ORDER BY d.date_distributed DESC 
    LIMIT 30
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution Sheet - Mailroom</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .no-print {
                display: none;
            }
        }

        /* Simple table styling */
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #e5e5e5;
            font-weight: 500;
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e5e5;
        }
    </style>
</head>

<body class="bg-white">
    <?php include './sidebar.php'; ?>
    <div class="max-w-6xl mx-auto p-8">
        <!-- Simple header -->
        <div class="border-b border-[#e5e5e5] pb-6 mb-6">
            <h1 class="text-2xl font-medium text-[#1e1e1e]">Distribution Sheet</h1>
            <p class="text-sm text-[#6e6e6e] mt-1">Generated: <?php echo date('F j, Y, g:i a'); ?></p>
        </div>

        <!-- Summary - simple cards with borders, no gradients -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="border border-[#e5e5e5] rounded-md p-4">
                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Documents</p>
                <p class="text-2xl font-medium text-[#1e1e1e] mt-1">
                    <?php echo $conn->query("SELECT COUNT(*) as total FROM documents")->fetch_assoc()['total']; ?>
                </p>
            </div>
            <div class="border border-[#e5e5e5] rounded-md p-4">
                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Parcels</p>
                <p class="text-2xl font-medium text-[#1e1e1e] mt-1">
                    <?php echo $conn->query("SELECT COUNT(*) as total FROM parcels_received")->fetch_assoc()['total']; ?>
                </p>
            </div>
            <div class="border border-[#e5e5e5] rounded-md p-4">
                <p class="text-xs text-[#6e6e6e] uppercase tracking-wide">Pending</p>
                <p class="text-2xl font-medium text-[#1e1e1e] mt-1">
                    <?php
                    $pending = $conn->query("
                        SELECT COUNT(*) as total 
                        FROM parcels_received pr 
                        LEFT JOIN parcels_pickup pp ON pr.id = pp.parcel_id 
                        WHERE pp.id IS NULL
                    ")->fetch_assoc()['total'];
                    echo $pending;
                    ?>
                </p>
            </div>
        </div>

        <!-- Distribution Table - simple, no fancy styling -->
        <div class="border border-[#e5e5e5] rounded-md overflow-hidden">
            <table>
                <thead>
                    <tr class="bg-[#fafafa]">
                        <th class="text-xs font-medium text-[#6e6e6e]">Date</th>
                        <th class="text-xs font-medium text-[#6e6e6e]">Document Name</th>
                        <th class="text-xs font-medium text-[#6e6e6e]">Type</th>
                        <th class="text-xs font-medium text-[#6e6e6e] text-center">Received</th>
                        <th class="text-xs font-medium text-[#6e6e6e] text-center">Distributed</th>
                        <th class="text-xs font-medium text-[#6e6e6e]">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-[#fafafa]">
                            <td class="text-sm text-[#1e1e1e] whitespace-nowrap"><?php echo $row['date_distributed']; ?></td>
                            <td class="text-sm text-[#1e1e1e]"><?php echo $row['document_name']; ?></td>
                            <td class="text-sm text-[#1e1e1e]"><?php echo $row['type']; ?></td>
                            <td class="text-sm text-[#1e1e1e] text-center"><?php echo $row['received_qty']; ?></td>
                            <td class="text-sm text-[#1e1e1e] text-center"><?php echo $row['distributed_qty']; ?></td>
                            <td class="text-sm text-[#1e1e1e]">
                                <?php
                                $remaining = $row['received_qty'] - $row['distributed_qty'];
                                if ($remaining == 0) {
                                    echo 'Complete';
                                } else {
                                    echo 'Pending: ' . $remaining;
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer signature lines -->
        <div class="mt-10 grid grid-cols-3 gap-8 text-sm text-[#6e6e6e]">
            <div>Prepared by: ______________________</div>
            <div>Date: ______________________</div>
            <div>Signature: ______________________</div>
        </div>

        <!-- Simple print button -->
        <div class="mt-8 text-center no-print">
            <button onclick="window.print()"
                class="px-4 py-2 border border-[#e5e5e5] bg-white rounded-md text-sm text-[#1e1e1e] hover:bg-[#fafafa]">
                <i class="fa-regular fa-print mr-2 text-[#6e6e6e]"></i>
                Print
            </button>
        </div>
    </div>
</body>

</html>