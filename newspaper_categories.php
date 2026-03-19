<?php
// categories.php
include "./config/db.php";

$message = '';
$error = '';

if (isset($_POST['save'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);

    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO newspaper_categories (category_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $desc);

        if ($stmt->execute()) {
            $message = "Category added successfully!";
        } else {
            $error = "Error adding category.";
        }
        $stmt->close();
    } else {
        $error = "Category name is required";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM newspaper_categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Category deleted successfully!";
    } else {
        $error = "Error deleting category.";
    }
    $stmt->close();
}

$result = $conn->query("SELECT * FROM newspaper_categories ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newspaper Categories - Mailroom</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="./images/logo.png">
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
    <?php include './sidebar.php'; ?>

    <main class="ml-60 min-h-screen">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-[#e5e5e5] bg-white">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-medium text-[#1e1e1e]">Newspaper Categories</h1>
                <button onclick="openModal()"
                    class="px-3 py-1.5 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                    <i class="fa-regular fa-plus mr-1 text-[#6e6e6e]"></i> Add Category
                </button>
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

            <!-- Categories Table -->
            <div class="bg-white border border-[#e5e5e5] rounded-md overflow-hidden">
                <table>
                    <thead>
                        <tr class="bg-[#fafafa]">
                            <th class="text-xs">ID</th>
                            <th class="text-xs">Category Name</th>
                            <th class="text-xs">Description</th>
                            <th class="text-xs">Created</th>
                            <th class="text-xs">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-[#fafafa]">
                                    <td class="text-sm text-[#6e6e6e]"><?php echo $row['id']; ?></td>
                                    <td class="text-sm font-medium text-[#1e1e1e]">
                                        <?php echo htmlspecialchars($row['category_name']); ?>
                                    </td>
                                    <td class="text-sm text-[#1e1e1e]">
                                        <?php echo $row['description'] ? htmlspecialchars($row['description']) : '-'; ?>
                                    </td>
                                    <td class="text-sm text-[#1e1e1e]">
                                        <?php echo $row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : '-'; ?>
                                    </td>
                                    <td class="text-sm">
                                        <a href="?delete=<?php echo $row['id']; ?>"
                                            onclick="return confirm('Delete this category?')"
                                            class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-sm text-[#6e6e6e] text-center py-8">
                                    No categories found. Add one to get started.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="categoryModal"
        class="fixed inset-0 bg-[#000000] bg-opacity-20 hidden items-center justify-center z-50"
        style="display: none;">
        <div class="bg-white border border-[#e5e5e5] rounded-md w-full max-w-md p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#1e1e1e]">Add Category</h3>
                <button onclick="closeModal()" class="text-[#9e9e9e] hover:text-[#1e1e1e]">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Category Name</label>
                        <input type="text"
                            name="name"
                            required
                            placeholder="e.g., Daily News, Sports, Business"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"
                            autocomplete='off'>
                    </div>

                    <div>
                        <label class="block text-xs text-[#6e6e6e] uppercase tracking-wide mb-1">Description</label>
                        <textarea name="description"
                            placeholder="Optional description"
                            rows="3"
                            class="w-full px-3 py-2 text-sm border border-[#e5e5e5] rounded-md focus:outline-none focus:border-[#9e9e9e]"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button"
                        onclick="closeModal()"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        Cancel
                    </button>
                    <button name="save"
                        class="px-4 py-2 text-sm border border-[#e5e5e5] rounded-md bg-white hover:bg-[#f5f5f4] text-[#1e1e1e]">
                        <i class="fa-regular fa-floppy-disk mr-1 text-[#6e6e6e]"></i>
                        Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('categoryModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('categoryModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // ESC key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>

</html>