<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config.php';
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

$msg = '';
// Show delete success message if redirected after deletion
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $msg = "✅ Category deleted successfully.";
}

// Handle category deletion
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: admin_blog_categories.php?msg=deleted");
        exit;
    } else {
        $msg = "❌ Error deleting category: " . $stmt->error;
    }
}

// Handle new category creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if ($name !== '') {
        // Fix slug generation: allow uppercase letters, then strtolower, trim hyphens
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');

        $stmt = $conn->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $slug);
        if ($stmt->execute()) {
            $msg = "✅ Category '" . htmlspecialchars($name) . "' created.";
        } else {
            $msg = "❌ Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $msg = "❌ Category name cannot be empty.";
    }
}

// Fetch existing categories ordered by creation time
$result = $conn->query("SELECT id, name, slug FROM categories ORDER BY created_at DESC");
$categories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin – Blog Categories</title>
  <style>
    /* Basic reset */
    * {
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      padding: 20px;
      background: #f9f9f9;
      margin: 0;
    }
    .container {
      max-width: 900px;
      margin: auto;
      background: #fff;
      padding: 25px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
    }
    h1 {
      margin-top: 0;
      font-size: 1.8rem;
      color: #0B7C3E;
      text-align: center;
    }
    .msg {
      padding: 12px 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      font-weight: 600;
      font-size: 1rem;
      line-height: 1.3;
    }
    .msg.success {
      background: #eaf7ef;
      border-left: 6px solid #0B7C3E;
      color: #0B7C3E;
    }
    .msg.error {
      background: #fdecea;
      border-left: 6px solid #d93025;
      color: #d93025;
    }
    form {
      margin-bottom: 30px;
    }
    label {
      display: block;
      font-weight: 600;
      margin-bottom: 6px;
    }
    input[type="text"] {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }
    input[type="text"]:focus {
      border-color: #0B7C3E;
      outline: none;
    }
    button {
      background: #0B7C3E;
      color: white;
      padding: 12px 24px;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    button:hover {
      background: #075E2E;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 10px;
    }
    th, td {
      text-align: left;
      padding: 12px 15px;
      border-bottom: 1px solid #ddd;
      word-break: break-word;
    }
    th {
      background: #f1f1f1;
      font-weight: 700;
      color: #333;
    }
    a.delete-link {
      color: #d93025;
      text-decoration: none;
      font-weight: 600;
    }
    a.delete-link:hover {
      text-decoration: underline;
    }
    .no-categories {
      font-style: italic;
      color: #555;
      text-align: center;
      padding: 20px 0;
    }
    /* Dashboard link styling */
    .dashboard-link {
      display: inline-block;
      margin-bottom: 25px;
      font-weight: 600;
      background: #0B7C3E;
      color: white;
      padding: 10px 18px;
      border-radius: 6px;
      text-decoration: none;
      transition: background-color 0.3s ease;
    }
    .dashboard-link:hover {
      background: #075E2E;
    }
    /* Responsive */
    @media (max-width: 600px) {
      th, td {
        padding: 10px 8px;
      }
      button, .dashboard-link {
        width: 100%;
        box-sizing: border-box;
      }
      input[type="text"] {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <h1>🗂 Blog Categories</h1>

  <a href="admin_dashboard.php" class="dashboard-link">← Back to Dashboard</a>

  <?php if ($msg): ?>
    <div class="msg <?= strpos($msg, '❌') === 0 ? 'error' : 'success' ?>">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <form method="post" novalidate>
    <label for="name">New Category Name</label>
    <input type="text" name="name" id="name" placeholder="e.g. Marketing Tips" required autocomplete="off" />
    <button type="submit">Add Category</button>
  </form>

  <?php if (count($categories)): ?>
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Slug</th>
          <th style="width: 110px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $cat): ?>
          <tr>
            <td><?= htmlspecialchars($cat['name']) ?></td>
            <td><?= htmlspecialchars($cat['slug']) ?></td>
            <td>
              <a href="admin_blog_categories.php?delete=<?= (int)$cat['id'] ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this category?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="no-categories">No categories yet! Start by creating one above.</p>
  <?php endif; ?>
</div>
</body>
</html>