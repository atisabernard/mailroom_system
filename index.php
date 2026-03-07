<!DOCTYPE html>
<html>
<head>
<title>Mail Room System</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

<div class="flex">

<?php include 'components/sidebar.php'; ?>

<div class="flex-1 p-8">

<h1 class="text-2xl font-bold mb-6">Dashboard</h1>

<div class="grid grid-cols-4 gap-6">

<div class="bg-white shadow p-6 rounded">
<h2 class="text-lg font-semibold">Newspapers</h2>
<p class="text-gray-500">Manage received newspapers</p>
</div>

<div class="bg-white shadow p-6 rounded">
<h2 class="text-lg font-semibold">Documents</h2>
<p class="text-gray-500">Legislation & reports</p>
</div>

<div class="bg-white shadow p-6 rounded">
<h2 class="text-lg font-semibold">Distribution</h2>
<p class="text-gray-500">Track distributed items</p>
</div>

<div class="bg-white shadow p-6 rounded">
<h2 class="text-lg font-semibold">Parcels</h2>
<p class="text-gray-500">Receive & pickup parcels</p>
</div>

</div>

</div>

</div>

</body>
</html>