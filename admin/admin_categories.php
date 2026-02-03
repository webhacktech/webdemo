<?php
require '../config.php';

$msg = '';
$editMode = false;
$editCategory = null;

// Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

    $stmt = $conn->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $slug);
    if ($stmt->execute()) {
        $msg = "✅ Category added.";
    } else {
        $msg = "❌ Error: " . $conn->error;
    }
    $stmt->close();
}

// Delete Category
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM categories WHERE id = $id");
    $msg = "❌ Category deleted.";
}

// Start Edit
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM categories WHERE id = $id");
    $editCategory = $res->fetch_assoc();
}

// Save Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

    $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $slug, $id);
    if ($stmt->execute()) {
        $msg = "✅ Category updated.";
        $editMode = false;
    } else {
        $msg = "❌ Error: " . $conn->error;
    }
    $stmt->close();
}

// Fetch All
$categories = [];
$res = $conn->query("SELECT * FROM categories ORDER BY name");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Blog Categories – Sellevo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="../logo.png">
  <style>
    :root {
      --green: #0B7C3E;
      --green-dark: #075E2E;
      --bg: #f9f9f9;
    }
    body {
      font-family: 'Segoe UI', sans-serif;
      background: var(--bg);
      padding: 20px;
      color: #222;
    }
    h2 {
      text-align: center;
      color: var(--green);
    }
    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: var(--green);
      text-decoration: none;
      font-weight: bold;
    }
    form, .table {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      max-width: 700px;
      margin: auto;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    input {
      width: 100%;
      padding: 12px;
      margin-bottom: 12px;
      border-radius: 6px;
      border: 1px solid #ccc;
    }
    button {
      background: var(--green);
      color: #fff;
      border: none;
      padding: 10px 16px;
      border-radius: 6px;
      cursor: pointer;
    }
    button:hover {
      background: var(--green-dark);
    }
    .msg {
      max-width: 700px;
      margin: 10px auto;
      background: #eaf7ef;
      padding: 10px;
      border-left: 4px solid var(--green);
      color: var(--green-dark);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      padding: 12px;
      border-bottom: 1px solid #eee;
      text-align: left;
    }
    .actions a {
      margin-right: 10px;
      color: var(--green-dark);
      text-decoration: none;
    }
    .actions a.delete {
      color: red;
    }
    .actions a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<a class="back-link" href="admin_dashboard.php">← Back to Admin Dashboard</a>
<h2><?= $editMode ? 'Edit Category' : 'Manage Blog Categories' ?></h2>

<?php if ($msg): ?>
  <div class="msg"><?=htmlspecialchars($msg)?></div>
<?php endif; ?>

<?php if ($editMode && $editCategory): ?>
  <form method="post">
    <input type="hidden" name="id" value="<?=$editCategory['id']?>">
    <label>Edit Category Name</label>
    <input type="text" name="name" value="<?=htmlspecialchars($editCategory['name'])?>" required>
    <button type="submit" name="update">Update Category</button>
    <a href="admin_categories.php" style="margin-left:10px;">Cancel</a>
  </form>
<?php else: ?>
  <form method="post">
    <label>Category Name</label>
    <input type="text" name="name" required placeholder="e.g. Marketing, Updates">
    <button type="submit" name="add">Add Category</button>
  </form>
<?php endif; ?>

<div class="table">
  <h3>Existing Categories</h3>
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Slug</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($categories) === 0): ?>
        <tr><td colspan="3">No categories found.</td></tr>
      <?php else: ?>
        <?php foreach($categories as $cat): ?>
          <tr>
            <td><?=htmlspecialchars($cat['name'])?></td>
            <td><?=htmlspecialchars($cat['slug'])?></td>
            <td class="actions">
              <a href="?edit=<?=$cat['id']?>">Edit</a>
              <a href="?delete=<?=$cat['id']?>" class="delete" onclick="return confirm('Delete this category?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>