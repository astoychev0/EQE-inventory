<?php
require_once 'db.php';

// 1. QUICK QUANTITY ADJUSTMENT (+1 / -1) LOGIC
if (isset($_GET['action'], $_GET['id'])) {
    $item_id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'increase') {
        $pdo->prepare("UPDATE items SET quantity = quantity + 1 WHERE id = :id")->execute(['id' => $item_id]);
    } elseif ($action === 'decrease') {
        $pdo->prepare("UPDATE items SET quantity = GREATEST(0, quantity - 1) WHERE id = :id")->execute(['id' => $item_id]);
    }

    // Redirect back to keep page and filters intact
    $queryParams = $_GET;
    unset($queryParams['action'], $queryParams['id']);
    $redirectUrl = 'index.php' . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
    header("Location: $redirectUrl");
    exit;
}

// 2. FILTERS AND SORTING PARAMETERS
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$location_filter = trim($_GET['location'] ?? '');
$sort_by = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'DESC';

// 3. PAGINATION SETTINGS
$limit = 10; // Брой артикули на страница
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = (int)(($page - 1) * $limit);

// Helper function to keep current parameters in pagination and sorting links
function getUrlWithParams($extraParams = []) {
    $params = $_GET;
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    $params = array_merge($params, $extraParams);
    return 'index.php?' . http_build_query($params);
}

function getSortUrl($column, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    return getUrlWithParams(['sort' => $column, 'order' => $newOrder, 'page' => 1]);
}

// Global Statistics
$total_items = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$low_stock = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE quantity <= min_quantity AND quantity > 0")->fetchColumn();
$out_of_stock = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE quantity = 0")->fetchColumn();

// Fetch unique categories and locations for filter dropdowns
$categories = $pdo->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location FROM items WHERE location IS NOT NULL AND location != '' ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);

// Build SQL Query for filtered items
$where_clauses = ["1=1"];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(name LIKE :search OR category LIKE :search OR location LIKE :search)";
    $params['search'] = "%$search%";
}

if ($category_filter !== '') {
    $where_clauses[] = "category = :category";
    $params['category'] = $category_filter;
}

if ($location_filter !== '') {
    $where_clauses[] = "location = :location";
    $params['location'] = $location_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// Get total count of filtered items
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE $where_sql");
$count_stmt->execute($params);
$total_filtered_items = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_filtered_items / $limit));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = (int)(($page - 1) * $limit);
}

// Sorting validation
$allowed_sorts = ['id', 'name', 'category', 'location', 'quantity'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'id';
}
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Fetch paginated data
$query = "SELECT * FROM items WHERE $where_sql ORDER BY $sort_by $order LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EQE Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --ottobock-blue: #003883;
            --ottobock-blue-hover: #00285e;
            --ottobock-blue-active: #001a40;
            --ottobock-accent: #0072ce;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-sub: #475569;
            --border-color: #cbd5e1;
            --table-header-bg: #f1f5f9;
            --table-row-alt: #f8fafc;
            --input-bg: #ffffff;
            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.08);
            --shadow-md: 0 4px 12px rgba(15, 23, 42, 0.05);
        }

        /* Премахване на плавни анимации за бързина */
        *, *::before, *::after {
            transition: none !important;
            animation: none !important;
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            min-height: 100vh;
            letter-spacing: -0.01em;
        }

        /* Top Brand Navigation Bar */
        .navbar-custom {
            background: var(--card-bg);
            border-bottom: 3px solid var(--ottobock-blue);
            padding: 16px 0;
            box-shadow: var(--shadow-sm);
        }

        .brand-title {
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--ottobock-blue);
            letter-spacing: -0.03em;
        }

        /* Stat Cards */
        .card-stat {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .card-stat::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            background: var(--ottobock-blue);
        }

        .card-stat.warn::after { background: #f59e0b; }
        .card-stat.danger::after { background: #ef4444; }

        .stat-label {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-sub);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            margin-top: 8px;
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-main);
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            border: 1px solid transparent;
        }

        /* Filter Section & Custom Form Inputs */
        .filter-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px;
            box-shadow: var(--shadow-sm);
        }

        .form-control-custom, .form-select-custom {
            background-color: var(--input-bg) !important;
            color: var(--text-main) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .form-control-custom::placeholder {
            color: var(--text-sub);
            opacity: 0.7;
        }

        .input-group-text-custom {
            background-color: var(--input-bg) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-sub) !important;
        }

        /* Table Styling */
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table {
            color: var(--text-main) !important;
            margin-bottom: 0;
            border-color: var(--border-color);
        }

        .table thead th {
            background: var(--table-header-bg);
            color: var(--text-main);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 800;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody td {
            padding: 14px 18px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
            color: var(--text-main);
        }

        .table tbody tr {
            background-color: var(--card-bg);
        }

        .table tbody tr:nth-child(even) {
            background-color: var(--table-row-alt);
        }

        /* Custom Status Badges */
        .badge-status {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.02em;
        }

        .badge-status-available {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge-status-warning {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .badge-status-danger {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* Fixed Brand Buttons */
        .btn-brand {
            background-color: var(--ottobock-blue) !important;
            color: #ffffff !important;
            font-weight: 700;
            border-radius: 8px;
            padding: 9px 18px;
            border: 1px solid var(--ottobock-blue) !important;
            font-size: 0.88rem;
            outline: none !important;
            box-shadow: none !important;
        }

        .btn-brand:hover {
            background-color: var(--ottobock-blue-hover) !important;
            border-color: var(--ottobock-blue-hover) !important;
            color: #ffffff !important;
        }

        .btn-brand:active, .btn-brand:focus-visible {
            background-color: var(--ottobock-blue-active) !important;
            border-color: var(--ottobock-blue-active) !important;
            color: #ffffff !important;
        }

        .btn-qty-action {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-main);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
        }

        .btn-qty-action:hover {
            background-color: var(--table-header-bg);
            color: var(--text-main);
        }

        .btn-table-action {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .btn-table-edit {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        .btn-table-delete {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .id-badge {
            font-family: 'JetBrains Mono', monospace;
            background: var(--table-header-bg);
            color: var(--text-sub);
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 0.78rem;
            font-weight: 700;
        }

        .category-chip {
            background: rgba(2, 132, 199, 0.08);
            color: #0284c7;
            border: 1px solid rgba(2, 132, 199, 0.2);
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* Pagination Static Styling */
        .pagination .page-link {
            background-color: var(--card-bg) !important;
            color: var(--text-main) !important;
            border-color: var(--border-color) !important;
            border-radius: 6px;
            margin: 0 2px;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 6px 12px;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--ottobock-blue) !important;
            border-color: var(--ottobock-blue) !important;
            color: #ffffff !important;
        }

        .pagination .page-item.disabled .page-link {
            opacity: 0.35;
            background-color: var(--card-bg) !important;
            color: var(--text-sub) !important;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-custom mb-4">
        <div class="container d-flex align-items-center justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-3" href="index.php">
                <img src="logo-ottobock 3.png" alt="Ottobock" style="height: 38px; object-fit: contain;">
                <div class="vr opacity-25" style="height: 26px; width: 1px; background-color: var(--text-sub);"></div>
                <div class="d-flex align-items-center gap-2">
                    <span class="brand-title">EQE Inventory</span>
                </div>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="add_item.php" class="btn btn-brand d-flex align-items-center gap-2">
                    <i class="fa-solid fa-plus fs-6"></i>
                    <span>Add New Item</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card-stat d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Total Inventory Items</div>
                        <div class="stat-value"><?= number_format($total_items) ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(0, 56, 131, 0.1); color: var(--ottobock-accent); border-color: var(--border-color);">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-stat warn d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Low Stock Warnings</div>
                        <div class="stat-value text-warning"><?= number_format($low_stock) ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-stat danger d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-label">Out of Stock Items</div>
                        <div class="stat-value text-danger"><?= number_format($out_of_stock) ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search Controls Bar -->
        <div class="filter-card mb-4">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text input-group-text-custom border-end-0">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" name="search" class="form-control form-control-custom border-start-0 ps-0" placeholder="Search by name, category, or location..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select form-select-custom" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="location" class="form-select form-select-custom" onchange="this.form.submit()">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>" <?= $location_filter === $loc ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-brand w-100">Filter</button>
                    <?php if ($search || $category_filter || $location_filter): ?>
                        <a href="index.php" class="btn btn-outline-secondary d-flex align-items-center justify-content-center px-3" style="border-radius: 8px; border-color: var(--border-color); color: var(--text-main);" title="Clear Filters">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Inventory Data Table -->
        <div class="table-container mb-4">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">
                                <a href="<?= getSortUrl('id', $sort_by, $order) ?>" class="text-decoration-none text-reset d-flex align-items-center gap-1">
                                    ID <?= $sort_by === 'id' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('name', $sort_by, $order) ?>" class="text-decoration-none text-reset d-flex align-items-center gap-1">
                                    Item Name <?= $sort_by === 'name' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('category', $sort_by, $order) ?>" class="text-decoration-none text-reset d-flex align-items-center gap-1">
                                    Category <?= $sort_by === 'category' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('location', $sort_by, $order) ?>" class="text-decoration-none text-reset d-flex align-items-center gap-1">
                                    Location <?= $sort_by === 'location' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('quantity', $sort_by, $order) ?>" class="text-decoration-none text-reset d-flex align-items-center gap-1">
                                    Quantity <?= $sort_by === 'quantity' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                                </a>
                            </th>
                            <th>Stock Status</th>
                            <th style="width: 100px;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-sub fw-medium">
                                    <i class="fa-solid fa-folder-open fs-2 d-block mb-2 opacity-50"></i>
                                    No records match the specified search or filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <span class="id-badge">
                                            #<?= sprintf('%03d', $item['id']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold"><?= htmlspecialchars($item['name']) ?></td>
                                    <td>
                                        <span class="category-chip">
                                            <?= htmlspecialchars($item['category'] ?: 'General') ?>
                                        </span>
                                    </td>
                                    <td class="fw-medium text-sub">
                                        <i class="fa-solid fa-location-dot me-1 text-danger opacity-75"></i>
                                        <?= htmlspecialchars($item['location'] ?: 'Unassigned') ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold font-monospace <?= ($item['quantity'] <= $item['min_quantity']) ? 'text-danger' : '' ?>">
                                                <?= $item['quantity'] ?> pcs
                                            </span>
                                            <!-- QUICK + / - CONTROLS -->
                                            <div class="d-inline-flex gap-1 ms-1">
                                                <a href="<?= getUrlWithParams(['action' => 'decrease', 'id' => $item['id']]) ?>" class="btn-qty-action" title="Decrease by 1">-</a>
                                                <a href="<?= getUrlWithParams(['action' => 'increase', 'id' => $item['id']]) ?>" class="btn-qty-action" title="Increase by 1">+</a>
                                            </div>
                                        </div>
                                        <div class="text-sub font-monospace" style="font-size: 0.7rem;">(min threshold: <?= $item['min_quantity'] ?>)</div>
                                    </td>
                                    <td>
                                        <?php
                                        if ($item['quantity'] == 0) {
                                            echo '<span class="badge-status badge-status-danger"><i class="fa-solid fa-circle-xmark"></i> Out of Stock</span>';
                                        } elseif ($item['quantity'] <= $item['min_quantity']) {
                                            echo '<span class="badge-status badge-status-warning"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</span>';
                                        } else {
                                            echo '<span class="badge-status badge-status-available"><i class="fa-solid fa-check"></i> In Stock</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="edit_item.php?id=<?= $item['id'] ?>" class="btn-table-action btn-table-edit" title="Edit Item">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="delete_item.php?id=<?= $item['id'] ?>" class="btn-table-action btn-table-delete" title="Delete Item" onclick="return confirm('Are you sure you want to delete this item?');">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Bar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="text-sub small fw-semibold">
                Showing <span class="text-main fw-bold"><?= $total_filtered_items > 0 ? $offset + 1 : 0 ?></span> to <span class="text-main fw-bold"><?= min($offset + $limit, $total_filtered_items) ?></span> of <span class="text-main fw-bold"><?= $total_filtered_items ?></span> items
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <!-- Previous Button -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page <= 1 ? '#' : getUrlWithParams(['page' => $page - 1]) ?>">
                            <i class="fa-solid fa-chevron-left me-1"></i> Prev
                        </a>
                    </li>
                    
                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                            <a class="page-link" href="<?= getUrlWithParams(['page' => $i]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- Next Button -->
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page >= $total_pages ? '#' : getUrlWithParams(['page' => $page + 1]) ?>">
                            Next <i class="fa-solid fa-chevron-right ms-1"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

    </div>

</body>
</html>