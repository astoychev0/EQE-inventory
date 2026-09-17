<?php
require_once 'db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: index.php");
    exit;
}

// Централизиран списък с категории (същият като в add_item.php)
$categories = [
    "Sensors & Boards", 
    "Motors & Drivers", 
    "Tools & Equipment", 
    "Cables & Connectors", 
    "Pneumatics", 
    "Consumables", 
    "Others"
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $min_quantity = (int)($_POST['min_quantity'] ?? 0);

    if (empty($name)) {
        $error = "Please enter an item name.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE items SET name = ?, category = ?, location = ?, quantity = ?, min_quantity = ? WHERE id = ?");
            $stmt->execute([$name, $category, $location, $quantity, $min_quantity, $id]);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - EQE Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --ottobock-blue: #003883;
            --ottobock-light-blue: #0072ce;
        }

        body {
            background-color: #ffffff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #0f172a;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: -50px;
            right: -120px;
            width: 650px;
            height: 700px;
            background: linear-gradient(135deg, rgba(0, 56, 131, 0.08) 0%, rgba(0, 114, 206, 0.02) 100%);
            transform: skewY(-10deg);
            z-index: -1;
            border-radius: 50px;
        }

        .navbar-custom {
            background: #ffffff;
            border-bottom: 4px solid var(--ottobock-blue);
            box-shadow: 0 10px 30px rgba(0, 56, 131, 0.06);
            padding: 18px 0;
        }

        .brand-title {
            color: var(--ottobock-blue);
            font-weight: 900;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 15px 35px rgba(0, 56, 131, 0.06);
        }

        .btn-ottobock {
            background: linear-gradient(135deg, var(--ottobock-blue), var(--ottobock-light-blue));
            color: #ffffff;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            box-shadow: 0 6px 20px rgba(0, 56, 131, 0.25);
        }

        .btn-ottobock:hover { color: #fff; }
    </style>
</head>
<body>

    <nav class="navbar navbar-custom mb-5">
        <div class="container d-flex align-items-center justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-4" href="index.php">
                <img src="logo-ottobock 3.png" alt="Ottobock" style="height: 48px; object-fit: contain;">
                <div class="vr opacity-25" style="height: 36px; background-color: var(--ottobock-blue); width: 2px;"></div>
                <span class="brand-title">EQE Inventory Tracking</span>
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4 fw-bold">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </nav>

    <div class="container" style="max-width: 650px;">
        <div class="form-card p-4 p-md-5">
            <h3 class="fw-extrabold mb-4 pb-3 border-bottom" style="color: var(--ottobock-blue); font-weight: 900;">
                <i class="fa-solid fa-pen-to-square me-2"></i>Edit Item #<?= sprintf('%03d', $item['id']) ?>
            </h3>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 rounded-3 mb-4 fw-semibold"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase">Item Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-lg fs-6 rounded-3" value="<?= htmlspecialchars($_POST['name'] ?? $item['name']) ?>" required>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase">Category</label>
                        <select name="category" class="form-select form-select-lg fs-6 rounded-3">
                            <?php 
                            $selected_category = $_POST['category'] ?? $item['category'];
                            foreach ($categories as $cat): 
                            ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= ($selected_category === $cat) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase">Location (EQE Room)</label>
                        <input type="text" name="location" class="form-control form-control-lg fs-6 rounded-3" value="<?= htmlspecialchars($_POST['location'] ?? $item['location']) ?>">
                    </div>
                </div>

                <div class="row g-3 mb-5">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase">Current Quantity</label>
                        <input type="number" name="quantity" class="form-control form-control-lg fs-6 rounded-3" value="<?= htmlspecialchars($_POST['quantity'] ?? $item['quantity']) ?>" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase">Min. Reorder Limit</label>
                        <input type="number" name="min_quantity" class="form-control form-control-lg fs-6 rounded-3" value="<?= htmlspecialchars($_POST['min_quantity'] ?? $item['min_quantity']) ?>" min="0">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="index.php" class="btn btn-light rounded-pill px-4 fw-bold text-secondary">Cancel</a>
                    <button type="submit" class="btn btn-ottobock px-5">Update Item</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>